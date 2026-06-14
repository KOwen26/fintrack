# Foundation — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete backend for Providers, Households, Accounts, Categories, and theme preference — every migration, model, policy, service, DTO, controller, route, and seeder the Foundation spec defines.

**Architecture:** Service pattern throughout — controllers are thin dispatchers, all business logic lives in `app/Services/`. Policies enforce access rules. Spatie Data DTOs typed-prop every Inertia response. No DB enums — string columns + PHP-backed enum casts.

**Tech Stack:** PHP 8.4, Laravel 13, Spatie Laravel Data, Pest 4, Inertia v3

---

## File Map

```
database/migrations/
  *_create_providers_table.php
  *_create_households_table.php
  *_create_household_members_table.php
  *_create_household_invitations_table.php
  *_create_accounts_table.php
  *_create_categories_table.php
  *_add_theme_preference_to_users_table.php

app/Enums/
  ProviderType.php
  ProviderStatus.php
  HouseholdMemberRole.php
  AccountType.php
  AccountAccessType.php

app/Models/
  Provider.php           Household.php
  HouseholdMember.php    HouseholdInvitation.php
  Account.php            Category.php
  User.php               (modify: add theme_preference, relationships)

database/factories/
  ProviderFactory.php    HouseholdFactory.php
  HouseholdMemberFactory.php  HouseholdInvitationFactory.php
  AccountFactory.php     CategoryFactory.php

app/Policies/
  AccountPolicy.php      CategoryPolicy.php      HouseholdPolicy.php

app/Http/Requests/
  StoreAccountRequest.php    UpdateAccountRequest.php
  StoreCategoryRequest.php   UpdateCategoryRequest.php
  StoreHouseholdRequest.php  InviteHouseholdMemberRequest.php
  UpdateUserThemeRequest.php

app/Services/
  HouseholdService.php   AccountService.php
  CategoryService.php    UserThemeService.php

app/Data/
  ProviderData.php       AccountData.php
  CategoryData.php       HouseholdData.php
  HouseholdMemberData.php  UserThemeData.php

app/Http/Controllers/
  AccountsController.php
  CategoriesController.php
  HouseholdsController.php
  HouseholdInvitationsController.php
  UserThemeController.php

app/Http/Middleware/HandleInertiaRequests.php   (modify: share theme_preference)
routes/web.php                                  (modify: add foundation routes)

database/seeders/
  ProviderSeeder.php     CategorySeeder.php
  DatabaseSeeder.php     (modify: register seeders)

tests/Feature/
  AccountTest.php        CategoryTest.php
  HouseholdTest.php      HouseholdInvitationTest.php
  UserThemeTest.php
```

---

## Task 1: Migrations

- [ ] **Generate all seven migration files**

```bash
php artisan make:migration create_providers_table --no-interaction
php artisan make:migration create_households_table --no-interaction
php artisan make:migration create_household_members_table --no-interaction
php artisan make:migration create_household_invitations_table --no-interaction
php artisan make:migration create_accounts_table --no-interaction
php artisan make:migration create_categories_table --no-interaction
php artisan make:migration add_theme_preference_to_users_table --no-interaction
```

- [ ] **Fill in `create_providers_table`**

Add the imports at the top of the migration class:

```php
use App\Enums\ProviderStatus;
```

```php
public function up(): void
{
    Schema::create('providers', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('logo_url')->nullable();
        $table->string('type');
        $table->string('status')->default(ProviderStatus::Active->value);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('providers');
}
```

- [ ] **Fill in `create_households_table`**

```php
public function up(): void
{
    Schema::create('households', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->foreignId('created_by')->constrained('users');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('households');
}
```

- [ ] **Fill in `create_household_members_table`**

```php
public function up(): void
{
    Schema::create('household_members', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('household_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('role'); // PHP enum: HouseholdMemberRole
        $table->timestamp('joined_at')->nullable();
        $table->timestamp('created_at')->nullable();

        $table->unique(['household_id', 'user_id']);
        $table->index('user_id');
    });
}

public function down(): void
{
    Schema::dropIfExists('household_members');
}
```

- [ ] **Fill in `create_household_invitations_table`**

```php
public function up(): void
{
    Schema::create('household_invitations', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('household_id')->constrained()->cascadeOnDelete();
        $table->foreignId('invited_by')->constrained('users');
        $table->string('email');
        $table->string('token')->unique();
        $table->timestamp('accepted_at')->nullable();
        $table->timestamp('expires_at');
        $table->timestamp('created_at')->nullable();

        $table->index(['email', 'household_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('household_invitations');
}
```

- [ ] **Fill in `create_accounts_table`**

```php
public function up(): void
{
    Schema::create('accounts', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('household_id')->constrained()->cascadeOnDelete();
        $table->foreignId('owner_id')->constrained('users');
        $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
        $table->string('name');
        $table->string('type');        // PHP enum: AccountType
        $table->string('access_type'); // PHP enum: AccountAccessType
        $table->decimal('initial_balance', 15, 2)->default(0);
        $table->decimal('credit_card_limit', 15, 2)->nullable();
        $table->char('currency', 3)->default('IDR');
        $table->timestamp('archived_at')->nullable();
        $table->softDeletes();
        $table->timestamps();

        $table->index('household_id');
        $table->index('owner_id');
        $table->index('archived_at');
        $table->index('deleted_at');
    });
}

public function down(): void
{
    Schema::dropIfExists('accounts');
}
```

- [ ] **Fill in `create_categories_table`**

```php
public function up(): void
{
    Schema::create('categories', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
        $table->string('name');
        $table->string('icon')->default('ph:tag');
        $table->string('color')->default('#6366f1');
        $table->boolean('is_fixed_cost')->default(false);
        $table->softDeletes();
        $table->timestamps();

        $table->index('user_id');
        $table->index('parent_id');
        $table->index('deleted_at');
    });
}

public function down(): void
{
    Schema::dropIfExists('categories');
}
```

- [ ] **Fill in `add_theme_preference_to_users_table`**

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table): void {
        $table->string('theme_preference')->nullable()->after('email');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table): void {
        $table->dropColumn('theme_preference');
    });
}
```

- [ ] **Run migrations**

```bash
php artisan migrate --no-interaction
```

Expected: 7 new migrations applied with no errors.

---

## Task 2: PHP Enums

- [ ] **Create `app/Enums/ProviderType.php`**

```php
<?php

namespace App\Enums;

enum ProviderType: string
{
    case Bank = 'bank';
    case DigitalBank = 'digital_bank';
    case EWallet = 'e_wallet';
    case CreditLoan = 'credit_loan';
    case Investment = 'investment';
}
```

- [ ] **Create `app/Enums/ProviderStatus.php`**

```php
<?php

namespace App\Enums;

enum ProviderStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
```

- [ ] **Create `app/Enums/HouseholdMemberRole.php`**

```php
<?php

namespace App\Enums;

enum HouseholdMemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
```

- [ ] **Create `app/Enums/AccountType.php`**

```php
<?php

namespace App\Enums;

enum AccountType: string
{
    case DebitAccount = 'debit_account';
    case CreditCard = 'credit_card';
    case CashWallet = 'cash_wallet';
    case EWallet = 'e_wallet';
    case Investment = 'investment';
}
```

- [ ] **Create `app/Enums/AccountAccessType.php`**

```php
<?php

namespace App\Enums;

enum AccountAccessType: string
{
    case Personal = 'personal';
    case Joint = 'joint';
}
```

---

## Task 3: Models + Factories

- [ ] **Create `app/Models/Provider.php`**

```bash
php artisan make:model Provider -f --no-interaction
```

```php
<?php

namespace App\Models;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    protected $casts = [
        'type' => ProviderType::class,
        'status' => ProviderStatus::class,
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
```

- [ ] **Fill `database/factories/ProviderFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'logo_url' => null,
            'type' => fake()->randomElement(ProviderType::cases())->value,
            'status' => ProviderStatus::Active->value,
        ];
    }
}
```

- [ ] **Create `app/Models/Household.php`**

```bash
php artisan make:model Household -f --no-interaction
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
```

- [ ] **Fill `database/factories/HouseholdFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->lastName() . ' Household',
            'created_by' => User::factory(),
        ];
    }
}
```

- [ ] **Create `app/Models/HouseholdMember.php`**

```bash
php artisan make:model HouseholdMember -f --no-interaction
```

```php
<?php

namespace App\Models;

use App\Enums\HouseholdMemberRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMember extends Model
{
    public $timestamps = false;

    protected $casts = [
        'role' => HouseholdMemberRole::class,
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Fill `database/factories/HouseholdMemberFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Enums\HouseholdMemberRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HouseholdMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'user_id' => User::factory(),
            'role' => HouseholdMemberRole::Member->value,
            'joined_at' => now(),
            'created_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => HouseholdMemberRole::Owner->value]);
    }

    public function pending(): static
    {
        return $this->state(['joined_at' => null]);
    }
}
```

- [ ] **Create `app/Models/HouseholdInvitation.php`**

```bash
php artisan make:model HouseholdInvitation -f --no-interaction
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdInvitation extends Model
{
    public $timestamps = false;

    protected $casts = [
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
```

- [ ] **Fill `database/factories/HouseholdInvitationFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HouseholdInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->safeEmail(),
            'token' => Str::random(64),
            'accepted_at' => null,
            'expires_at' => now()->addHours(48),
            'created_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }

    public function accepted(): static
    {
        return $this->state(['accepted_at' => now()->subMinutes(10)]);
    }
}
```

- [ ] **Create `app/Models/Account.php`**

```bash
php artisan make:model Account -f --no-interaction
```

```php
<?php

namespace App\Models;

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $casts = [
        'type' => AccountType::class,
        'access_type' => AccountAccessType::class,
        'initial_balance' => 'decimal:2',
        'credit_card_limit' => 'decimal:2',
        'archived_at' => 'datetime',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $householdIds = HouseholdMember::query()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->pluck('household_id');

        return $query->where(function (Builder $q) use ($user, $householdIds): void {
            $q->where('owner_id', $user->id)
              ->orWhere(function (Builder $q) use ($householdIds): void {
                  $q->where('access_type', AccountAccessType::Joint->value)
                    ->whereIn('household_id', $householdIds);
              });
        });
    }
}
```

- [ ] **Fill `database/factories/AccountFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'owner_id' => User::factory(),
            'provider_id' => null,
            'name' => fake()->words(2, true),
            'type' => AccountType::DebitAccount->value,
            'access_type' => AccountAccessType::Personal->value,
            'initial_balance' => 0,
            'credit_card_limit' => null,
            'currency' => 'IDR',
            'archived_at' => null,
        ];
    }

    public function joint(): static
    {
        return $this->state(['access_type' => AccountAccessType::Joint->value]);
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

- [ ] **Create `app/Models/Category.php`**

```bash
php artisan make:model Category -f --no-interaction
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
```

- [ ] **Fill `database/factories/CategoryFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'name' => fake()->word(),
            'icon' => 'ph:tag',
            'color' => fake()->hexColor(),
            'is_fixed_cost' => false,
        ];
    }

    public function child(int $parentId): static
    {
        return $this->state(['parent_id' => $parentId]);
    }

    public function fixed(): static
    {
        return $this->state(['is_fixed_cost' => true]);
    }
}
```

- [ ] **Modify `app/Models/User.php` — add `theme_preference`, `householdMemberships()`, and `SoftDeletes`**

Add to the existing `User` model:

```php
// Add to imports
use App\Enums\HouseholdMemberRole; // not needed here but useful
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// Add trait after HasApiTokens, HasFactory, Notifiable:
use SoftDeletes;

// Add to $fillable array:
'theme_preference',

// Add relationships:
public function householdMemberships(): HasMany
{
    return $this->hasMany(HouseholdMember::class);
}

public function categories(): HasMany
{
    return $this->hasMany(Category::class);
}
```

---

## Task 4: Policies

- [ ] **Create `app/Policies/AccountPolicy.php`**

```bash
php artisan make:policy AccountPolicy --model=Account --no-interaction
```

```php
<?php

namespace App\Policies;

use App\Enums\AccountAccessType;
use App\Models\Account;
use App\Models\HouseholdMember;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Account $account): bool
    {
        return $this->canAccess($user, $account);
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

    private function canAccess(User $user, Account $account): bool
    {
        if ($account->owner_id === $user->id) {
            return true;
        }

        if ($account->access_type !== AccountAccessType::Joint) {
            return false;
        }

        return HouseholdMember::query()
            ->where('household_id', $account->household_id)
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->exists();
    }
}
```

- [ ] **Create `app/Policies/CategoryPolicy.php`**

```bash
php artisan make:policy CategoryPolicy --model=Category --no-interaction
```

```php
<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
    }

    public function delete(User $user, Category $category): bool
    {
        return $category->user_id === $user->id;
    }
}
```

- [ ] **Create `app/Policies/HouseholdPolicy.php`**

```bash
php artisan make:policy HouseholdPolicy --model=Household --no-interaction
```

```php
<?php

namespace App\Policies;

use App\Enums\HouseholdMemberRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;

class HouseholdPolicy
{
    public function view(User $user, Household $household): bool
    {
        return HouseholdMember::query()
            ->where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->exists();
    }

    public function update(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    public function invite(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    public function removeMember(User $user, Household $household): bool
    {
        return $this->isOwner($user, $household);
    }

    private function isOwner(User $user, Household $household): bool
    {
        return HouseholdMember::query()
            ->where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->where('role', HouseholdMemberRole::Owner->value)
            ->exists();
    }
}
```

---

## Task 5: Form Requests

- [ ] **Create `app/Http/Requests/StoreAccountRequest.php`**

```bash
php artisan make:request StoreAccountRequest --no-interaction
```

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
            'household_id' => ['required', 'integer', 'exists:households,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(AccountType::class)],
            'access_type' => ['required', 'string', Rule::enum(AccountAccessType::class)],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'initial_balance' => ['required', 'numeric', 'min:0'],
            'credit_card_limit' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/UpdateAccountRequest.php`**

```bash
php artisan make:request UpdateAccountRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
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
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'credit_card_limit' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/StoreCategoryRequest.php`**

```bash
php artisan make:request StoreCategoryRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_fixed_cost' => ['required', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/UpdateCategoryRequest.php`**

```bash
php artisan make:request UpdateCategoryRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_fixed_cost' => ['required', 'boolean'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/StoreHouseholdRequest.php`**

```bash
php artisan make:request StoreHouseholdRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/InviteHouseholdMemberRequest.php`**

```bash
php artisan make:request InviteHouseholdMemberRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteHouseholdMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/UpdateUserThemeRequest.php`**

```bash
php artisan make:request UpdateUserThemeRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['required', 'string', 'max:50'],
        ];
    }
}
```

---

## Task 6: Services

- [ ] **Create `app/Services/HouseholdService.php`**

```bash
php artisan make:class Services/HouseholdService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Enums\HouseholdMemberRole;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Support\Str;

class HouseholdService
{
    public function create(User $user, string $name): Household
    {
        $household = Household::create([
            'name' => $name,
            'created_by' => $user->id,
        ]);

        $household->members()->create([
            'user_id' => $user->id,
            'role' => HouseholdMemberRole::Owner->value,
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        return $household;
    }

    public function invite(Household $household, string $email, User $invitedBy): HouseholdInvitation
    {
        $household->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        return $household->invitations()->create([
            'invited_by' => $invitedBy->id,
            'email' => $email,
            'token' => Str::random(64),
            'expires_at' => now()->addHours(48),
            'created_at' => now(),
        ]);
    }

    public function acceptInvitation(HouseholdInvitation $invitation, User $user): HouseholdMember
    {
        $member = $invitation->household->members()->create([
            'user_id' => $user->id,
            'role' => HouseholdMemberRole::Member->value,
            'joined_at' => now(),
            'created_at' => now(),
        ]);

        $invitation->update(['accepted_at' => now()]);

        return $member;
    }

    public function removeMember(HouseholdMember $member): void
    {
        $member->delete();
    }
}
```

- [ ] **Create `app/Services/AccountService.php`**

```bash
php artisan make:class Services/AccountService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;

class AccountService
{
    public function create(User $user, array $data): Account
    {
        return Account::create([
            ...$data,
            'owner_id' => $user->id,
        ]);
    }

    public function update(Account $account, array $data): Account
    {
        $account->update($data);

        return $account->fresh();
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

- [ ] **Create `app/Services/CategoryService.php`**

```bash
php artisan make:class Services/CategoryService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;

class CategoryService
{
    public function create(User $user, array $data): Category
    {
        return Category::create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh();
    }

    public function softDelete(Category $category): void
    {
        $category->delete();
    }
}
```

- [ ] **Create `app/Services/UserThemeService.php`**

```bash
php artisan make:class Services/UserThemeService --no-interaction
```

```php
<?php

namespace App\Services;

use App\Models\User;

class UserThemeService
{
    public function update(User $user, string $theme): void
    {
        $user->update(['theme_preference' => $theme]);
    }
}
```

---

## Task 7: Data Objects (Spatie Laravel Data)

DTOs are only created for **complex or combined shapes**. Simple model data (accounts, categories, providers, theme preference) is passed directly to `Inertia::render()` — Wayfinder's model generation handles the TypeScript types.

Foundation requires **two DTOs** because `HouseholdData` combines data across three tables (`households`, `household_members`, `users`) and cannot be expressed as a single model instance.

- [ ] **Create `app/Data/HouseholdMemberData.php`**

```php
<?php

namespace App\Data;

use App\Enums\HouseholdMemberRole;
use Spatie\LaravelData\Data;

class HouseholdMemberData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $user_id,
        public readonly string $name,
        public readonly HouseholdMemberRole $role,
        public readonly ?string $joined_at,
    ) {}
}
```

- [ ] **Create `app/Data/HouseholdData.php`**

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class HouseholdData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        /** @var HouseholdMemberData[] */
        public readonly array $members,
    ) {}
}
```

- [ ] **Generate TypeScript types**

```bash
composer generate:ts
```

Expected: `resources/js/wayfinder/` updated. Only `HouseholdData` and `HouseholdMemberData` are new DTO types — all other model types come from Wayfinder's model generation (`App.Models.*`).

---

## Task 8: Controllers

- [ ] **Create `app/Http/Controllers/AccountsController.php`**

```bash
php artisan make:controller AccountsController --no-interaction
```

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
    public function __construct(private readonly AccountService $accountService) {}

    public function index(Request $request): Response
    {
        $accounts = Account::query()
            ->visibleTo($request->user())
            ->whereNull('archived_at')
            ->with('provider')
            ->get();

        return Inertia::render('accounts/index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('accounts/create', [
            'providers' => Provider::where('status', 'active')->orderBy('name')->get(),
            'household_id' => $request->user()
                ->householdMemberships()
                ->whereNotNull('joined_at')
                ->value('household_id'),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $this->accountService->create($request->user(), $request->validated());

        return to_route('accounts.show', $account)->with('message', 'Account created.');
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
            'account'   => $account->load('provider'),
            'providers' => Provider::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);
        $this->accountService->update($account, $request->validated());

        return to_route('accounts.show', $account)->with('message', 'Account updated.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);
        $this->accountService->softDelete($account);

        return to_route('accounts.index')->with('message', 'Account deleted.');
    }

    public function archive(Account $account): RedirectResponse
    {
        $this->authorize('archive', $account);
        $this->accountService->archive($account);

        return to_route('accounts.index')->with('message', 'Account archived.');
    }

    public function restore(Account $account): RedirectResponse
    {
        $this->authorize('archive', $account);
        $this->accountService->restore($account);

        return to_route('accounts.show', $account)->with('message', 'Account restored.');
    }
}
```

- [ ] **Create `app/Http/Controllers/CategoriesController.php`**

```bash
php artisan make:controller CategoriesController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoriesController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        return Inertia::render('categories/index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->user(), $request->validated());

        return back()->with('message', 'Category created.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $this->categoryService->update($category, $request->validated());

        return back()->with('message', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        $this->categoryService->softDelete($category);

        return back()->with('message', 'Category deleted.');
    }
}
```

- [ ] **Create `app/Http/Controllers/HouseholdsController.php`**

```bash
php artisan make:controller HouseholdsController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Data\HouseholdData;
use App\Data\HouseholdMemberData;
use App\Http\Requests\InviteHouseholdMemberRequest;
use App\Http\Requests\StoreHouseholdRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdsController extends Controller
{
    public function __construct(private readonly HouseholdService $householdService) {}

    public function show(Request $request): Response
    {
        $membership = $request->user()
            ->householdMemberships()
            ->whereNotNull('joined_at')
            ->with('household.members.user')
            ->first();

        $household = $membership?->household;

        return Inertia::render('household/settings', [
            'household' => $household ? HouseholdData::from([
                'id' => $household->id,
                'name' => $household->name,
                'members' => $household->members->map(fn (HouseholdMember $m) => new HouseholdMemberData(
                    id: $m->id,
                    user_id: $m->user_id,
                    name: $m->user->name,
                    role: $m->role,
                    joined_at: $m->joined_at?->toISOString(),
                ))->toArray(),
            ]) : null,
        ]);
    }

    public function store(StoreHouseholdRequest $request): RedirectResponse
    {
        $this->householdService->create($request->user(), $request->validated()['name']);

        return to_route('household.settings')->with('message', 'Household created.');
    }

    public function invite(InviteHouseholdMemberRequest $request): RedirectResponse
    {
        $membership = $request->user()
            ->householdMemberships()
            ->whereNotNull('joined_at')
            ->first();

        abort_unless($membership !== null, 403);

        $household = $membership->household;
        $this->authorize('invite', $household);
        $this->householdService->invite($household, $request->validated()['email'], $request->user());

        return back()->with('message', 'Invitation sent.');
    }

    public function removeMember(Request $request, HouseholdMember $member): RedirectResponse
    {
        $this->authorize('removeMember', $member->household);
        $this->householdService->removeMember($member);

        return back()->with('message', 'Member removed.');
    }
}
```

- [ ] **Create `app/Http/Controllers/HouseholdInvitationsController.php`**

```bash
php artisan make:controller HouseholdInvitationsController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Models\HouseholdInvitation;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdInvitationsController extends Controller
{
    public function __construct(private readonly HouseholdService $householdService) {}

    public function show(string $token): Response|RedirectResponse
    {
        $invitation = HouseholdInvitation::where('token', $token)->firstOrFail();

        if (! $invitation->isPending()) {
            return to_route('household.settings')->with('message', 'Invitation is no longer valid.');
        }

        return Inertia::render('household/invitation', [
            'invitation' => [
                'token' => $token,
                'household_name' => $invitation->household->name,
                'invited_by' => $invitation->inviter->name,
                'expires_at' => $invitation->expires_at->toISOString(),
            ],
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = HouseholdInvitation::where('token', $token)->firstOrFail();

        abort_unless($invitation->isPending(), 422, 'Invitation is no longer valid.');

        $this->householdService->acceptInvitation($invitation, $request->user());

        return to_route('household.settings')->with('message', 'You have joined the household.');
    }

    public function decline(string $token): RedirectResponse
    {
        HouseholdInvitation::where('token', $token)->firstOrFail()->update(['accepted_at' => null]);

        return to_route('dashboard')->with('message', 'Invitation declined.');
    }
}
```

- [ ] **Create `app/Http/Controllers/UserThemeController.php`**

```bash
php artisan make:controller UserThemeController --no-interaction
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserThemeRequest;
use App\Services\UserThemeService;
use Illuminate\Http\RedirectResponse;

class UserThemeController extends Controller
{
    public function __construct(private readonly UserThemeService $userThemeService) {}

    public function update(UpdateUserThemeRequest $request): RedirectResponse
    {
        $this->userThemeService->update($request->user(), $request->validated()['theme']);

        return back();
    }
}
```

---

## Task 9: Routes & Middleware

- [ ] **Update `routes/web.php` — add Foundation routes inside auth middleware group**

```php
<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\HouseholdInvitationsController;
use App\Http\Controllers\HouseholdsController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\UserThemeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [LandingController::class, 'index'])->name('home');

require __DIR__ . '/auth.php';

Route::middleware(['auth', 'verified:auth.verification.notice'])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard/dashboard'))->name('dashboard');

    // Accounts
    Route::get('accounts', [AccountsController::class, 'index'])->name('accounts.index');
    Route::get('accounts/create', [AccountsController::class, 'create'])->name('accounts.create');
    Route::post('accounts', [AccountsController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{account}', [AccountsController::class, 'show'])->name('accounts.show');
    Route::get('accounts/{account}/edit', [AccountsController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{account}', [AccountsController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{account}', [AccountsController::class, 'destroy'])->name('accounts.destroy');
    Route::post('accounts/{account}/archive', [AccountsController::class, 'archive'])->name('accounts.archive');
    Route::post('accounts/{account}/restore', [AccountsController::class, 'restore'])->name('accounts.restore');

    // Categories
    Route::get('categories', [CategoriesController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoriesController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');

    // Household
    Route::get('household/settings', [HouseholdsController::class, 'show'])->name('household.settings');
    Route::post('household', [HouseholdsController::class, 'store'])->name('household.store');
    Route::post('household/invite', [HouseholdsController::class, 'invite'])->name('household.invite');
    Route::delete('household/members/{member}', [HouseholdsController::class, 'removeMember'])->name('household.members.destroy');

    // Household invitations (accessible without full auth check since invitee may not have account yet)
    Route::get('household/invitations/{token}', [HouseholdInvitationsController::class, 'show'])->name('household.invitations.show');
    Route::post('household/invitations/{token}/accept', [HouseholdInvitationsController::class, 'accept'])->name('household.invitations.accept');
    Route::post('household/invitations/{token}/decline', [HouseholdInvitationsController::class, 'decline'])->name('household.invitations.decline');

    // Theme
    Route::put('settings/theme', [UserThemeController::class, 'update'])->name('settings.theme.update');

    require __DIR__ . '/settings.php';
});

require __DIR__ . '/dev.php';
```

- [ ] **Update `app/Http/Middleware/HandleInertiaRequests.php` — share `theme_preference`**

Change the `auth.user` share from:
```php
'auth' => [
    'user' => fn () => $request->user()
        ? $request->user()->only('id', 'name', 'email')
        : null,
```

To:
```php
'auth' => [
    'user' => fn () => $request->user()
        ? $request->user()->only('id', 'name', 'email', 'theme_preference')
        : null,
```

- [ ] **Regenerate Wayfinder typed route functions**

```bash
php artisan wayfinder:generate --no-interaction
```

Expected: `resources/js/actions/` directory populated with typed controller functions.

---

## Task 10: Seeders

- [ ] **Create `database/seeders/ProviderSeeder.php`**

```bash
php artisan make:seeder ProviderSeeder --no-interaction
```

```php
<?php

namespace Database\Seeders;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['name' => 'BCA', 'slug' => 'bca', 'type' => ProviderType::Bank->value],
            ['name' => 'Mandiri', 'slug' => 'mandiri', 'type' => ProviderType::Bank->value],
            ['name' => 'BNI', 'slug' => 'bni', 'type' => ProviderType::Bank->value],
            ['name' => 'BRI', 'slug' => 'bri', 'type' => ProviderType::Bank->value],
            ['name' => 'CIMB Niaga', 'slug' => 'cimb-niaga', 'type' => ProviderType::Bank->value],
            ['name' => 'Jenius', 'slug' => 'jenius', 'type' => ProviderType::Bank->value],
            ['name' => 'GoPay', 'slug' => 'gopay', 'type' => ProviderType::EWallet->value],
            ['name' => 'OVO', 'slug' => 'ovo', 'type' => ProviderType::EWallet->value],
            ['name' => 'Dana', 'slug' => 'dana', 'type' => ProviderType::EWallet->value],
            ['name' => 'ShopeePay', 'slug' => 'shopeepay', 'type' => ProviderType::EWallet->value],
            ['name' => 'LinkAja', 'slug' => 'linkaja', 'type' => ProviderType::EWallet->value],
        ];

        foreach ($providers as $provider) {
            DB::table('providers')->updateOrInsert(
                ['slug' => $provider['slug']],
                [...$provider, 'status' => ProviderStatus::Active->value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
```

- [ ] **Create `database/seeders/CategorySeeder.php`**

```bash
php artisan make:seeder CategorySeeder --no-interaction
```

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** @var array<array{name: string, icon: string, color: string, fixed: bool, children: array<array{name: string, icon: string, color: string, fixed: bool}>}> */
    private array $structure = [
        [
            'name' => 'Income', 'icon' => 'ph:arrow-circle-down-bold', 'color' => '#22c55e', 'fixed' => false,
            'children' => [
                ['name' => 'Salary', 'icon' => 'ph:briefcase-bold', 'color' => '#22c55e', 'fixed' => true],
                ['name' => 'Freelance', 'icon' => 'ph:laptop-bold', 'color' => '#22c55e', 'fixed' => false],
                ['name' => 'Business Revenue', 'icon' => 'ph:storefront-bold', 'color' => '#22c55e', 'fixed' => false],
                ['name' => 'Investment Returns', 'icon' => 'ph:trend-up-bold', 'color' => '#22c55e', 'fixed' => false],
                ['name' => 'Other Income', 'icon' => 'ph:plus-circle-bold', 'color' => '#22c55e', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Food & Drinks', 'icon' => 'ph:fork-knife-bold', 'color' => '#f97316', 'fixed' => false,
            'children' => [
                ['name' => 'Groceries', 'icon' => 'ph:shopping-cart-bold', 'color' => '#f97316', 'fixed' => false],
                ['name' => 'Dining Out', 'icon' => 'ph:hamburger-bold', 'color' => '#f97316', 'fixed' => false],
                ['name' => 'Coffee & Snacks', 'icon' => 'ph:coffee-bold', 'color' => '#f97316', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Transport', 'icon' => 'ph:car-bold', 'color' => '#3b82f6', 'fixed' => false,
            'children' => [
                ['name' => 'Fuel', 'icon' => 'ph:gas-pump-bold', 'color' => '#3b82f6', 'fixed' => true],
                ['name' => 'Ride-hailing', 'icon' => 'ph:motorcycle-bold', 'color' => '#3b82f6', 'fixed' => false],
                ['name' => 'Parking', 'icon' => 'ph:parking-sign-bold', 'color' => '#3b82f6', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Utilities', 'icon' => 'ph:lightning-bold', 'color' => '#eab308', 'fixed' => true,
            'children' => [
                ['name' => 'Electricity', 'icon' => 'ph:lightning-bold', 'color' => '#eab308', 'fixed' => true],
                ['name' => 'Internet', 'icon' => 'ph:wifi-bold', 'color' => '#eab308', 'fixed' => true],
                ['name' => 'Water', 'icon' => 'ph:drop-bold', 'color' => '#eab308', 'fixed' => true],
                ['name' => 'Phone', 'icon' => 'ph:phone-bold', 'color' => '#eab308', 'fixed' => true],
            ],
        ],
        [
            'name' => 'Housing', 'icon' => 'ph:house-bold', 'color' => '#8b5cf6', 'fixed' => true,
            'children' => [
                ['name' => 'Rent', 'icon' => 'ph:key-bold', 'color' => '#8b5cf6', 'fixed' => true],
                ['name' => 'Home Maintenance', 'icon' => 'ph:wrench-bold', 'color' => '#8b5cf6', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Health', 'icon' => 'ph:heart-beat-bold', 'color' => '#ef4444', 'fixed' => false,
            'children' => [
                ['name' => 'Doctor', 'icon' => 'ph:stethoscope-bold', 'color' => '#ef4444', 'fixed' => false],
                ['name' => 'Medicine', 'icon' => 'ph:pill-bold', 'color' => '#ef4444', 'fixed' => false],
                ['name' => 'Gym', 'icon' => 'ph:barbell-bold', 'color' => '#ef4444', 'fixed' => true],
            ],
        ],
        [
            'name' => 'Entertainment', 'icon' => 'ph:game-controller-bold', 'color' => '#ec4899', 'fixed' => false,
            'children' => [
                ['name' => 'Streaming', 'icon' => 'ph:television-bold', 'color' => '#ec4899', 'fixed' => true],
                ['name' => 'Games', 'icon' => 'ph:game-controller-bold', 'color' => '#ec4899', 'fixed' => false],
                ['name' => 'Hobbies', 'icon' => 'ph:paint-brush-bold', 'color' => '#ec4899', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Shopping', 'icon' => 'ph:bag-bold', 'color' => '#14b8a6', 'fixed' => false,
            'children' => [
                ['name' => 'Clothing', 'icon' => 'ph:t-shirt-bold', 'color' => '#14b8a6', 'fixed' => false],
                ['name' => 'Electronics', 'icon' => 'ph:device-mobile-bold', 'color' => '#14b8a6', 'fixed' => false],
                ['name' => 'Household Items', 'icon' => 'ph:lamp-bold', 'color' => '#14b8a6', 'fixed' => false],
            ],
        ],
        [
            'name' => 'Education', 'icon' => 'ph:graduation-cap-bold', 'color' => '#6366f1', 'fixed' => false,
            'children' => [
                ['name' => 'Courses', 'icon' => 'ph:book-open-bold', 'color' => '#6366f1', 'fixed' => false],
                ['name' => 'Books', 'icon' => 'ph:books-bold', 'color' => '#6366f1', 'fixed' => false],
                ['name' => 'School Fees', 'icon' => 'ph:student-bold', 'color' => '#6366f1', 'fixed' => true],
            ],
        ],
        [
            'name' => 'Other Expense', 'icon' => 'ph:dots-three-circle-bold', 'color' => '#6b7280', 'fixed' => false,
            'children' => [],
        ],
    ];

    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $this->seedForUser($user);
    }

    public function seedForUser(User $user): void
    {
        foreach ($this->structure as $group) {
            $parent = Category::create([
                'user_id' => $user->id,
                'parent_id' => null,
                'name' => $group['name'],
                'icon' => $group['icon'],
                'color' => $group['color'],
                'is_fixed_cost' => $group['fixed'],
            ]);

            foreach ($group['children'] as $child) {
                Category::create([
                    'user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'name' => $child['name'],
                    'icon' => $child['icon'],
                    'color' => $child['color'],
                    'is_fixed_cost' => $child['fixed'],
                ]);
            }
        }
    }
}
```

- [ ] **Update `database/seeders/DatabaseSeeder.php` — register new seeders**

```php
public function run(): void
{
    $this->call([
        ProviderSeeder::class,
        // CategorySeeder runs after a user is created:
        // CategorySeeder::class,
    ]);
}
```

- [ ] **Run seeders**

```bash
php artisan db:seed --class=ProviderSeeder --no-interaction
```

Expected: providers table populated with 11 rows.

---

## Task 11: Feature Tests

- [ ] **Create `tests/Feature/AccountTest.php`**

```bash
php artisan make:test AccountTest --pest --no-interaction
```

```php
<?php

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createUserWithHousehold(): array
{
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create([
        'household_id' => $household->id,
        'user_id' => $user->id,
    ]);

    return [$user, $household];
}

it('lists only visible accounts for the authenticated user', function (): void {
    [$user, $household] = createUserWithHousehold();
    $personal = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $household->id]);
    $other = Account::factory()->create(); // different user

    $this->actingAs($user)->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('accounts/index')
            ->has('accounts', 1)
            ->where('accounts.0.id', $personal->id)
        );
});

it('stores a new personal account', function (): void {
    [$user, $household] = createUserWithHousehold();

    $this->actingAs($user)->post(route('accounts.store'), [
        'household_id' => $household->id,
        'name' => 'BCA Savings',
        'type' => AccountType::DebitAccount->value,
        'access_type' => AccountAccessType::Personal->value,
        'initial_balance' => 1_000_000,
        'currency' => 'IDR',
        'provider_id' => null,
        'credit_card_limit' => null,
    ])->assertRedirect(route('accounts.show', Account::latest()->first()));

    expect(Account::where('name', 'BCA Savings')->exists())->toBeTrue();
});

it('prevents viewing another user personal account', function (): void {
    [$user] = createUserWithHousehold();
    $otherAccount = Account::factory()->create(['access_type' => AccountAccessType::Personal->value]);

    $this->actingAs($user)->get(route('accounts.show', $otherAccount))
        ->assertForbidden();
});

it('allows household member to view joint account', function (): void {
    [$owner, $household] = createUserWithHousehold();
    $member = User::factory()->create();
    HouseholdMember::factory()->create([
        'household_id' => $household->id,
        'user_id' => $member->id,
    ]);
    $joint = Account::factory()->joint()->create([
        'owner_id' => $owner->id,
        'household_id' => $household->id,
    ]);

    $this->actingAs($member)->get(route('accounts.show', $joint))
        ->assertOk();
});

it('archives an account', function (): void {
    [$user, $household] = createUserWithHousehold();
    $account = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $household->id]);

    $this->actingAs($user)->post(route('accounts.archive', $account))
        ->assertRedirect(route('accounts.index'));

    expect($account->fresh()->archived_at)->not->toBeNull();
});

it('soft-deletes an account', function (): void {
    [$user, $household] = createUserWithHousehold();
    $account = Account::factory()->create(['owner_id' => $user->id, 'household_id' => $household->id]);

    $this->actingAs($user)->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'));

    expect($account->fresh())->toBeNull();
    expect(Account::withTrashed()->find($account->id))->not->toBeNull();
});
```

- [ ] **Create `tests/Feature/CategoryTest.php`**

```bash
php artisan make:test CategoryTest --pest --no-interaction
```

```php
<?php

use App\Models\Category;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('lists categories for authenticated user with children', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->create(['user_id' => $user->id]);
    $child = Category::factory()->child($parent->id)->create(['user_id' => $user->id]);
    Category::factory()->create(); // another user

    $this->actingAs($user)->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('categories/index')
            ->has('categories', 1)
            ->has('categories.0.children', 1)
        );
});

it('stores a top-level category', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Groceries',
        'icon' => 'ph:shopping-cart-bold',
        'color' => '#f97316',
        'is_fixed_cost' => false,
        'parent_id' => null,
    ])->assertRedirect();

    expect(Category::where('name', 'Groceries')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('prevents deleting another user category', function (): void {
    $user = User::factory()->create();
    $otherCategory = Category::factory()->create();

    $this->actingAs($user)->delete(route('categories.destroy', $otherCategory))
        ->assertForbidden();
});

it('soft-deletes a category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->delete(route('categories.destroy', $category))
        ->assertRedirect();

    expect($category->fresh())->toBeNull();
    expect(Category::withTrashed()->find($category->id))->not->toBeNull();
});
```

- [ ] **Create `tests/Feature/HouseholdTest.php`**

```bash
php artisan make:test HouseholdTest --pest --no-interaction
```

```php
<?php

use App\Enums\HouseholdMemberRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a household and adds creator as owner', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('household.store'), ['name' => 'My Family'])
        ->assertRedirect(route('household.settings'));

    $household = Household::where('name', 'My Family')->first();
    expect($household)->not->toBeNull();

    $member = HouseholdMember::where('household_id', $household->id)->where('user_id', $user->id)->first();
    expect($member->role)->toBe(HouseholdMemberRole::Owner);
    expect($member->joined_at)->not->toBeNull();
});

it('shows household settings page with members', function (): void {
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('household.settings'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('household/settings')
            ->has('household')
            ->where('household.name', $household->name)
        );
});

it('owner can send invitation', function (): void {
    $user = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $user->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $user->id]);

    $this->actingAs($user)->post(route('household.invite'), ['email' => 'partner@example.com'])
        ->assertRedirect();

    expect($household->invitations()->where('email', 'partner@example.com')->exists())->toBeTrue();
});

it('member cannot remove other members', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $owner->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $owner->id]);
    $memberRecord = HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $member->id]);

    $this->actingAs($member)->delete(route('household.members.destroy', $memberRecord))
        ->assertForbidden();
});
```

- [ ] **Create `tests/Feature/HouseholdInvitationTest.php`**

```bash
php artisan make:test HouseholdInvitationTest --pest --no-interaction
```

```php
<?php

use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('shows a valid invitation page', function (): void {
    $owner = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $owner->id]);
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $household->id,
        'invited_by' => $owner->id,
        'email' => 'partner@example.com',
    ]);

    $invitee = User::factory()->create(['email' => 'partner@example.com']);

    $this->actingAs($invitee)->get(route('household.invitations.show', $invitation->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('household/invitation'));
});

it('accepts an invitation and adds member to household', function (): void {
    $owner = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $owner->id]);
    HouseholdMember::factory()->owner()->create(['household_id' => $household->id, 'user_id' => $owner->id]);
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $household->id,
        'invited_by' => $owner->id,
    ]);

    $invitee = User::factory()->create();

    $this->actingAs($invitee)->post(route('household.invitations.accept', $invitation->token))
        ->assertRedirect(route('household.settings'));

    expect(HouseholdMember::where('user_id', $invitee->id)->where('household_id', $household->id)->exists())->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('rejects acceptance of an expired invitation', function (): void {
    $owner = User::factory()->create();
    $household = Household::factory()->create(['created_by' => $owner->id]);
    $invitation = HouseholdInvitation::factory()->expired()->create([
        'household_id' => $household->id,
        'invited_by' => $owner->id,
    ]);
    $invitee = User::factory()->create();

    $this->actingAs($invitee)->post(route('household.invitations.accept', $invitation->token))
        ->assertStatus(422);
});
```

- [ ] **Create `tests/Feature/UserThemeTest.php`**

```bash
php artisan make:test UserThemeTest --pest --no-interaction
```

```php
<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('updates the user theme preference', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.theme.update'), ['theme' => 'dark'])
        ->assertRedirect();

    expect($user->fresh()->theme_preference)->toBe('dark');
});

it('clears theme preference when set to null equivalent', function (): void {
    $user = User::factory()->create(['theme_preference' => 'dark']);

    $this->actingAs($user)->put(route('settings.theme.update'), ['theme' => 'light'])
        ->assertRedirect();

    expect($user->fresh()->theme_preference)->toBe('light');
});

it('rejects invalid theme values that exceed max length', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('settings.theme.update'), ['theme' => str_repeat('a', 51)])
        ->assertSessionHasErrors('theme');
});
```

- [ ] **Run all feature tests**

```bash
php artisan test --compact --filter="AccountTest|CategoryTest|HouseholdTest|HouseholdInvitationTest|UserThemeTest"
```

Expected: All tests pass. Fix any failures before proceeding.

---

## Task 12: PHP Formatting

- [ ] **Run Pint formatter on all modified PHP files**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: Files reformatted to project style with no errors.

---

## Task 13: Commit

- [ ] **Stage all new and modified files**

```bash
git add database/migrations/ app/Enums/ app/Models/ database/factories/ app/Policies/ app/Http/Requests/ app/Services/ app/Data/ app/Http/Controllers/ app/Http/Middleware/HandleInertiaRequests.php routes/web.php database/seeders/ tests/Feature/ resources/js/types/generated.d.ts resources/js/actions/
```

- [ ] **Commit**

```bash
git commit -m "$(cat <<'EOF'
feat(foundation): add providers, households, accounts, categories backend

Implements all migrations, models, policies, services, DTOs, controllers,
and feature tests for the Foundation spec. Includes 2-level category seeder
and provider seeder for Indonesian banks and e-wallets.

Co-Authored-By: Claude Sonnet 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```
