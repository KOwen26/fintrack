<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createBudgetSetup(): array
{
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create([
        'household_id' => $household->id,
        'user_id' => $user->id,
    ]);

    $account = Account::factory()->create([
        'owner_id' => $user->id,
        'household_id' => $household->id,
    ]);

    $category = Category::factory()->create();

    return [$user, $account, $category];
}

it('lists budgets for an account and period', function (): void {
    [$user, $account, $category] = createBudgetSetup();
    Budget::factory()->forPeriod(2026, 6)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs($user)->get(route('budgets.index', ['account' => $account->id, 'year' => 2026, 'month' => 6]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('budgets/index')
            ->has('budgets_with_status', 1)
        );
});

it('creates a budget via store', function (): void {
    [$user, $account, $category] = createBudgetSetup();

    $this->actingAs($user)->post(route('budgets.store', $account), [
        'category_id' => $category->id,
        'limit_amount' => 1_500_000,
        'year' => 2026,
        'month' => 6,
    ])->assertRedirect(route('budgets.index', $account));

    expect(Budget::where('account_id', $account->id)
        ->where('category_id', $category->id)
        ->where('year', 2026)
        ->where('month', 6)
        ->exists()
    )->toBeTrue();
});

it('upserts a budget when the same period already exists', function (): void {
    [$user, $account, $category] = createBudgetSetup();
    $year = now()->year;
    $month = now()->month;

    Budget::factory()->forPeriod($year, $month)->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
        'limit_amount' => 1_000_000,
    ]);

    $this->actingAs($user)->post(route('budgets.store', $account), [
        'category_id' => $category->id,
        'limit_amount' => 2_000_000,
        'year' => $year,
        'month' => $month,
    ])->assertRedirect(route('budgets.index', $account));

    expect(Budget::where('account_id', $account->id)
        ->where('category_id', $category->id)
        ->value('limit_amount'))->toBe('2000000.00');
});

it('soft-deletes a budget', function (): void {
    [$user, $account, $category] = createBudgetSetup();
    $budget = Budget::factory()->create([
        'account_id' => $account->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs($user)->delete(route('budgets.destroy', [$account, $budget]))
        ->assertRedirect(route('budgets.index', $account));

    expect(Budget::withTrashed()->find($budget->id))->not->toBeNull();
});

it('prevents another user from deleting a budget', function (): void {
    [$user, $account] = createBudgetSetup();
    $budget = Budget::factory()->create([
        'account_id' => $account->id,
    ]);

    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)->delete(route('budgets.destroy', [$account, $budget]))
        ->assertForbidden();
});
