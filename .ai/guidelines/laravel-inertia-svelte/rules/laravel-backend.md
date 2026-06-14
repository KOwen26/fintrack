# Laravel Backend Rules

## PHP Conventions

- PHP 8.4 — use constructor property promotion, readonly properties, first-class callables
- Always declare explicit return types and typed parameters: `function create(User $user, array $data): Account`
- Use curly braces on all control structures, even single-line bodies
- Enums: backed string enums, TitleCase case names — `case DebitAccount = 'debit_account'`
- PHPDoc blocks for complex return types; inline comments only for non-obvious invariants

## Architecture Patterns

### Service Pattern
All business logic lives in `app/Services/`. Controllers are thin dispatchers that call one service method and return an Inertia response. Never put queries, calculations, or conditional logic directly in a controller.

```php
// ✅ correct
class AccountsController extends Controller
{
    public function __construct(private readonly AccountService $accountService) {}

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $account = $this->accountService->create($request->user(), $request->validated());
        return to_route('accounts.show', $account)->with('message', 'Account created.');
    }
}

// ❌ wrong — logic in controller
public function store(Request $request): RedirectResponse
{
    $account = Account::create([...$request->validated(), 'owner_id' => $request->user()->id]);
    // ...
}
```

### Event-Listener Pattern
Side-effects (cache invalidation, notifications, post-save hooks) live in Listeners attached to Events — never inline inside a service method. Services fire an event after the primary action; listeners react.

```php
// Service fires event, does NOT touch cache directly
class TransactionService
{
    public function create(array $data): Transaction
    {
        $transaction = Transaction::create($data);
        TransactionSaved::dispatch($transaction);
        return $transaction;
    }
}

// Listener owns the side-effect
class InvalidateAccountBalanceCache
{
    public function handle(TransactionSaved $event): void
    {
        Cache::tags(['account:' . $event->transaction->account_id])->flush();
    }
}
```

### Aggregates via SQL — Never PHP
Balance calculations, budget spend, report totals, and any sum/count over rows must be computed using SQL aggregates (`SUM`, `COUNT`, `selectRaw`, `withSum`). Never fetch a collection and reduce it in PHP.

```php
// ✅ correct — single SQL aggregate
$balance = DB::table('accounts')
    ->selectRaw('initial_balance + SUM(CASE WHEN type IN (?,?) THEN amount ELSE -amount END) AS balance',
        ['income', 'transfer_in'])
    ->where('id', $accountId)
    ->value('balance');

// ❌ wrong — PHP reduction
$balance = $account->initial_balance;
foreach ($account->transactions as $t) {
    $balance += in_array($t->type, ['income', 'transfer_in']) ? $t->amount : -$t->amount;
}
```

## Controllers

- Return `Inertia::render('page/name', [...])` passing Spatie Data objects — not raw arrays
- Use `$this->authorize()` or Gate checks for every write action
- Use `to_route()` for redirects after mutations
- Inject services via constructor property promotion

```php
return Inertia::render('accounts/index', [
    'accounts' => AccountData::collect($accounts),
]);
```

## Form Requests

All validation lives in `app/Http/Requests/`. Never use inline `$request->validate()`.

```php
// ✅ correct
class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'type'           => ['required', 'string', Rule::enum(AccountType::class)],
            'initial_balance'=> ['required', 'numeric', 'min:0'],
        ];
    }
}
```

## Eloquent Models

- Use `SoftDeletes` on any model that soft-deletes
- Cast enum-backed string columns with `$casts`; never use raw strings in code
- Define named scopes for common query patterns (e.g. `scopeVisibleTo`)

```php
protected $casts = [
    'type'           => AccountType::class,
    'access_type'    => AccountAccessType::class,
    'initial_balance'=> 'decimal:2',
    'archived_at'    => 'datetime',
];
```

## Policies

Every resource that requires authorization has a Policy. Register automatically via model discovery. Use `$this->authorize()` in controllers.

```php
class AccountPolicy
{
    public function update(User $user, Account $account): bool
    {
        return $account->owner_id === $user->id;
    }
}
```

## Spatie Laravel Data (DTOs)

**Only create a DTO when the response shape is complex or combined** — e.g. it joins data from multiple models, carries computed values, or doesn't map directly to a single Eloquent model. Simple model data is passed directly to `Inertia::render()` as an Eloquent model or collection; Wayfinder generates the TypeScript types via `App.Models.*`.

```php
// ✅ correct — simple model, pass directly
return Inertia::render('accounts/index', [
    'accounts' => $accounts->load('provider'),
    'providers' => Provider::where('status', 'active')->get(),
]);

// ✅ correct — complex/combined shape needs a DTO
// HouseholdData joins household + members + user.name (cross-model)
return Inertia::render('household/settings', [
    'household' => HouseholdData::from([
        'id'      => $household->id,
        'name'    => $household->name,
        'members' => $household->members->map(fn (HouseholdMember $m) => new HouseholdMemberData(
            id:        $m->id,
            user_id:   $m->user_id,
            name:      $m->user->name,  // joined from users table
            role:      $m->role,
            joined_at: $m->joined_at?->toISOString(),
        ))->toArray(),
    ]),
]);

// ❌ wrong — AccountData is just a model wrapper, not needed
return Inertia::render('accounts/index', [
    'accounts' => AccountData::collect($accounts),
]);
```

When a DTO is needed, run `composer generate:ts` after any Data class change to sync types in `resources/js/wayfinder/`.

## Migrations

### No DB Enums
Never use `$table->enum()`. Use `$table->string()` and enforce values via PHP-backed enum casts on the model.

```php
// ✅ correct
$table->string('type');   // cast to AccountType::class on model

// ❌ wrong
$table->enum('type', ['debit_account', 'credit_card', ...]);
```

### No Magic Strings for Defaults
When setting a default for an enum-backed column, use the PHP enum's `.value` — not a string literal. Import the enum at the top of the migration class.

```php
use App\Enums\ProviderStatus;

$table->string('status')->default(ProviderStatus::Active->value);
```

### Column Order
Sort columns in this sequence:

1. `$table->id()`
2. Relation keys (`foreignId`) — unless the FK is tightly bound to adjacent data (e.g. a morph pair), in which case move it next to those columns
3. Core / grouped data columns (name, amount, type, etc.) — keep related fields together
4. Status, notes, long-text, JSON columns
5. `archived_at`, `deleted_at` (soft delete), then `timestamps()`

```php
Schema::create('accounts', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('household_id')->constrained()->cascadeOnDelete();   // FKs
    $table->foreignId('owner_id')->constrained('users');
    $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
    $table->string('name');                                                  // data
    $table->string('type');
    $table->string('access_type');
    $table->decimal('initial_balance', 15, 2)->default(0);
    $table->decimal('credit_card_limit', 15, 2)->nullable();
    $table->char('currency', 3)->default('IDR');
    $table->timestamp('archived_at')->nullable();                           // archive
    $table->softDeletes();                                                   // soft delete
    $table->timestamps();                                                    // timestamps
});
```

## Caching

- Use Redis (preferred) or the `database` cache driver — both support tags from Laravel 11+
- Cache key pattern: `{entity}:{id}:{type}` e.g. `balance:account:42`
- Tag pattern: `Cache::tags(['account:42'])->remember(...)`
- Invalidate via event listeners, not inline in services
- Treat cache as derived data — always maintain a fallback that recomputes from the database

## Seeding

- `ProviderSeeder` — seeds reference data (banks, e-wallets)
- `CategorySeeder` — seeds 2-level default category hierarchy per user
- Run via `php artisan db:seed --class=ProviderSeeder`
