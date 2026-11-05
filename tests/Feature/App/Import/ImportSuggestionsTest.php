<?php

use App\Ai\Agents\ImportSuggestions;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportSuggestionStatus;
use App\Enums\ProviderType;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Provider;
use App\Models\User;
use Pest\Mixins\Expectation;

beforeEach(function (): void {
    config([
        'feature.import-suggestions.enabled' => true,
        'ai.providers.ollama-cloud.key' => 'test-key',
    ]);

    $this->provider = Provider::factory()->create([
        'name' => 'Mandiri',
        'slug' => 'mandiri',
        'type' => ProviderType::Bank,
    ]);

    [$this->user, $this->account] = userWithAccount(['provider_id' => $this->provider->id]);
});

function stageUnsuggestedList(User $user, Account $account): ImportBatch
{
    config(['feature.import-suggestions.enabled' => false]);

    return resolve(ImportService::class)->upload($user, $account, statementFile());
}

test('upload defers suggestions and rows land categorized', function (): void {
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
        ['row_number' => 2, 'category' => 'other_expenses', 'transaction_type' => 'expense'],
        ['row_number' => 3, 'category' => 'salary', 'transaction_type' => 'income'],
    ]]]);

    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);

    $batch = ImportBatch::query()->sole();

    // Deferred callbacks flush when the request terminates inside the test
    // client; if they ever stop, $this->app->flushDeferred() forces them.
    expect($batch->suggestion_status)->toBe(ImportSuggestionStatus::Completed)
        ->and($batch->rows->firstWhere('row_number', 1)->category_id)->toBe(Category::DiningOut);
});

test('upload skips suggestions when the gate is closed', function (): void {
    config(['feature.import-suggestions.enabled' => false]);

    $this->actingAs($this->user)
        ->post(route('imports.store', $this->account), ['statement' => statementFile()]);

    expect(ImportBatch::query()->sole()->suggestion_status)->toBeNull();
});

test('staging rows carry a transaction type derived from flow', function (): void {
    stageUnsuggestedList($this->user, $this->account);

    expect(ImportRow::query()->get())
        ->each(fn ($row): Expectation => expect($row->transaction_type)->not->toBeNull());
});

test('update keeps the parsed flow when the type is transfer', function (): void {
    $batch = stageUnsuggestedList($this->user, $this->account);

    $inflowRow = $batch->rows->firstWhere('flow', 'inflow') ?? $batch->rows->first();
    $destination = Account::factory()->create(['owner_id' => $this->user->id]);

    $this->actingAs($this->user)
        ->patch(route('imports.rows.update', ['batch' => $batch->id, 'row' => $inflowRow->id]), [
            'transaction_type' => 'transfer',
            'transfer_destination_account_id' => $destination->id,
        ])
        ->assertRedirectToRoute('imports.show', $batch);

    expect($inflowRow->fresh()->flow)->toBe(Cashflow::Inflow)
        ->and($inflowRow->fresh()->transaction_type->value)->toBe('transfer');
});

test('approve rejects a transfer row without a destination', function (): void {
    $batch = stageUnsuggestedList($this->user, $this->account);

    ImportRow::query()->first()->update([
        'transaction_type' => 'transfer',
        'transfer_destination_account_id' => null,
    ]);

    $this->actingAs($this->user)
        ->post(route('imports.approve', $batch))
        ->assertSessionHasErrors('rows');

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Staging);
});

test('retry resets and re-runs suggestions for the owner', function (): void {
    $batch = stageUnsuggestedList($this->user, $this->account);
    $batch->update(['suggestion_status' => ImportSuggestionStatus::Failed]);

    config(['feature.import-suggestions.enabled' => true]);
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'other_expenses', 'transaction_type' => 'expense'],
    ]]]);

    $this->actingAs($this->user)
        ->post(route('imports.suggestions.retry', $batch))
        ->assertRedirectToRoute('imports.show', $batch);

    // The deferred re-run completed and re-applied a suggestion.
    expect($batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Completed);
});

test('retry is forbidden for a stranger', function (): void {
    $batch = stageUnsuggestedList($this->user, $this->account);

    $this->actingAs(User::factory()->create())
        ->post(route('imports.suggestions.retry', $batch))
        ->assertForbidden();
});

test('retry rejects non-staging batches', function (): void {
    $batch = stageUnsuggestedList($this->user, $this->account);
    $batch->update([
        'status' => ImportBatchStatus::Discarded,
        'discarded_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('imports.suggestions.retry', $batch))
        ->assertUnprocessable();
});

test('show lazily fails stale processing suggestions', function (): void {
    $batch = stageUnsuggestedList($this->user, $this->account);
    $batch->update([
        'suggestion_status' => ImportSuggestionStatus::Processing,
        'updated_at' => now()->subMinutes(11),
    ]);

    $this->actingAs($this->user)
        ->get(route('imports.show', $batch))
        ->assertOk();

    expect($batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Failed);
});
