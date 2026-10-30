# Transaction Templates — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if this plan runs as a slice. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The backend for user-managed transaction templates: schema, model, service (pruned read projections), owner-only policy + validation, settings CRUD routes, and server-side create-form seeding via `?template=`.

**Architecture:** One new table (`transaction_templates` — single optional default source account, transfer destination + fee, required amount), a static-read / instance-write service, a settings controller rendering 3 Inertia pages (index / show / shared form), and `TransactionFormData::fromTemplate` folding a pruned template into the create payload. Spec: `docs/superpowers/specs/2026-10-27-transaction-templates-design.md`. Companion frontend plan: `2026-10-27-transaction-templates-frontend.md` — it depends on this plan's Wayfinder regen.

**Tech Stack:** Laravel PHP 8.4-style (final readonly classes, enum casts), Pest, Inertia, Wayfinder.

**Workflow Mode:** `direct` — write tests alongside implementation, but never run tests, formatters, or commit. No final verification task.

**Complexity:** Medium

## Global Constraints

- Spec is authoritative: `docs/superpowers/specs/2026-10-27-transaction-templates-design.md`. Naming: model `TransactionTemplate`, table `transaction_templates`, routes `transaction-templates.*`.
- No DTO for templates (raw Eloquent models to Inertia — Wayfinder types them). No events, no caching, no soft deletes on templates.
- No pivot table. `account_id` single nullable FK; `destination_account_id` nullable FK for transfers; `amount` required `decimal(15,2)`.
- Category values are enum-backed (`Category` case values, e.g. `'dining_out'`); `InitialBalance` is excluded everywhere a category is choosable.
- Never hand-edit `resources/js/wayfinder/**`; run `php artisan wayfinder:generate`.
- PHP: explicit return types, curly braces on all control structures, `final` classes, constructor property promotion. Validation arrays match `SaveTransactionRequest` style.
- Tests: Pest, `test('…', function (): void {})` in `tests/Feature/**`, `it('…')` in `tests/Integration/Services/**`; `User::factory()->create()` (verified by default). Write tests, never run them (`direct`).

## File Structure

**Created:** `database/migrations/2026_10_27_000001_create_transaction_templates_table.php`, `app/Models/TransactionTemplate.php`, `database/factories/TransactionTemplateFactory.php`, `app/Policies/TransactionTemplatePolicy.php`, `app/Http/Requests/SaveTransactionTemplateRequest.php`, `app/Services/TransactionTemplateService.php`, `app/Http/Controllers/Settings/TransactionTemplateController.php`.

**Modified:** `routes/settings.php`, `app/Data/Transaction/TransactionFormData.php` (add `fromTemplate`), `app/Http/Controllers/TransactionController.php` (`create` only).

**Tests:** `tests/Integration/Services/TransactionTemplateServiceTest.php`, `tests/Feature/App/Settings/TransactionTemplateTest.php`, `tests/Feature/App/TransactionTemplateSeedingTest.php`.

---
### Task 1: Migration, Model, Factory

**Complexity:** Low

**Files:**
- Create: `database/migrations/2026_10_27_000001_create_transaction_templates_table.php`
- Create: `app/Models/TransactionTemplate.php`
- Create: `database/factories/TransactionTemplateFactory.php`

**Interfaces:**
- Consumes: `App\Enums\TransactionType`, `App\Enums\Category`, `App\Objects\Decoration` (existing).
- Produces: `App\Models\TransactionTemplate` with casts `type: TransactionType`, `category_id: Category`, `amount`/`fee_amount`: `decimal:2`, `decorations`: `Decoration`; relations `user()`, `account()`, `destinationAccount()`; `TransactionTemplateFactory` with `income()` / `transfer()` states and default `type: Expense`, `category_id: DiningOut`, `amount: 25000`. Later tasks rely on all of these names.

No dedicated test — casts and relations are exercised by the service and feature tests in Tasks 2–4 (the project has no standalone model test convention beyond `DecorationsTest`, which this does not extend).

- [x] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('name');
            $table->string('type');                     // TransactionType cast
            $table->string('category_id')->nullable();  // Category cast; null for transfers
            $table->decimal('amount', 15, 2);
            $table->foreignId('destination_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('fee_amount', 15, 2)->nullable();
            $table->string('description')->nullable();
            $table->json('decorations')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_templates');
    }
};
```

- [x] **Step 2: Write the model**

```php
<?php

namespace App\Models;

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Objects\Decoration;
use Database\Factories\TransactionTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransactionTemplate extends Model
{
    /** @use HasFactory<TransactionTemplateFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'category_id' => Category::class,
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'decorations' => Decoration::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Optional default source account — a soft default when applying, never a restriction. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Fixed destination for transfer templates. */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }
}
```

- [x] **Step 3: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\TransactionTemplate;
use App\Models\User;
use Database\Factories\Concerns\HasDecorations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionTemplate>
 */
class TransactionTemplateFactory extends Factory
{
    use HasDecorations;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => null,
            'name' => fake()->words(2, true),
            'type' => TransactionType::Expense,
            'category_id' => Category::DiningOut,
            'amount' => 25_000,
            'destination_account_id' => null,
            'fee_amount' => null,
            'description' => null,
            'decorations' => $this->randomDecorations(),
        ];
    }

    public function income(): static
    {
        return $this->state([
            'type' => TransactionType::Income,
            'category_id' => Category::Salary,
            'amount' => 5_000_000,
        ]);
    }

    public function transfer(): static
    {
        return $this->state([
            'type' => TransactionType::Transfer,
            'category_id' => null,
            'destination_account_id' => Account::factory(),
            'amount' => 1_000_000,
        ]);
    }
}
```

(The factory needs `use App\Models\Account;` added to imports.)

---

### Task 2: TransactionTemplateService

**Complexity:** Medium

**Files:**
- Create: `app/Services/TransactionTemplateService.php`
- Test: `tests/Integration/Services/TransactionTemplateServiceTest.php`

**Interfaces:**
- Consumes: `TransactionTemplate` (Task 1), `AccountService::getAccountIdsByUser(User): Collection` (existing — owned + not archived ids, soft-deleted excluded by global scope).
- Produces (all names used by Tasks 3–4):
  - `public static function getTemplatesByUser(User $user): Collection` — management list, `latest('id')`, eager `account:id,name` + `destinationAccount:id,name`
  - `public static function getTemplateById(User $user, int $templateId): ?TransactionTemplate` — owner-scoped, **pruned** for form seeding
  - `public static function getQuickAccessTemplates(User $user): Collection` — pruned read projections
  - `public function create(User $creator, array $data): TransactionTemplate`
  - `public function update(TransactionTemplate $template, array $data): TransactionTemplate`
  - `public function delete(TransactionTemplate $template): void`

- [x] **Step 1: Write the service tests**

```php
<?php

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\TransactionTemplateService;

it('creates a template scoped to its creator with enum and decoration casts', function (): void {
    $user = User::factory()->create();

    $template = resolve(TransactionTemplateService::class)->create($user, [
        'name' => 'Netflix',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 54_000,
        'description' => 'Monthly subscription',
        'account_id' => null,
        'destination_account_id' => null,
        'fee_amount' => null,
        'decorations' => ['icon' => 'tv', 'color' => 'red-500'],
    ]);

    expect($template->user_id)->toBe($user->id)
        ->and($template->type)->toBe(TransactionType::Expense)
        ->and($template->category_id)->toBe(Category::Streaming)
        ->and((float) $template->amount)->toEqual(54_000.0)
        ->and($template->decorations?->icon)->toBe('tv')
        ->and($template->decorations?->color)->toBe('red-500');
});

it('updates a template in place and returns a fresh instance', function (): void {
    $user = User::factory()->create();
    $service = resolve(TransactionTemplateService::class);

    $template = $service->create($user, [
        'name' => 'Netflix',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 54_000,
    ]);

    $updated = $service->update($template, [
        'name' => 'Netflix Premium',
        'type' => TransactionType::Expense->value,
        'category_id' => Category::Streaming->value,
        'amount' => 65_000,
    ]);

    expect($updated->id)->toBe($template->id)
        ->and($updated->name)->toBe('Netflix Premium')
        ->and((float) $updated->amount)->toEqual(65_000.0);
});

it('hard-deletes a template', function (): void {
    $template = TransactionTemplate::factory()->create();

    resolve(TransactionTemplateService::class)->delete($template);

    expect(TransactionTemplate::query()->count())->toBe(0);
});

it('lists only the user templates newest first with accounts eager-loaded', function (): void {
    $user = User::factory()->create();
    $source = Account::factory()->create(['owner_id' => $user->id]);
    $older = TransactionTemplate::factory()->create(['user_id' => $user->id]);
    $newer = TransactionTemplate::factory()->create(['user_id' => $user->id, 'account_id' => $source->id]);
    TransactionTemplate::factory()->create(); // another user's

    $templates = TransactionTemplateService::getTemplatesByUser($user);

    expect($templates)->toHaveCount(2)
        ->and($templates->first()->id)->toBe($newer->id)
        ->and($templates->last()->id)->toBe($older->id)
        ->and($templates->first()->relationLoaded('account'))->toBeTrue();
});

it('getTemplateById returns only owned templates', function (): void {
    $user = User::factory()->create();
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id]);
    $foreign = TransactionTemplate::factory()->create();

    expect(TransactionTemplateService::getTemplateById($user, $template->id)?->id)->toBe($template->id)
        ->and(TransactionTemplateService::getTemplateById($user, $foreign->id))->toBeNull()
        ->and(TransactionTemplateService::getTemplateById($user, 999_999))->toBeNull();
});

it('getQuickAccessTemplates nulls archived default sources but keeps selectable ones', function (): void {
    $user = User::factory()->create();
    $selectable = Account::factory()->create(['owner_id' => $user->id]);
    $archived = Account::factory()->archived()->create(['owner_id' => $user->id]);
    // Created oldest-first: the list is latest-first, so [1] is the archived one.
    TransactionTemplate::factory()->create(['user_id' => $user->id, 'account_id' => $archived->id]);
    TransactionTemplate::factory()->create(['user_id' => $user->id, 'account_id' => $selectable->id]);

    $templates = TransactionTemplateService::getQuickAccessTemplates($user);

    expect($templates[0]->account_id)->toBe($selectable->id)
        ->and($templates[1]->account_id)->toBeNull();
});

it('getQuickAccessTemplates nulls unselectable transfer destinations', function (): void {
    $user = User::factory()->create();
    $archived = Account::factory()->archived()->create(['owner_id' => $user->id]);
    TransactionTemplate::factory()->transfer()->create([
        'user_id' => $user->id,
        'destination_account_id' => $archived->id,
    ]);

    $templates = TransactionTemplateService::getQuickAccessTemplates($user);

    expect($templates[0]->destination_account_id)->toBeNull();
});

it('getTemplateById prunes an archived default source for seeding', function (): void {
    $user = User::factory()->create();
    $archived = Account::factory()->archived()->create(['owner_id' => $user->id]);
    $template = TransactionTemplate::factory()->create(['user_id' => $user->id, 'account_id' => $archived->id]);

    $seed = TransactionTemplateService::getTemplateById($user, $template->id);

    expect($seed?->account_id)->toBeNull();
});
```

- [x] **Step 2: Write the service**

```php
<?php

namespace App\Services;

use App\Enums\Category;
use App\Models\TransactionTemplate;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reference-data CRUD + read projections for user-private transaction
 * templates. Static reads and instance writes mirror AccountService. No
 * events, no caching — personal, small, low-frequency data.
 */
final readonly class TransactionTemplateService
{
    /** Management list: newest first, accounts eager-loaded for display. */
    public static function getTemplatesByUser(User $user): Collection
    {
        return TransactionTemplate::query()
            ->where('user_id', $user->id)
            ->with(['account:id,name', 'destinationAccount:id,name'])
            ->latest('id')
            ->get();
    }

    /**
     * Owner-scoped lookup for form seeding. The returned instance is a
     * pruned read projection: unselectable defaults are nulled so the
     * folding DTO never resurrects an archived/deleted account.
     */
    public static function getTemplateById(User $user, int $templateId): ?TransactionTemplate
    {
        $template = TransactionTemplate::query()
            ->where('user_id', $user->id)
            ->find($templateId);

        return $template !== null ? self::prune(self::selectableAccountIds($user), $template) : null;
    }

    /**
     * The user's templates as in-memory read projections for the create
     * form's template-select. Mutates unsaved instances only.
     */
    public static function getQuickAccessTemplates(User $user): Collection
    {
        $selectableIds = self::selectableAccountIds($user);

        return self::getTemplatesByUser($user)
            ->map(fn (TransactionTemplate $template): TransactionTemplate => self::prune($selectableIds, $template));
    }

    public function create(User $creator, array $data): TransactionTemplate
    {
        return TransactionTemplate::create([...$data, 'user_id' => $creator->id]);
    }

    public function update(TransactionTemplate $template, array $data): TransactionTemplate
    {
        $template->update($data);

        return $template->fresh();
    }

    public function delete(TransactionTemplate $template): void
    {
        $template->delete();
    }

    /** Owned + not archived + not soft-deleted account ids. */
    private static function selectableAccountIds(User $user): Collection
    {
        return AccountService::getAccountIdsByUser($user);
    }

    /**
     * Null out defaults that are no longer selectable, and guard category
     * drift (a stored slug removed from the enum in future).
     */
    private static function prune(Collection $selectableIds, TransactionTemplate $template): TransactionTemplate
    {
        if ($template->account_id !== null && ! $selectableIds->contains($template->account_id)) {
            $template->setAttribute('account_id', null);
        }

        if ($template->destination_account_id !== null && ! $selectableIds->contains($template->destination_account_id)) {
            $template->setAttribute('destination_account_id', null);
        }

        $rawCategory = $template->getRawOriginal('category_id');

        if (is_string($rawCategory) && Category::tryFrom($rawCategory) === null) {
            $template->setAttribute('category_id', null);
        }

        return $template;
    }
}
```

---

### Task 3: Policy, Request, Controller, Routes

**Complexity:** Medium

**Files:**
- Create: `app/Policies/TransactionTemplatePolicy.php`
- Create: `app/Http/Requests/SaveTransactionTemplateRequest.php`
- Create: `app/Http/Controllers/Settings/TransactionTemplateController.php`
- Modify: `routes/settings.php`
- Test: `tests/Feature/App/Settings/TransactionTemplateTest.php`

**Interfaces:**
- Consumes: `TransactionTemplateService` (Task 2), `AccountService::getAccountsByUser`, `CategoryService::getCategories` (existing).
- Produces: routes `settings/transaction-templates` (index/create/store/show/edit/update/destroy), names `transaction-templates.*`; `SaveTransactionTemplateRequest` used by both store and update; Wayfinder will generate `App\Http\Controllers\Settings\TransactionTemplateController` + named-route module `@wayfinder/routes/transaction-templates` after Task 4's regen.

- [x] **Step 1: Write the feature tests**

```php
<?php

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\TransactionTemplate;
use App\Models\User;

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
            ->has('categories')
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
            ->has('categories')
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
```

- [x] **Step 2: Write the policy**

```php
<?php

namespace App\Policies;

use App\Models\TransactionTemplate;
use App\Models\User;

/**
 * Templates are user-private: shareable-account viewers never see them.
 */
final class TransactionTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransactionTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function delete(User $user, TransactionTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
}
```

- [x] **Step 3: Write the form request**

```php
<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\TransactionTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

/**
 * Shared store/update validation. Type-conditional fields mirror
 * SaveTransactionRequest's prohibited/required style; account links must
 * be owned by the submitting user.
 */
final class SaveTransactionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template !== null
            ? $this->user()->can('update', $template)
            : $this->user()->can('create', TransactionTemplate::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn ($value) => $value === '' ? null : $value,
            $this->only(['account_id', 'destination_account_id', 'fee_amount', 'description']),
        ));
    }

    public function rules(): array
    {
        $isTransfer = $this->input('type') === TransactionType::Transfer->value;

        $ownedAccount = fn () => Rule::exists('accounts', 'id')->where('owner_id', $this->user()->id);

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', Rule::enum(TransactionType::class)],
            'category_id' => $isTransfer
                ? ['prohibited']
                : ['required', 'string', Rule::enum(Category::class)->except(Category::InitialBalance)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
            'account_id' => ['nullable', 'integer', $ownedAccount()],
            'destination_account_id' => $isTransfer
                ? ['required', 'integer', $ownedAccount()]
                : ['prohibited'],
            'fee_amount' => $isTransfer
                ? ['nullable', 'numeric', 'min:0.01']
                : ['prohibited'],
            'decorations' => ['nullable', 'array'],
            'decorations.icon' => ['required_with:decorations', 'string', 'max:100'],
            'decorations.color' => ['required_with:decorations', 'string', 'max:100'],
        ];
    }
}
```

(Remove the unused `RedirectResponse` import.)

- [x] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTransactionTemplateRequest;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\AccountService;
use App\Services\CategoryService;
use App\Services\TransactionTemplateService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class TransactionTemplateController extends Controller
{
    public function __construct(
        private readonly TransactionTemplateService $templateService,
        #[CurrentUser] private readonly ?User $user,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', TransactionTemplate::class);

        return Inertia::render('app/settings/transaction-templates/index', [
            'templates' => TransactionTemplateService::getTemplatesByUser($this->user),
            'categories' => CategoryService::getCategories(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', TransactionTemplate::class);

        return Inertia::render('app/settings/transaction-templates/form', [
            'template' => null,
            'accounts' => AccountService::getAccountsByUser($this->user),
            'categories' => CategoryService::getCategories(),
        ]);
    }

    public function store(SaveTransactionTemplateRequest $request): RedirectResponse
    {
        $this->templateService->create($this->user, $request->validated());

        return to_route('transaction-templates.index')->flash('Template created.');
    }

    public function show(TransactionTemplate $template): Response
    {
        $this->authorize('view', $template);

        return Inertia::render('app/settings/transaction-templates/show', [
            'template' => $template->load(['account:id,name', 'destinationAccount:id,name']),
            'categories' => CategoryService::getCategories(),
        ]);
    }

    public function edit(TransactionTemplate $template): Response
    {
        $this->authorize('update', $template);

        return Inertia::render('app/settings/transaction-templates/form', [
            'template' => $template,
            'accounts' => AccountService::getAccountsByUser($this->user),
            'categories' => CategoryService::getCategories(),
        ]);
    }

    public function update(SaveTransactionTemplateRequest $request, TransactionTemplate $template): RedirectResponse
    {
        $this->templateService->update($template, $request->validated());

        return to_route('transaction-templates.show', $template)->flash('Template updated.');
    }

    public function destroy(TransactionTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        $this->templateService->delete($template);

        return to_route('transaction-templates.index')->flash('Template deleted.');
    }
}
```

- [x] **Step 5: Register the routes**

In `routes/settings.php`, inside the existing `Route::middleware('auth')` group (after the ProfileController block), add:

```php
use App\Http\Controllers\Settings\TransactionTemplateController;

Route::prefix('settings/transaction-templates')->name('transaction-templates.')->group(function (): void {
    Route::controller(TransactionTemplateController::class)->group(function (): void {
        Route::get('', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('', 'store')->name('store');
        Route::get('{template}', 'show')->name('show');
        Route::get('{template}/edit', 'edit')->name('edit');
        Route::put('{template}', 'update')->name('update');
        Route::delete('{template}', 'destroy')->name('destroy');
    });
});
```

(The `use` line joins the existing imports at the top of the file.)

---

### Task 4: Form seeding — `fromTemplate`, `TransactionController::create`, Wayfinder regen

**Complexity:** Medium

**Files:**
- Modify: `app/Data/Transaction/TransactionFormData.php` (add `fromTemplate`)
- Modify: `app/Http/Controllers/TransactionController.php` (`create` only)
- Test: `tests/Feature/App/TransactionTemplateSeedingTest.php`

**Interfaces:**
- Consumes: `TransactionTemplateService::getTemplateById` / `getQuickAccessTemplates` (Task 2), `TransactionFormData` constructor (existing).
- Produces: `TransactionFormData::fromTemplate(TransactionTemplate $template, ?int $contextAccountId = null): self`; `GET /transactions/create` accepts `?template={id}` (+ optional `account_id` context) and ships a `templates` prop. After this task, run Wayfinder — the frontend plan (`2026-10-27-transaction-templates-frontend.md`) imports `@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController`, `@wayfinder/routes/transaction-templates`, and `App.Models.TransactionTemplate`.

- [x] **Step 1: Write the seeding feature tests**

```php
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
```

- [x] **Step 2: Add `fromTemplate` to `TransactionFormData`**

Add to `app/Data/Transaction/TransactionFormData.php`, beside `fromTransaction` / `fromTransfer`:

```php
    /**
     * Fold a transaction template into create-form defaults. Pruned input
     * expected (TransactionTemplateService::getTemplateById) so unselectable
     * accounts never reach the form. The context account — an explicit
     * account_id query param — wins over the template's default; a
     * destination equal to the resolved source is dropped rather than
     * tripping the form's different-account rule.
     */
    public static function fromTemplate(TransactionTemplate $template, ?int $contextAccountId = null): self
    {
        $accountId = $contextAccountId ?? $template->account_id;

        return new self(
            id: null,
            type: $template->type,
            amount: (float) $template->amount,
            transaction_date: now(),
            description: $template->description,
            account_id: $accountId,
            destination_account_id: $template->type === TransactionType::Transfer && $template->destination_account_id !== $accountId
                ? $template->destination_account_id
                : null,
            category_id: $template->category_id,
            fee_amount: $template->fee_amount !== null ? (float) $template->fee_amount : null,
            transfer_id: null,
        );
    }
```

Add `use App\Models\TransactionTemplate;` to the file's imports.

- [x] **Step 3: Update `TransactionController::create`**

Replace the existing `create` method body:

```php
    public function create(Request $request): Response
    {
        $this->authorize('create', Transaction::class);

        $template = TransactionTemplateService::getTemplateById($this->user, $request->integer('template'));

        return Inertia::render('app/transaction/create', [
            'transaction' => $template !== null
                ? TransactionFormData::fromTemplate($template, $request->integer('account_id') ?: null)
                : TransactionFormData::defaultFromRequest($request),
            'accounts' => $this->accountService->getAccountsByUser($this->user),
            'templates' => TransactionTemplateService::getQuickAccessTemplates($this->user),
        ]);
    }
```

Add `use App\Services\TransactionTemplateService;` to the file's imports.

- [x] **Step 4: Regenerate Wayfinder**

Run: `php artisan wayfinder:generate --no-interaction`

This is a codegen step (not a verification): Tasks 5–8 import from the generated output (`App/Http/Controllers/Settings/TransactionTemplateController`, `routes/transaction-templates`, `App.Models.TransactionTemplate`, `App.Enums.TransactionType` unchanged).

---
## Self-Review (completed)

Spec §1–§8, §11, §13, §14 all map to Tasks 1–4; no placeholders; service/controller names consistent across tasks; `direct` mode — no run/verify/commit steps; Pest tests scoped to the project's service/feature conventions. Complexity: Medium.
