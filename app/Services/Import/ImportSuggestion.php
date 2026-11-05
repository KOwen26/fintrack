<?php

namespace App\Services\Import;

use App\Ai\Agents\ImportSuggestions as ImportSuggestionsAgent;
use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportSuggestionStatus;
use App\Enums\TransactionType;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Log\Logger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ImportSuggestion
{
    public static function isEnabled(): bool
    {
        return (bool) config('feature.import-suggestions.enabled')
            && filled(config('ai.providers.ollama-cloud.key'));
    }

    /**
     * Runs category/type suggestions for a staged batch. Every failure
     * mode leaves the batch reviewable with its staged defaults.
     */
    public function suggestCategories(ImportBatch $batch): void
    {
        if ($batch->status !== ImportBatchStatus::Staging
            || ! in_array($batch->suggestion_status, [ImportSuggestionStatus::Pending, ImportSuggestionStatus::Processing], true)) {
            return;
        }

        $startedAt = now();

        $batch->update(['suggestion_status' => ImportSuggestionStatus::Processing]);

        /** @var Collection<int, ImportRow> $rows */
        $rows = $batch->rows()
            ->whereNull('parse_errors')
            ->whereNotNull('flow')
            ->whereNotNull('amount')
            // Only rows exactly as the parser staged them — anything the user  already re-categorized, re-typed, or marked as a transfer is theirs, and suggestions must not overwrite it.
            ->whereIn('category_id', [Category::OtherIncome->value, Category::OtherExpenses->value])
            ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->whereNull('transfer_destination_account_id')
            ->orderBy('row_number')
            ->get();

        $this->log()->info('Import suggestions run started.', [
            'batch_id' => $batch->id,
            'candidate_rows' => $rows->count(),
            'chunk_size' => (int) config('feature.import-suggestions.chunk_size'),
        ]);

        $errors = [];
        $appliedTotal = 0;

        foreach ($rows->chunk((int) config('feature.import-suggestions.chunk_size')) as $chunk) {
            // Mid-run lifecycle guard: approving/discarding between chunks stops the run.
            $batch->refresh();

            if ($batch->status !== ImportBatchStatus::Staging) {
                $batch->update([
                    'suggestion_status' => ImportSuggestionStatus::Failed,
                    'suggestion_error' => 'Batch left staging before suggestions finished.',
                ]);

                $this->log()->warning('Import suggestions run aborted — batch left staging.', [
                    'batch_id' => $batch->id,
                ]);

                return;
            }

            try {
                $counts = $this->suggestChunk($chunk);
            } catch (Throwable) {
                try {
                    // One inline retry per chunk — the second failure tolerates the chunk.
                    $counts = $this->suggestChunk($chunk);
                } catch (Throwable $second) {
                    $this->log()->warning('Import suggestions chunk failed after retry.', [
                        'batch_id' => $batch->id,
                        'error' => $second->getMessage(),
                        'exception' => $second,
                    ]);

                    $errors[] = $second->getMessage();

                    continue;
                }
            }

            $appliedTotal += $counts['applied'];

            $this->log()->debug('Import suggestions chunk processed.', [
                'batch_id' => $batch->id,
                ...$counts,
            ]);
        }

        $status = $appliedTotal > 0 ? ImportSuggestionStatus::Completed : ImportSuggestionStatus::Failed;

        $batch->update([
            'suggestion_status' => $status,
            'suggestion_error' => $errors === [] ? null : implode(' | ', array_slice($errors, 0, 3)),
            'suggested_at' => $appliedTotal > 0 ? now() : null,
        ]);

        $this->log()->{$status === ImportSuggestionStatus::Completed ? 'info' : 'warning'}('Import suggestions run finished.', [
            'batch_id' => $batch->id,
            'status' => $status->value,
            'applied' => $appliedTotal,
            'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
            'errors' => $errors,
        ]);
    }

    public function retry(ImportBatch $batch): void
    {
        $this->log()->info('Import suggestions retry requested.', ['batch_id' => $batch->id]);

        $batch->update([
            'suggestion_status' => ImportSuggestionStatus::Pending,
            'suggestion_error' => null,
            'suggested_at' => null,
        ]);

        defer(fn () => $this->suggestCategories($batch));
    }

    public function failIfStale(ImportBatch $batch): void
    {
        if ($batch->suggestion_status === ImportSuggestionStatus::Processing
            && $batch->updated_at->lt(now()->subMinutes(10))) {
            $batch->update([
                'suggestion_status' => ImportSuggestionStatus::Failed,
                'suggestion_error' => 'Timed out while processing suggestions.',
            ]);

            $this->log()->warning('Import suggestions run was stale — marked failed.', [
                'batch_id' => $batch->id,
                'last_activity' => $batch->updated_at->toDateTimeString(),
            ]);
        }
    }

    /**
     * Prompts the agent for one chunk and applies validated suggestions.
     *
     * @param  Collection<int, ImportRow>  $chunk
     *
     * @return array{applied: int, skipped_edited: int, dropped_slug: int, dropped_direction: int, dropped_type: int}
     */
    private function suggestChunk(Collection $chunk): array
    {
        $prompt = $chunk->map(
            fn (ImportRow $row): string => sprintf(
                '#%d | %s | %s | %s',
                $row->row_number,
                $row->transaction_date?->toDateString() ?? '—',
                $this->redactIdentifiers($row->description),
                $row->flow->value,
            )
        )->implode("\n");

        $this->log()->debug('Import suggestions prompt sent.', [
            'batch_id' => $chunk->first()?->batch_id,
            'model' => (string) config('ai.providers.ollama-cloud.models.text.default'),
            'prompt' => $prompt,
        ]);

        $response = (new ImportSuggestionsAgent)->prompt(
            $prompt,
            provider: 'ollama-cloud',
            timeout: (int) config('feature.import-suggestions.timeout'),
        );

        $this->log()->debug('Import suggestions response received.', [
            'batch_id' => $chunk->first()?->batch_id,
            'response' => $response?->toArray(),
        ]);

        // Structured outputs are not enforced on Ollama Cloud — the model may
        // answer with the {"rows": [...]} envelope or a bare array. Accept both;
        // per-item validation below guards everything else.
        $structured = $response?->toArray() ?? [];
        $rows = $structured['rows'] ?? $structured;
        $counts = ['applied' => 0, 'skipped_edited' => 0, 'dropped_slug' => 0, 'dropped_direction' => 0, 'dropped_type' => 0];

        if (! is_array($rows)) {
            $this->log()->warning('Import suggestions response was not a usable array.', [
                'batch_id' => $chunk->first()?->batch_id,
                'response' => $structured,
            ]);

            return $counts;
        }

        $byRowNumber = $chunk->keyBy(fn (ImportRow $row): int => $row->row_number);

        foreach ($rows as $suggestion) {
            if (! is_array($suggestion)) {
                continue;
            }

            $row = $byRowNumber->get($suggestion['row_number'] ?? 0);

            if ($row === null) {
                continue;
            }

            $category = Category::tryFrom((string) ($suggestion['category'] ?? ''));

            // Unknown or direction-illegal slug — keep the staged default.
            if ($category === null || $category === Category::InitialBalance) {
                $counts['dropped_slug']++;

                continue;
            }

            if ($category->group()->flow() !== $row->flow) {
                $counts['dropped_direction']++;

                continue;
            }

            $type = TransactionType::tryFrom((string) ($suggestion['transaction_type'] ?? ''));

            // Type must match the row's flow, or be a transfer (destination stays the user's pick).
            if ($type === null
                || ($type !== TransactionType::Transfer && $type->toCashflow() !== $row->flow)) {
                $counts['dropped_type']++;

                continue;
            }

            // Guarded apply — same parser-default conditions as the candidate
            // query, re-checked at write time: a row the user touched between
            // candidate selection and now matches nothing and is skipped.
            $updated = ImportRow::query()
                ->whereKey($row->id)
                ->whereIn('category_id', [Category::OtherIncome->value, Category::OtherExpenses->value])
                ->whereIn('transaction_type', [TransactionType::Income->value, TransactionType::Expense->value])
                ->whereNull('transfer_destination_account_id')
                ->update([
                    'category_id' => $category->value,
                    'transaction_type' => $type->value,
                ]);

            if ($updated === 0) {
                $counts['skipped_edited']++;
            }

            $counts['applied'] += $updated;
        }

        return $counts;
    }

    /**
     * Long digit runs in statement descriptions are account, phone, and QR
     * references — identifiers the categorization task never needs. Masked
     * before the prompt is assembled so the API call and the debug log both
     * carry the redacted form; merchant text stays intact as the signal.
     */
    private function redactIdentifiers(?string $description): string
    {
        return (string) preg_replace('/\d{5,}/', '[REDACTED]', (string) $description);
    }

    private function log(): Logger
    {
        return Log::channel('import-suggestions');
    }
}
