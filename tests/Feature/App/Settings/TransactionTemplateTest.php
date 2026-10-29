<?php

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\TagService;

test('lists only the authenticated user templates with categories', function (): void {
    $user = User::factory()->create();
    TransactionTemplate::factory()->create(['user_id' => $user->id]);
    TransactionTemplate::factory()->create(); // another user's

    $this->actingAs($user)->get(route('transaction-templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/transaction-templates/index')
            ->has('templates', 1)
            ->has('categories')
        );
});

test('renders the empty template form', function (): void {
    $user = User::factory()->create();
    Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->get(route('transaction-templates.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/transaction-templates/form')
            ->where('template', null)
            ->has('accounts')
        );
});

test('renders the edit form with the template', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('transaction-templates.edit', $template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/transaction-templates/form')
            ->where('template.id', $template->id)
            ->has('accounts')
        );
});

test('renders the template detail page', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('transaction-templates.show', $template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/transaction-templates/show')
            ->where('template.id', $template->id)
            ->has('categories')
        );
});

test('stores an expense template and redirects to the index', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('transaction-templates.store'), [
        'name' => 'Netflix',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 54_000,
        'description' => 'Monthly subscription',
        'account_id' => $account->id,
        'decorations' => ['icon' => 'tv', 'color' => 'red-500'],
    ]);

    $template = TransactionTemplate::query()->where('name', 'Netflix')->first();
    expect($template)->not->toBeNull()
        ->and($template->account_id)->toBe($account->id)
        ->and($template->category_id)->toBe(Category::Streaming)
        ->and($template->decorations?->icon)->toBe('tv');

    $response->assertRedirectToRoute('transaction-templates.index');
});

test('stores a transfer template with destination and fee', function (): void {
    $user = User::factory()->create();
    $destination = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('transaction-templates.store'), [
        'name' => 'Savings sweep',
        'type' => TransactionType::Transfer->value,
        'amount' => 1_000_000,
        'destination_account_id' => $destination->id,
        'fee_amount' => 2_500,
    ])->assertRedirectToRoute('transaction-templates.index');

    expect(TransactionTemplate::query()->where('name', 'Savings sweep')->first())
        ->destination_account_id->toBe($destination->id);
});

test('rejects an empty payload with the required-field errors', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('transaction-templates.store'), [])->assertInvalid([
        'name',
        'type',
        'amount',
        'category_id',
    ]);
});

test('rejects an income template without a category', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('transaction-templates.store'), [
        'name' => 'Salary',
        'type' => TransactionType::Income->value,
        'amount' => 5_000_000,
    ])->assertInvalid(['category_id']);
});

test('rejects a transfer template without a destination', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('transaction-templates.store'), [
        'name' => 'Sweep',
        'type' => TransactionType::Transfer->value,
        'amount' => 1_000_000,
    ])->assertInvalid(['destination_account_id']);
});

test('prohibits transfer-only fields on an expense template', function (): void {
    $user = User::factory()->create();
    $foreignAccount = Account::factory()->create();

    $this->actingAs($user)->post(route('transaction-templates.store'), [
        'name' => 'Coffee',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::SnacksAndDrinks->value,
        'amount' => 25_000,
        'destination_account_id' => $foreignAccount->id,
        'fee_amount' => 1_000,
    ])->assertInvalid(['destination_account_id', 'fee_amount']);
});

test('rejects a default account owned by someone else', function (): void {
    $user = User::factory()->create();
    $foreignAccount = Account::factory()->create();

    $this->actingAs($user)->post(route('transaction-templates.store'), [
        'name' => 'Netflix',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 54_000,
        'account_id' => $foreignAccount->id,
    ])->assertInvalid(['account_id']);
});

test('rejects the initial balance category', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('transaction-templates.store'), [
        'name' => 'Bad',
        'type' => TransactionType::Income->value,
        'category_id' => Category::InitialBalance->value,
        'amount' => 1_000,
    ])->assertInvalid(['category_id']);
});

test('stores template tags normalized', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('transaction-templates.store'), [
        'name' => 'Netflix',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 54_000,
        'tags' => ['Bills & Rent!', 'food!'],
    ])->assertRedirectToRoute('transaction-templates.index');

    expect(TransactionTemplate::query()->where('name', 'Netflix')->first()->tags?->toArray())
        ->toBe(['bills_rent', 'food']);
});

test('rejects template tags over the cap', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('transaction-templates.store'), [
        'name' => 'Netflix',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 54_000,
        'tags' => array_fill(0, TagService::MAX_TAGS + 1, 'x'),
    ])->assertInvalid(['tags']);

    expect(TransactionTemplate::query()->where('name', 'Netflix')->exists())->toBeFalse();
});

test('another user cannot view, update, or delete a template', function (): void {
    $intruder = User::factory()->create();
    $template = TransactionTemplate::factory()->create();

    $this->actingAs($intruder)->get(route('transaction-templates.show', $template))->assertForbidden();
    $this->actingAs($intruder)->put(route('transaction-templates.update', $template), [
        'name' => 'Hijacked',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 1_000,
    ])->assertForbidden();
    $this->actingAs($intruder)->delete(route('transaction-templates.destroy', $template))->assertForbidden();

    expect($template->fresh()->name)->not->toBe('Hijacked');
});

test('the owner updates and deletes a template', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->put(route('transaction-templates.update', $template), [
        'name' => 'Renamed',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 60_000,
    ])->assertRedirectToRoute('transaction-templates.show', ['template' => $template]);

    expect($template->fresh()->name)->toBe('Renamed');

    $this->actingAs($user)->delete(route('transaction-templates.destroy', $template))
        ->assertRedirectToRoute('transaction-templates.index');

    expect(TransactionTemplate::query()->find($template->id))->toBeNull();
});

test('guests are redirected to login', function (): void {
    $this->get(route('transaction-templates.index'))->assertRedirect(route('auth.login'));
});
