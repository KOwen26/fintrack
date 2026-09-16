# Laravel Backend Rules

> **About the examples:** Code samples use a neutral sample domain (`User`, `Team`, `UserStatus`) purely to illustrate the conventions — these are shapes any Laravel app ships with, not this app's business scope. They are **not** a domain spec: never assume models, columns, or enum values from these docs. Always derive the real domain shape from the actual code and the Wayfinder-generated types (`@wayfinder/*`).

## PHP Conventions

- PHP 8.4 — use constructor property promotion, readonly properties, first-class callables
- Always declare explicit return types and typed parameters: `function create(User $actor, array $data): User`
- Use curly braces on all control structures, even single-line bodies
- Enums: backed string enums, TitleCase case names — `case Active = 'active'`
- PHPDoc blocks for complex return types (e.g. `@return array{pruned: int, failed: int}`); inline comments only for non-obvious invariants

## Architecture Patterns

### Service Pattern

All business logic lives in `app/Services/`. Controllers are thin dispatchers that call one service method and return an Inertia response. Never put queries, calculations, or conditional logic directly in a controller.

```php
// ✅ correct — app/Http/Controllers/UserController.php
class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->userService->create($request->user(), $request->validated());
        return to_route('users.show', $user)->flash('User created.');
    }
}

// ❌ wrong — logic in controller
public function store(Request $request): RedirectResponse
{
    $user = User::create([...$request->validated(), 'team_id' => $request->user()->team_id]);
}
```

Services can inject other services when needed:

```php
// app/Services/UserService.php
class UserService
{
    public function __construct(private readonly TeamService $teamService) {}
}
```

### Event-Listener Pattern

Side-effects (cache invalidation, notifications, post-save hooks) live in Listeners attached to Events — never inline inside a service method. Services fire an event after the primary action; listeners react.

```php
// app/Services/UserService.php — service fires event, does NOT touch cache
class UserService
{
    public function update(User $actor, User $user, array $data): User
    {
        $user->update($data);
        UserSaved::dispatch($user);
        return $user;
    }
}

// app/Listeners/InvalidateUserCache.php — listener owns the side-effect
class InvalidateUserCache
{
    // Union type — one handler for two related events
    public function handle(UserSaved | UserDeleted $event): void
    {
        Cache::tags(['user:' . $event->user->id])->flush();
    }

    // Named handler for a third, structurally different event on the same listener
    public function handleTeamMemberAdded(TeamMemberAdded $event): void
    {
        Cache::tags(['team:' . $event->team->id])->flush();
    }
}
```

### DB Transactions for Multi-Step Writes

Wrap operations that must succeed or fail together in `DB::transaction()`:

```php
// app/Services/UserService.php
public function createWithInvite(User $actor, array $data): User
{
    return DB::transaction(function () use ($actor, $data): User {
        $user = $this->create($actor, [...]);

        if ($data['send_invite'] ?? false) {
            $this->teamService->sendInvite($user);
        }

        return $user;
    });
}
```

Each iteration of a loop that can partially fail should wrap its own `DB::transaction` with a try/catch.

### Aggregates via SQL — Never PHP

Report totals, status counts, and any sum/count over rows must be computed using SQL aggregates. Never fetch a collection and reduce it in PHP.

```php
// ✅ correct — count by status in SQL
$activeCount = DB::table('users')
    ->selectRaw(
        'SUM(CASE WHEN users.status IN (?, ?) THEN 1 ELSE 0 END) AS active_count',
        [UserStatus::Active->value, UserStatus::Trial->value]
    )
    ->whereNull('users.deleted_at')
    ->value('active_count');

// ❌ wrong — PHP reduction
$count = 0;
foreach (User::all() as $user) { ... }
```

## Controllers

- Return `Inertia::render('page/name', [...])` passing **raw Eloquent models** — not DTOs (DTOs only for cross-model shapes, see Spatie Data section)
- Use `$this->authorize()` for every write action and `view` checks on resource pages
- Use `abort_unless()` for quick business-rule guards that don't warrant a policy
- Use `to_route()` for redirects after mutations; use `back()` when there's no single canonical destination
- Inject services via constructor property promotion

```php
// to_route() — after create/update/destroy with a known destination
return to_route('users.show', $user)->flash('User created.');

// back() — after actions like invites where destination varies
return back()->flash('Invitation sent.');

// abort_unless() — quick guard before policy check
abort_unless($invite !== null, 404);
$this->authorize('accept', $invite);
```

Authorize with extra model context (second arg to `[ChildModel::class, $context]`):

```php
// app/Http/Controllers/ActivityController.php
$this->authorize('viewAny', [Activity::class, $user]);
$this->authorize('create', [Activity::class, $user]);
```

## Form Requests

All validation lives in `app/Http/Requests/`. Never use inline `$request->validate()`.

```php
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'string', 'email', 'max:255'],
            'status' => ['required', 'string', Rule::enum(UserStatus::class)],
        ];
    }
}
```

## Eloquent Models

- Use `SoftDeletes` on any model that soft-deletes
- Use `protected function casts(): array` (method form, not `$casts` property)
- Cast enum-backed columns to PHP enum classes; never use raw strings in code
- Use `$guarded = []` on all models (mass-assignment open, rely on Form Requests); exception: `User` uses explicit `$fillable`
- Use `#[Scope]` attribute on `protected function` for named scopes — **not** the old `scopeName()` naming convention
- Domain behavior that belongs to the model (e.g. date math, derived state) goes as a public method on the model

```php
// app/Models/User.php
class User extends Authenticatable
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'status'];

    protected function casts(): array
    {
        return [
            'status'            => UserStatus::class,
            'email_verified_at' => 'datetime',
            'settings'          => 'array',
        ];
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $actor): Builder
    {
        // ...returns query filtered to users the actor can see
    }
}

// All other models use $guarded = []:
// app/Models/Team.php
class Team extends Model
{
    protected $guarded = [];
    // ...
}

// Domain behavior on the model (date math)
public function gracePeriodEndsOn(Carbon $suspendedAt): Carbon
{
    return match ($this->status) {
        UserStatus::Suspended => $suspendedAt->addDays(30),
        UserStatus::Trial     => $suspendedAt->addDays(7),
        // ...
    };
}

// Disable timestamps when not needed
// app/Models/TeamUser.php
public $timestamps = false;
```

Use `#[UseFactory]` attribute to bind a factory to a model:

```php
// app/Models/User.php
#[UseFactory(UserFactory::class)]
class User extends Authenticatable { ... }
```

## Enums

Backed string enums with TitleCase case names. Add static helper methods to return semantic value subsets used in SQL and validation:

```php
// app/Enums/UserStatus.php
enum UserStatus: string
{
    case Active    = 'active';
    case Trial     = 'trial';
    case Suspended = 'suspended';
    case Banned    = 'banned';

    /** @return array<string> Statuses counted toward the active-user metric */
    public static function activeStates(): array
    {
        return [self::Active->value, self::Trial->value];
    }

    /** @return array<string> Statuses that may still sign in */
    public static function loginableStates(): array
    {
        return [self::Active->value, self::Trial->value, self::Suspended->value];
    }
}
```

## Policies

Every resource that requires authorization has a Policy. Register automatically via model discovery.

- Extract shared access logic into a private `canAccess()` method
- Delegate to a related model's policy via `$actor->can('view', $relatedModel)` rather than re-implementing ownership checks
- Custom methods beyond CRUD are fine: `archive`, `restore`, `invite`, `removeMember`, `toggle`

```php
// app/Policies/UserPolicy.php — custom method + private extractor
public function impersonate(User $actor, User $user): bool
{
    return $actor->is_admin;
}

private function canAccess(User $actor, User $user): bool
{
    if ($actor->is_admin) { return true; }
    // same-team member check...
}

// app/Policies/ActivityPolicy.php — delegation pattern
public function view(User $actor, Activity $activity): bool
{
    return $actor->can('view', $activity->user);  // delegates to UserPolicy
}
```

## Spatie Laravel Data (DTOs)

**Only create a DTO when the response shape is complex or combined** — it joins data from multiple models, carries computed values, or doesn't map directly to a single Eloquent model.

Simple model data is passed directly to `Inertia::render()` as an Eloquent model or collection; Wayfinder generates the TypeScript types via `App.Models.*`.

```php
// ✅ correct — simple model, pass directly
return Inertia::render('users/index', [
    'users' => $users->load('team'),
]);

// ✅ correct — complex cross-model shape uses a DTO
// app/Http/Controllers/TeamController.php
return Inertia::render('teams/settings', [
    'team' => $team ? TeamData::from([
        'id'      => $team->id,
        'name'    => $team->name,
        'members' => $team->members->map(fn (User $m) => new TeamMemberData(
            id:        $m->id,
            user_id:   $m->id,
            name:      $m->name,   // joined from users table
            role:      $m->pivot->role,
            joined_at: $m->pivot->joined_at?->toISOString(),
        ))->toArray(),
    ]) : null,
]);

// ❌ wrong — wrapping a single model in a DTO for no reason
return Inertia::render('users/index', [
    'users' => UserData::collect($users),
]);
```

DTOs are also used as **input normalizers** inside services, not just response shapes:

```php
// app/Services/UserService.php
private function normalizeSettings(array $data): array
{
    if (! isset($data['settings'])) { return $data; }
    $data['settings'] = SettingsData::from($data['settings'])->toArray();
    return $data;
}
```

When a DTO is created, run `composer generate:ts` to sync types in `resources/js/types/`.

## Migrations

### No DB Enums

Never use `$table->enum()`. Use `$table->string()` and enforce values via PHP-backed enum casts on the model.

```php
$table->string('status');   // cast to UserStatus::class on model
```

### No Magic Strings for Defaults

```php
use App\Enums\UserStatus;

$table->string('status')->default(UserStatus::Active->value);
```

### Column Order

1. `$table->id()`
2. Relation keys (`foreignId`) — unless the FK is tightly bound to adjacent data (e.g. morph pair)
3. Core / grouped data columns (name, amount, type, etc.)
4. Status, notes, JSON columns
5. `archived_at`, `softDeletes()`, then `timestamps()`

> **Note:** One legacy migration has `softDeletes()` before `timestamps()`. For new migrations follow the order above.

### Column Types

- `$table->decimal(15, 2)` for monetary amounts
- `$table->char('currency', 3)->default('USD')` for currency codes (ISO 4217)
- `$table->uuid('correlation_id')` for link/correlation IDs
- `$table->date()` for event dates (not `datetime`)
- `$table->smallInteger()` / `$table->tinyInteger()` for year/month columns

### Indexes

Declare explicit indexes for all foreign keys and any column used in `WHERE` or `ORDER BY`:

```php
// database/migrations/2026_01_01_000000_create_logins_table.php
$table->index('user_id');
$table->index(['user_id', 'login_date']);
$table->index(['user_id', 'type', 'login_date']);  // composite for reporting queries
$table->index('deleted_at');
```

## Caching

- Use Redis — supports cache tags
- Cache key pattern: `{type}:{scope}:{id}` e.g. `stats:user:42`
- Tag pattern: `Cache::tags(["user:{$id}"])->rememberForever($key, fn () => ...)`
- Invalidate via event listeners, not inline in services
- Treat cache as derived data — always maintain a fallback that recomputes from the database

```php
// app/Services/UserStatsService.php
return Cache::tags(["user:{$user->id}"])
    ->rememberForever("stats:user:{$user->id}", function () use ($user): string {
        // SQL aggregate query...
    });
```

## Artisan Commands

Commands delegate to a service and return `self::SUCCESS` / `self::FAILURE`:

```php
// app/Console/Commands/PruneStaleUsers.php
class PruneStaleUsers extends Command
{
    protected $signature = 'users:prune-stale';

    public function __construct(private readonly UserService $userService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->userService->pruneStale();

        $this->info("Pruned: {$result['pruned']}  Failed: {$result['failed']}");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
```

## Shared Inertia Props

`HandleInertiaRequests::share()` exposes these props to every page:

```php
[
    'csrf_token'  => csrf_token(),
    'auth'        => [
        'user'        => fn () => $request->user()?->only('id', 'name', 'email', 'theme_preference'),
        'permissions' => fn () => $request->user()?->getPermissionsViaRoles()->pluck('name')->toArray(),
    ],
    'flash'       => [
        'type'    => fn () => $request->session()->get('type'),
        'message' => fn () => $request->session()->get('message'),
        'details' => fn () => $request->session()->get('details'),
    ],
    'meta'        => [
        'app_name'             => config('app.name'),
        'current_route_name'   => fn () => $method === 'GET' ? $request->route()->getName() : null,
        'previous_route_name'  => fn () => $method === 'GET' ? Route::getPreviousName() : null,
    ],
]
```

## Spatie Permission

User model uses `HasRoles` trait. Permissions are derived from roles (not assigned directly) and exposed via `auth.permissions` shared prop. A `GeneratePermissionTypes` Artisan command generates TypeScript types for permissions.

## Seeding

- Reference data (e.g. countries, statuses) ships in a dedicated seeder
- Per-record defaults (e.g. default settings for each new user) ship in their own seeder
- Run via `php artisan db:seed --class=...`
