<?php

namespace App\Services;

use App\Data\Transaction\TransactionData;
use App\Data\Transaction\TransferData;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Import\ImportFileException;
use App\Services\Import\ImportFingerprint;
use App\Services\Import\ParsedRow;
use App\Services\Import\ParsedStatement;
use App\Services\Import\ParserRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staging lifecycle for provider statement imports: upload (parse →
 * default → fingerprint → stage), row editing, approve (materialize via
 * the existing transaction/transfer services so balance observers stay
 * intact), and discard.
 */
final readonly class ImportService
{
    private const int INSERT_CHUNK = 250;

    public function __construct(
        private readonly ParserRegistry $registry,
        private readonly TransactionService $transactionService,
        private readonly TransferService $transferService,
    ) {}

    public function upload(User $user, Account $account, UploadedFile $file): ImportBatch
    {
        $provider = $account->provider;

        if ($provider === null || ! $this->registry->supports($provider)) {
            throw ValidationException::withMessages([
                'statement' => 'Statement import is not available for this provider yet.',
            ]);
        }

        try {
            $statement = $this->registry->resolve($provider)->parse($file);
        } catch (ImportFileException $importFileException) {
            throw ValidationException::withMessages([
                'statement' => $importFileException->getMessage(),
            ]);
        }

        return DB::transaction(function () use ($user, $account, $file, $statement): ImportBatch {
            $existing = $this->existingFingerprints($account, $statement);

            $batch = ImportBatch::create([
                'created_by' => $user->id,
                'account_id' => $account->id,
                'provider_id' => $account->provider_id,
                'source_filename' => $file->getClientOriginalName(),
                'status' => ImportBatchStatus::Staging,
                'total_rows' => count($statement->rows),
            ]);

            $seen = [];
            $rows = [];
            foreach ($statement->rows as $index => $parsed) {
                $fingerprint = ImportFingerprint::make($account->id, $parsed->date, $parsed->amount, $parsed->description);
                $isDuplicate = in_array($fingerprint, $existing, true) || isset($seen[$fingerprint]);
                $seen[$fingerprint] = true;

                $rows[] = [
                    'batch_id' => $batch->id,
                    'row_number' => $index + 1,
                    'transaction_date' => $parsed->date?->toDateTimeString(),
                    'description' => $parsed->description,
                    'amount' => $parsed->amount,
                    'flow' => $parsed->flow?->value,
                    'category_id' => $parsed->flow === null
                        ? null
                        : ($parsed->flow->isInflow() ? Category::OtherIncome->value : Category::OtherExpenses->value),
                    'is_included' => $parsed->errors === [] && ! $isDuplicate,
                    'is_duplicate' => $isDuplicate,
                    'transfer_destination_account_id' => null,
                    'fingerprint' => $fingerprint,
                    'parse_errors' => $parsed->errors === [] ? null : json_encode($parsed->errors),
                    'raw' => json_encode($parsed->raw),
                ];
            }

            // insert() bypasses casts — enum values are pre-stringified and
            // the JSON columns (parse_errors, raw) pre-encoded above.
            collect($rows)
                ->chunk(self::INSERT_CHUNK)
                ->each(fn (Collection $chunk): bool => ImportRow::insert($chunk->all()));

            return $batch;
        });
    }

    /**
     * Fingerprints of the account's live transactions inside the file's date span.
     *
     * @return list<string>
     */
    private function existingFingerprints(Account $account, ParsedStatement $statement): array
    {
        $dates = collect($statement->rows)
            ->filter(fn (ParsedRow $row): bool => $row->date !== null)
            ->map(fn (ParsedRow $row): string => $row->date->toDateString());

        if ($dates->isEmpty()) {
            return [];
        }

        return Transaction::query()
            ->where('account_id', $account->id)
            ->whereBetween('transaction_date', [$dates->min() . ' 00:00:00', $dates->max() . ' 23:59:59'])
            ->get(['transaction_date', 'amount', 'description'])
            ->map(fn (Transaction $transaction): string => ImportFingerprint::make(
                $account->id,
                CarbonImmutable::parse($transaction->transaction_date),
                (float) $transaction->amount,
                $transaction->description,
            ))
            ->all();
    }

    /**
     * Review-time editing of one staged row. Only staging batches are
     * editable. `is_duplicate` stays an upload-time fact: an explicit edit
     * (or include toggle) is the user overriding it, so it is never
     * re-checked here.
     */
    public function updateRow(ImportRow $row, array $data): ImportRow
    {
        $this->assertStaging($row->batch);

        if (isset($data['transfer_destination_account_id'])
            && (int) $data['transfer_destination_account_id'] === $row->batch->account_id) {
            throw ValidationException::withMessages([
                'transfer_destination_account_id' => 'The transfer destination must be a different account.',
            ]);
        }

        $row->update([
            'transaction_date' => $data['transaction_date'] ?? $row->transaction_date?->toDateTimeString(),
            'description' => array_key_exists('description', $data) ? $data['description'] : $row->description,
            'amount' => $data['amount'] ?? $row->amount,
            'flow' => isset($data['flow']) ? Cashflow::from($data['flow']) : $row->flow,
            'category_id' => isset($data['category_id']) ? Category::from($data['category_id']) : $row->category_id,
            'transfer_destination_account_id' => array_key_exists('transfer_destination_account_id', $data)
                ? $data['transfer_destination_account_id']
                : $row->transfer_destination_account_id,
            'is_included' => $data['is_included'] ?? $row->is_included,
        ]);

        return $row->fresh();
    }

    private function assertStaging(ImportBatch $batch): void
    {
        if ($batch->status !== ImportBatchStatus::Staging) {
            throw ValidationException::withMessages([
                'batch' => 'Only staging batches can be modified.',
            ]);
        }
    }

    /**
     * Bulk-approve: re-validate every included row server-side (client
     * state is not trusted), then materialize through the existing
     * transaction/transfer services inside one DB transaction so balance
     * observers and events fire exactly as for hand-entered rows.
     */
    public function approve(ImportBatch $batch): int
    {
        $this->assertStaging($batch);

        /** @var Collection<int, ImportRow> $rows */
        $rows = $batch->rows()->where('is_included', true)->orderBy('row_number')->get();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'batch' => 'No rows are selected for import.',
            ]);
        }

        $rows->each(fn (ImportRow $row): null => $this->assertRowImportable($row));

        return DB::transaction(function () use ($batch, $rows): int {
            $count = 0;

            foreach ($rows as $row) {
                $row->transfer_destination_account_id !== null
                    ? $this->importTransferRow($batch, $row)
                    : $this->importPlainRow($batch, $row);
                $count++;
            }

            $batch->update([
                'status' => ImportBatchStatus::Approved,
                'imported_rows' => $count,
                'approved_at' => now(),
            ]);

            return $count;
        });
    }

    public function discard(ImportBatch $batch): void
    {
        $this->assertStaging($batch);

        $batch->update([
            'status' => ImportBatchStatus::Discarded,
            'discarded_at' => now(),
        ]);
    }

    private function assertRowImportable(ImportRow $row): void
    {
        if ($row->parse_errors !== null) {
            throw ValidationException::withMessages([
                'rows' => "Row {$row->row_number} still has parse errors.",
            ]);
        }

        if ($row->transaction_date === null || $row->amount === null || $row->amount <= 0 || $row->flow === null) {
            throw ValidationException::withMessages([
                'rows' => "Row {$row->row_number} is missing a date, amount, or flow.",
            ]);
        }

        $isTransfer = $row->transfer_destination_account_id !== null;

        if (! $isTransfer && $row->category_id === null) {
            throw ValidationException::withMessages([
                'rows' => "Row {$row->row_number} has no category.",
            ]);
        }

        if ($isTransfer) {
            if ((int) $row->transfer_destination_account_id === $row->batch->account_id) {
                throw ValidationException::withMessages([
                    'rows' => "Row {$row->row_number} transfers to its own account.",
                ]);
            }

            $destinationExists = Account::query()
                ->where('id', $row->transfer_destination_account_id)
                ->where('owner_id', $row->batch->created_by)
                ->exists();

            if (! $destinationExists) {
                throw ValidationException::withMessages([
                    'rows' => "Row {$row->row_number} has an invalid transfer destination.",
                ]);
            }
        }
    }

    private function importPlainRow(ImportBatch $batch, ImportRow $row): void
    {
        $this->transactionService->create($batch->creator, new TransactionData(
            account_id: $batch->account_id,
            type: $row->flow->isInflow() ? TransactionType::Income : TransactionType::Expense,
            amount: (float) $row->amount,
            transaction_date: $row->transaction_date->toDateTimeString(),
            category_id: $row->category_id,
            description: $row->description,
        ));
    }

    /** Direction follows the row's flow: outflow books this account as source; inflow books it as destination. */
    private function importTransferRow(ImportBatch $batch, ImportRow $row): void
    {
        $this->transferService->create($batch->creator, new TransferData(
            account_id: $row->flow->isOutflow()
                ? $batch->account_id
                : $row->transfer_destination_account_id,
            destination_account_id: $row->flow->isOutflow()
                ? $row->transfer_destination_account_id
                : $batch->account_id,
            amount: (float) $row->amount,
            transaction_date: $row->transaction_date->toDateTimeString(),
            description: $row->description,
        ));
    }
}
