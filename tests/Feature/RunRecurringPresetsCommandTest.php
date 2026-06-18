<?php

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use App\Events\RecurringPresetExecuted;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionRecurringPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function createPresetForUser(array $overrides = []): TransactionRecurringPreset
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return TransactionRecurringPreset::factory()->monthly()->create(array_merge([
        'created_by' => $user->id,
        'account_id' => $account->id,
        'type' => TransactionPresetType::Expense->value,
        'amount' => 500_000,
        'next_run_date' => today(),
        'is_active' => true,
    ], $overrides));
}

it('generates a transaction and advances next_run_date for a due monthly preset', function (): void {
    $preset = createPresetForUser(['next_run_date' => today()]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    expect(Transaction::where('account_id', $preset->account_id)->count())->toBe(1);

    $preset->refresh();
    expect($preset->last_run_date->toDateString())->toBe(today()->toDateString());
    expect($preset->next_run_date->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString());
});

it('does not generate a transaction for a future preset', function (): void {
    createPresetForUser(['next_run_date' => today()->addDay()]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    expect(Transaction::count())->toBe(0);
});

it('does not generate a transaction for an inactive preset', function (): void {
    createPresetForUser(['next_run_date' => today(), 'is_active' => false]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    expect(Transaction::count())->toBe(0);
});

it('deactivates preset when next_run_date exceeds recurrence_end_date', function (): void {
    $endDate = today()->addMonthNoOverflow()->subDay(); // next run would be after end
    $preset = createPresetForUser([
        'next_run_date' => today(),
        'recurrence_end_date' => $endDate->toDateString(),
        'frequency' => RecurringFrequency::Monthly->value,
    ]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    $preset->refresh();
    expect($preset->is_active)->toBeFalse();
});

it('dispatches RecurringPresetExecuted event after execution', function (): void {
    Event::fake([RecurringPresetExecuted::class]);
    createPresetForUser(['next_run_date' => today()]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    Event::assertDispatched(RecurringPresetExecuted::class);
});

it('continues processing other presets when one fails', function (): void {
    // Create one that will succeed and one with an invalid account_id that will fail
    $good = createPresetForUser(['next_run_date' => today()]);
    $bad = createPresetForUser(['next_run_date' => today(), 'account_id' => 99999]);

    // We expect the command to complete (not throw) even though one preset fails
    $this->artisan('presets:run-recurring');

    // The good preset should have been executed regardless
    expect(Transaction::where('account_id', $good->account_id)->count())->toBe(1);
});

it('advances weekly preset correctly', function (): void {
    $preset = createPresetForUser([
        'next_run_date' => today(),
        'frequency' => RecurringFrequency::Weekly->value,
    ]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    $preset->refresh();
    expect($preset->next_run_date->toDateString())->toBe(today()->addWeek()->toDateString());
});

it('advances daily preset correctly', function (): void {
    $preset = createPresetForUser([
        'next_run_date' => today(),
        'frequency' => RecurringFrequency::Daily->value,
    ]);

    $this->artisan('presets:run-recurring')->assertSuccessful();

    $preset->refresh();
    expect($preset->next_run_date->toDateString())->toBe(today()->addDay()->toDateString());
});
