<?php

use App\Data\Transaction\TransactionData;
use App\Enums\Category;
use App\Enums\ImportBatchStatus;
use App\Enums\ProviderType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Provider;
use App\Models\Transfer;
use App\Models\User;
use App\Services\ImportService;
use App\Services\TransactionService;
use Illuminate\Validation\ValidationException;

function importableAccount(User $user): Account
{
    $provider = Provider::factory()->create([
        'name' => 'Mandiri',
        'slug' => 'mandiri',
        'type' => ProviderType::Bank,
    ]);

    return Account::factory()
        ->for($user, 'owner')
        ->create(['provider_id' => $provider->id]);
}

it('uploads a statement and stages rows with category defaults', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = resolve(ImportService::class)->upload($user, $account, statementFile());

    expect($batch->status)->toBe(ImportBatchStatus::Staging)
        ->and($batch->total_rows)->toBe(3)
        ->and($batch->rows)->toHaveCount(3);

    $inflow = $batch->rows->firstWhere('flow', 'inflow');
    $outflow = $batch->rows->firstWhere('flow', 'outflow');

    expect($inflow->category_id)->toBe(Category::OtherIncome)
        ->and($outflow->category_id)->toBe(Category::OtherExpenses)
        ->and($inflow->is_included)->toBeTrue()
        ->and($inflow->is_duplicate)->toBeFalse()
        ->and($inflow->raw)->not->toBeEmpty();
});

it('flags duplicates against existing transactions and within the file', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    resolve(TransactionService::class)->create($user, new TransactionData(
        account_id: $account->id,
        type: TransactionType::Income,
        amount: 2500000,
        transaction_date: '2026-10-31 00:00:00',
        category_id: Category::OtherIncome,
        description: 'TRSF E-BANKING CR BCA',
    ));

    $batch = resolve(ImportService::class)->upload($user, $account, statementFile());

    $duplicate = $batch->rows->firstWhere('is_duplicate', true);

    expect($duplicate)->not->toBeNull()
        ->and($duplicate->description)->toBe('TRSF E-BANKING CR BCA')
        ->and($duplicate->is_included)->toBeFalse();
});

it('rejects upload when the provider has no parser', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->for($user, 'owner')->create(); // no provider

    resolve(ImportService::class)->upload($user, $account, statementFile());
})->throws(ValidationException::class);

it('maps parser file failures to validation errors and creates nothing', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    try {
        resolve(ImportService::class)->upload($user, $account, statementFile('mandiri-unrecognized.xlsx'));
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey('statement');
    }

    expect(ImportBatch::count())->toBe(0);
});

it('updates editable fields on a staging row', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = resolve(ImportService::class)->upload($user, $account, statementFile());
    $row = $batch->rows->first();

    resolve(ImportService::class)->updateRow($row, [
        'description' => 'Edited description',
        'amount' => 999999,
        'flow' => 'outflow',
        'category_id' => Category::Electricity->value,
        'is_included' => false,
    ]);

    expect($row->fresh())
        ->description->toBe('Edited description')
        ->amount->toBe(999999.0)
        ->flow->toBe('outflow')
        ->category_id->toBe(Category::Electricity)
        ->is_included->toBeFalse();
});

it('rejects a transfer destination equal to the batch account', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = resolve(ImportService::class)->upload($user, $account, statementFile());
    $row = $batch->rows->first();

    resolve(ImportService::class)->updateRow($row, [
        'transfer_destination_account_id' => $account->id,
    ]);
})->throws(ValidationException::class);

it('rejects editing rows of non-staging batches', function (): void {
    $batch = ImportBatch::factory()->approved()->create();
    $row = ImportRow::factory()->create(['batch_id' => $batch->id]);

    resolve(ImportService::class)->updateRow($row, ['description' => 'nope']);
})->throws(ValidationException::class);

it('approves included rows into real transactions and updates the balance', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);
    $balanceBefore = $account->current_balance;

    $batch = resolve(ImportService::class)->upload($user, $account, statementFile()); // 2 inflows + 1 outflow
    $imported = resolve(ImportService::class)->approve($batch);

    expect($imported)->toBe(3)
        ->and($batch->fresh())
        ->status->toBe(ImportBatchStatus::Approved)
        ->imported_rows->toBe(3)
        ->approved_at->not->toBeNull();

    // 2,500,000 + 25,000 inflows − 350,000 outflow = +2,175,000
    expect($account->fresh()->current_balance - $balanceBefore)->toBe(2175000);

    // staged rows survive as audit trail
    expect($batch->rows()->count())->toBe(3);
});

it('approve materializes a transfer-marked row as an aggregate with a mirrored member', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);
    $destination = Account::factory()->for($user, 'owner')->create();
    $destinationBefore = $destination->current_balance;

    $batch = resolve(ImportService::class)->upload($user, $account, statementFile());
    $row = $batch->rows->firstWhere('description', 'PEMBAYARAN LISTRIK PLN');

    resolve(ImportService::class)->updateRow($row, ['transfer_destination_account_id' => $destination->id]);
    resolve(ImportService::class)->approve($batch);

    $transfer = Transfer::query()->sole();
    $members = $transfer->transactions;

    expect($members)->toHaveCount(2)
        ->and($members->where('account_id', $account->id)->firstWhere('flow', 'outflow'))->not->toBeNull()
        ->and($members->where('account_id', $destination->id)->firstWhere('flow', 'inflow'))->not->toBeNull()
        ->and($destination->fresh()->current_balance - $destinationBefore)->toBe(350000);
});

it('approve re-validates and rejects error rows left included', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = resolve(ImportService::class)->upload($user, $account, statementFile('mandiri-dirty.xlsx'));
    $errorRow = $batch->rows->firstWhere('is_included', false);

    resolve(ImportService::class)->updateRow($errorRow, ['is_included' => true]);

    resolve(ImportService::class)->approve($batch->fresh());
})->throws(ValidationException::class);

it('approve rejects zero included rows', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = resolve(ImportService::class)->upload($user, $account, statementFile());
    $batch->rows()->update(['is_included' => false]);

    resolve(ImportService::class)->approve($batch->fresh());
})->throws(ValidationException::class);

it('discard keeps rows and flips status', function (): void {
    $user = User::factory()->create();
    $account = importableAccount($user);

    $batch = resolve(ImportService::class)->upload($user, $account, statementFile());
    resolve(ImportService::class)->discard($batch);

    expect($batch->fresh())
        ->status->toBe(ImportBatchStatus::Discarded)
        ->discarded_at->not->toBeNull()
        ->and($batch->rows()->count())->toBe(3);
});
