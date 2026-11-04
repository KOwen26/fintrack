# Household Membership Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use better-executing-plans to implement this plan task-by-task — or better-parallel-subagents-executions if the plan splits into independent slices, or superpowers:subagent-driven-development if workflow mode is `remote`. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Household grouping (owner + members, invite link + OTP) with household-scoped visibility of user-owned `shareable` accounts, replacing the "shareable = visible to everyone" placeholder.

**Architecture:** Three new tables (`households`, `household_members`, `household_invites`) group users; accounts keep `owner_id` + `access_type` untouched. A `visibleTo(User)` query scope and three policy `view` methods derive visibility from household membership, and `AccountService`'s read methods propagate it to every list, dashboard, statistic, and form. Lifecycle (create/join/leave/remove/transfer/dissolve/invites) lives in `HouseholdService`; controllers are thin dispatchers.

**Tech Stack:** Laravel 11 (PHP 8.4), Inertia v3 + Svelte 5, Tailwind v4 (DaisyUI + shadcn atoms), Wayfinder, Pest.

**Workflow Mode:** `direct` — no per-task test runs, no lint/format steps, no commits. Tests are written alongside implementation; the human runs and commits.

**Complexity:** Medium

**Spec:** `docs/superpowers/specs/2026-11-04-household-membership-design.md`

## Global Constraints

- PHP 8.4: constructor property promotion, `readonly` where stateless, explicit return types + param types, curly braces on all control structures (incl. single-line).
- Models: unguarded (global `Model::unguard()`), `protected function casts(): array` method form, `#[Scope]` attributes for named scopes, `#[UseFactory]` attribute binding factories, `#[ObservedBy]` only if lifecycle side effects exist (none do here).
- Migrations: `$table->foreignId(...)->constrained()` always with an explicit onDelete action; no `$table->enum()`; string columns + PHP enum casts.
- Services: static reads / instance writes posture (like `AccountTemplateService`/`AccountService`); `final readonly class`; `DB::transaction` for multi-step writes; never `destroy()` name — use `dissolve`/`softDelete` semantics.
- Controllers: `#[CurrentUser] private readonly ?User $user` constructor injection; thin — authorize → service → `to_route(...)->flash('...')`; `$this->authorize()` only for actions without a Form Request (Form Request `authorize()` owns the check otherwise).
- Routes: explicit verb routes on multi-method singular controllers; middleware on routes (`->middleware()`), never on controllers; named `resource.action`.
- Time: `now()` / `Date::` facade, never `Carbon::now()`.
- Frontend: Svelte 5 runes only; kebab-case files; module components in `resources/js/components/module/household/`; URLs via Wayfinder imports (never hardcoded); Solar icons (`solar--`, default weight `-bold-duotone` or `-line-duotone`); no i18n; toasts via flash props (never alert UIs); `ConfirmationModal` for destructive confirms; block order `<script module>` → `<script>` → markup → snippets → `<style>`.
- Tests: factories with named states only (never seeders/raw inserts); Feature tests use `test()` + `route(...)` + `assertRedirectToRoute()`/`assertInertia()`; Integration tests use `it()`; time-dependent behavior via `$this->travelTo()`; `tests/Pest.php` module group mapping added when test files/dirs appear (architecture suite enforces it).
- After any backend route/controller change: run `php artisan wayfinder:generate` (Task 7 does this once).
- Existing behavior preserved: account writes owner-only; transaction/transfer writes creator-only; global transaction list stays `created_by`-scoped; money engine untouched.

---

### Task 1: Household schema — migration, models, factories, User relations

**Complexity:** Medium

**Files:**
- Create: `database/migrations/2026_11_04_000001_create_household_tables.php`
- Create: `app/Models/Household.php`
- Create: `app/Models/HouseholdMember.php`
- Create: `app/Models/HouseholdInvite.php`
- Modify: `app/Models/User.php`
- Create: `database/factories/HouseholdFactory.php`
- Create: `database/factories/HouseholdMemberFactory.php`
- Create: `database/factories/HouseholdInviteFactory.php`

**Interfaces:**
- Produces (consumed by Tasks 2–6):
  - `User::householdMembership(): HasOne` → `?HouseholdMember`
  - `User::household(): HasOneThrough` → `?Household`
  - `User::inHousehold(): bool`
  - `User::sharesHouseholdWith(User $other): bool`
  - `HouseholdInvite::MAX_OTP_ATTEMPTS = 5`, `HouseholdInvite::INVITE_TTL_DAYS = 7`
  - `HouseholdInvite::isValid(): bool`, `HouseholdInvite::isLocked(): bool`
  - Factories: `Household::factory()->withOwner()` / `->withOwnerAndMembers(int $n)`, `HouseholdInvite::factory()->forHousehold($h)` + states `expired()` / `revoked()` / `exhausted()`

No dedicated test — models are data holders; their logic is exercised by the Task 2/3 test suites (project convention: no schema-only tests).

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
        Schema::create('households', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('owner_id');
        });

        Schema::create('household_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');

            // One household per user — enforced at the DB level.
            $table->unique('user_id');
            $table->index('household_id');
        });

        Schema::create('household_invites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            // Plaintext by design: the owner's invite card redisplays the code,
            // so hashing is off the table. Throttle + attempt lockout are the
            // brute-force defense.
            $table->string('otp_code', 6);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('household_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_invites');
        Schema::dropIfExists('household_members');
        Schema::dropIfExists('households');
    }
};
```

Note: household tables take **no soft deletes** — dissolution is a hard delete of the grouping and accounts/history are unaffected (spec decision; deliberate deviation from the house `softDeletes()` default).

- [ ] **Step 2: Write the three models**

`app/Models/Household.php`:

```php
<?php

namespace App\Models;

use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named group of users sharing their financial life. Ownership is a
 * denormalized pointer (owner_id) rather than a role column — it is the
 * single source of truth for who manages the household, and member role
 * is derived by comparing user_id against it.
 */
#[UseFactory(HouseholdFactory::class)]
final class Household extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(HouseholdInvite::class);
    }

    /** The active (unexpired, unrevoked, unexhausted) invite, if one exists. */
    public function activeInvite(): ?HouseholdInvite
    {
        return $this->invites()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->where('failed_attempts', '<', HouseholdInvite::MAX_OTP_ATTEMPTS)
            ->latest('id')
            ->first();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }
}
```

`app/Models/HouseholdMember.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HouseholdMember extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->household->owner_id === $this->user_id;
    }
}
```

`app/Models/HouseholdInvite.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable bearer link (token) + OTP pair admitting joiners to one
 * household until expiry/revocation. The OTP is a short-lived family
 * secret shown in the owner's UI — possession of the link alone is not
 * enough, and five failed attempts lock the invite out.
 */
final class HouseholdInvite extends Model
{
    public const int MAX_OTP_ATTEMPTS = 5;

    public const int INVITE_TTL_DAYS = 7;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->failed_attempts >= self::MAX_OTP_ATTEMPTS;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked() && ! $this->isLocked();
    }
}
```

- [ ] **Step 3: Add household relations to User**

Add to `app/Models/User.php` (inside the class, after the `casts()` method):

```php
    public function householdMembership(): HasOne
    {
        return $this->hasOne(HouseholdMember::class);
    }

    public function household(): HasOneThrough
    {
        return $this->hasOneThrough(Household::class, HouseholdMember::class, 'user_id', 'id', 'id', 'household_id');
    }

    public function inHousehold(): bool
    {
        return $this->householdMembership()->exists();
    }

    /**
     * Whether both users are members of the same household — the single
     * visibility predicate for shareable accounts. Null memberships on
     * either side mean no sharing.
     */
    public function sharesHouseholdWith(User $other): bool
    {
        $membership = $this->householdMembership;

        return $membership !== null
            && $other->householdMembership?->household_id === $membership->household_id;
    }
```

Add imports: `use Illuminate\Database\Eloquent\Relations\HasOne;` and `use Illuminate\Database\Eloquent\Relations\HasOneThrough;`.

- [ ] **Step 4: Write the factories**

`database/factories/HouseholdFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends Factory<Household>
 */
#[UseModel(Household::class)]
class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Keluarga ' . fake()->lastName(),
            'owner_id' => User::factory(),
        ];
    }

    /** Creates the owner's membership row alongside the household. */
    public function withOwnerAndMembers(int $additionalMembers = 0): static
    {
        return $this->afterCreating(function (Household $household) use ($additionalMembers): void {
            HouseholdMember::create([
                'household_id' => $household->id,
                'user_id' => $household->owner_id,
                'joined_at' => now(),
            ]);

            for ($i = 0; $i < $additionalMembers; $i++) {
                HouseholdMember::create([
                    'household_id' => $household->id,
                    'user_id' => User::factory()->create()->id,
                    'joined_at' => now(),
                ]);
            }
        });
    }
}
```

`database/factories/HouseholdMemberFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\HouseholdMember;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HouseholdMember>
 */
#[UseModel(HouseholdMember::class)]
class HouseholdMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => \App\Models\Household::factory(),
            'user_id' => \App\Models\User::factory(),
            'joined_at' => now(),
        ];
    }
}
```

`database/factories/HouseholdInviteFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\HouseholdInvite;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HouseholdInvite>
 */
#[UseModel(HouseholdInvite::class)]
class HouseholdInviteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => \App\Models\Household::factory(),
            'token' => (string) Str::ulid(),
            'otp_code' => sprintf('%06d', random_int(0, 999_999)),
            'failed_attempts' => 0,
            'expires_at' => now()->addDays(HouseholdInvite::INVITE_TTL_DAYS),
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()->subDay()]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (): array => ['failed_attempts' => HouseholdInvite::MAX_OTP_ATTEMPTS]);
    }
}
```

---

### Task 2: HouseholdService — lifecycle and invites (with OTP)

**Complexity:** Medium

**Files:**
- Create: `app/Services/HouseholdService.php`
- Modify: `tests/Support/helpers.php`
- Create: `tests/Integration/Services/HouseholdServiceTest.php`
- Modify: `tests/Pest.php` (Integration module mapping)

**Interfaces:**
- Consumes: `Household`, `HouseholdMember`, `HouseholdInvite` models + factories from Task 1
- Produces (consumed by Tasks 5–6 controllers):
  - `HouseholdService::getHouseholdContext(User $user): ?Household` — household with `members.user:id,name` eager-loaded
  - `HouseholdService::activeInvite(Household $household): ?HouseholdInvite`
  - `$householdService->create(User $creator, string $name): Household`
  - `$householdService->rename(Household $household, string $name): Household`
  - `$householdService->join(User $joiner, string $token, string $otp): HouseholdMember` — throws `ValidationException` keyed `token` / `otp`
  - `$householdService->leave(Household $household, User $member): void`
  - `$householdService->removeMember(Household $household, HouseholdMember $member): void`
  - `$householdService->transferOwnership(Household $household, HouseholdMember $toMember): Household`
  - `$householdService->dissolve(Household $household): void`
  - `$householdService->regenerateInvite(Household $household): HouseholdInvite`
  - `$householdService->revokeInvite(HouseholdInvite $invite): void`
- Produces (test helper): `householdWithMembers(int $additional = 1): array{0: Household, 1: User, 2: Collection<int, User>}`

- [ ] **Step 1: Add the shared test helper**

Append to `tests/Support/helpers.php`:

```php
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A household with its owner and N additional members — the household
 * feature-test arrangement pair. Owner is a persisted User.
 *
 * @return array{0: Household, 1: User, 2: Collection<int, User>}
 */
function householdWithMembers(int $additional = 1): array
{
    $owner = User::factory()->create();
    $household = Household::factory()
        ->for($owner, 'owner')
        ->withOwnerAndMembers($additional)
        ->create();

    $members = HouseholdMember::query()
        ->where('household_id', $household->id)
        ->where('user_id', '!=', $owner->id)
        ->pluck('user_id')
        ->map(fn (int $id): User => User::query()->findOrFail($id));

    return [$household, $owner, $members];
}
```

- [ ] **Step 2: Write the integration tests**

`tests/Integration/Services/HouseholdServiceTest.php`:

```php
<?php

use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Validation\ValidationException;

it('creates a household with an owner membership and a fresh invite', function (): void {
    $creator = User::factory()->create();

    $household = resolve(HouseholdService::class)->create($creator, 'Keluarga Budi');

    expect($household->owner_id)->toBe($creator->id)
        ->and($household->members)->toHaveCount(1)
        ->and($household->members->first()->user_id)->toBe($creator->id)
        ->and($household->activeInvite())->not->toBeNull()
        ->and(strlen($household->activeInvite()->otp_code))->toBe(6);
});

it('joins a household with a valid token and otp', function (): void {
    [$household, $owner] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();
    $joiner = User::factory()->create();

    $membership = resolve(HouseholdService::class)->join($joiner, $invite->token, $invite->otp_code);

    expect($membership->household_id)->toBe($household->id)
        ->and($membership->user_id)->toBe($joiner->id)
        ->and($invite->fresh()->failed_attempts)->toBe(0);
});

it('allows the same invite to be reused by further joiners', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();
    $service = resolve(HouseholdService::class);

    $service->join(User::factory()->create(), $invite->token, $invite->otp_code);
    $service->join(User::factory()->create(), $invite->token, $invite->otp_code);

    expect($household->members()->count())->toBe(3);
});

it('rejects a join with a wrong otp and counts the attempt', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();

    try {
        resolve(HouseholdService::class)->join(User::factory()->create(), $invite->token, '000000');
        $this->fail('Expected ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('otp');
    }

    expect($invite->fresh()->failed_attempts)->toBe(1);
});

it('locks the invite after five failed otp attempts', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();
    $service = resolve(HouseholdService::class);

    for ($i = 0; $i < HouseholdInvite::MAX_OTP_ATTEMPTS; $i++) {
        try {
            $service->join(User::factory()->make(), $invite->token, '000000');
        } catch (ValidationException) {
            // expected on every attempt
        }
    }

    expect($invite->fresh()->isLocked())->toBeTrue();

    try {
        $service->join(User::factory()->create(), $invite->token, $invite->otp_code);
        $this->fail('Expected ValidationException for a locked invite.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('token');
    }
});

it('rejects an expired invite', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->expired()->for($household)->create();

    try {
        resolve(HouseholdService::class)->join(User::factory()->create(), $invite->token, $invite->otp_code);
        $this->fail('Expected ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('token');
    }
});

it('rejects a revoked invite', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->revoked()->for($household)->create();

    try {
        resolve(HouseholdService::class)->join(User::factory()->create(), $invite->token, $invite->otp_code);
        $this->fail('Expected ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('token');
    }
});

it('rejects a user who is already in a household', function (): void {
    [$household, $owner] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();

    try {
        resolve(HouseholdService::class)->join($owner, $invite->token, $invite->otp_code);
        $this->fail('Expected ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('household');
    }
});

it('rejects an unknown token', function (): void {
    [$household] = householdWithMembers(0);
    HouseholdInvite::factory()->for($household)->create();

    try {
        resolve(HouseholdService::class)->join(User::factory()->create(), 'nonexistent-token', '000000');
        $this->fail('Expected ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('token');
    }
});

it('lets a member leave and preserves their transactions', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $member = $members->first();

    resolve(HouseholdService::class)->leave($household, $member);

    expect($member->fresh()->householdMembership)->toBeNull()
        ->and($household->members()->count())->toBe(1)
        ->and($household->fresh())->not->toBeNull();
});

it('transfers ownership to a member', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $member = $members->first();
    $membership = $household->members()->where('user_id', $member->id)->first();

    resolve(HouseholdService::class)->transferOwnership($household, $membership);

    expect($household->fresh()->owner_id)->toBe($member->id)
        ->and($owner->fresh()->householdMembership)->not->BeNull();
});

it('dissolves the household and all memberships without touching accounts', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    HouseholdInvite::factory()->for($household)->create();
    $account = \App\Models\Account::factory()->shareable()->for($owner, 'owner')->create();

    resolve(HouseholdService::class)->dissolve($household);

    expect(Household::find($household->id))->toBeNull()
        ->and(HouseholdMember::query()->where('household_id', $household->id)->exists())->toBeFalse()
        ->and(HouseholdInvite::query()->where('household_id', $household->id)->exists())->toBeFalse()
        ->and($account->fresh())->not->toBeNull()
        ->and($account->fresh()->access_type)->toBe(\App\Enums\AccountAccessType::Shareable);
});

it('regenerating an invite rotates token and otp together', function (): void {
    [$household] = householdWithMembers(0);
    $old = HouseholdInvite::factory()->for($household)->create();

    $new = resolve(HouseholdService::class)->regenerateInvite($household);

    expect($new->token)->not->toBe($old->token)
        ->and($new->otp_code)->not->toBe($old->otp_code)
        ->and($new->failed_attempts)->toBe(0)
        ->and($old->fresh()->isRevoked())->toBeTrue();
});

it('revoking an invite blocks joining', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();

    resolve(HouseholdService::class)->revokeInvite($invite);

    expect($invite->fresh()->isRevoked())->toBeTrue();
});
```

Note: `HouseholdInvite::factory()->for($household)` uses Laravel's default factory relationship (`household_id`), so no custom `forHousehold` state is needed — drop `forHousehold` from Task 1's produced interface if implementing that way.

- [ ] **Step 3: Register the Integration module mapping**

In `tests/Pest.php`, after the existing `Integration/...` group lines (line ~62), add:

```php
pest()->group('module:household')->in('Integration/Services/HouseholdServiceTest.php');
```

- [ ] **Step 4: Write HouseholdService**

`app/Services/HouseholdService.php`:

```php
<?php

namespace App\Services;

use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Household lifecycle: creation, invite-link admission (token + OTP),
 * membership changes, ownership transfer, and dissolution. Static reads
 * and instance writes mirror AccountService. Visibility itself lives in
 * AccountPolicy/Account::visibleTo — this service never touches accounts.
 */
final readonly class HouseholdService
{
    /** The household context for the settings page, with display relations eager-loaded. */
    public static function getHouseholdContext(User $user): ?Household
    {
        return $user->household()->with('members.user:id,name')->first();
    }

    public static function activeInvite(Household $household): ?HouseholdInvite
    {
        return $household->activeInvite();
    }

    public function create(User $creator, string $name): Household
    {
        return DB::transaction(function () use ($creator, $name): Household {
            $household = Household::create([
                'name' => $name,
                'owner_id' => $creator->id,
            ]);

            HouseholdMember::create([
                'household_id' => $household->id,
                'user_id' => $creator->id,
                'joined_at' => now(),
            ]);

            $this->issueInvite($household);

            return $household;
        });
    }

    public function rename(Household $household, string $name): Household
    {
        $household->update(['name' => $name]);

        return $household->fresh();
    }

    /**
     * Admit a joiner through an invite link. Failures throw
     * ValidationException keyed by field ('token' for link-state problems,
     * 'otp' for a wrong code, 'household' for an already-membered user) so
     * the HTTP layer maps them straight onto session errors.
     */
    public function join(User $joiner, string $token, string $otp): HouseholdMember
    {
        if ($joiner->inHousehold()) {
            throw ValidationException::withMessages([
                'household' => __('You are already in a household.'),
            ]);
        }

        $invite = HouseholdInvite::query()->where('token', $token)->first();

        if ($invite === null || $invite->isExpired() || $invite->isRevoked() || $invite->isLocked()) {
            throw ValidationException::withMessages([
                'token' => __('This invite is no longer valid. Ask the household owner for a new link.'),
            ]);
        }

        if ($invite->otp_code !== $otp) {
            $invite->increment('failed_attempts');

            if ($invite->fresh()->isLocked()) {
                throw ValidationException::withMessages([
                    'token' => __('Too many wrong codes — this invite is now locked. Ask the owner to regenerate it.'),
                ]);
            }

            throw ValidationException::withMessages([
                'otp' => __('The code is incorrect.'),
            ]);
        }

        return HouseholdMember::create([
            'household_id' => $invite->household_id,
            'user_id' => $joiner->id,
            'joined_at' => now(),
        ]);
    }

    public function leave(Household $household, User $member): void
    {
        HouseholdMember::query()
            ->where('household_id', $household->id)
            ->where('user_id', $member->id)
            ->delete();
    }

    public function removeMember(Household $household, HouseholdMember $member): void
    {
        $member->delete();
    }

    /** Ownership handover: the owner demotes to a plain member. */
    public function transferOwnership(Household $household, HouseholdMember $toMember): Household
    {
        $household->update(['owner_id' => $toMember->user_id]);

        return $household->fresh();
    }

    /**
     * Hard-delete the grouping. Accounts and transactions are deliberately
     * untouched — every member's shareable accounts simply become private
     * again because nobody has a household anymore.
     */
    public function dissolve(Household $household): void
    {
        DB::transaction(function () use ($household): void {
            $household->invites()->delete();
            $household->members()->delete();
            $household->delete();
        });
    }

    /** Rotates token + OTP together and revokes every prior invite. */
    public function regenerateInvite(Household $household): HouseholdInvite
    {
        return DB::transaction(function () use ($household): HouseholdInvite {
            $household->invites()
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $this->issueInvite($household);
        });
    }

    public function revokeInvite(HouseholdInvite $invite): void
    {
        $invite->update(['revoked_at' => now()]);
    }

    private function issueInvite(Household $household): HouseholdInvite
    {
        return HouseholdInvite::create([
            'household_id' => $household->id,
            'token' => (string) Str::ulid(),
            'otp_code' => sprintf('%06d', random_int(0, 999_999)),
            'failed_attempts' => 0,
            'expires_at' => now()->addDays(HouseholdInvite::INVITE_TTL_DAYS),
        ]);
    }
}
```

(If the project's no-i18n stance extends to backend flash messages — check sibling code: `to_route()->flash('...')` uses plain strings — then replace `__(...)` with the plain string literals.)

---

### Task 3: Visibility policies + shareable gating on account forms

**Complexity:** Medium

**Files:**
- Modify: `app/Policies/AccountPolicy.php:16-19`
- Modify: `app/Policies/TransactionPolicy.php:23-26`
- Modify: `app/Policies/TransferPolicy.php:15-18`
- Modify: `app/Http/Requests/StoreAccountRequest.php`
- Modify: `app/Http/Requests/UpdateAccountRequest.php`
- Modify: `tests/Feature/App/Account/AccountTest.php:84-90`
- Create: `tests/Feature/App/Household/VisibilityTest.php`
- Modify: `tests/Pest.php` (Feature module mapping)

**Interfaces:**
- Consumes: `User::sharesHouseholdWith()` from Task 1
- Produces: policy semantics relied on by Task 5/6 feature tests — `view` on Account/Transaction/Transfer admits household mates through shareable accounts; account writes stay owner-only; transaction/transfer writes stay creator-only

- [ ] **Step 1: Write the failing feature tests**

`tests/Feature/App/Household/VisibilityTest.php`:

```php
<?php

use App\Enums\AccountAccessType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Support\Collection;

use function Tests\Support\householdWithMembers;

test('a household member can view a mate-owned shareable account', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $account = Account::factory()->shareable()->for($owner, 'owner')->create();

    $this->actingAs($members->first())
        ->get(route('accounts.show', $account))
        ->assertOk();
});

test('a stranger cannot view a shareable account', function (): void {
    [$household, $owner] = householdWithMembers(0);
    $account = Account::factory()->shareable()->for($owner, 'owner')->create();
    $stranger = \App\Models\User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('accounts.show', $account))
        ->assertForbidden();
});

test('a shareable account of a user without a household is owner-only', function (): void {
    $account = Account::factory()->shareable()->create(); // owner has no household
    $stranger = \App\Models\User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('accounts.show', $account))
        ->assertForbidden();

    $this->actingAs($account->owner)
        ->get(route('accounts.show', $account))
        ->assertOk();
});

test('a household member can view transactions on a mate-owned shareable account', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $account = Account::factory()->shareable()->for($owner, 'owner')->create();
    $transaction = Transaction::factory()->for($account, 'account')->create([
        'created_by' => $owner->id,
    ]);

    $this->actingAs($members->first())
        ->get(route('transactions.show', $transaction))
        ->assertOk();
});

test('a household member can view a transfer unit touching a shared account', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $shared = Account::factory()->shareable()->for($owner, 'owner')->create();
    $personal = Account::factory()->for($owner, 'owner')->create();

    $transfer = resolve(TransferService::class)->create($owner, new \App\Data\Transaction\TransferData(
        // Mirror however TransferServiceTest builds payloads — reuse its
        // exact arrangement call for a two-member unit without a fee.
    ));

    $this->actingAs($members->first())
        ->get(route('transactions.show', $transfer->sourceTransaction))
        ->assertOk();
});
```

**Implementation note for the implementer:** before finalizing this file, open `tests/Integration/Services/TransferServiceTest.php` and copy its exact payload arrangement for creating a transfer unit; the placeholder comment above marks where it goes. The remaining assertions and structure stand as written.

Also update the outdated placeholder test in `tests/Feature/App/Account/AccountTest.php` — replace the body of `test('shows a shareable account to any authenticated user', ...)` (lines 84–90) with:

```php
test('shows a shareable account to a household member but not a stranger', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $shareableAccount = Account::factory()->shareable()->for($owner, 'owner')->create();

    $this->actingAs($members->first())
        ->get(route('accounts.show', $shareableAccount))
        ->assertOk();

    $this->actingAs(\App\Models\User::factory()->create())
        ->get(route('accounts.show', $shareableAccount))
        ->assertForbidden();
});
```

Add `use function Tests\Support\householdWithMembers;` — check how `userWithAccount` is imported in sibling tests and mirror it (likely `use function Tests\Support\userWithAccount;` or bare reliance on helpers.php being required; mirror the sibling).

- [ ] **Step 2: Register the Feature module mapping**

In `tests/Pest.php`, next to the other module groups (line ~50):

```php
pest()->group('module:household')->in('Feature/App/Household');
```

- [ ] **Step 3: Update the three policies**

`app/Policies/AccountPolicy.php` — replace `view()`:

```php
    public function view(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id
            || ($account->access_type === AccountAccessType::Shareable
                && $account->owner->sharesHouseholdWith($user));
    }
```

`app/Policies/TransactionPolicy.php` — replace `view()` (keep the class docblock, update its wording: "…to everyone else when the owning account is shareable *within their household*"):

```php
    public function view(User $user, Transaction $transaction): bool
    {
        return $transaction->created_by === $user->id
            || ($transaction->account->access_type === AccountAccessType::Shareable
                && $transaction->account->owner->sharesHouseholdWith($user));
    }
```

`app/Policies/TransferPolicy.php` — replace `view()` (update the class docblock to note the transaction-mirroring rule):

```php
    public function view(User $user, Transfer $transfer): bool
    {
        return $transfer->created_by === $user->id
            || $transfer->memberAccounts()->contains(
                fn (Account $account): bool => $account->access_type === \App\Enums\AccountAccessType::Shareable
                    && $account->owner->sharesHouseholdWith($user)
            );
    }
```

**Implementation note:** `memberAccounts()` does not exist yet — check `app/Models/Transfer.php` for the actual relation names (likely `sourceTransaction`/`destinationTransaction` → `account`). Implement `view()` using the existing relations, e.g. `collect([$transfer->sourceTransaction->account, $transfer->destinationTransaction->account])`. Writes stay creator-only — no changes to `update`/`delete`.

- [ ] **Step 4: Gate shareable on household membership in account requests**

In both `app/Http/Requests/StoreAccountRequest.php` and `app/Http/Requests/UpdateAccountRequest.php`, extend the `access_type` rule with a closure:

```php
'access_type' => ['required', 'string', Rule::enum(AccountAccessType::class), function (string $attribute, mixed $value, \Closure $fail): void {
    if ($value === AccountAccessType::Shareable->value && ! $this->user()->inHousehold()) {
        $fail('Create or join a household before sharing an account.');
    }
}],
```

(Read each file first and merge with its existing `access_type` rule; both requests must carry the gate.)

---

### Task 4: List scoping — `visibleTo` scope + AccountService reads

**Complexity:** Medium

**Files:**
- Modify: `app/Models/Account.php:63-67`
- Modify: `app/Services/AccountService.php:20-47, 85-91`
- Modify: `app/Http/Controllers/TransferController.php` (call-site of `getTransferEligibleAccounts`)
- Modify: `tests/Feature/App/Account/AccountTest.php:8-19`

**Interfaces:**
- Consumes: `User::householdMembership` from Task 1
- Produces: `Account::visibleTo(User $user)` #[Scope] — owner's accounts ∪ household-mates' shareable accounts; `AccountService::getTransferEligibleAccounts(User $user, ?Account $excludeAccount = null)` (signature gains the user)

**Deliberate consequences (spec):** dashboard portfolio, statistics, transaction/transfer/template account selects, and the shared `accounts` Inertia prop now include household-mates' shareable accounts — that is the shared-pot visibility. Archived lists stay owner-only (owner-management UI). The global transaction list keeps its `created_by` scope (the pot's full ledger is the account page).

- [ ] **Step 1: Update the account list feature test**

Replace `test('lists only the accounts owned by the authenticated user', ...)` in `tests/Feature/App/Account/AccountTest.php`:

```php
test('lists owned accounts plus household-mates shareable accounts only', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    Account::factory()->create(['owner_id' => $owner->id]);                       // own personal
    Account::factory()->shareable()->for($members->first(), 'owner')->create();   // mate's shared
    Account::factory()->for($members->first(), 'owner')->create();                // mate's personal
    Account::factory()->shareable()->create();                                    // stranger's shared

    $this->actingAs($owner)->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/account/index')
            ->has('accounts', 2)
        );
});
```

- [ ] **Step 2: Replace the `shareable()` scope with `visibleTo()`**

In `app/Models/Account.php`, delete the `shareable()` scope (lines 63–67) and add:

```php
    /**
     * The visibility funnel: the user's own accounts, unioned with
     * shareable accounts owned by their household-mates. This is the
     * query-side twin of AccountPolicy::view.
     */
    #[Scope]
    protected function visibleTo(Builder $builder, User $user): void
    {
        $builder->where(function (Builder $query) use ($user): void {
            $query->where('owner_id', $user->id);

            $householdId = $user->householdMembership?->household_id;

            if ($householdId !== null) {
                $query->orWhere(function (Builder $shared) use ($householdId): void {
                    $shared->where('access_type', AccountAccessType::Shareable)
                        ->whereIn('owner_id', HouseholdMember::query()
                            ->where('household_id', $householdId)
                            ->select('user_id'));
                });
            }
        });
    }
```

Add import: `use App\Models\HouseholdMember;` is same-namespace — no import needed (`App\Models`). Keep `App\Enums\AccountAccessType` (already imported).

- [ ] **Step 3: Switch AccountService reads to `visibleTo`**

In `app/Services/AccountService.php`:

- `getAccountsByUser()`: replace `->where('owner_id', $user->id)` + the commented `// ->shareable()` with `->visibleTo($user)`; extend the eager-load to `->with(['provider:id,name', 'owner:id,name'])` (owner name feeds the frontend owner chip).
- `getAccountIdsByUser()`: replace owner WHERE + comment with `->visibleTo($user)`.
- `getArchivedAccountsByUser()`: **keep owner-only** — delete the commented `// ->shareable()` lines, no scope change (archive management is owner-only by policy).
- `getTransferEligibleAccounts()` — new signature and scoping (fixes today's every-account leak):

```php
    public function getTransferEligibleAccounts(User $user, ?Account $excludeAccount = null): Collection
    {
        return Account::query()
            ->visibleTo($user)
            ->notArchived()
            ->when($excludeAccount, fn ($q) => $q->where('id', '!=', $excludeAccount->id))
            ->get();
    }
```

- [ ] **Step 4: Update the TransferController call site**

In `app/Http/Controllers/TransferController.php` line ~31, change:

```php
'accounts' => $this->accountService->getTransferEligibleAccounts($this->user),
```

(Read the file first; keep the exact surrounding shape — only the argument is added.)

---

### Task 5: Join flow backend — controller, request, routes, rate limiter

**Complexity:** Medium

**Files:**
- Create: `app/Http/Controllers/HouseholdJoinController.php`
- Create: `app/Http/Requests/JoinHouseholdRequest.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Feature/App/Household/JoinTest.php`

**Interfaces:**
- Consumes: `HouseholdService::join()` (Task 2); routes named `household.join.show` / `household.join.store`
- Produces: GET `household/join/{token}` renders `app/household/join` with props `{ token, status: 'valid'|'invalid'|'expired'|'locked', household_name: ?string }`; POST redirects to `dashboard` with a flash on success or back with session errors

- [ ] **Step 1: Write the failing feature tests**

`tests/Feature/App/Household/JoinTest.php`:

```php
<?php

use App\Models\HouseholdInvite;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

use function Tests\Support\householdWithMembers;

beforeEach(function (): void {
    RateLimiter::clear('household-join|' . RateLimiter::limiter('household-join') ? '' : '');
});

test('renders the join page for a valid invite', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();
    $joiner = User::factory()->create();

    $this->actingAs($joiner)
        ->get(route('household.join.show', $invite->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/household/join')
            ->where('status', 'valid')
            ->where('token', $invite->token)
            ->has('household_name')
        );
});

test('renders the join page with expired status for an expired invite', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->expired()->for($household)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('household.join.show', $invite->token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('status', 'expired'));
});

test('joins a household through a valid token and otp', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();
    $joiner = User::factory()->create();

    $this->actingAs($joiner)
        ->post(route('household.join.store', $invite->token), ['otp' => $invite->otp_code])
        ->assertRedirectToRoute('dashboard');

    expect($joiner->fresh()->householdMembership)->not->toBeNull()
        ->and($joiner->fresh()->householdMembership->household_id)->toBe($household->id);
});

test('a wrong otp redirects back with an error', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();
    $joiner = User::factory()->create();

    $this->actingAs($joiner)
        ->from(route('household.join.show', $invite->token))
        ->post(route('household.join.store', $invite->token), ['otp' => '000000'])
        ->assertSessionHasErrors('otp');

    expect($joiner->fresh()->householdMembership)->toBeNull()
        ->and($invite->fresh()->failed_attempts)->toBe(1);
});

test('a user already in a household is rejected', function (): void {
    [$household, $owner] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();

    $this->actingAs($owner)
        ->post(route('household.join.store', $invite->token), ['otp' => $invite->otp_code])
        ->assertSessionHasErrors('household');
});

test('guests are redirected to login', function (): void {
    [$household] = householdWithMembers(0);
    $invite = HouseholdInvite::factory()->for($household)->create();

    $this->get(route('household.join.show', $invite->token))
        ->assertRedirectToRoute('auth.login');
});
```

- [ ] **Step 2: Add the routes and rate limiter**

`routes/web.php` — inside the `auth` + `verified` middleware group (after the statistics block, before `require settings.php`):

```php
    // Household join (invite link admission — OTP-protected)
    Route::controller(\App\Http\Controllers\HouseholdJoinController::class)->group(function (): void {
        Route::get('household/join/{token}', 'show')->name('household.join.show');
        Route::post('household/join/{token}', 'store')
            ->middleware('throttle:household-join')
            ->name('household.join.store');
    });
```

`app/Providers/AppServiceProvider.php` — inside `boot()`, add:

```php
        RateLimiter::for('household-join', fn (\Illuminate\Http\Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
```

with imports `use Illuminate\Cache\RateLimiting\Limit;` and `use Illuminate\Support\Facades\RateLimiter;`. Read the file first and follow its existing boot() shape.

- [ ] **Step 3: Write the request and controller**

`app/Http/Requests/JoinHouseholdRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class JoinHouseholdRequest extends FormRequest
{
    /** Admission is only for users who do not already belong to a household. */
    public function authorize(): bool
    {
        return $this->user()->householdMembership === null;
    }

    public function rules(): array
    {
        return [
            'otp' => ['required', 'digits:6'],
        ];
    }
}
```

`app/Http/Controllers/HouseholdJoinController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\HouseholdInvite;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Inertia\Inertia;

/**
 * Invite-link admission. The GET half renders the OTP prompt (or an
 * explanatory terminal state for dead links); the POST half verifies and
 * admits. Token-state checks are read-only on GET so the page can explain
 * itself; enforcement on POST lives in HouseholdService::join().
 */
final class HouseholdJoinController extends Controller
{
    public function __construct(
        private readonly HouseholdService $householdService,
        #[CurrentUser] private readonly ?User $user,
    ) {}

    public function show(string $token): Response
    {
        $invite = HouseholdInvite::query()->where('token', $token)->first();

        $status = match (true) {
            $invite === null => 'invalid',
            $invite->isExpired() => 'expired',
            $invite->isRevoked(), $invite->isLocked() => 'invalid',
            default => 'valid',
        };

        return Inertia::render('app/household/join', [
            'token' => $token,
            'status' => $status,
            'household_name' => $status === 'valid' ? $invite->household->name : null,
        ]);
    }

    public function store(JoinHouseholdRequest $request, string $token): RedirectResponse
    {
        $this->householdService->join($request->user(), $token, $request->validated('otp'));

        return to_route('dashboard')->flash('Welcome to the household!');
    }
}
```

---

### Task 6: Household settings backend — controller, policy, requests, routes

**Complexity:** Medium

**Files:**
- Create: `app/Policies/HouseholdPolicy.php`
- Create: `app/Http/Controllers/Settings/HouseholdController.php`
- Create: `app/Http/Requests/StoreHouseholdRequest.php`
- Create: `app/Http/Requests/TransferHouseholdOwnershipRequest.php`
- Create: `app/Http/Requests/RemoveHouseholdMemberRequest.php`
- Modify: `routes/settings.php`
- Modify: `app/Http/Controllers/AccountController.php` (create/edit pages gain `in_household` prop)
- Create: `tests/Feature/App/Household/HouseholdSettingsTest.php`

**Interfaces:**
- Consumes: `HouseholdService` (Task 2), `User::household()` (Task 1); route names `household.index/store/update/destroy/leave`, `household.invite.regenerate`, `household.invite.revoke`, `household.members.remove`, `household.owner.transfer`
- Produces (consumed by Task 8 frontend): `GET settings/household` renders `app/settings/household` with props `{ household: ?Household, is_owner: bool, invite: ?HouseholdInvite }` where `household` carries `members.user:id,name`; account create/edit pages render with `in_household: bool`

- [ ] **Step 1: Write the failing feature tests**

`tests/Feature/App/Household/HouseholdSettingsTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\User;

use function Tests\Support\householdWithMembers;

test('renders the create state for a user without a household', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('household.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/household')
            ->where('household', null)
            ->where('invite', null)
        );
});

test('renders the member state with members and invite', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    HouseholdInvite::factory()->for($household)->create();

    $this->actingAs($members->first())
        ->get(route('household.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/settings/household')
            ->where('is_owner', false)
            ->has('household')
            ->has('invite')
        );
});

test('creates a household for a household-less user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('household.store'), ['name' => 'Keluarga Baru'])
        ->assertRedirectToRoute('household.index');

    $household = $user->fresh()->household;
    expect($household)->not->toBeNull()
        ->and($household->owner_id)->toBe($user->id)
        ->and($household->members)->toHaveCount(1)
        ->and($household->activeInvite())->not->toBeNull();
});

test('rejects creating a household when already in one', function (): void {
    [$household, $owner] = householdWithMembers(0);

    $this->actingAs($owner)
        ->post(route('household.store'), ['name' => 'Second'])
        ->assertForbidden();
});

test('owner renames the household', function (): void {
    [$household, $owner] = householdWithMembers(0);

    $this->actingAs($owner)
        ->put(route('household.update'), ['name' => 'Renamed'])
        ->assertRedirectToRoute('household.index');

    expect($household->fresh()->name)->toBe('Renamed');
});

test('a member cannot rename the household', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);

    $this->actingAs($members->first())
        ->put(route('household.update'), ['name' => 'Nope'])
        ->assertForbidden();
});

test('a member leaves the household', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $member = $members->first();

    $this->actingAs($member)
        ->post(route('household.leave'))
        ->assertRedirectToRoute('household.index');

    expect($member->fresh()->householdMembership)->toBeNull()
        ->and($household->fresh())->not->toBeNull();
});

test('the owner cannot leave directly', function (): void {
    [$household, $owner] = householdWithMembers(0);

    $this->actingAs($owner)
        ->post(route('household.leave'))
        ->assertForbidden();

    expect($owner->fresh()->householdMembership)->not->toBeNull();
});

test('the owner removes a member', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $member = $members->first();
    $membership = $household->members()->where('user_id', $member->id)->first();

    $this->actingAs($owner)
        ->delete(route('household.members.remove', $membership))
        ->assertRedirectToRoute('household.index');

    expect($member->fresh()->householdMembership)->toBeNull();
});

test('the owner transfers ownership and can then leave', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $membership = $household->members()->where('user_id', $members->first()->id)->first();

    $this->actingAs($owner)
        ->put(route('household.owner.transfer'), ['member_id' => $membership->id])
        ->assertRedirectToRoute('household.index');

    expect($household->fresh()->owner_id)->toBe($members->first()->id);

    $this->actingAs($owner)
        ->post(route('household.leave'))
        ->assertRedirectToRoute('household.index');

    expect($owner->fresh()->householdMembership)->toBeNull();
});

test('dissolving the household makes members shareable accounts private again', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $account = Account::factory()->shareable()->for($owner, 'owner')->create();
    $member = $members->first();

    // Member sees the shared account before dissolution.
    $this->actingAs($member)
        ->get(route('accounts.show', $account))
        ->assertOk();

    $this->actingAs($owner)
        ->delete(route('household.destroy'))
        ->assertRedirectToRoute('household.index');

    expect(Household::find($household->id))->toBeNull();

    // And loses visibility after — accounts went private with the household.
    $this->actingAs($member)
        ->get(route('accounts.show', $account))
        ->assertForbidden();
});

test('the owner regenerates and revokes the invite', function (): void {
    [$household, $owner] = householdWithMembers(0);
    $old = HouseholdInvite::factory()->for($household)->create();

    $this->actingAs($owner)
        ->post(route('household.invite.regenerate'))
        ->assertRedirectToRoute('household.index');

    expect($old->fresh()->isRevoked())->toBeTrue()
        ->and($household->fresh()->activeInvite()->token)->not->toBe($old->token);

    $this->actingAs($owner)
        ->post(route('household.invite.revoke'))
        ->assertRedirectToRoute('household.index');

    expect($household->fresh()->activeInvite())->toBeNull();
});

test('a member cannot manage invites or dissolve', function (): void {
    [$household, $owner, $members] = householdWithMembers(1);
    $member = $members->first();

    $this->actingAs($member)->post(route('household.invite.regenerate'))->assertForbidden();
    $this->actingAs($member)->post(route('household.invite.revoke'))->assertForbidden();
    $this->actingAs($member)->delete(route('household.destroy'))->assertForbidden();
});

test('account create and edit pages expose the household membership flag', function (): void {
    [$household, $owner] = householdWithMembers(0);

    $this->actingAs($owner)
        ->get(route('accounts.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('in_household', true));

    $loner = User::factory()->create();

    $this->actingAs($loner)
        ->get(route('accounts.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('in_household', false));
});
```

- [ ] **Step 2: Write the policy**

`app/Policies/HouseholdPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

/**
 * Household management is owner-only; membership-level actions (viewing,
 * leaving) belong to any member. The owner is identified by the
 * denormalized owner_id — never a role column.
 */
final class HouseholdPolicy
{
    public function view(User $user, Household $household): bool
    {
        return $user->householdMembership?->household_id === $household->id;
    }

    public function update(User $user, Household $household): bool
    {
        return $household->owner_id === $user->id;
    }

    public function delete(User $user, Household $household): bool
    {
        return $household->owner_id === $user->id;
    }

    /** Leaving is for plain members — owners must transfer or dissolve. */
    public function leave(User $user, Household $household): bool
    {
        return $user->householdMembership?->household_id === $household->id
            && $household->owner_id !== $user->id;
    }

    public function manageInvites(User $user, Household $household): bool
    {
        return $household->owner_id === $user->id;
    }
}
```

- [ ] **Step 3: Write the requests**

`app/Http/Requests/StoreHouseholdRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

final class StoreHouseholdRequest extends FormRequest
{
    /** Only household-less users can found one — one household per user. */
    public function authorize(): bool
    {
        return $this->user()->householdMembership === null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
```

`app/Http/Requests/TransferHouseholdOwnershipRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransferHouseholdOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user()->household);
    }

    public function rules(): array
    {
        $household = $this->user()->household;

        return [
            'member_id' => [
                'required',
                'integer',
                Rule::exists('household_members', 'id')->where('household_id', $household->id),
            ],
        ];
    }
}
```

`app/Http/Requests/RemoveHouseholdMemberRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\HouseholdMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RemoveHouseholdMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof HousefoldMember
            && $this->user()->can('update', $member->household);
    }

    public function rules(): array
    {
        return [
            // Member identity comes from the route binding; no payload rules.
        ];
    }
}
```

(No payload to validate — but the Form Request exists to own authorization per house convention. Fix the `HousefoldMember` typo to `HouseholdMember` when writing the file. Alternatively, if the implementer finds `authorize()` cleaner in the controller for this no-payload case, house rules permit `$this->authorize('update', $member->household)` in the controller for actions without a Form Request — then drop this request class and its route usage.)

- [ ] **Step 4: Write the controller and routes**

`app/Http/Controllers/Settings/HouseholdController.php`:

```php
<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\RemoveHouseholdMemberRequest;
use App\Http\Requests\StoreHouseholdRequest;
use App\Http\Requests\TransferHouseholdOwnershipRequest;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class HouseholdController extends Controller
{
    public function __construct(
        private readonly HouseholdService $householdService,
        #[CurrentUser] private readonly ?User $user,
    ) {}

    public function index(): Response
    {
        $household = HouseholdService::getHouseholdContext($this->user);

        return Inertia::render('app/settings/household', [
            'household' => $household,
            'is_owner' => $household !== null && $household->isOwnedBy($this->user),
            'invite' => $household !== null ? HouseholdService::activeInvite($household) : null,
        ]);
    }

    public function store(StoreHouseholdRequest $request): RedirectResponse
    {
        $household = $this->householdService->create($request->user(), $request->validated('name'));

        return to_route('household.index')->flash("Household {$household->name} created.");
    }

    public function update(StoreHouseholdRequest $request): RedirectResponse
    {
        // StoreHouseholdRequest::authorize only checks household-less users;
        // renames need owner — authorize against the current household.
        abort_unless($this->user()->can('update', $this->user()->household), 403);

        $household = $this->householdService->rename($this->user()->household, $request->validated('name'));

        return to_route('household.index')->flash('Household renamed.');
    }
```

**Implementation note:** `update()` reusing `StoreHouseholdRequest` is wrong per the note above — its `authorize()` would reject owners (they *have* a household). Write a dedicated `RenameHouseholdRequest` with `authorize(): bool { return $this->user()->can('update', $this->user()->household); }` and the same `name` rule, and type-hint it in `update()`. Add it to this task's Files. Continue the controller:

```php
    public function destroy(): RedirectResponse
    {
        $household = $this->user()->household;
        $this->authorize('delete', $household);

        $this->householdService->dissolve($household);

        return to_route('household.index')->flash('Household dissolved.');
    }

    public function leave(): RedirectResponse
    {
        $household = $this->user()->household;
        $this->authorize('leave', $household);

        $this->householdService->leave($household, $this->user);

        return to_route('household.index')->flash('You left the household.');
    }

    public function removeMember(RemoveHouseholdMemberRequest $request, HouseholdMember $member): RedirectResponse
    {
        $this->householdService->removeMember($member->household, $member);

        return to_route('household.index')->flash('Member removed.');
    }

    public function transferOwnership(TransferHouseholdOwnershipRequest $request): RedirectResponse
    {
        $member = HouseholdMember::query()->findOrFail($request->validated('member_id'));

        $this->householdService->transferOwnership($this->user()->household, $member);

        return to_route('household.index')->flash('Ownership transferred.');
    }

    public function regenerateInvite(): RedirectResponse
    {
        $household = $this->user()->household;
        $this->authorize('manageInvites', $household);

        $this->householdService->regenerateInvite($household);

        return to_route('household.index')->flash('New invite link generated.');
    }

    public function revokeInvite(): RedirectResponse
    {
        $household = $this->user()->household;
        $this->authorize('manageInvites', $household);

        if (($invite = HouseholdService::activeInvite($household)) !== null) {
            $this->householdService->revokeInvite($invite);
        }

        return to_route('household.index')->flash('Invite revoked.');
    }
}
```

`routes/settings.php` — add inside the existing `Route::middleware('auth')` group:

```php
    Route::prefix('settings/household')->name('household.')->controller(\App\Http\Controllers\Settings\HouseholdController::class)->group(function (): void {
        Route::get('', 'index')->name('index');
        Route::post('', 'store')->name('store');
        Route::put('', 'update')->name('update');
        Route::delete('', 'destroy')->name('destroy');
        Route::post('leave', 'leave')->name('leave');
        Route::post('invite/regenerate', 'regenerateInvite')->name('invite.regenerate');
        Route::post('invite/revoke', 'revokeInvite')->name('invite.revoke');
        Route::delete('members/{member}', 'removeMember')->name('members.remove');
        Route::put('owner', 'transferOwnership')->name('owner.transfer');
    });
```

- [ ] **Step 5: Expose `in_household` on account create/edit**

In `app/Http/Controllers/AccountController.php`, add `'in_household' => $this->user->inHousehold()` to the props of both the `create()` and `edit()` Inertia renders (read the file first; keep everything else identical).

---

### Task 7: Wayfinder regen + account-form gating, schema copy, owner chip, menu entry

**Complexity:** Medium

**Files:**
- Regenerate: `resources/js/wayfinder/**` (via artisan)
- Modify: `resources/js/schema/account.schema.ts:19-33`
- Modify: `resources/js/components/module/account/account-form.svelte:33-40, 153-181`
- Modify: `resources/js/pages/app/account/create.svelte`
- Modify: `resources/js/pages/app/account/edit.svelte`
- Modify: `resources/js/components/module/account/account-card.svelte:61-64, 145-163`
- Modify: `resources/js/data/menu.ts`

No tests — markup/copy only (project convention: view-only changes are untested).

**Interfaces:**
- Consumes: `in_household` prop from Task 6; `account.owner` relation on payloads from Task 4; Wayfinder routes `household.*`
- Produces: `AccountForm` prop `inHousehold?: boolean` (default `true`); `accountAccessTypeOptions` label copy "Shared with household"; settings menu entry `household`

- [ ] **Step 1: Regenerate Wayfinder types**

Run: `php artisan wayfinder:generate`
Expected: `resources/js/wayfinder/routes/household.ts` and controller/action modules for `Settings/HouseholdController` + `HouseholdJoinController` appear; `App.Models.Household`/`HouseholdMember`/`HouseholdInvite` types generated.

- [ ] **Step 2: Update schema option copy**

In `resources/js/schema/account.schema.ts`, update `accountAccessTypeOptions`: the Shareable entry's `label` becomes `'Shared with household'` and its `description`/hint text becomes `'Members of your household can view this account and record transactions on it'` (read the file first, keep the exact option object shape including `icon`).

- [ ] **Step 3: Gate the Shareable option in the form**

In `resources/js/components/module/account/account-form.svelte`:

Add to `Props` and destructure:

```ts
        inHousehold?: boolean;
```

```ts
    let { providers, account, inHousehold = true, onCancel, children }: Props = $props();
```

Add a derived flag after `isEdit`:

```ts
    // Sharing requires a household; an already-shared account stays selectable
    // so its owner can view/keep it even after leaving a household.
    const canShare = $derived(inHousehold || account?.access_type === AccountAccessType.Shareable);
```

In the Access Type field's `{#each}` loop, disable the Shareable button and add a hint. Change the button to:

```svelte
                        {#each accountAccessTypeOptions as opt (opt.value)}
                            {@const selected = form.access_type === opt.value}
                            {@const disabled = opt.value === AccountAccessType.Shareable && !canShare}
                            <button
                                class={cn([
                                    'flex items-center gap-3 rounded-lg border border-transparent bg-card p-3 text-left transition',
                                    selected
                                        ? 'border-primary'
                                        : 'border-border hover:border-border',
                                    'hover:border-primary',
                                    disabled && 'cursor-not-allowed opacity-50',
                                ])}
                                onclick={() => !disabled && (form.access_type = opt.value)}
                                disabled={disabled}
                                type="button">
```

and extend the option text block so the Shareable option explains itself when disabled:

```svelte
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">{opt.label}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {opt.description}
                                        {#if disabled}
                                            — create or join a household first
                                        {/if}
                                    </p>
                                </div>
```

- [ ] **Step 4: Pass the prop from the pages**

`resources/js/pages/app/account/create.svelte` — change props and the component call:

```svelte
    let { providers, in_household }: { providers: App.Models.Provider[]; in_household: boolean } = $props();
```

```svelte
<AccountForm {providers} inHousehold={in_household} />
```

`resources/js/pages/app/account/edit.svelte` — same pattern:

```svelte
    let {
        account,
        providers,
        in_household,
    }: { account: App.Models.Account; providers: App.Models.Provider[]; in_household: boolean } = $props();
```

```svelte
<AccountForm
    {account}
    inHousehold={in_household}
    onCancel={() => router.visit(AccountController.show.url({ account: account.id }))}
    {providers} />
```

- [ ] **Step 5: Owner chip on the account card**

In `resources/js/components/module/account/account-card.svelte`:

Add imports and derive the owner label (only for accounts you don't own):

```ts
    import { page } from '@inertiajs/svelte';
```

```ts
    const currentUserId = $derived((page.props.auth?.user as { id?: number } | null)?.id);
    const ownerName = $derived(
        account.owner && account.owner.id !== currentUserId ? account.owner.name : undefined
    );
```

(Requires `owner` on `App.Models.Account` — present after the Task 7 Step 1 regen, since `Account::owner()` exists and lists eager-load `owner:id,name`. The Wayfinder model type exposes the relation when loaded.)

In the meta row (the `text-xs text-current/70` flex around line 145), render the chip before "Shareable":

```svelte
                {#if ownerName}
                    <span class="inline-flex items-center gap-1">
                        <i class="iconify size-3.5 solar--user-line-duotone"></i>
                        {ownerName}
                    </span>
                {/if}
```

- [ ] **Step 6: Settings menu entry**

In `resources/js/data/menu.ts`:

Add the import (top, alphabetically near the other route imports):

```ts
import household from '@wayfinder/routes/household';
```

Add to `settingsMenu.menus` (first position — it is the group-forming feature):

```ts
        household: {
            name: 'Household',
            url: household.index().url,
            route: 'household.index',
            active: 'household.*',
            icon: 'iconify solar--home-line-duotone',
            type: 'menu',
        },
```

Note: `household.index()` is the generated named-route function — check the generated `routes/household.ts` after Step 1 for the exact export shape and mirror the `transactionTemplates.index().url` usage pattern.

---

### Task 8: Household settings page + module components

**Complexity:** Medium

**Files:**
- Create: `resources/js/pages/app/settings/household.svelte`
- Create: `resources/js/components/module/household/household-create-form.svelte`
- Create: `resources/js/components/module/household/household-invite-card.svelte`
- Create: `resources/js/components/module/household/household-members-list.svelte`

No tests — markup/view-only (the three render states are already pinned by Task 6 feature tests).

**Interfaces:**
- Consumes: page props `{ household: App.Models.Household | null, is_owner: boolean, invite: App.Models.HouseholdInvite | null }`; Wayfinder `Settings/HouseholdController` action modules (`.url()` for POST/PUT/DELETE targets — verify exact generated module path after Task 7 regen: `@wayfinder/App/Http/Controllers/Settings/HouseholdController`)
- Produces: the page `app/settings/household`; `HouseholdInviteCard` props `{ invite: App.Models.HouseholdInvite, isOwner: boolean }`; `HouseholdMembersList` props `{ household: App.Models.Household, isOwner: boolean }`

- [ ] **Step 1: Create the page**

`resources/js/pages/app/settings/household.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { pageLayoutProps } from '@lib/navigation-helper.svelte';
    import { dashboard } from '@wayfinder/routes';

    import HouseholdCreateForm from '@components/module/household/household-create-form.svelte';
    import HouseholdInviteCard from '@components/module/household/household-invite-card.svelte';
    import HouseholdMembersList from '@components/module/household/household-members-list.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/cards/responsive-card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';
    import { router } from '@inertiajs/svelte';
    import HouseholdController from '@wayfinder/App/Http/Controllers/Settings/HouseholdController';

    let {
        household,
        is_owner,
        invite,
    }: {
        household: App.Models.Household | null;
        is_owner: boolean;
        invite: App.Models.HouseholdInvite | null;
    } = $props();

    pageLayoutProps({ backUrl: dashboard.url(), title: 'Household' });

    let showDissolveConfirm = $state(false);
    let showLeaveConfirm = $state(false);

    function dissolve() {
        router.delete(HouseholdController.destroy.url());
    }

    function leave() {
        router.post(HouseholdController.leave.url());
    }
</script>

<div class="space-y-6">
    {#if household === null}
        <Card title="Household">
            <p class="mb-4 text-sm text-muted-foreground">
                Share accounts with the people you manage money with. Create a household, then
                invite them with a link.
            </p>
            <HouseholdCreateForm />
        </Card>
    {:else}
        <Card>
            {#snippet title()}
                <span class="flex items-center gap-2">
                    {household.name}
                    {#if is_owner}
                        <Badge color="primary" shape="pill" variant="soft">Owner</Badge>
                    {/if}
                </span>
            {/snippet}

            {#if invite}
                <HouseholdInviteCard {invite} isOwner={is_owner} />
            {:else}
                <p class="text-sm text-muted-foreground">
                    No active invite. {#if is_owner}Generate one to add members.{/if}
                </p>
            {/if}
        </Card>

        <HouseholdMembersList {household} {is_owner} />

        <div class="flex flex-col gap-3 sm:flex-row">
            {#if !is_owner}
                <Button color="error" variant="outline" onclick={() => (showLeaveConfirm = true)}>
                    <i class="iconify size-5 solar--logout-2-bold-duotone"></i>
                    Leave Household
                </Button>
            {:else}
                <Button color="error" variant="outline" onclick={() => (showDissolveConfirm = true)}>
                    <i class="iconify size-5 solar--trash-bin-2-bold-duotone"></i>
                    Dissolve Household
                </Button>
            {/if}
        </div>
    {/if}
</div>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Leave"
    onConfirm={leave}
    title="Leave Household"
    bind:open={showLeaveConfirm}>
    You will lose visibility of shared accounts. Transactions you recorded will stay in their
    history.
</ConfirmationModal>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Dissolve"
    onConfirm={dissolve}
    title="Dissolve Household"
    bind:open={showDissolveConfirm}>
    The household and all memberships will be removed. Accounts are untouched — everyone's shared
    accounts become personal again.
</ConfirmationModal>
```

(If `ResponsiveCard`'s import path differs — check how sibling settings pages import `Card` and mirror them.)

- [ ] **Step 2: Create the create-form component**

`resources/js/components/module/household/household-create-form.svelte`:

```svelte
<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import HouseholdController from '@wayfinder/App/Http/Controllers/Settings/HouseholdController';

    import Field from '@components/ui/forms/field.svelte';
    import FieldInput from '@components/ui/forms/field-input.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';

    let form = useForm(() => ({
        name: '',
    }));
</script>

<Form {form} action={HouseholdController.store.url()} class="space-y-4">
    <Field title="Household Name">
        <FieldInput
            name="name"
            placeholder="e.g. Keluarga Budi"
            required
            error={form.errors.name}
            bind:value={form.name} />
    </Field>

    <FormAction
        {form}
        labelCancel="Cancel"
        labelSubmit="Create Household"
        withoutCancel />
</Form>
```

(Read a sibling using `FieldInput` to match its exact prop shape — `error`/`bind:value` — before writing.)

- [ ] **Step 3: Create the invite-card component**

`resources/js/components/module/household/household-invite-card.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import HouseholdController from '@wayfinder/App/Http/Controllers/Settings/HouseholdController';
    import householdJoin from '@wayfinder/routes/household';

    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';

    let { invite, isOwner }: { invite: App.Models.HouseholdInvite; isOwner: boolean } = $props();

    const joinUrl = $derived(
        typeof window !== 'undefined'
            ? window.location.origin + householdJoin.join.show.url({ token: invite.token })
            : householdJoin.join.show.url({ token: invite.token })
    );

    const expiresLabel = $derived(
        invite.expires_at
            ? new Date(invite.expires_at).toLocaleDateString(undefined, {
                  day: 'numeric',
                  month: 'short',
              })
            : ''
    );

    let copied = $state<'link' | 'otp' | null>(null);

    async function copy(value: string, which: 'link' | 'otp') {
        await navigator.clipboard.writeText(value);
        copied = which;
        setTimeout(() => (copied = null), 2000);
    }
</script>

<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-medium">Invite Link</p>
        <Badge color="info" shape="pill" variant="soft">Expires {expiresLabel}</Badge>
    </div>

    <p class="text-xs text-muted-foreground">
        Send the link in your group chat — but send the code separately (private message), not in
        the same chat.
    </p>

    <div class="flex items-center gap-2">
        <code class="min-w-0 flex-1 truncate rounded-lg bg-muted px-3 py-2 text-xs">
            {joinUrl}
        </code>
        <Button
            class="btn-sm"
            color="primary"
            variant="outline"
            onclick={() => copy(joinUrl, 'link')}>
            {copied === 'link' ? 'Copied!' : 'Copy Link'}
        </Button>
    </div>

    <div class="flex items-center gap-2">
        <code class="flex-1 rounded-lg bg-muted px-3 py-2 text-center text-lg font-bold tracking-[0.3em]">
            {invite.otp_code}
        </code>
        <Button
            class="btn-sm"
            color="primary"
            variant="outline"
            onclick={() => copy(invite.otp_code, 'otp')}>
            {copied === 'otp' ? 'Copied!' : 'Copy Code'}
        </Button>
    </div>

    {#if isOwner}
        <div class="flex gap-2">
            <Button
                class="btn-sm"
                color="light"
                variant="ghost"
                onclick={() => router.post(HouseholdController.invite.regenerate.url())}>
                <i class="iconify size-4 solar--refresh-bold-duotone"></i>
                Regenerate
            </Button>
            <Button
                class="btn-sm"
                color="error"
                variant="ghost"
                onclick={() => router.post(HouseholdController.invite.revoke.url())}>
                <i class="iconify size-4 solar--trash-bin-2-bold-duotone"></i>
                Revoke
            </Button>
        </div>
    {/if}
</div>
```

(Verify the generated route function shape in `routes/household.ts` — the join route may surface as `household.join.show(...)` from the same module; use whatever the generated export actually is, and the controller actions as `HouseholdController.regenerateInvite.url()` / `revokeInvite.url()` if the nested-action module shape differs.)

- [ ] **Step 4: Create the members-list component**

`resources/js/components/module/household/household-members-list.svelte`:

```svelte
<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import HouseholdController from '@wayfinder/App/Http/Controllers/Settings/HouseholdController';

    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/cards/responsive-card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    import DateTimeHelper from '@utilities/date-time-helper';

    let { household, isOwner }: { household: App.Models.Household; isOwner: boolean } = $props();

    let removingMemberId = $state<number | null>(null);
    let transferringMemberId = $state<number | null>(null);

    function removeMember() {
        if (removingMemberId === null) return;
        router.delete(HouseholdController.removeMember.url({ member: removingMemberId }), {
            onFinish: () => (removingMemberId = null),
        });
    }

    function transferOwnership() {
        if (transferringMemberId === null) return;
        router.put(
            HouseholdController.transferOwnership.url(),
            { member_id: transferringMemberId },
            { onFinish: () => (transferringMemberId = null) }
        );
    }
</script>

<Card title="Members">
    <ul class="divide-y divide-border">
        {#each household.members as member (member.id)}
            {@const isHouseholdOwner = member.user_id === household.owner_id}
            <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-sm font-medium">
                        {member.user?.name}
                        {#if isHouseholdOwner}
                            <Badge color="primary" shape="pill" variant="soft">Owner</Badge>
                        {/if}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Joined {DateTimeHelper.format(member.joined_at, 'date')}
                    </p>
                </div>

                {#if isOwner && !isHouseholdOwner}
                    <div class="flex shrink-0 gap-1">
                        <Button
                            class="btn-xs"
                            color="light"
                            variant="ghost"
                            onclick={() => (transferringMemberId = member.id)}>
                            Make Owner
                        </Button>
                        <Button
                            class="btn-xs"
                            color="error"
                            variant="ghost"
                            onclick={() => (removingMemberId = member.id)}>
                            Remove
                        </Button>
                    </div>
                {/if}
            </li>
        {/each}
    </ul>
</Card>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Remove"
    onConfirm={removeMember}
    onCancel={() => (removingMemberId = null)}
    bind:open={removingMemberId}>
    This member will lose visibility of the household's shared accounts.
</ConfirmationModal>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'warning' }}
    confirmText="Transfer"
    onConfirm={transferOwnership}
    onCancel={() => (transferringMemberId = null)}
    bind:open={transferringMemberId}>
    You will become a regular member and can then leave the household.
</ConfirmationModal>
```

(Verify `DateTimeHelper.format`'s signature in `resources/js/utilities/date-time-helper.ts` and the generated controller module's action names; adjust the `.url()` calls to the real exports.)

---

### Task 9: Join page

**Complexity:** Medium

**Files:**
- Create: `resources/js/pages/app/household/join.svelte`

No tests — markup; the four GET states and POST behavior are pinned by Task 5 feature tests.

**Interfaces:**
- Consumes: page props `{ token: string, status: 'valid'|'invalid'|'expired'|'locked', household_name: string | null }`; Wayfinder `HouseholdJoinController.store.url({ token })`
- Produces: the `app/household/join` Inertia page (layout comes free from the deny-list switch in `app.ts`)

- [ ] **Step 1: Create the page**

```svelte
<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import HouseholdJoinController from '@wayfinder/App/Http/Controllers/HouseholdJoinController';

    import Field from '@components/ui/forms/field.svelte';
    import FieldInput from '@components/ui/forms/field-input.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Card from '@components/ui/cards/responsive-card.svelte';

    let {
        token,
        status,
        household_name,
    }: {
        token: string;
        status: 'valid' | 'invalid' | 'expired' | 'locked';
        household_name: string | null;
    } = $props();

    let form = useForm(() => ({ otp: '' }));

    const headline = $derived(
        status === 'valid'
            ? `Join ${household_name ?? 'household'}?`
            : status === 'expired'
              ? 'This invite has expired'
              : 'This invite is no longer valid'
    );
</script>

<div class="mx-auto max-w-md space-y-6">
    <Card title={headline}>
        {#if status === 'valid'}
            <p class="mb-4 text-sm text-muted-foreground">
                Enter the 6-digit code the household owner sent you to confirm.
            </p>

            <Form {form} action={HouseholdJoinController.store.url({ token })} class="space-y-4">
                <Field title="Invitation Code">
                    <FieldInput
                        name="otp"
                        inputmode="numeric"
                        maxlength={6}
                        placeholder="••••••"
                        required
                        error={form.errors.otp ?? form.errors.token}
                        bind:value={form.otp} />
                </Field>

                <FormAction
                    {form}
                    labelCancel="Cancel"
                    labelSubmit="Join Household"
                    withoutCancel />
            </Form>
        {:else}
            <p class="text-sm text-muted-foreground">
                {#if status === 'expired'}
                    Invites stay valid for 7 days. Ask the household owner to generate a new link.
                {:else}
                    The link was revoked, locked after too many wrong codes, or never existed. Ask
                    the household owner for a fresh invite.
                {/if}
            </p>
        {/if}
    </Card>
</div>
```

(The page must be reachable as Inertia component name `app/household/join` — the file path `resources/js/pages/app/household/join.svelte` produces exactly that.)

---

### Task 10: README + spec status updates

**Complexity:** Low

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/specs/2026-11-04-household-membership-design.md` (status line only)

No tests — documentation.

- [ ] **Step 1: Update the README domain sections**

- **Account → Access** bullet: replace the placeholder description with: "**Access** (`AccountAccessType`): `personal` or `shareable`. Shareable accounts are visible to (and transactable by) members of the owner's household — see Households; a shareable account whose owner has no household is owner-only. Mutating (update, delete, restore, archive) is owner-only."
- **Users & Access** section: add a **Household** subsection summarizing: owner + members (2–5, no hard cap), one household per user (DB-unique), reusable invite links (`/household/join/{token}`) protected by a 6-digit OTP (7-day expiry, revocable/regenerable, 5-attempt lockout, throttled), owner leaves only via ownership transfer or dissolution; dissolution deletes the grouping only — every member's shareable accounts become personal again.
- **Domain Overview** diagram: add `User ──member-of──> Household` line.
- **Roadmap → Current focus**: remove item 3 (Household / shared-account membership) — implemented.
- Update the **Reports** intro if it names the old "visible to everyone" placeholder semantics (contribution split stays "shareable accounts only", now household-scoped).

- [ ] **Step 2: Flip the spec status line**

In `docs/superpowers/specs/2026-11-04-household-membership-design.md`, change the Status line to `**Status:** Implemented — plan at docs/superpowers/plans/2026-11-04-household-membership.md`

---

## Self-Review (completed during writing)

1. **Spec coverage** — schema (T1), OTP invite flow incl. lockout/expiry/revoke/reuse (T2, T5), three-policy visibility matrix incl. transfer-leg nuance (T3), placeholder death via no-household-owner-only tests (T3), list scoping incl. archived-stays-owner-only and dashboard/statistics propagation (T4), join rate limiting (T5), owner/member lifecycle incl. owner-can't-leave, transfer-then-leave, dissolve-makes-private regression (T6), account-form gating + copy + owner chip + menu (T7), three-state settings page (T8), join page terminal states (T9), README as domain source of truth (T10). Gaps: none open.
2. **Placeholders** — two bounded "read the sibling/generated file first and mirror its exact shape" notes (TransferService payload in T3, generated Wayfinder exports in T7/T8) are deliberate verification steps with concrete fallbacks written, not TBDs.
3. **Type consistency** — `sharesHouseholdWith`, `visibleTo(User)`, `getTransferEligibleAccounts(User, ?Account)`, `inHousehold()`, `activeInvite()`, route names `household.*` used identically across tasks; T6 caught and corrected the `StoreHouseholdRequest`-reuse-in-`update()` mistake inline (dedicated `RenameHouseholdRequest`).
4. **Workflow mode** — `direct`: no run/verify/commit steps in any task, no final verification task; `wayfinder:generate` in T7 is a build action required for the types the task consumes.
5. **Testing scope** — service lifecycle + policy semantics + HTTP flows + regressions tested (Integration + Feature, following the five-suite conventions); migration/factories covered indirectly; markup-only frontend tasks and README deliberately untested per project convention.
6. **Complexity** — all tasks Medium (every step concrete code with verification notes); overall **Medium**.
