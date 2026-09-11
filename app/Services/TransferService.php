<?php

namespace App\Services;

use App\Data\Transaction\TransactionData;
use App\Data\Transaction\TransferData;
use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use App\Events\TransactionDeleted;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Transfer-unit lifecycle: creating, editing, and deleting whole units —
 * the Transfer aggregate plus its member rows (source, destination, and
 * optional fee). Plain-row CRUD and list queries live in TransactionService,
 * which this service reuses for booking individual rows.
 */
class TransferService
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function create(User $creator, TransferData $data): Transfer
    {
        $sourceAccount = Account::findOrFail($data->account_id);
        $destinationAccount = Account::findOrFail($data->destination_account_id);

        return DB::transaction(function () use ($creator, $sourceAccount, $destinationAccount, $data): Transfer {
            $transfer = Transfer::create([
                'created_by' => $creator->id,
                'amount' => $data->amount,
                'fee_amount' => $data->fee_amount,
                'transaction_date' => $data->transaction_date,
                'description' => $data->description,
            ]);

            $this->createUnitTransactions($transfer, $creator, $sourceAccount, $destinationAccount, $data);

            return $transfer;
        });
    }

    /**
     * Unit edit = update the aggregate in place (stable id), then soft-delete
     * the member transactions (observers reverse balances) and re-create them
     * from the updated aggregate. The payload covers all rows including the fee.
     */
    public function update(Transfer $transfer, TransferData $data): Transfer
    {
        $sourceAccount = Account::findOrFail($data->account_id);
        $destinationAccount = Account::findOrFail($data->destination_account_id);

        return DB::transaction(function () use ($transfer, $sourceAccount, $destinationAccount, $data): Transfer {
            $transfer->update([
                'amount' => $data->amount,
                'fee_amount' => $data->fee_amount,
                'transaction_date' => $data->transaction_date,
                'description' => $data->description,
            ]);

            $transfer->transactions()->get()->each(function (Transaction $member): void {
                $member->delete();
                TransactionDeleted::dispatch($member);
            });

            $this->createUnitTransactions($transfer, $transfer->creator, $sourceAccount, $destinationAccount, $data);

            return $transfer->fresh();
        });
    }

    /**
     * Deleting any unit member deletes the whole unit: aggregate + every
     * member row (fee included), symmetric. Soft deletes never fire the FK
     * cascade, so the pairing is app-enforced inside one transaction.
     */
    public function deleteUnit(Transaction $member): void
    {
        DB::transaction(function () use ($member): void {
            Transaction::where('transfer_id', $member->transfer_id)->get()
                ->each(function (Transaction $row): void {
                    $row->delete();
                    TransactionDeleted::dispatch($row);
                });

            $member->transfer?->delete();
        });
    }

    private function createUnitTransactions(Transfer $transfer, User $creator, Account $source, Account $destination, TransferData $data): void
    {
        $this->transactionService->create($creator, new TransactionData(
            account_id: $source->id,
            type: TransactionType::Transfer,
            amount: $data->amount,
            transaction_date: $data->transaction_date,
            description: $data->description,
            flow: TransactionFlow::Outflow,
            transfer_id: $transfer->id,
        ));

        $this->transactionService->create($creator, new TransactionData(
            account_id: $destination->id,
            type: TransactionType::Transfer,
            amount: $data->amount,
            transaction_date: $data->transaction_date,
            description: $data->description,
            flow: TransactionFlow::Inflow,
            transfer_id: $transfer->id,
        ));

        if ($data->fee_amount !== null && $data->fee_amount > 0) {
            $this->transactionService->create($creator, new TransactionData(
                account_id: $source->id,
                type: TransactionType::Expense,
                amount: $data->fee_amount,
                transaction_date: $data->transaction_date,
                category_id: $this->resolveTransferFeeCategory(),
                description: 'Transfer fee',
                flow: TransactionFlow::Outflow,
                transfer_id: $transfer->id,
            ));
        }
    }

    /**
     * Resolve the Admin Fees child category for booking transfer fees.
     * Returns null when it does not exist — the fee then books as uncategorized.
     */
    private function resolveTransferFeeCategory(): ?int
    {
        return Category::query()
            ->where('name', 'Admin Fees')
            ->levelChildren()
            ->first()
            ?->id;
    }
}
