<?php

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\TransactionTemplate;
use App\Models\User;

test('seeds the create form from a template', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);
    $template = TransactionTemplate::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
    ]);

    $this->actingAs($user)->get(route('transactions.create', ['template' => $template->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/create')
            ->where('transaction.type', TransactionType::Expense->value)
            ->where('transaction.category_id', Category::DiningOut->value)
            ->where('transaction.amount', 25000)
            ->where('transaction.account_id', $account->id)
            ->has('templates', 1)
            ->has('accounts')
        );
});

test('an explicit account_id query param wins over the template default', function (): void {
    $user = User::factory()->create();
    $default = Account::factory()->create(['owner_id' => $user->id]);
    $explicit = Account::factory()->create(['owner_id' => $user->id]);
    $template = TransactionTemplate::factory()->create([
        'user_id' => $user->id,
        'account_id' => $default->id,
    ]);

    $this->actingAs($user)->get(route('transactions.create', [
        'template' => $template->id,
        'account_id' => $explicit->id,
    ]))->assertInertia(fn ($page) => $page
        ->where('transaction.account_id', $explicit->id)
    );
});

test('a transfer template seeds the destination and fee', function (): void {
    $user = User::factory()->create();
    $destination = Account::factory()->create(['owner_id' => $user->id]);
    $template = TransactionTemplate::factory()->transfer()->create([
        'user_id' => $user->id,
        'destination_account_id' => $destination->id,
        'fee_amount' => 2_500,
    ]);

    $this->actingAs($user)->get(route('transactions.create', ['template' => $template->id]))
        ->assertInertia(fn ($page) => $page
            ->where('transaction.type', TransactionType::Transfer->value)
            ->where('transaction.destination_account_id', $destination->id)
            ->where('transaction.fee_amount', 2500)
        );
});

test('a transfer destination equal to the resolved context source is dropped', function (): void {
    $user = User::factory()->create();
    $destination = Account::factory()->create(['owner_id' => $user->id]);
    $template = TransactionTemplate::factory()->transfer()->create([
        'user_id' => $user->id,
        'destination_account_id' => $destination->id,
    ]);

    $this->actingAs($user)->get(route('transactions.create', [
        'template' => $template->id,
        'account_id' => $destination->id,
    ]))->assertInertia(fn ($page) => $page
        ->where('transaction.destination_account_id', null)
    );
});

test('prefills the create form with the template’s tags', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->create([
        'user_id' => $user->id,
        'tags' => ['food'],
    ]);

    $this->actingAs($user)->get(route('transactions.create', ['template' => $template->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/transaction/create')
            ->where('transaction.tags', ['food'])
        );
});

test('a foreign or missing template id is silently ignored', function (): void {
    $user = User::factory()->create();
    $foreign = TransactionTemplate::factory()->create();

    $this->actingAs($user)->get(route('transactions.create', ['template' => $foreign->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transaction.amount', null)
            ->where('transaction.account_id', null)
        );

    $this->actingAs($user)->get(route('transactions.create', ['template' => 999_999]))
        ->assertOk();
});
