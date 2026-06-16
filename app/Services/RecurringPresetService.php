<?php

namespace App\Services;

use App\Events\RecurringPresetExecuted;
use App\Models\TransactionRecurringPreset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecurringPresetService
{
    public function __construct(private readonly TransactionService $transactionService) {}

    public function create(User $user, array $data): TransactionRecurringPreset
    {
        return TransactionRecurringPreset::create([
            ...$data,
            'created_by' => $user->id,
        ]);
    }

    public function update(TransactionRecurringPreset $preset, array $data): TransactionRecurringPreset
    {
        $preset->update($data);

        return $preset->fresh();
    }

    public function softDelete(TransactionRecurringPreset $preset): void
    {
        $preset->delete();
    }

    public function toggle(TransactionRecurringPreset $preset, bool $active): TransactionRecurringPreset
    {
        $preset->update(['is_active' => $active]);

        return $preset->fresh();
    }

    /**
     * Execute all due recurring presets. Called by the daily Artisan command.
     * Each preset is wrapped in its own DB transaction + try/catch so a single
     * failure never blocks the rest.
     *
     * If the command runs after a missed date (e.g. server was down), exactly
     * ONE transaction is generated and next_run_date advances from today — no backfill.
     *
     * @return array{executed: int, failed: int}
     */
    public function runDue(): array
    {
        $executed = 0;
        $failed = 0;

        $duePresets = TransactionRecurringPreset::due()->with('account')->get();

        foreach ($duePresets as $preset) {
            try {
                DB::transaction(function () use ($preset): void {
                    $today = today();

                    // Create the transaction via the Ledger TransactionService
                    $transaction = $this->transactionService->create([
                        'account_id' => $preset->account_id,
                        'category_id' => $preset->category_id,
                        'user_id' => $preset->created_by,
                        'type' => $preset->type->value,
                        'amount' => $preset->amount,
                        'description' => $preset->description ?? $preset->name,
                        'date' => $today,
                    ]);

                    // Advance the schedule from today (no backfill)
                    $newNextRunDate = $preset->advanceNextRunDate($today);

                    $updates = [
                        'last_run_date' => $today,
                        'next_run_date' => $newNextRunDate,
                    ];

                    // Deactivate if we've passed the end date
                    if ($preset->recurrence_end_date !== null && $newNextRunDate->gt($preset->recurrence_end_date)) {
                        $updates['is_active'] = false;
                    }

                    $preset->update($updates);

                    RecurringPresetExecuted::dispatch($preset, $transaction);
                });

                $executed++;
            } catch (Throwable $e) {
                $failed++;
                Log::error('RecurringPresetService::runDue failed for preset', [
                    'preset_id' => $preset->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['executed' => $executed, 'failed' => $failed];
    }
}
