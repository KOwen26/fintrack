<?php

use App\Data\Transaction\TransactionDetailData;
use App\Data\Transaction\TransactionFormData;
use App\Enums\Category;
use App\Enums\DatePeriodPreset;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TagService;
use App\Services\TransactionListService;
use Illuminate\Http\Request;

function createTaggingAccount(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}

function taggingExpensePayload(Account $account): array
{
    return [
        'account_id' => $account->id,
        'type' => TransactionType::Expense->value,
        'amount' => 25_000,
        'transaction_date' => now()->toDateString(),
        'category_id' => Category::GeneralShopping->value,
    ];
}

test('stores normalized tags on a plain transaction', function (): void {
    [$user, $account] = createTaggingAccount();

    $this->actingAs($user)->post(route('transactions.store'), [
        ...taggingExpensePayload($account),
        'tags' => ['Bills & Rent!', 'food!'],
    ])->assertRedirectToRoute('transactions.index');

    $stored = Transaction::query()->where('account_id', $account->id)->first();

    expect($stored->tags?->toArray())->toBe(['bills_rent', 'food']);
});

test('stores null when tags are omitted or all-dropped', function (): void {
    [$user, $account] = createTaggingAccount();

    $this->actingAs($user)->post(route('transactions.store'), taggingExpensePayload($account));
    $this->actingAs($user)->post(route('transactions.store'), [
        ...taggingExpensePayload($account),
        'tags' => ['!!!'],
    ]);

    $rows = Transaction::query()->where('account_id', $account->id)->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->tags)->toBeNull()
        ->and($rows[1]->tags)->toBeNull();
});

test('rejects more than MAX_TAGS tags without persisting', function (): void {
    [$user, $account] = createTaggingAccount();

    $this->actingAs($user)->post(route('transactions.store'), [
        ...taggingExpensePayload($account),
        'tags' => array_fill(0, TagService::MAX_TAGS + 1, 'x'),
    ])->assertSessionHasErrors('tags');

    expect(Transaction::query()->where('account_id', $account->id)->exists())->toBeFalse();
});

test('rejects a raw tag over MAX_RAW_LENGTH without persisting', function (): void {
    [$user, $account] = createTaggingAccount();

    $this->actingAs($user)->post(route('transactions.store'), [
        ...taggingExpensePayload($account),
        'tags' => [str_repeat('a', TagService::MAX_RAW_LENGTH + 1)],
    ])->assertSessionHasErrors('tags.0');

    expect(Transaction::query()->where('account_id', $account->id)->exists())->toBeFalse();
});

test('replaces tags wholesale on update', function (): void {
    [$user, $account] = createTaggingAccount();
    $transaction = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'tags' => ['a'],
    ]);

    $this->actingAs($user)->put(route('transactions.update', $transaction), [
        ...taggingExpensePayload($account),
        'tags' => ['b'],
    ])->assertRedirectToRoute('transactions.index');

    expect($transaction->fresh()->tags?->toArray())->toBe(['b']);
});

test('exposes tags on list rows and the detail DTO', function (): void {
    [$user, $account] = createTaggingAccount();

    $tagged = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'transaction_date' => now()->subDay()->format('Y-m-d H:i:s'),
        'tags' => ['food'],
    ]);
    $untagged = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'transaction_date' => now()->subDay()->format('Y-m-d H:i:s'),
    ]);

    $rows = TransactionListService::getTransactions($user, DatePeriodPreset::YearOnYear);
    $tagRow = collect($rows)->firstWhere('id', $tagged->id);
    $untaggedRow = collect($rows)->firstWhere('id', $untagged->id);

    expect($tagRow['tags'])->toBe(['food'])
        ->and($untaggedRow['tags'])->toBeNull()
        ->and(TransactionDetailData::fromTransaction($tagged->fresh())->tags)->toBe(['food']);
});

test('seeds the edit form from the row — transfers from the source row', function (): void {
    [$user, $account] = createTaggingAccount();
    $destination = Account::factory()->create(['owner_id' => $user->id]);

    $tagged = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'tags' => ['food'],
    ]);

    $transfer = Transfer::factory()->create(['created_by' => $user->id]);
    Transaction::factory()->transferOutflow($transfer)->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'tags' => ['savings'],
    ]);
    Transaction::factory()->transferInflow($transfer)->create([
        'account_id' => $destination->id,
        'created_by' => $user->id,
    ]);

    expect(TransactionFormData::fromTransaction($tagged)->tags)->toBe(['food'])
        ->and(TransactionFormData::fromTransfer($transfer)->tags)->toBe(['savings'])
        ->and(TransactionFormData::defaultFromRequest(new Request([]))->tags)->toBeNull();
});

test('passes the tags prop on the create and edit pages', function (): void {
    [$user, $account] = createTaggingAccount();
    Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
        'tags' => ['food'],
    ]);

    $this->actingAs($user)->get(route('transactions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/create')
            ->where('tags', ['food'])
        );

    $transaction = Transaction::factory()->create([
        'account_id' => $account->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/edit')
            ->where('tags', ['food'])
        );
});
