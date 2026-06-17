# Laravel Backend Rules

## PHP Conventions

- PHP 8.4 — use constructor property promotion, readonly properties, first-class callables
- Always declare explicit return types and typed parameters: `function create(User $user, array $data): Account`
- Use curly braces on all control structures, even single-line bodies
- Enums: backed string enums, TitleCase case names — `case DebitAccount = 'debit_account'`
- PHPDoc blocks for complex return types (e.g. `@return array{executed: int, failed: int}`); inline comments only for non-obvious invariants

## Architecture Patterns

### Service Pattern

All business logic lives in `app/Services/`. Controllers are thin dispatchers that call one service method and return an Inertia response. Never put queries, calculations, or conditional logic directly in a controller.

```php
// ✅ correct — app/Http/Controllers/AccountsController.php
class AccountsController extends Controller
{
    public function __construct(private readonly AccountService $accountService) {}

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $this->accountService->create($request->user(), $request->validated());
        return to_route('accounts.show', $account)->flash('Account created.');
    }
}

// ❌ wrong — logic in controller
public function store(Request $request): RedirectResponse
{
    $account = Account::create([...$request->validated(), 'owner_id' => $request->user()->id]);
}
```

Services can inject other services when needed:

```php
// app/Services/RecurringPresetService.php
class RecurringPresetService
{
    public function __construct(private readonly TransactionService $transactionService) {}
}
```

### Event-Listener Pattern

Side-effects (cache invalidation, notifications, post-save hooks) live in Listeners attached to Events — never inline inside a service method. Services fire an event after the primary action; listeners react.

```php
// app/Services/TransactionService.php — service fires event, does NOT touch cache
class TransactionService
{
    public function create(Account $account, User $creator, array $data): Transaction
    {
        $transaction = Transaction::create([...]);
        TransactionSaved::dispatch($transaction);
        return $transaction;
    }
}

// app/Listeners/InvalidateAccountBalanceCache.php — listener owns the side-effect
class InvalidateAccountBalanceCache
{
    // Union type — one handler for two related events
    public function handle(TransactionSaved | TransactionDeleted $event): void
    {
        Cache::tags(['account:' . $event->transaction->account_id])->flush();
    }

    // Named handler for a third, structurally different event on the same listener
    public function handleRecurringPresetExecuted(RecurringPresetExecuted $event): void
    {
        Cache::tags(['account:' . $event->preset->account_id])->flush();
    }
}
```

### DB Transactions for Multi-Step Writes

Wrap operations that must succeed or fail together in `DB::transaction()`:

```php
// app/Services/TransactionService.php
public function createTransfer(...): Transaction
{
    $linkId = (string) Str::uuid();

    return DB::transaction(function () use (...): Transaction {
        $outflow = $this->create($sourceAccount, $creator, [...]);
        $this->create($destinationAccount, $creator, [...]);

        if ($feeAmount !== null && $feeAmount > 0) {
            $this->create($sourceAccount, $creator, [...]);
        }

        return $outflow;
    });
}
```

Each iteration of a loop that can partially fail should wrap its own `DB::transaction` with a try/catch (see `RecurringPresetService::runDue()`).

### Aggregates via SQL — Never PHP

Balance calculations, budget spend, report totals, and any sum/count over rows must be computed using SQL aggregates. Never fetch a collection and reduce it in PHP.

```php
// ✅ correct — app/Services/BalanceService.php
$balance = DB::table('accounts')
    ->selectRaw(
        'accounts.initial_balance + COALESCE(SUM(CASE
            WHEN t.type IN (?, ?) THEN t.amount
            WHEN t.type IN (?, ?, ?) THEN -t.amount
            ELSE 0
        END), 0) AS balance',
        ['income', 'transfer_in', 'expense', 'transfer_out', 'fee']
    )
    ->leftJoin('transactions as t', fn ($join) => $join->on('t.account_id', '=', 'accounts.id')->whereNull('t.deleted_at'))
    ->where('accounts.id', $account->id)
    ->groupBy('accounts.id', 'accounts.initial_balance')
    ->value('balance');

// ❌ wrong — PHP reduction
$balance = $account->initial_balance;
foreach ($account->transactions as $t) { ... }
```

## Controllers

- Return `Inertia::render('page/name', [...])` passing **raw Eloquent models** — not DTOs (DTOs only for cross-model shapes, see Spatie Data section)
- Use `$this->authorize()` for every write action and `view` checks on resource pages
- Use `abort_unless()` for quick business-rule guards that don't warrant a policy
- Use `to_route()` for redirects after mutations; use `back()` when there's no single canonical destination
- Inject services via constructor property promotion

```php
// to_route() — after create/update/destroy with a known destination
return to_route('accounts.show', $account)->flash('Account created.');

// back() — after actions like invite/remove where destination varies
return back()->flash('Invitation sent.');

// abort_unless() — quick guard before policy check
abort_unless($membership !== null, 403);
$this->authorize('invite', $household);
```

Authorize with extra model context (second arg to `[Transaction::class, $account]`):

```php
// app/Http/Controllers/TransactionsController.php
$this->authorize('viewAny', [Transaction::class, $account]);
$this->authorize('create', [Transaction::class, $account]);
```

## Form Requests

All validation lives in `app/Http/Requests/`. Never use inline `$request->validate()`.

```php
class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'type'            => ['required', 'string', Rule::enum(AccountType::class)],
            'initial_balance' => ['required', 'numeric', 'min:0'],
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
// app/Models/Account.php
class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'         => AccountType::class,
            'access_type'  => AccountAccessType::class,
            'initial_balance' => 'decimal:2',
            'archived_at'  => 'datetime',
            'cosmetics'    => 'array',
        ];
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        // ...returns query filtered to accounts the user can see
    }
}

// app/Models/TransactionRecurringPreset.php
// Domain behavior on the model (date math)
public function advanceNextRunDate(Carbon $from): Carbon
{
    return match ($this->frequency) {
        RecurringFrequency::Daily  => $from->addDay(),
        RecurringFrequency::Weekly => $from->addWeek(),
        // ...
    };
}

// Disable timestamps when not needed
// app/Models/HouseholdMember.php
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
// app/Enums/TransactionType.php
enum TransactionType: string
{
    case Income      = 'income';
    case Expense     = 'expense';
    case TransferOut = 'transfer_out';
    case TransferIn  = 'transfer_in';
    case Fee         = 'fee';

    /** @return array<string> */
    public static function inflows(): array
    {
        return [self::Income->value, self::TransferIn->value];
    }

    /** @return array<string> */
    public static function outflows(): array
    {
        return [self::Expense->value, self::TransferOut->value, self::Fee->value];
    }

    /** @return array<string> Types that count toward budget spend */
    public static function spendTypes(): array
    {
        return [self::Expense->value, self::Fee->value];
    }
}
```

## Policies

Every resource that requires authorization has a Policy. Register automatically via model discovery.

- Extract shared access logic into a private `canAccess()` method
- Delegate to a related model's policy via `$user->can('view', $relatedModel)` rather than re-implementing ownership checks
- Custom methods beyond CRUD are fine: `archive`, `restore`, `invite`, `removeMember`, `toggle`

```php
// app/Policies/AccountPolicy.php — custom method + private extractor
public function archive(User $user, Account $account): bool
{
    return $account->owner_id === $user->id;
}

private function canAccess(User $user, Account $account): bool
{
    if ($account->owner_id === $user->id) { return true; }
    // joint account + household member check...
}

// app/Policies/TransactionPolicy.php — delegation pattern
public function view(User $user, Transaction $transaction): bool
{
    return $user->can('view', $transaction->account);  // delegates to AccountPolicy
}
```

## Spatie Laravel Data (DTOs)

**Only create a DTO when the response shape is complex or combined** — it joins data from multiple models, carries computed values, or doesn't map directly to a single Eloquent model.

Simple model data is passed directly to `Inertia::render()` as an Eloquent model or collection; Wayfinder generates the TypeScript types via `App.Models.*`.

```php
// ✅ correct — simple model, pass directly
return Inertia::render('accounts/index', [
    'accounts' => $accounts->load('provider'),
]);

// ✅ correct — complex cross-model shape uses a DTO
// app/Http/Controllers/HouseholdsController.php
return Inertia::render('household/settings', [
    'household' => $household ? HouseholdData::from([
        'id'      => $household->id,
        'name'    => $household->name,
        'members' => $household->members->map(fn (HouseholdMember $m) => new HouseholdMemberData(
            id:        $m->id,
            user_id:   $m->user_id,
            name:      $m->user->name,   // joined from users table
            role:      $m->role,
            joined_at: $m->joined_at?->toISOString(),
        ))->toArray(),
    ]) : null,
]);

// ❌ wrong — wrapping a single model in a DTO for no reason
return Inertia::render('accounts/index', [
    'accounts' => AccountData::collect($accounts),
]);
```

DTOs are also used as **input normalizers** inside services, not just response shapes:

```php
// app/Services/AccountService.php
private function normalizeCosmetics(array $data): array
{
    if (! isset($data['cosmetics'])) { return $data; }
    $data['cosmetics'] = CosmeticData::from($data['cosmetics'])->toArray();
    return $data;
}
```

When a DTO is created, run `composer generate:ts` to sync types in `resources/js/types/`.

## Migrations

### No DB Enums

Never use `$table->enum()`. Use `$table->string()` and enforce values via PHP-backed enum casts on the model.

```php
$table->string('type');   // cast to TransactionType::class on model
```

### No Magic Strings for Defaults

```php
use App\Enums\ProviderStatus;

$table->string('status')->default(ProviderStatus::Active->value);
```

### Column Order

1. `$table->id()`
2. Relation keys (`foreignId`) — unless the FK is tightly bound to adjacent data (e.g. morph pair)
3. Core / grouped data columns (name, amount, type, etc.)
4. Status, notes, JSON columns
5. `archived_at`, `softDeletes()`, then `timestamps()`

> **Note:** The `transaction_recurring_presets` migration has `softDeletes()` before `timestamps()`. For new migrations follow the order above.

### Column Types

- `$table->decimal(15, 2)` for monetary amounts
- `$table->char('currency', 3)->default('IDR')` for currency codes
- `$table->uuid('transfer_link_id')` for link/correlation IDs
- `$table->date()` for transaction/event dates (not `datetime`)
- `$table->smallInteger()` / `$table->tinyInteger()` for year/month columns

### Indexes

Declare explicit indexes for all foreign keys and any column used in `WHERE` or `ORDER BY`:

```php
// app/database/migrations/2026_06_16_161919_create_transactions_table.php
$table->index('account_id');
$table->index(['account_id', 'transaction_date']);
$table->index(['account_id', 'type', 'transaction_date']);  // composite for reporting queries
$table->index('deleted_at');
```

## Caching

- Use Redis — supports cache tags
- Cache key pattern: `{type}:{scope}:{id}` e.g. `balance:account:42`
- Tag pattern: `Cache::tags(["account:{$id}"])->rememberForever($key, fn () => ...)`
- Invalidate via event listeners, not inline in services
- Treat cache as derived data — always maintain a fallback that recomputes from the database

```php
// app/Services/BalanceService.php
return Cache::tags(["account:{$account->id}"])
    ->rememberForever("balance:account:{$account->id}", function () use ($account): string {
        // SQL aggregate query...
    });
```

## Artisan Commands

Commands delegate to a service and return `self::SUCCESS` / `self::FAILURE`:

```php
// app/Console/Commands/RunRecurringPresets.php
class RunRecurringPresets extends Command
{
    protected $signature = 'presets:run-recurring';

    public function __construct(private readonly RecurringPresetService $recurringPresetService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->recurringPresetService->runDue();

        $this->info("Executed: {$result['executed']}  Failed: {$result['failed']}");

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

- `ProviderSeeder` — seeds reference data (banks, e-wallets)
- `CategorySeeder` — seeds 2-level default category hierarchy per user
- Run via `php artisan db:seed --class=ProviderSeeder`
