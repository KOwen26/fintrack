<?php

use App\Data\Transaction\TransactionListData;
use App\Data\Transaction\TransferData;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionListService;
use App\Services\TransferService;
use Pest\Expectation;

function createTransferAccounts(): array
{
    $user = User::factory()->create();
    $source = Account::factory()->create(['owner_id' => $user->id]);
    $destination = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $source, $destination];
}

function unitData(Account $source, Account $destination, ?float $fee = null, ?array $tags = null): TransferData
{
    return new TransferData(
        account_id: $source->id,
        destination_account_id: $destination->id,
        amount: 500_000,
        transaction_date: now()->toDateString(),
        fee_amount: $fee,
        description: 'Savings move',
        tags: $tags,
    );
}

it('creates a transfer unit with an aggregate, source/destination rows, and an optional fee row', function (): void {
    [$user, $source, $destination] = createTransferAccounts();

    $transfer = resolve(TransferService::class)->create($user, unitData($source, $destination, 6_500.0));

    expect((float) $transfer->fee_amount)->toEqual(6_500.0);

    $rows = $transfer->transactions()->get();
    expect($rows)->toHaveCount(3);

    $sourceRow = $rows->first(fn ($row): bool => $row->type === TransactionType::Transfer && $row->flow === Cashflow::Outflow);
    $destinationRow = $rows->first(fn ($row): bool => $row->type === TransactionType::Transfer && $row->flow === Cashflow::Inflow);
    $feeRow = $rows->first(fn ($row): bool => $row->type === TransactionType::Expense);

    expect($sourceRow->account_id)->toBe($source->id)
        ->and($destinationRow->account_id)->toBe($destination->id)
        ->and($feeRow->account_id)->toBe($source->id)
        ->and((float) $feeRow->amount)->toEqual(6_500.0)
        ->and($feeRow->category_id)->toBe(Category::AdminBankFees);
});

it('recreates member rows and keeps the transfer id stable on unit edit', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    $service = resolve(TransferService::class);

    $transfer = $service->create($user, unitData($source, $destination));
    $originalRowIds = $transfer->transactions()->pluck('id');

    $edited = $service->update($transfer, new TransferData(
        account_id: $source->id,
        destination_account_id: $destination->id,
        amount: 700_000,
        transaction_date: now()->toDateString(),
        fee_amount: null,
        description: 'Edited',
    ));

    expect($edited->id)->toBe($transfer->id)
        ->and((float) $edited->amount)->toEqual(700_000.0)
        ->and($edited->fee_amount)->toBeNull();

    $liveRows = $edited->transactions()->get();
    expect($liveRows)->toHaveCount(2);

    $liveRows->each(fn ($row): Expectation => expect($originalRowIds)->not->toContain($row->id));
    expect(Transaction::withTrashed()->where('transfer_id', $edited->id)->count())->toBe(4);
});

it('puts transfer tags on the source row only', function (): void {
    [$user, $source, $destination] = createTransferAccounts();

    $transfer = resolve(TransferService::class)->create($user, unitData($source, $destination, 2_500.0, ['monthly_sweep']));

    $rows = $transfer->transactions()->get();
    $sourceRow = $rows->first(fn ($row): bool => $row->type === TransactionType::Transfer && $row->flow === Cashflow::Outflow);
    $destinationRow = $rows->first(fn ($row): bool => $row->type === TransactionType::Transfer && $row->flow === Cashflow::Inflow);
    $feeRow = $rows->firstWhere('type', TransactionType::Expense);

    expect($sourceRow->tags?->toArray())->toBe(['monthly_sweep'])
        ->and($destinationRow->tags)->toBeNull()
        ->and($feeRow->tags)->toBeNull();
});

it('keeps tags on the source row across unit edit-recreate', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    $service = resolve(TransferService::class);

    $transfer = $service->create($user, unitData($source, $destination, null, ['a']));

    $edited = $service->update($transfer, new TransferData(
        account_id: $source->id,
        destination_account_id: $destination->id,
        amount: 700_000,
        transaction_date: now()->toDateString(),
        fee_amount: null,
        description: 'Edited',
        tags: ['b'],
    ));

    $liveRows = $edited->transactions()->get();
    $sourceRow = $liveRows->first(fn ($row): bool => $row->type === TransactionType::Transfer && $row->flow === Cashflow::Outflow);
    $destinationRow = $liveRows->first(fn ($row): bool => $row->type === TransactionType::Transfer && $row->flow === Cashflow::Inflow);

    expect($sourceRow->tags?->toArray())->toBe(['b'])
        ->and($destinationRow->tags)->toBeNull();
});

it('soft-deletes the aggregate and every member row (fee included) from any member', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    $service = resolve(TransferService::class);

    $transfer = $service->create($user, unitData($source, $destination, 2_500.0));
    $feeRow = $transfer->transactions()->get()->firstWhere('type', TransactionType::Expense);

    // Deleting the FEE cascades the whole unit — symmetric unit semantics.
    $service->deleteUnit($feeRow);

    expect($transfer->fresh()->trashed())->toBeTrue()
        ->and(Transaction::where('transfer_id', $transfer->id)->count())->toBe(0)
        ->and(Transaction::withTrashed()->where('transfer_id', $transfer->id)->count())->toBe(3);
});

it('folds destination_account_id from both movement rows but not the fee', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    $transfer = resolve(TransferService::class)->create($user, unitData($source, $destination, 1_000.0));

    $rows = $transfer->transactions()->get();
    $sourceRow = $rows->first(fn ($row): bool => $row->type === TransactionType::Transfer && $row->flow === Cashflow::Outflow);
    $destinationRow = $rows->first(fn ($row): bool => $row->type === TransactionType::Transfer && $row->flow === Cashflow::Inflow);
    $feeRow = $rows->firstWhere('type', TransactionType::Expense);

    expect(TransactionListData::fromTransaction($sourceRow)->destination_account_id)->toBe($destination->id)
        ->and(TransactionListData::fromTransaction($destinationRow)->destination_account_id)->toBe($source->id)
        ->and(TransactionListData::fromTransaction($feeRow)->destination_account_id)->toBeNull();
});

it('hides inflow rows from the global list but shows fee and outflow rows', function (): void {
    [$user, $source, $destination] = createTransferAccounts();
    resolve(TransferService::class)->create($user, unitData($source, $destination, 1_000.0));

    $global = TransactionListService::getTransactions($user);

    expect($global->where('flow', Cashflow::Inflow))->toBeEmpty()
        ->and($global)->toHaveCount(2); // outflow row + fee row
});
