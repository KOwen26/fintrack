<?php

use App\Ai\Agents\ImportSuggestions;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportSuggestionStatus;
use App\Enums\TransactionType;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Import\ImportSuggestion;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function (): void {
    config([
        'feature.import-suggestions.enabled' => true,
        'ai.providers.ollama-cloud.key' => 'test-key',
    ]);

    $this->batch = ImportBatch::factory()->create([
        'status' => ImportBatchStatus::Staging,
        'suggestion_status' => ImportSuggestionStatus::Pending,
    ]);

    $this->gofood = ImportRow::factory()->create([
        'batch_id' => $this->batch->id,
        'row_number' => 1,
        'description' => 'GOPAY QRIS GOFOOD JAKARTA 609015160267',
        'amount' => 45000,
        'flow' => Cashflow::Outflow,
        'category_id' => Category::OtherExpenses->value,
        'transaction_type' => TransactionType::Expense->value,
        'parse_errors' => null,
        'raw' => [],
    ]);

    $this->salary = ImportRow::factory()->create([
        'batch_id' => $this->batch->id,
        'row_number' => 2,
        'description' => 'GAJI OKTOBER PT MAJU',
        'amount' => 8000000,
        'flow' => Cashflow::Inflow,
        'category_id' => Category::OtherIncome->value,
        'transaction_type' => TransactionType::Income->value,
        'parse_errors' => null,
        'raw' => [],
    ]);
});

function runSuggestions(ImportBatch $batch): void
{
    resolve(ImportSuggestion::class)->suggestCategories($batch);
}

it('is enabled when the feature flag and API key are set', function (): void {
    config([
        'feature.import-suggestions.enabled' => true,
        'ai.providers.ollama-cloud.key' => 'test-key',
    ]);

    expect(ImportSuggestion::isEnabled())->toBeTrue();
});

it('is disabled when the feature flag is off', function (): void {
    config([
        'feature.import-suggestions.enabled' => false,
        'ai.providers.ollama-cloud.key' => 'test-key',
    ]);

    expect(ImportSuggestion::isEnabled())->toBeFalse();
});

it('is disabled when the API key is missing', function (): void {
    config([
        'feature.import-suggestions.enabled' => true,
        'ai.providers.ollama-cloud.key' => null,
    ]);

    expect(ImportSuggestion::isEnabled())->toBeFalse();
});

it('redacts long digit runs before the prompt leaves the app', function (): void {
    $captured = null;
    ImportSuggestions::fake(function (AgentPrompt $prompt) use (&$captured): array {
        $captured = $prompt->prompt;

        return ['rows' => []];
    });

    runSuggestions($this->batch);

    expect($captured)->toContain('GOFOOD JAKARTA [REDACTED]')
        ->not->toContain('609015160267');
});

it('applies suggested categories and types', function (): void {
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
        ['row_number' => 2, 'category' => 'salary', 'transaction_type' => 'income'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::DiningOut)
        ->and($this->salary->fresh()->category_id)->toBe(Category::Salary)
        ->and($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Completed)
        ->and($this->batch->fresh()->suggested_at)->not->toBeNull();
});

it('ignores unknown category slugs', function (): void {
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'not_a_slug', 'transaction_type' => 'expense'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::OtherExpenses);
});

it('ignores direction-illegal categories', function (): void {
    // dining_out flows out; the row flows in — the suggestion must be dropped.
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 2, 'category' => 'dining_out', 'transaction_type' => 'income'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->salary->fresh()->category_id)->toBe(Category::OtherIncome);
});

it('marks transfer without touching the destination', function (): void {
    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'admin_bank_fees', 'transaction_type' => 'transfer'],
    ]]]);

    runSuggestions($this->batch);

    $row = $this->gofood->fresh();

    expect($row->transaction_type)->toBe(TransactionType::Transfer)
        ->and($row->transfer_destination_account_id)->toBeNull()
        ->and($row->flow)->toBe(Cashflow::Outflow);
});

it('marks failed when every chunk fails', function (): void {
    ImportSuggestions::fake(fn () => throw new RuntimeException('ollama down'));

    runSuggestions($this->batch);

    expect($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Failed)
        ->and($this->batch->fresh()->suggestion_error)->toBe('ollama down')
        ->and($this->gofood->fresh()->category_id)->toBe(Category::OtherExpenses);
});

it('records partial coverage as completed with an error note', function (): void {
    config(['feature.import-suggestions.chunk_size' => 1]);

    $calls = 0;
    ImportSuggestions::fake(function () use (&$calls): array {
        $calls++;

        // Chunk 1 (gofood) succeeds; every later attempt for chunk 2 fails.
        if ($calls === 1) {
            return ['rows' => [
                ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
            ]];
        }

        throw new RuntimeException('chunk 2 exploded');
    });

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::DiningOut)
        ->and($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Completed)
        ->and($this->batch->fresh()->suggestion_error)->toContain('chunk 2 exploded');
});

it('skips rows the user already categorized during review', function (): void {
    // The user re-filed this row while the run was in flight — the
    // suggestion must not overwrite their choice.
    $this->gofood->update(['category_id' => Category::Fuel]);

    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::Fuel)
        ->and($this->gofood->fresh()->transaction_type)->toBe(TransactionType::Expense)
        ->and($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Failed);
});

it('no-ops for non-staging batches', function (): void {
    $this->batch->update(['status' => ImportBatchStatus::Approved]);

    ImportSuggestions::fake([['rows' => [
        ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
    ]]]);

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::OtherExpenses)
        ->and($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Pending);
});

it('applies suggestions to rows staged without timestamps', function (): void {
    // upload() stages via insert() — timestamps stay null on freshly staged rows.
    ImportRow::query()->insert([
        'batch_id' => $this->batch->id,
        'row_number' => 6,
        'description' => 'TRANSFER BCA KE GOPAY 0812',
        'amount' => 150000,
        'flow' => Cashflow::Outflow->value,
        'transaction_type' => TransactionType::Expense->value,
        'category_id' => Category::OtherExpenses->value,
        'is_included' => true,
        'is_duplicate' => false,
        'fingerprint' => 'fp-6',
        'raw' => '[]',
        'created_at' => null,
        'updated_at' => null,
    ]);

    ImportSuggestions::fake([['rows' => [
        ['row_number' => 6, 'category' => 'admin_bank_fees', 'transaction_type' => 'transfer'],
    ]]]);

    runSuggestions($this->batch);

    $row = ImportRow::query()->where('row_number', 6)->first();

    expect($row->transaction_type)->toBe(TransactionType::Transfer)
        ->and($row->category_id)->toBe(Category::AdminBankFees)
        ->and($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Completed);
});

it('tolerates a bare-array response when the provider ignores the schema', function (): void {
    // Ollama Cloud does not support structured outputs — the model answers
    // with a bare JSON array instead of the {"rows": [...]} envelope.
    ImportSuggestions::fake([[
        ['row_number' => 1, 'category' => 'dining_out', 'transaction_type' => 'expense'],
        ['row_number' => 2, 'category' => 'salary', 'transaction_type' => 'income'],
    ]]);

    runSuggestions($this->batch);

    expect($this->gofood->fresh()->category_id)->toBe(Category::DiningOut)
        ->and($this->salary->fresh()->category_id)->toBe(Category::Salary)
        ->and($this->batch->fresh()->suggestion_status)->toBe(ImportSuggestionStatus::Completed);
});
