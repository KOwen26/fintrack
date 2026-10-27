# Transaction Templates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** User-managed transaction templates (Settings CRUD) applied server-side to prefill the transaction create form via a `template-select` chip row.

**Architecture:** One new table/model (`transaction_templates`, single optional default source account, transfer destination + fee), owner-only policy, service with static reads (`get*`) + instance writes, settings controller rendering 3 Inertia pages (index / show / shared form). Applying a template is **server-side only**: chips navigate to `transactions/create?template=id&account_id=…` and `TransactionFormData::fromTemplate` folds it. Spec: `docs/superpowers/specs/2026-10-27-transaction-templates-design.md`.

**Tech Stack:** Laravel 8.4-style PHP (final readonly classes, enum casts, `#[Scope]`), Pest, Spatie data (existing `TransactionFormData` only), Inertia v3 + Svelte 5 runes, Wayfinder, Tailwind/DaisyUI atoms.

**Workflow Mode:** `direct` — write tests alongside implementation, but never run tests, formatters, or commit. No final verification task.

**Complexity:** Medium

## Global Constraints

- Spec is authoritative: `docs/superpowers/specs/2026-10-27-transaction-templates-design.md`. Naming: model `TransactionTemplate`, table `transaction_templates`, routes `transaction-templates.*`, frontend components `template-*` in `components/module/template/`.
- No DTO for templates (raw Eloquent models to Inertia — Wayfinder types them). No events, no caching, no soft deletes on templates.
- No pivot table. `account_id` single nullable FK; `destination_account_id` nullable FK for transfers; `amount` required `decimal(15,2)`.
- Category values are enum-backed (`Category` case values, e.g. `'dining_out'`); `InitialBalance` is excluded everywhere a category is choosable.
- Never hardcode URLs — Wayfinder imports only. Never hand-edit `resources/js/wayfinder/**`; run `php artisan wayfinder:generate`.
- Svelte 5 runes only (`$props`, `$state`, `$derived`, `$effect`, `bind:`); kebab-case filenames; prefer existing UI atoms (`Field`, `AccountSelect`, `CategorySelect`, `Tabs`, `ConfirmationModal`, `DecorationBadge`) over raw HTML.
- PHP: explicit return types, curly braces on all control structures, `final` classes, constructor property promotion. Curly-brace arrays in validation rules match `SaveTransactionRequest` style.
- Tests: Pest, `test('…', function (): void {})` style, `User::factory()->create()` (verified by default). Service tests in `tests/Integration/Services/`, feature tests in `tests/Feature/App/`. Write tests, never run them (`direct`).

## File Structure

**Backend — created:** `database/migrations/2026_10_27_000001_create_transaction_templates_table.php`, `app/Models/TransactionTemplate.php`, `database/factories/TransactionTemplateFactory.php`, `app/Policies/TransactionTemplatePolicy.php`, `app/Http/Requests/SaveTransactionTemplateRequest.php`, `app/Services/TransactionTemplateService.php`, `app/Http/Controllers/Settings/TransactionTemplateController.php`.

**Backend — modified:** `routes/settings.php`, `app/Data/Transaction/TransactionFormData.php` (add `fromTemplate`), `app/Http/Controllers/TransactionController.php` (`create` only).

**Tests — created:** `tests/Integration/Services/TransactionTemplateServiceTest.php`, `tests/Feature/App/Settings/TransactionTemplateTest.php`, `tests/Feature/App/TransactionTemplateSeedingTest.php`.

**Frontend — created:** `resources/js/schema/template.schema.ts`, `resources/js/components/module/template/{template-list,template-form,template-detail,template-select}.svelte`, `resources/js/pages/app/settings/transaction-templates/{index,show,form}.svelte`.

**Frontend — modified:** `resources/js/data/menu.ts`, `resources/js/pages/app/transaction/create.svelte`, `resources/js/components/module/transaction/transaction-form.svelte`, `README.md` (roadmap reword).

**Explicitly unchanged:** `app.ts` (layout switch defaults to `DashboardLayout` for every non-auth page — verified), `DashboardController`, dashboard page, DTO files beyond `TransactionFormData`.

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

- [ ] **Step 1: Write the migration**

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

- [ ] **Step 2: Write the model**

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

- [ ] **Step 3: Write the factory**

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

- [ ] **Step 1: Write the service tests**

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

- [ ] **Step 2: Write the service**

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

- [ ] **Step 1: Write the feature tests**

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
    $this->get(route('transaction-templates.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Write the policy**

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

- [ ] **Step 3: Write the form request**

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

- [ ] **Step 4: Write the controller**

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

- [ ] **Step 5: Register the routes**

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
- Produces: `TransactionFormData::fromTemplate(TransactionTemplate $template, ?int $contextAccountId = null): self`; `GET /transactions/create` accepts `?template={id}` (+ optional `account_id` context) and ships a `templates` prop. After this task, run Wayfinder — all later tasks import `@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController`, `@wayfinder/routes/transaction-templates`, and `App.Models.TransactionTemplate`.

- [ ] **Step 1: Write the seeding feature tests**

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
            ->where('transaction.amount', 25000.0)
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
            ->where('transaction.fee_amount', 2500.0)
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

- [ ] **Step 2: Add `fromTemplate` to `TransactionFormData`**

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

- [ ] **Step 3: Update `TransactionController::create`**

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

- [ ] **Step 4: Regenerate Wayfinder**

Run: `php artisan wayfinder:generate --no-interaction`

This is a codegen step (not a verification): Tasks 5–8 import from the generated output (`App/Http/Controllers/Settings/TransactionTemplateController`, `routes/transaction-templates`, `App.Models.TransactionTemplate`, `App.Enums.TransactionType` unchanged).

---

### Task 5: Settings management frontend — schema, menu, index page, template-list

**Complexity:** Medium

**Files:**
- Create: `resources/js/schema/template.schema.ts`
- Create: `resources/js/components/module/template/template-list.svelte`
- Create: `resources/js/pages/app/settings/transaction-templates/index.svelte`
- Modify: `resources/js/data/menu.ts`

**Interfaces:**
- Consumes: Task 4's Wayfinder output; existing `Formatter.currency`, `DecorationBadge`, `CurrencyAmount`, `Link`, `MobilePageLayout`.
- Produces: `templateSchema` + `templateSubtitle(template, categories)` + `labelForType(type)` exports (used by Task 7's detail view); settings menu entry "Transaction Templates".

No JS test suite exists in this project (quality tooling is Pest/Pint/Rector/ESLint/Prettier) — no test step for frontend tasks, matching house convention.

- [ ] **Step 1: Write `resources/js/schema/template.schema.ts`**

```ts
import type { DataSchema } from '@lib/data-composer';
import type { App } from '@wayfinder/types';

import TransactionType from '@wayfinder/App/Enums/TransactionType';

import CurrencyAmount from '@components/data/currency-amount.svelte';
import Formatter from '@utilities/formatter';

export const templateTypeOptions = [
    { value: TransactionType.Income, label: 'Income' },
    { value: TransactionType.Expense, label: 'Expense' },
    { value: TransactionType.Transfer, label: 'Transfer' },
];

export const labelForType = (type: App.Enums.TransactionType): string =>
    templateTypeOptions.find((o) => o.value === type)?.label ?? type;

/** One-line summary for list rows: "Expense · Dining Out" or "Transfer → BCA Savings". */
export const templateSubtitle = (
    template: App.Models.TransactionTemplate,
    categories: App.Data.CategoryData[]
): string => {
    if (template.type === TransactionType.Transfer) {
        return `${labelForType(template.type)} → ${template.destinationAccount?.name ?? 'Pick destination'}`;
    }

    const category = categories.find((c) => c.id === template.category_id)?.name ?? template.category_id ?? '—';

    return `${labelForType(template.type)} · ${category}`;
};

export const templateSchema: DataSchema<App.Models.TransactionTemplate> = {
    name: { label: 'Name' },
    type: { label: 'Type', value: (data) => labelForType(data.type) },
    category_id: {
        label: 'Category',
        show: (data) => data.type !== TransactionType.Transfer,
        value: (data) => data.category_id ?? '—',
    },
    amount: { label: 'Amount', value: (data) => Formatter.currency(data.amount) },
    account_id: {
        label: 'Default Source Account',
        value: (data) => data.account?.name ?? '—',
    },
    destination_account_id: {
        label: 'Destination Account',
        show: (data) => data.type === TransactionType.Transfer,
        value: (data) => data.destinationAccount?.name ?? '—',
    },
    fee_amount: {
        label: 'Transfer Fee',
        show: (data) => data.type === TransactionType.Transfer,
        value: (data) => (data.fee_amount !== null ? Formatter.currency(data.fee_amount) : '—'),
    },
    description: { label: 'Note', value: (data) => data.description ?? '—' },
};
```

(If `CurrencyAmount` ends up unused in this file, drop that import — it is used by `template-list.svelte` below.)

- [ ] **Step 2: Add the settings menu entry**

In `resources/js/data/menu.ts`, extend the `settingsMenu.menus` object (after `appearance`) and add the named-route import alongside the existing ones:

```ts
import transactionTemplates from '@wayfinder/routes/transaction-templates';
```

```ts
        transaction_templates: {
            name: 'Transaction Templates',
            url: transactionTemplates.index().url,
            route: 'transaction-templates.index',
            active: 'transaction-templates.*',
            icon: 'iconify solar--repeat-line-duotone',
            type: 'menu',
        },
```

- [ ] **Step 3: Write `resources/js/components/module/template/template-list.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import { templateSubtitle } from '@schema/template.schema';

    import Link from '@components/ui/link.svelte';

    let {
        templates,
        categories,
    }: {
        templates: App.Models.TransactionTemplate[];
        categories: App.Data.CategoryData[];
    } = $props();
</script>

<ul class="space-y-1">
    {#each templates as template (template.id)}
        <li>
            <Link
                class="flex items-center gap-3 rounded-xl px-3 py-3 transition-colors hover:bg-base-200 focus:bg-base-200"
                href={TransactionTemplateController.show.url({ template: template.id })}>
                <DecorationBadge decorations={template.decorations} size="sm" />

                <span class="min-w-0 grow">
                    <span class="block truncate text-sm font-semibold">{template.name}</span>
                    <span class="block truncate text-xs text-base-content/60">
                        {templateSubtitle(template, categories)}
                    </span>
                </span>

                <span class="shrink-0 text-sm font-medium">
                    <CurrencyAmount value={Number(template.amount)} />
                </span>

                <i class="iconify size-5 shrink-0 text-neutral solar--alt-arrow-right-linear" aria-hidden="true"
                ></i>
            </Link>
        </li>
    {/each}
</ul>
```

- [ ] **Step 4: Write `resources/js/pages/app/settings/transaction-templates/index.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';
    import settings from '@wayfinder/routes/settings';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TemplateList from '@components/module/template/template-list.svelte';
    import Button from '@components/ui/button.svelte';

    let {
        templates,
        categories,
    }: {
        templates: App.Models.TransactionTemplate[];
        categories: App.Data.CategoryData[];
    } = $props();

    setLayoutProps({ title: 'Transaction Templates', backUrl: settings.index.url() });
</script>

<MobilePageLayout variant="4/5">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Templates</h2>

        <Button color="primary" href={TransactionTemplateController.create.url()} size="sm">
            <i class="iconify size-4 solar--add-bold-duotone"></i>
            Add
        </Button>
    </div>

    <div class="mt-4">
        {#if templates.length}
            <TemplateList {categories} {templates} />
        {:else}
            <p class="px-3 py-8 text-center text-sm text-base-content/60">
                No templates yet — create one to prefill transactions in one tap.
            </p>
        {/if}
    </div>
</MobilePageLayout>
```

---

### Task 6: Shared form page + template-form component

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/module/template/template-form.svelte`
- Create: `resources/js/pages/app/settings/transaction-templates/form.svelte`

**Interfaces:**
- Consumes: `AccountSelect` (`value`, `accounts`, `clearable`, `required`, `disabled`, `placeholder` props), `CategorySelect` (`cashflow`, `value`, `required`), `DecorationIconSelector` / `DecorationColorSelector` (`value` bindable slug string), `Tabs`/`TabsList`/`TabsTrigger` (`value`, `bind:value`, `onchange`), `CurrencyInput` (`bind:value`, `inputmode`, `placeholder`), `Form` (`{...action}` from Wayfinder `.form()`, `{form}`, `id`), `ConfirmationModal` (`bind:open`, `title`, `confirmText`, `cancelText`, `confirmButtonProps`, `onConfirm`).
- Produces: the create/edit form used by both routes; `form.svelte` page props `{ template?: App.Models.TransactionTemplate | null; accounts: App.Models.Account[] }`.

- [ ] **Step 1: Write `resources/js/components/module/template/template-form.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { useForm, router } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import AccountSelect from '@components/module/account/account-select.svelte';
    import CategorySelect from '@components/module/category/category-select.svelte';
    import DecorationColorSelector from '@components/module/decoration-color-selector.svelte';
    import DecorationIconSelector from '@components/module/decoration-icon-selector.svelte';
    import BottomActionBar from '@components/navigation/bottom-action-bar.svelte';
    import TabsList from '@components/ui/atoms/tabs/tabs-list.svelte';
    import TabsTrigger from '@components/ui/atoms/tabs/tabs-trigger.svelte';
    import Tabs from '@components/ui/atoms/tabs/tabs.svelte';
    import Button from '@components/ui/button.svelte';
    import CurrencyInput from '@components/ui/forms/currency-input.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';
    import Textarea from '@components/ui/forms/textarea.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    interface Props {
        template?: App.Models.TransactionTemplate | null;
        accounts: App.Models.Account[];
    }

    let { template = null, accounts }: Props = $props();

    const isEdit = $derived(template !== null);

    const form = useForm({
        name: template?.name ?? '',
        type: template?.type ?? TransactionType.Expense,
        category_id: template?.category_id ?? '',
        amount: template?.amount !== null && template?.amount !== undefined ? String(template.amount) : '',
        description: template?.description ?? '',
        account_id: template?.account_id ?? '',
        destination_account_id: template?.destination_account_id ?? '',
        fee_amount:
            template?.fee_amount !== null && template?.fee_amount !== undefined
                ? String(template.fee_amount)
                : '',
        decorations: {
            icon: template?.decorations?.icon ?? '',
            color: template?.decorations?.color ?? '',
        },
    });

    const isTransfer = $derived(form.type === TransactionType.Transfer);
    const isIncome = $derived(form.type === TransactionType.Income);

    const action = $derived(
        isEdit && template
            ? TransactionTemplateController.update.form({ template: template.id })
            : TransactionTemplateController.store.form()
    );

    /* A transfer's destination never offers the (optional) default source. */
    const destinationAccounts = $derived(
        form.account_id
            ? accounts.filter((account) => String(account.id) !== String(form.account_id))
            : accounts
    );

    $effect(() => {
        if (form.destination_account_id && form.destination_account_id === form.account_id) {
            form.destination_account_id = '';
        }
    });

    /* A type switch keeps shared values and drops type-specific ones. */
    function handleTypeChange(): void {
        form.category_id = '';
        form.destination_account_id = '';
        form.fee_amount = '';
        form.clearErrors();
    }

    /* Strip fields the request prohibits for the active type, and empty decorations. */
    form.transform((data) => {
        const payload: Record<string, unknown> = { ...data };

        if (data.type === TransactionType.Transfer) {
            delete payload.category_id;
        } else {
            delete payload.destination_account_id;
            delete payload.fee_amount;
        }

        if (!data.decorations?.icon && !data.decorations?.color) {
            delete payload.decorations;
        }

        return payload;
    });

    const submitDisabled = $derived(
        !form.name ||
            !form.amount ||
            (isTransfer ? !form.destination_account_id : !form.category_id)
    );

    let showDeleteConfirm = $state(false);

    function destroy(): void {
        if (!template) {
            return;
        }

        router.delete(TransactionTemplateController.destroy.url({ template: template.id }));
    }
</script>

<Form id="transaction-template-form" {...action} {form}>
    <div class="space-y-4">
        <Field error={form.errors.name} required title="Name">
            <Input placeholder="e.g. Netflix" bind:value={form.name} />
        </Field>

        <Field title="Icon">
            <DecorationIconSelector bind:value={form.decorations.icon} />
        </Field>

        <Field title="Color">
            <DecorationColorSelector bind:value={form.decorations.color} />
        </Field>

        <Tabs onchange={handleTypeChange} bind:value={form.type}>
            <TabsList class="w-full">
                <TabsTrigger value={TransactionType.Income}>Income</TabsTrigger>
                <TabsTrigger value={TransactionType.Expense}>Expense</TabsTrigger>
                <TabsTrigger value={TransactionType.Transfer}>Transfer</TabsTrigger>
            </TabsList>
        </Tabs>

        {#if !isTransfer}
            <Field error={form.errors.category_id} required title="Category">
                <CategorySelect
                    cashflow={isIncome ? 'inflow' : 'outflow'}
                    required
                    bind:value={form.category_id} />
            </Field>
        {:else}
            <Field error={form.errors.destination_account_id} required title="Destination Account">
                <AccountSelect
                    {accounts}
                    placeholder="Select Destination Account"
                    required
                    bind:value={form.destination_account_id} />
            </Field>

            <Field error={form.errors.fee_amount} title="Transfer fee (optional)">
                <CurrencyInput inputmode="numeric" placeholder="0" bind:value={form.fee_amount} />
            </Field>
        {/if}

        <Field error={form.errors.amount} required title="Amount">
            <CurrencyInput inputmode="numeric" placeholder="0" bind:value={form.amount} />
        </Field>

        <Field error={form.errors.account_id} title="Default Source Account (optional)">
            <AccountSelect
                {accounts}
                placeholder="No default account"
                bind:value={form.account_id} />
        </Field>

        <Field error={form.errors.description} title="Note">
            <Textarea class="min-h-20" placeholder="Optional note or memo" bind:value={form.description} />
        </Field>
    </div>
</Form>

<BottomActionBar>
    <div class="flex gap-3">
        {#if isEdit}
            <Button color="error" onclick={() => (showDeleteConfirm = true)} size="icon" variant="outline">
                <i class="iconify solar--trash-bin-minimalistic-bold-duotone"></i>
            </Button>
        {/if}

        <SubmitButton
            class="grow"
            disabled={submitDisabled}
            form="transaction-template-form"
            submitting={form.processing}>
            {isEdit ? 'Save Changes' : 'Create Template'}
        </SubmitButton>
    </div>
</BottomActionBar>

<ConfirmationModal
    title="Delete Template"
    confirmText="Delete"
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    onConfirm={destroy}
    bind:open={showDeleteConfirm}>
    This will permanently delete the template. It cannot be undone.
</ConfirmationModal>
```

Notes for the implementer: mirror `transaction-form.svelte`'s markup conventions exactly (Field `title`/`error` usage, Tabs structure — all three tabs stay enabled; the bound value drives the active state).

- [ ] **Step 2: Write `resources/js/pages/app/settings/transaction-templates/form.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TemplateForm from '@components/module/template/template-form.svelte';

    let {
        template = null,
        accounts,
    }: {
        template?: App.Models.TransactionTemplate | null;
        accounts: App.Models.Account[];
    } = $props();

    setLayoutProps({
        title: template ? 'Edit Template' : 'Add Template',
        backUrl: template
            ? TransactionTemplateController.show.url({ template: template.id })
            : TransactionTemplateController.index.url(),
    });
</script>

<MobilePageLayout variant="full">
    <TemplateForm {accounts} {template} />
</MobilePageLayout>
```

---

### Task 7: Detail page + template-detail component

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/module/template/template-detail.svelte`
- Create: `resources/js/pages/app/settings/transaction-templates/show.svelte`

**Interfaces:**
- Consumes: `templateSchema` (Task 5), `DataComposer.from(schema).toDataDisplay(data)`, `DataList` (`@components/data/data-list.svelte` — verify the exact filename against siblings; `account-detail.svelte` imports it), `ConfirmationModal`, `Button`.
- Produces: the show page used by `transaction-templates.show`.

- [ ] **Step 1: Write `resources/js/components/module/template/template-detail.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { DataComposer } from '@lib/data-composer';
    import { templateSchema } from '@schema/template.schema';

    import DataList from '@components/data/data-list.svelte';

    let { template }: { template: App.Models.TransactionTemplate } = $props();

    const details = $derived(DataComposer.from(templateSchema).toDataDisplay(template));
</script>

<DataList data={details} />
```

(If `account-detail.svelte` resolves account names differently — e.g. it consumes an `accounts` prop — follow its exact pattern for the DataList composition; the schema already reads `data.account?.name` from the eager-loaded relation, so no `accounts` prop should be needed here.)

- [ ] **Step 2: Write `resources/js/pages/app/settings/transaction-templates/show.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router, setLayoutProps } from '@inertiajs/svelte';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import Card from '@components/ui/card.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TemplateDetail from '@components/module/template/template-detail.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { template }: { template: App.Models.TransactionTemplate } = $props();

    setLayoutProps({ title: template.name, backUrl: TransactionTemplateController.index.url() });

    let showDeleteConfirm = $state(false);

    function destroy(): void {
        router.delete(TransactionTemplateController.destroy.url({ template: template.id }));
    }
</script>

<MobilePageLayout variant="full">
    <div class="space-y-6">
        <Card title={template.name}>
            <TemplateDetail {template} />
        </Card>

        <div class="flex gap-3">
            <Button
                class="grow"
                color="primary"
                href={TransactionTemplateController.edit.url({ template: template.id })}
                variant="outline">
                Edit
            </Button>

            <Button color="error" onclick={() => (showDeleteConfirm = true)} variant="outline">
                <i class="iconify size-5 solar--trash-bin-minimalistic-bold-duotone"></i>
                Delete
            </Button>
        </div>
    </div>
</MobilePageLayout>

<ConfirmationModal
    title="Delete Template"
    confirmText="Delete"
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    onConfirm={destroy}
    bind:open={showDeleteConfirm}>
    This will permanently delete the template. It cannot be undone.
</ConfirmationModal>
```

---

### Task 8: template-select + create-form wiring + README

**Complexity:** Medium

**Files:**
- Create: `resources/js/components/module/template/template-select.svelte`
- Modify: `resources/js/pages/app/transaction/create.svelte`
- Modify: `resources/js/components/module/transaction/transaction-form.svelte`
- Modify: `README.md`

**Interfaces:**
- Consumes: `templates` prop (Task 4's `getQuickAccessTemplates`), `TransactionController.create.url({ query })`, `DecorationBadge`, `CurrencyAmount`.
- Produces: the complete quick-access surface; nothing downstream.

- [ ] **Step 1: Write `resources/js/components/module/template/template-select.svelte`**

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import DecorationBadge from '@components/module/decoration-badge.svelte';

    let {
        templates,
        selectedAccountId = null,
    }: {
        templates: App.Models.TransactionTemplate[];
        selectedAccountId?: string | number | null;
    } = $props();

    /* Presets bound to the selected account surface first; the rest follow. */
    const ordered = $derived.by(() => {
        const key = String(selectedAccountId ?? '');

        return [...templates].sort((a, b) => {
            const aMatch = a.account_id !== null && String(a.account_id) === key ? 0 : 1;
            const bMatch = b.account_id !== null && String(b.account_id) === key ? 0 : 1;

            return aMatch - bMatch;
        });
    });

    /* Chips are pure navigation: the server folds the template (§8 of the spec). */
    function apply(template: App.Models.TransactionTemplate): void {
        router.visit(
            TransactionController.create.url({
                query: {
                    template: template.id,
                    ...(selectedAccountId ? { account_id: selectedAccountId } : {}),
                },
            }),
            { preserveScroll: true }
        );
    }
</script>

<div class="-mx-5 flex gap-2 overflow-x-auto px-5 pb-1">
    {#each ordered as template (template.id)}
        <button
            class="flex shrink-0 items-center gap-2 rounded-full border border-base-content/15 bg-base-100 px-3 py-1.5 transition-colors hover:border-base-content/35"
            type="button"
            onclick={() => apply(template)}>
            <DecorationBadge decorations={template.decorations} size="sm" />
            <span class="whitespace-nowrap text-sm font-medium">{template.name}</span>
            <span class="whitespace-nowrap text-xs text-base-content/50">
                <CurrencyAmount value={Number(template.amount)} />
            </span>
        </button>
    {/each}
</div>
```

- [ ] **Step 2: Forward the prop in `resources/js/pages/app/transaction/create.svelte`**

```svelte
<script lang="ts">
    import type { Data, Models } from '@type/type';
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';

    let {
        transaction,
        accounts,
        templates = [],
    }: {
        transaction: Data.TransactionFormData;
        accounts: Models.Account[];
        templates?: App.Models.TransactionTemplate[];
    } = $props();

    setLayoutProps({ title: 'Add New Transaction', backUrl: TransactionController.index.url() });
</script>

<TransactionForm {accounts} {templates} {transaction} />
```

- [ ] **Step 3: Render the select in `resources/js/components/module/transaction/transaction-form.svelte`**

Add to the imports: `import TemplateSelect from '@components/module/template/template-select.svelte';`

Extend the `Props` interface and destructuring (after `amountPresets`):

```ts
        /** Quick-access templates — create surface only. */
        templates?: App.Models.TransactionTemplate[];
```

```ts
    let { transaction, accounts, templates = [], amountPresets = DEFAULT_AMOUNT_PRESETS }: Props = $props();
```

Render directly under the type `Tabs` block, still inside the `<Form>`, gated to create mode:

```svelte
        {#if !isEdit && templates.length}
            <div class="mt-4">
                <TemplateSelect {templates} selectedAccountId={form.account_id} />
            </div>
        {/if}
```

- [ ] **Step 4: Reword the README roadmap**

In `README.md` → `## Roadmap` → `### Current focus`: delete the "Transaction Presets Feature" block (its three bullets) and leave "Transaction Tagging Feature" as the remaining current-focus item. In `### Later`, reword the scheduled-transaction entry to template vocabulary:

```markdown
- Scheduled Transaction (Template improvement)
  - from a transaction template the user can set it as a scheduled transaction, either requiring confirmation or not
```

Also add a sentence to the README domain narrative (near the Transaction section) once shipped:

```markdown
- **Templates**: user-private transaction form presets (name, decoration, type, category or transfer destination, amount, optional default source account), managed under Settings and applied server-side to prefill the create form via `?template=`.
```

---

## Self-Review (completed)

1. **Spec coverage** — schema/model/factory (T1), service incl. pruning + seeding lookup (T2), policy/request/controller/routes (T3), `fromTemplate` + `?template=` seeding + `templates` prop + codegen (T4), settings index + menu + schema (T5), shared form pages (T6), detail (T7), quick access + create wiring + README (T8). Spec §9's three pages, §10's server-side chips, §13 codegen, §14 tests all map to tasks. §11 edge cases are covered by T2's prune tests and T4's seeding tests.
2. **Placeholders** — none; every step carries code. The `TabsTrigger disabled` note in T6 flags copied-verbatim styling rather than leaving prose.
3. **Type consistency** — service method names identical in T2/T3/T4; `template-*` component names consistent across T5–T8; `form.decorations.icon` binding matches `DecorationIconSelector`'s bindable `value`.
4. **Workflow mode** — `direct`: no run/verify/commit steps anywhere, no final verification task.
5. **Testing scope** — backend tasks carry Pest tests matching house conventions (service integration + HTTP feature); frontend tasks carry none (no JS suite exists — verified). Task 1 deliberately has no test (casts covered by T2).
6. **Complexity** — T1 Low; T2–T8 Medium (all steps concrete code). Overall: **Medium**.
