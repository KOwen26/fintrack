# Remove Household System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the household feature entirely so all users share a flat view of all accounts and transactions, while accounts retain an `owner_id` for edit/delete authorization.

**Architecture:** Clean-slate migration rewrite (dev-only, no prod data). All household PHP files are deleted after their references are first removed from dependents. Frontend household pages are deleted; nav and account form are updated. Wayfinder is regenerated last.

**Tech Stack:** PHP 8.4 / Laravel 13 / Pest 4 / Svelte 5 / Inertia v3 / Wayfinder

## Global Constraints

- Run `vendor/bin/pint --dirty --format agent` after every PHP file change.
- Never hardcode URLs — use Wayfinder functions.
- All `.svelte` and `.ts` filenames must be `kebab-case`.

---

## File Map

### Delete (PHP)
- `database/migrations/2026_06_15_142537_create_households_table.php`
- `database/migrations/2026_06_15_142538_create_household_invitations_table.php`
- `database/migrations/2026_06_15_142538_create_household_members_table.php`
- `app/Models/Household.php`
- `app/Models/HouseholdMember.php`
- `app/Models/HouseholdInvitation.php`
- `database/factories/HouseholdFactory.php`
- `database/factories/HouseholdMemberFactory.php`
- `database/factories/HouseholdInvitationFactory.php`
- `app/Enums/HouseholdMemberRole.php`
- `app/Data/HouseholdData.php`
- `app/Data/HouseholdMemberData.php`
- `app/Services/HouseholdService.php`
- `app/Policies/HouseholdPolicy.php`
- `app/Http/Controllers/HouseholdsController.php`
- `app/Http/Controllers/HouseholdInvitationsController.php`
- `app/Http/Requests/StoreHouseholdRequest.php`
- `app/Http/Requests/InviteHouseholdMemberRequest.php`
- `tests/Feature/HouseholdTest.php`
- `tests/Feature/HouseholdInvitationTest.php`

### Delete (Frontend)
- `resources/js/pages/household/settings.svelte`
- `resources/js/pages/household/invitation.svelte`

### Modify (PHP)
- `database/migrations/2026_06_15_142539_create_accounts_table.php` — remove `household_id`
- `app/Models/Account.php` — remove `household()` + `visibleTo` scope
- `app/Models/User.php` — remove `householdMemberships()`
- `app/Policies/AccountPolicy.php` — `view()` returns `true`; remove `canAccess()`
- `app/Services/AccountService.php` — remove `visibleTo($user)`; drop `User` param from query methods
- `app/Http/Controllers/AccountsController.php` — remove `HouseholdService` dep; remove `household_id` from `create()`
- `app/Http/Controllers/TransactionsController.php` — update `getTransferEligibleAccounts` call (remove `$request->user()` arg)
- `app/Http/Requests/StoreAccountRequest.php` — remove `household_id` rule
- `database/factories/AccountFactory.php` — remove `household_id`; remove `joint()` state
- `routes/web.php` — remove 7 household routes + 2 controller imports
- `tests/Pest.php` — rewrite `createUserWithAccountAndHousehold()`
- `tests/Feature/AccountTest.php` — full rewrite
- `tests/Feature/TransactionTest.php` — update helper to remove household setup
- `tests/Feature/BalanceServiceTest.php` — update helper to remove household setup
- `tests/Feature/BudgetTest.php` — update helper to remove household setup
- `tests/Feature/ReportTest.php` — update helper to remove household setup
- `tests/Feature/RecurringPresetTest.php` — update helper to remove household setup
- `tests/Feature/RunRecurringPresetsCommandTest.php` — update helper to remove household setup

### Modify (Frontend)
- `resources/js/components/navigation/bottom-nav.svelte` — remove Household nav item
- `resources/js/components/module/account/account-form.svelte` — remove `household_id`
- `resources/js/pages/accounts/create.svelte` — remove `household_id` prop

---

## Task 1: Rewrite accounts migration + delete household migrations

**Files:**
- Modify: `database/migrations/2026_06_15_142539_create_accounts_table.php`
- Delete: `database/migrations/2026_06_15_142537_create_households_table.php`
- Delete: `database/migrations/2026_06_15_142538_create_household_invitations_table.php`
- Delete: `database/migrations/2026_06_15_142538_create_household_members_table.php`

- [ ] **Step 1: Rewrite the accounts migration**

Replace the entire contents of `database/migrations/2026_06_15_142539_create_accounts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('access_type');
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('credit_card_limit', 15, 2)->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->json('cosmetics')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('owner_id');
            $table->index('archived_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
```

- [ ] **Step 2: Delete the three household migration files**

```bash
rm database/migrations/2026_06_15_142537_create_households_table.php
rm "database/migrations/2026_06_15_142538_create_household_invitations_table.php"
rm "database/migrations/2026_06_15_142538_create_household_members_table.php"
```

---

## Task 2: Remove household references from all existing PHP files

These edits remove every reference to household classes **before** deleting those classes, keeping the codebase in a compilable state throughout.

**Files:**
- Modify: `app/Models/Account.php`
- Modify: `app/Models/User.php`
- Modify: `app/Policies/AccountPolicy.php`
- Modify: `app/Services/AccountService.php`
- Modify: `app/Http/Controllers/AccountsController.php`
- Modify: `app/Http/Controllers/TransactionsController.php`
- Modify: `app/Http/Requests/StoreAccountRequest.php`
- Modify: `database/factories/AccountFactory.php`
- Modify: `routes/web.php`

**Key interface changes produced by this task:**
- `AccountService::getVisibleAccounts()` — no longer takes a `User` parameter
- `AccountService::getTransferEligibleAccounts(?Account $excludeAccount)` — no longer takes a `User` parameter

- [ ] **Step 1: Replace `app/Models/Account.php`**

```php
<?php

namespace App\Models;

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'access_type' => AccountAccessType::class,
            'initial_balance' => 'decimal:2',
            'credit_card_limit' => 'decimal:2',
            'archived_at' => 'datetime',
            'cosmetics' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
```

- [ ] **Step 2: Replace `app/Models/User.php`**

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'theme_preference',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 3: Replace `app/Policies/AccountPolicy.php`**

```php
<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Account $account): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }

    public function delete(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }

    public function restore(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }

    public function archive(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }
}
```

- [ ] **Step 4: Replace `app/Services/AccountService.php`**

```php
<?php

namespace App\Services;

use App\Data\CosmeticData;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AccountService
{
    public function getVisibleAccounts(): Collection
    {
        return Account::query()
            ->whereNull('archived_at')
            ->with('provider')
            ->get();
    }

    public function getTransferEligibleAccounts(?Account $excludeAccount = null): Collection
    {
        return Account::query()
            ->whereNull('archived_at')
            ->when($excludeAccount, fn ($q) => $q->where('id', '!=', $excludeAccount->id))
            ->get();
    }

    public function create(User $user, array $data): Account
    {
        return Account::create([...$this->normalizeCosmetics($data), 'owner_id' => $user->id]);
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($this->normalizeCosmetics($data));

        return $account->fresh();
    }

    private function normalizeCosmetics(array $data): array
    {
        if (! isset($data['cosmetics'])) {
            return $data;
        }

        $data['cosmetics'] = CosmeticData::from($data['cosmetics'])->toArray();

        return $data;
    }

    public function archive(Account $account): Account
    {
        $account->update(['archived_at' => now()]);

        return $account->fresh();
    }

    public function restore(Account $account): Account
    {
        $account->update(['archived_at' => null]);

        return $account->fresh();
    }

    public function softDelete(Account $account): void
    {
        $account->delete();
    }
}
```

- [ ] **Step 5: Replace `app/Http/Controllers/AccountsController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Provider;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountsController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('accounts/index', [
            'accounts' => $this->accountService->getVisibleAccounts(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('accounts/create', [
            'providers' => Provider::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $this->accountService->create($request->user(), $request->validated());

        return to_route('accounts.show', $account)->flash('Account created.');
    }

    public function show(Request $request, Account $account): Response
    {
        $this->authorize('view', $account);

        return Inertia::render('accounts/show', [
            'account' => $account->load('provider'),
        ]);
    }

    public function edit(Account $account): Response
    {
        $this->authorize('update', $account);

        return Inertia::render('accounts/edit', [
            'account' => $account->load('provider'),
            'providers' => Provider::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);
        $this->accountService->update($account, $request->validated());

        return to_route('accounts.show', $account)->flash('Account updated.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);
        $this->accountService->softDelete($account);

        return to_route('accounts.index')->flash('Account deleted.');
    }

    public function archive(Account $account): RedirectResponse
    {
        $this->authorize('archive', $account);
        $this->accountService->archive($account);

        return to_route('accounts.index')->flash('Account archived.');
    }

    public function restore(Account $account): RedirectResponse
    {
        $this->authorize('archive', $account);
        $this->accountService->restore($account);

        return to_route('accounts.show', $account)->flash('Account restored.');
    }
}
```

- [ ] **Step 6: Update `app/Http/Controllers/TransactionsController.php`**

In the `create` method, change:

```php
'accounts' => $this->accountService->getTransferEligibleAccounts($request->user(), $account),
```

to:

```php
'accounts' => $this->accountService->getTransferEligibleAccounts($account),
```

- [ ] **Step 7: Replace `app/Http/Requests/StoreAccountRequest.php`**

```php
<?php

namespace App\Http\Requests;

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(AccountType::class)],
            'access_type' => ['required', 'string', Rule::enum(AccountAccessType::class)],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'initial_balance' => ['required', 'numeric', 'min:0'],
            'credit_card_limit' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'cosmetics' => ['nullable', 'array'],
            'cosmetics.icon' => ['required_with:cosmetics', 'string', 'max:100'],
            'cosmetics.color' => ['required_with:cosmetics', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
```

- [ ] **Step 8: Replace `database/factories/AccountFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'provider_id' => null,
            'name' => fake()->words(2, true),
            'type' => AccountType::DebitAccount->value,
            'access_type' => AccountAccessType::Personal->value,
            'initial_balance' => 0,
            'credit_card_limit' => null,
            'currency' => 'IDR',
            'cosmetics' => [
                'icon' => 'ph:wallet-bold',
                'color' => fake()->hexColor(),
            ],
            'archived_at' => null,
        ];
    }

    public function creditCard(): static
    {
        return $this->state([
            'type' => AccountType::CreditCard->value,
            'credit_card_limit' => 5_000_000,
        ]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
```

- [ ] **Step 9: Remove household routes from `routes/web.php`**

Remove the two `use` imports at the top:

```php
use App\Http\Controllers\HouseholdInvitationsController;
use App\Http\Controllers\HouseholdsController;
```

Remove the entire household + invitation route block:

```php
// Household
Route::get('household/settings', [HouseholdsController::class, 'show'])->name('household.settings');
Route::post('household', [HouseholdsController::class, 'store'])->name('household.store');
Route::post('household/invite', [HouseholdsController::class, 'invite'])->name('household.invite');
Route::delete('household/members/{member}', [HouseholdsController::class, 'removeMember'])->name('household.members.destroy');

// Household invitations
Route::get('household/invitations/{token}', [HouseholdInvitationsController::class, 'show'])->name('household.invitations.show');
Route::post('household/invitations/{token}/accept', [HouseholdInvitationsController::class, 'accept'])->name('household.invitations.accept');
Route::post('household/invitations/{token}/decline', [HouseholdInvitationsController::class, 'decline'])->name('household.invitations.decline');
```

- [ ] **Step 10: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

---

## Task 3: Delete all household-only PHP files

- [ ] **Step 1: Delete models**

```bash
rm app/Models/Household.php
rm app/Models/HouseholdMember.php
rm app/Models/HouseholdInvitation.php
```

- [ ] **Step 2: Delete factories**

```bash
rm database/factories/HouseholdFactory.php
rm database/factories/HouseholdMemberFactory.php
rm database/factories/HouseholdInvitationFactory.php
```

- [ ] **Step 3: Delete enum, DTOs, service, policy**

```bash
rm app/Enums/HouseholdMemberRole.php
rm app/Data/HouseholdData.php
rm app/Data/HouseholdMemberData.php
rm app/Services/HouseholdService.php
rm app/Policies/HouseholdPolicy.php
```

- [ ] **Step 4: Delete controllers and form requests**

```bash
rm app/Http/Controllers/HouseholdsController.php
rm app/Http/Controllers/HouseholdInvitationsController.php
rm app/Http/Requests/StoreHouseholdRequest.php
rm app/Http/Requests/InviteHouseholdMemberRequest.php
```

- [ ] **Step 5: Verify no stray references**

```bash
grep -r "Household\|household_id" app/ database/ routes/ --include="*.php"
```

Expected: no output. If any hits appear, fix them before continuing.

---

## Task 4: Rewrite and delete tests

Every test file that uses `Household::factory()` or `household_id` must be updated. The global helper in `Pest.php` is also replaced.

**Files:**
- Modify: `tests/Pest.php`
- Modify: `tests/Feature/AccountTest.php`
- Modify: `tests/Feature/TransactionTest.php`
- Modify: `tests/Feature/BalanceServiceTest.php`
- Modify: `tests/Feature/BudgetTest.php`
- Modify: `tests/Feature/ReportTest.php`
- Modify: `tests/Feature/RecurringPresetTest.php`
- Modify: `tests/Feature/RunRecurringPresetsCommandTest.php`
- Delete: `tests/Feature/HouseholdTest.php`
- Delete: `tests/Feature/HouseholdInvitationTest.php`

- [ ] **Step 1: Delete household tests**

```bash
rm tests/Feature/HouseholdTest.php
rm tests/Feature/HouseholdInvitationTest.php
```

- [ ] **Step 2: Update `tests/Pest.php`**

Remove `Household` and `HouseholdMember` imports. Rewrite `createUserWithAccountAndHousehold()` to return `[user, null, account]` so existing callers that destructure `[$user, $household, $account]` don't break (the `$household` variable will be `null` — safe as long as callers don't use it directly):

```php
<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Expectation;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

pest()->browser()->timeout(5000);

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function something()
{
    // ..
}

/**
 * Create a user and account for testing.
 * Returns [user, null, account] — null in position 1 for backwards compatibility.
 */
function createUserWithAccountAndHousehold(array $accountAttributes = []): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(array_merge(['owner_id' => $user->id], $accountAttributes));

    return [$user, null, $account];
}
```

- [ ] **Step 3: Replace `tests/Feature/AccountTest.php`**

```php
<?php

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists all accounts for any authenticated user', function (): void {
    $user = User::factory()->create();
    Account::factory()->create(['owner_id' => $user->id]);
    Account::factory()->create(); // owned by a different user

    $this->actingAs($user)->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('accounts/index')
            ->has('accounts', 2)
        );
});

it('stores a new account', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('accounts.store'), [
        'name' => 'BCA Savings',
        'type' => AccountType::DebitAccount->value,
        'access_type' => AccountAccessType::Personal->value,
        'initial_balance' => 1_000_000,
        'currency' => 'IDR',
        'provider_id' => null,
        'credit_card_limit' => null,
        'cosmetics' => [
            'icon' => 'ph:wallet-bold',
            'color' => '#22c55e',
        ],
    ])->assertRedirect();

    $account = Account::where('name', 'BCA Savings')->first();
    expect($account)->not->toBeNull();
    expect($account->cosmetics)->toMatchArray(['icon' => 'ph:wallet-bold', 'color' => '#22c55e']);
});

it('allows any user to view any account', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner

    $this->actingAs($user)->get(route('accounts.show', $otherAccount))
        ->assertOk();
});

it('prevents non-owner from editing an account', function (): void {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create(); // different owner

    $this->actingAs($user)->get(route('accounts.edit', $otherAccount))
        ->assertForbidden();
});

it('archives an account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('accounts.archive', $account))
        ->assertRedirect(route('accounts.index'));

    expect($account->fresh()->archived_at)->not->toBeNull();
});

it('soft-deletes an account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'));

    expect(Account::find($account->id))->toBeNull();
    expect(Account::withTrashed()->find($account->id))->not->toBeNull();
});
```

- [ ] **Step 4: Update `tests/Feature/TransactionTest.php`**

Remove `use App\Models\Household;` and `use App\Models\HouseholdMember;`. Replace `createAccountForUser()`:

```php
function createAccountForUser(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}
```

- [ ] **Step 5: Update `tests/Feature/BalanceServiceTest.php`**

Remove `use App\Models\Household;` and `use App\Models\HouseholdMember;`. Replace `setupBalanceAccount()`:

```php
function setupBalanceAccount(float $initialBalance = 0): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'owner_id' => $user->id,
        'initial_balance' => $initialBalance,
    ]);

    return [$user, $account];
}
```

- [ ] **Step 6: Update `tests/Feature/BudgetTest.php`**

Remove `use App\Models\Household;` and `use App\Models\HouseholdMember;`. Replace `createBudgetSetup()`:

```php
function createBudgetSetup(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);
    $category = Category::factory()->create();

    return [$user, $account, $category];
}
```

- [ ] **Step 7: Update `tests/Feature/ReportTest.php`**

Remove `use App\Models\Household;` and `use App\Models\HouseholdMember;`. Find any helper function that creates a household and simplify it to:

```php
$user = User::factory()->create();
$account = Account::factory()->create(['owner_id' => $user->id]);
```

- [ ] **Step 8: Update `tests/Feature/RecurringPresetTest.php`**

Remove `use App\Models\Household;` and `use App\Models\HouseholdMember;`. Replace `createUserWithAccount()`:

```php
function createUserWithAccount(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['owner_id' => $user->id]);

    return [$user, $account];
}
```

- [ ] **Step 9: Update `tests/Feature/RunRecurringPresetsCommandTest.php`**

Remove `use App\Models\Household;` and `use App\Models\HouseholdMember;`. Replace any household setup helper with:

```php
$user = User::factory()->create();
$account = Account::factory()->create(['owner_id' => $user->id]);
```

- [ ] **Step 10: Verify no stray references**

```bash
grep -r "Household\|household_id" tests/ --include="*.php"
```

Expected: no output.

---

## Task 5: Update frontend

**Files:**
- Delete: `resources/js/pages/household/settings.svelte`
- Delete: `resources/js/pages/household/invitation.svelte`
- Modify: `resources/js/components/navigation/bottom-nav.svelte`
- Modify: `resources/js/components/module/account/account-form.svelte`
- Modify: `resources/js/pages/accounts/create.svelte`

- [ ] **Step 1: Delete household pages**

```bash
rm resources/js/pages/household/settings.svelte
rm resources/js/pages/household/invitation.svelte
```

- [ ] **Step 2: Replace `resources/js/components/navigation/bottom-nav.svelte`**

Remove the Household link and its import:

```svelte
<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AccountsController from '@wayfinder/App/Http/Controllers/AccountsController';
    import CategoriesController from '@wayfinder/App/Http/Controllers/CategoriesController';

    const currentRoute = $derived(
        (page.props.meta as { current_route_name?: string } | null)?.current_route_name ?? ''
    );
    const isActive = (prefix: string) => currentRoute.startsWith(prefix);
</script>

<nav
    class="btm-nav btm-nav-sm fixed bottom-0 left-0 right-0 z-50 border-t border-base-300 bg-base-100">
    <a
        class:active={isActive('accounts')}
        aria-label="Accounts"
        href={AccountsController.index.url()}>
        <i class="iconify size-5 ph--wallet-bold"></i>
        <span class="btm-nav-label text-xs">Accounts</span>
    </a>

    <a
        class:active={isActive('categories')}
        aria-label="Categories"
        href={CategoriesController.index.url()}>
        <i class="iconify size-5 ph--tag-bold"></i>
        <span class="btm-nav-label text-xs">Categories</span>
    </a>

    <button class="rounded-full bg-primary text-primary-content" aria-label="Quick add" disabled>
        <i class="iconify size-6 ph--plus-bold"></i>
    </button>

    <a class:active={isActive('dashboard')} aria-label="Reports" href="/dashboard">
        <i class="iconify size-5 ph--chart-bar-bold"></i>
        <span class="btm-nav-label text-xs">Reports</span>
    </a>
</nav>
```

- [ ] **Step 3: Replace `resources/js/components/module/account/account-form.svelte`**

Remove `household_id` from props and form data:

```svelte
<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import AccountsController from '@wayfinder/App/Http/Controllers/AccountsController';

    import { accountSchema } from '@schema/account.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        providers: App.Models.Provider[];
        account?: App.Models.Account;
        onCancel?: () => void;
    }

    let { providers, account, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!account);

    const providerOptions = $derived([
        { value: '', label: '— None —' },
        ...providers.map((p) => ({ value: p.id, label: p.name })),
    ]);

    const formSchema = $derived(() => {
        const composer = DataComposer.from(accountSchema).extendSchema({
            provider_id: {
                label: 'Provider (optional)',
                form: () => ({ type: 'select', name: 'provider_id', options: providerOptions }),
            },
        });

        if (isEdit && account) {
            return composer.except(['access_type', 'initial_balance']).toFormGenerator({
                name: account.name,
                type: account.type,
                provider_id: account.provider_id ?? '',
                credit_card_limit: account.credit_card_limit
                    ? Number(account.credit_card_limit)
                    : null,
                currency: account.currency,
            });
        }

        return composer.toFormGenerator({
            type: AccountType.DebitAccount,
            access_type: AccountAccessType.Personal,
            provider_id: '',
            initial_balance: 0,
            credit_card_limit: null,
            currency: 'IDR',
        });
    });

    const action = $derived(
        isEdit && account
            ? AccountsController.update.url({ account: account.id })
            : AccountsController.store.url()
    );

    const method = $derived(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Account');
</script>

<Card>
    <FormGenerator
        id="account-form"
        {action}
        formSchema={formSchema()}
        {method}
        withoutSubmit
        bind:form />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="account-form"
        labelCancel="Cancel"
        labelSubmit={submitLabel}
        onCancel={onCancel ?? (() => window.history.back())}
        withoutCancel={!onCancel && !isEdit} />
</div>
```

- [ ] **Step 4: Replace `resources/js/pages/accounts/create.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import AccountsController from '@wayfinder/App/Http/Controllers/AccountsController';

    import AccountForm from '@components/module/account/account-form.svelte';
    import Button from '@components/ui/button.svelte';

    let { providers }: { providers: App.Models.Provider[] } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={AccountsController.index.url()}
            variant="ghost">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <h1 class="text-xl font-bold">New Account</h1>
    </div>

    <AccountForm {providers} />
</div>
```

---

## Task 6: Regenerate Wayfinder

- [ ] **Step 1: Regenerate Wayfinder**

```bash
php artisan wayfinder:generate
```

- [ ] **Step 2: Verify removed files are gone**

```bash
ls resources/js/wayfinder/App/Http/Controllers/ | grep -i household
ls resources/js/wayfinder/App/Enums/ | grep -i household
```

Expected: no output for both commands.
