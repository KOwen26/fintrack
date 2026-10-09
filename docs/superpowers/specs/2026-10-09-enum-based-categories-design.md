# Enum-Based Categories — Design

Date: 2026-10-09
Status: Draft — pending review

## Problem

Categories are the only enumerable domain concept in this codebase stored as database rows. Every comparable concept (`AccountType`, `TransactionType`, `TransactionFlow`, `ProviderStatus`…) is a string column backed by a PHP enum with Wayfinder-generated constants. The DB catalog produces recurring friction:

1. **System booking resolves categories by magic name.** Transfer fees look up a row named `'Admin Fees'`; opening balances look up `'Initial Balance'`. Both can silently fail (rename/deletion → uncategorized booking). The `fixed` flag that should prevent this has no enforcement — there are no mutation endpoints at all.
2. **Every test that books opening balances must seed `CategorySeeder`** (five `beforeEach` lines added in the initial-balance change).
3. **Dead surface**: `CategoryController::index` renders a `categories/index` page that does not exist; `TransactionService::getCategoryTransactions()` has zero callers; `category-badge.svelte` and `category-form.svelte` (+ `category.schema.ts`) are unimported.
4. Two-level parent/child structure enforced by convention ("parents are groupings, not bookable"), an `order` decimal, and a read-only controller+service for what is effectively a static, curated catalog.

Stated needs: the catalog is **selectable by the user** and **settable by the system**. User-created categories are far future; the nearer future is per-user *hiding* of presets (and additions) via a preferences layer.

## Decision

Replace the `categories` table with a backed-string PHP enum `Category` carrying its presentation metadata as methods, plus a `CategoryGroup` enum for the parent groupings. `transactions.category_id` keeps its name but changes type from int FK to **nullable string enum value**. A DTO layer (`CategoryData`/`CategoryGroupData`) presents the enum catalog as table-like rows across the boundary. The table, model, factory, seeder, and dead backend surfaces are deleted; `CategoryService` survives as the catalog provider.

This matches the house pattern for every other enumerable concept and makes system-set categories compile-time constants instead of runtime name lookups. The string column is the door-opener for the future preferences layer: custom categories slot into the same column under their own stable keys; hidden presets become a per-user exception list that references enum values.

**Scope:** Backend only. The app is not live and existing data is disposable — the database is refreshed with `migrate:fresh --seed`, so there is **no data migration/backfill**. Frontend/TS adaptation is out of scope: Wayfinder regeneration carries the generated types, and component updates are deferred follow-up work (see Frontend section).

## The `Category` enum

`app/Enums/Category.php` — backed string, values are snake_case slugs of the labels. This is the **complete target inventory** (56 cases, transcribed from the seeder tree, in display order):

```php
enum Category: string
{
    // Income — input
    case Salary = 'salary';                                    // fixed
    case Freelance = 'freelance';
    case BusinessRevenue = 'business_revenue';
    case GrantsAndStipends = 'grants_and_stipends';
    case InvestmentReturns = 'investment_returns';
    case Dividends = 'dividends';
    case OtherIncome = 'other_income';
    case InitialBalance = 'initial_balance';                   // fixed, system-only
    // Finance
    case AdminFees = 'admin_fees';
    case Taxes = 'taxes';
    case Interest = 'interest';
    case Insurance = 'insurance';                              // fixed
    // Food & Drinks
    case DiningOut = 'dining_out';
    case SnacksAndDrinks = 'snacks_and_drinks';
    case CoffeeAndDesserts = 'coffee_and_desserts';            // seeder label has a trailing space — trimmed
    case FoodTakeouts = 'food_takeouts';
    case BuffetFineDining = 'buffet_fine_dining';              // label: 'Buffet / Fine Dining'
    // Utilities
    case Electricity = 'electricity';                          // fixed
    case Water = 'water';                                      // fixed
    case GasAndCooking = 'gas_and_cooking';
    case Internet = 'internet';                                // fixed
    case MobileAndPrepaid = 'mobile_and_prepaid';
    // Service & Housing
    case Rent = 'rent';                                        // fixed
    case HomeMaintenance = 'home_maintenance';
    case CleaningServices = 'cleaning_services';
    case PropertyServices = 'property_services';
    // Shopping
    case Groceries = 'groceries';
    case Clothing = 'clothing';
    case BeautyAndGrooming = 'beauty_and_grooming';
    case Electronics = 'electronics';
    case HouseholdItems = 'household_items';
    // Entertainment & Leisure
    case Hobbies = 'hobbies';
    case Sports = 'sports';
    case Games = 'games';
    case CinemaAndShows = 'cinema_and_shows';
    case Streaming = 'streaming';
    case TravelAndTourism = 'travel_and_tourism';
    case FestivalsEvents = 'festivals_events';                 // label: 'Festivals / Events'
    // Transport
    case Fuel = 'fuel';                                        // fixed
    case PublicTransport = 'public_transport';                 // fixed
    case RideHailingTaxis = 'ride_hailing_taxis';              // label: 'Ride-hailing / Taxis'
    case BusTrains = 'bus_trains';                             // label: 'Bus / Trains'
    case FlightsFerries = 'flights_ferries';                   // label: 'Flights / Ferries'
    case TravelServices = 'travel_services';
    // Health & Wellness
    case Medicine = 'medicine';
    case DoctorVisits = 'doctor_visits';
    case TraditionalTherapy = 'traditional_therapy';
    case FitnessAndGyms = 'fitness_and_gyms';                  // fixed
    case FamilyCare = 'family_care';
    // Education
    case Tuition = 'tuition';                                  // fixed
    case UniversitySchoolFees = 'university_school_fees';      // label: 'University / School Fees', fixed
    case BooksAndStationery = 'books_and_stationery';
    case CoursesAndWorkshops = 'courses_and_workshops';
    // Socials
    case FamilyAndFriends = 'family_and_friends';
    case Gifts = 'gifts';
    case CharityAndDonations = 'charity_and_donations';
}
```

Metadata methods are exhaustive `match` expressions over `self::cases()`. One shown in full as the pattern — the rest follow identically, transcribed from the seeder entries:

```php
public function label(): string
{
    return match ($this) {
        self::Salary => 'Salary',
        self::AdminFees => 'Admin Fees',
        self::CoffeeAndDesserts => 'Coffee & Desserts',   // trailing space dropped
        self::BuffetFineDining => 'Buffet / Fine Dining',
        // … every remaining case …
    };
}

public function group(): CategoryGroup
{
    return match ($this) {
        self::Salary, self::Freelance, self::BusinessRevenue, self::GrantsAndStipends,
        self::InvestmentReturns, self::Dividends, self::OtherIncome, self::InitialBalance,
            => CategoryGroup::Income,
        self::AdminFees, self::Taxes, self::Interest, self::Insurance,
            => CategoryGroup::Finance,
        // … one arm per group …
    };
}
```

`decorations()` wraps the child entry's `icon_slug`/`color_slug` in `DecorationData` — the same shape the old `decorations` column cast to, shared with accounts and providers. `isFixedCost()` mirrors the `fixed` flag. Case declaration order replaces the `order` decimal — `Category::cases()` IS the display order, both for cases within a group and groups themselves.

**System cases pinned by this spec:** `Category::AdminFees = 'admin_fees'` (transfer fees) and `Category::InitialBalance = 'initial_balance'` (opening balances). `InitialBalance` remains the only non-bookable case (users may legitimately book `AdminFees` expenses themselves).

## `CategoryGroup` and `CategoryType`

`app/Enums/CategoryGroup.php` — backed string, one case per current parent group, in display order:

```php
enum CategoryGroup: string
{
    case Income = 'income';                              // type: input
    case Finance = 'finance';
    case FoodAndDrinks = 'food_and_drinks';
    case Utilities = 'utilities';
    case ServiceAndHousing = 'service_and_housing';
    case Shopping = 'shopping';
    case EntertainmentAndLeisure = 'entertainment_and_leisure';
    case Transport = 'transport';
    case HealthAndWellness = 'health_and_wellness';
    case Education = 'education';
    case Socials = 'socials';
}
```

Each carries `label()` and `decorations()` (transcribed from the group entries — e.g. `Income` → `round-arrow-down` / `green-700`, `Transport` → `wheel-angle` / `slate-900`) plus:

```php
public function type(): CategoryType   // Income => input, everything else => output
public function children(): array      // Category::cases() filtered by group, declaration order
```

`CategoryType` (input/output) **stays** — it is already a Wayfinder-exported enum and remains the income/expense axis for groups and the seeder. `CategoryGroup::Income->type() === CategoryType::Input` replaces the seeder's `'type' => 'input'` column semantics.

## Storage & migration

No new migration — the original migrations are edited in place (the app is not live; the database is refreshed with `migrate:fresh --seed`):

1. `2026_06_16_161919_create_transactions_table.php`: `category_id` changes from `foreignId()->nullable()->constrained('categories')->nullOnDelete()` to `string('category_id')->nullable()` — the **name and its `$table->index('category_id')` stay**.
2. `2026_06_15_142539_create_categories_table.php` is deleted.

`transactions.category_id` stays **nullable**: transfer in/out rows carry no category (unchanged); plain expense/income rows always have one (form-required); fee rows carry `admin_fees`; opening rows carry `initial_balance`.

## Backend consumers

- **`Transaction` model**: drop the `category()` relation; the `category_id` column becomes a string enum cast — `'category_id' => Category::class` (nullable enum cast).
- **`TransactionData` / `TransactionFormData` / `TransactionListData` / `TransactionDetailData`**: `?int $category_id` → `?Category $category_id` (Spatie casts strings to enums; Wayfinder regenerates the union type). The separate embedded `?Category $category` model property in the List/Detail DTOs dies — display metadata resolves from the catalog DTOs (below), not from a loaded relation; `loadMissing('category')`/`with('category')`/`'category.parent'` eager loads are removed everywhere.
- **`TransactionService`**: `getCategoryTransactions()` deleted (zero callers, dead drill-down); list queries drop the `category` eager load; `where('category_id', …)` keeps working unchanged against the string column.
- **`TransferService`**: `resolveTransferFeeCategory()` deleted; fee rows book `category_id: Category::AdminFees` — always resolves, the null-fallback path disappears.
- **`AccountService::syncInitialBalance`**: matches `where('category_id', Category::InitialBalance)` — `CategoryService::initialBalanceCategoryId()` is deleted.
- **`SaveTransactionRequest`**: `category_id` rule set becomes `['required', Rule::enum(Category::class)->unless(Category::InitialBalance)]` (replaces `exists` + `notIn` + the lookup import). Field name unchanged.
- **Deleted**: the `Category` model, `CategoryFactory`, `CategorySeeder` (+ its `DatabaseSeeder` call), `CategoryController`, the `categories.index` route. `CategoryService` **survives** — see the DTO section.

## SpendingService & reporting

`globalCategorySpending` currently joins `categories` (twice, for parent grouping) to get name/color/icon and rolls up parents in PHP. Rewrite:

- SQL keeps the aggregate and groups by `t.category_id` (the string value; column name unchanged) — aggregation stays in SQL.
- PHP maps each grouped value through `CategorySpendingItemData::fromEnum($category, total, percentage)` — the DTO derives its own `group`/`name`/`decorations` from the enum — and the parent-grouped transform uses `Category::from($value)->group()` instead of the parent-row index. The existing parent-grouped output shape is preserved.
- `CategorySpendingItemData`: `?int $categoryId`/`?int $parentId` become `?string $category_id`/`?string $group` (enum values). `CategorySpendingReportData`/`ChildSpendingItemData` follow.

## Category DTOs — the catalog as rows

The enum is the config; DTOs are how the catalog crosses the boundary **shaped like the table it replaces**, so consumers keep receiving "category rows" (`App.Data.*` via `composer generate:ts`):

```php
// app/Data/Category/CategoryData.php
#[TypeScript]
class CategoryData extends Data
{
    public function __construct(
        public string $id,             // enum value — the row's identity (name kept from the old table)
        public string $name,
        public DecorationData $decorations,
        public CategoryType $type,     // derived from the group
        public bool $is_fixed_cost,
        public CategoryGroupData $group,
    ) {}

    public static function fromEnum(Category $category): self { … }
}

// app/Data/Category/CategoryGroupData.php
#[TypeScript]
class CategoryGroupData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public DecorationData $decorations,
        public CategoryType $type,
    ) {}

    public static function fromEnum(CategoryGroup $group): self { … }
}
```

(The enum's metadata method stays `label()` — a `name()` method would sit confusingly next to the native `UnitEnum::$name` property, which holds the *case* name like `AdminFees`. The DTO maps it to `name`, matching the old column.)

**`CategoryService` survives as the catalog provider** — its internals switch from DB queries to the enum, its call sites stay:

```php
CategoryService::getCategories(): Collection<CategoryData>            // flat, bookable (excludes InitialBalance)
CategoryService::getGroupedCategories(): Collection<array{ … }>      // group rows with `options` children, today's shape
```

`getBookableCategories()` keeps its role (the future preferences layer extends only this method); `initialBalanceCategoryId()` is deleted (superseded by the `Category::InitialBalance` constant).

**Example — the targeted data shapes.** A single category row, as the DTO now materializes it:

```php
CategoryData::fromEnum(Category::AdminFees)->toArray();
// [
//     'id'            => 'admin_fees',
//     'name'          => 'Admin Fees',
//     'decorations'   => ['icon' => 'wallet', 'color' => 'green-700'],
//     'type'          => 'output',
//     'is_fixed_cost' => false,
//     'group'         => [
//         'id'          => 'finance',
//         'name'        => 'Finance',
//         'decorations' => ['icon' => 'hand-money', 'color' => 'green-700'],
//         'type'        => 'output',
//     ],
// ]
```

And the grouped catalog exactly as `static.groupedCategories` ships it to the frontend (excerpt — `Income` and `Finance` groups; compare today's `getGroupedCategories` shape: identical field names, `type` added):

```php
CategoryService::getGroupedCategories();
// [
//     [
//         'id'          => 'income',
//         'name'        => 'Income',
//         'type'        => 'input',
//         'decorations' => ['icon' => 'round-arrow-down', 'color' => 'green-700'],
//         'options'     => [
//             ['id' => 'salary', 'name' => 'Salary', 'decorations' => ['icon' => 'case', 'color' => 'green-700'], 'type' => 'input', 'is_fixed_cost' => true],
//             ['id' => 'freelance', 'name' => 'Freelance', 'decorations' => ['icon' => 'laptop', 'color' => 'green-700'], 'type' => 'input', 'is_fixed_cost' => false],
//             // …
//         ],
//     ],
//     [
//         'id'      => 'finance',
//         'name'    => 'Finance',
//         // …
//         'options' => [
//             ['id' => 'admin_fees', 'name' => 'Admin Fees', 'decorations' => ['icon' => 'wallet', 'color' => 'green-700'], 'type' => 'output', 'is_fixed_cost' => false],
//             // …
//         ],
//     ],
//     // … remaining groups in declaration order …
// ]
```

`static.categories` (flat, bookable) is the same option shape minus the group nesting, with `initial_balance` excluded.

## Shared props & frontend

- **`HandleInertiaRequests`** (backend, in scope): `static.categories` and `static.groupedCategories` keep flowing from `CategoryService`, now returning `CategoryData`/grouped DTOs instead of model rows — the JSON shape matches today's (flat list + grouped-with-options, same `id`-keyed identity), with `type` added alongside the unchanged `decorations` object.
- **Frontend (out of scope)**: component updates — the exhaustive `resources/js/data/categories.ts` map, `category-select`/`category-info` value shape, `transaction-list`/`-item`/`-filter` lookups, `transaction.schema.ts` — are deferred follow-up work. Wayfinder regeneration updates the generated types (`App.Enums.Category` union, DTO shapes, `App.Models.Category` removal); until the components adapt there will be TS errors, which is acceptable pre-launch. The frontend dead code (`category-badge.svelte`, `category-form.svelte`, `category.schema.ts`) is left for that follow-up as well.

## Seeder, factories, tests

- **`CategorySeeder`**: deleted, and its call removed from `DatabaseSeeder` (which keeps `ProviderSeeder` + `DummyDataSeeder`).
- **`DummyDataSeeder`**: replaces its `Category` model pick lists with `Category::cases()` — bookable pool = all cases except `InitialBalance`; income pool = cases whose `group()->type()` is `Input`; expense pool = the `Output` side. Coverage logic (`coveredIncomeIds`) keyed by string values.
- **`TransactionFactory`**: default `category_id => Category::factory()` becomes a random bookable case.
- **`AccountFactory` hook / `syncInitialBalance`**: no seeding dependency anymore — **all five `beforeEach` seeder lines added by the initial-balance change are removed**, along with the `CategorySeeder` imports.
- **Tests referencing `Category::factory()`** (transaction-related suites) follow the factory default; the summarize/summarize-shape tests are unaffected. `InitialBalanceCategoryTest` does not exist (dropped earlier); `InitialBalanceGuardTest`'s "other category" lookup becomes any case except `InitialBalance`.
- **`php artisan wayfinder:generate` + `composer generate:ts`**: required — enum union types and DTO shapes change. Frontend component adaptation is the deferred follow-up (see Frontend section).

## Behavior compatibility

Not applicable as a migration concern — the app is not live and data is disposable (`migrate:fresh --seed`). By construction the enum reproduces today's labels, icons, colors, grouping, and ordering (declaration order mirrors the seeder), so once the frontend adapts, nothing user-visible changes. System booking paths get strictly safer: constants instead of name lookups, no null-fallback branches.

## Out of scope

- **All frontend/TS adaptation** — deferred follow-up; Wayfinder regen carries the generated types.
- Per-user preferences layer (hide presets / custom categories) — future; enabled by the string column and the single server-side bookable-list resolver.
- User-created categories and any category CRUD endpoints.
- Enforcing "income transactions pick input-group categories" (not enforced today either).
- Budgets / `is_fixed_cost` consumers (the flag is carried as `isFixedCost()` for that future).

- **`SpendingService`** — see its own section: the aggregate groups by `t.category_id` (name unchanged), DTO ids become string enum values.

## Veto points (decisions I made; flip any before planning)

1. Enum named `Category`, groups in `CategoryGroup`; the transaction column **keeps the name `category_id`** (type-only change int → string).
2. Dead backend surfaces deleted outright (controller, route, `getCategoryTransactions`) rather than adapted — they are unreachable today. Frontend dead code is deferred with the rest of the frontend work.
3. `CategoryType` kept as the group's income/expense axis rather than inlined as booleans.
4. `order` decimals are not carried — declaration order is the order.
5. `CategoryData`/`CategoryGroupData` keep the `id` field name (holding the string enum value), expose presentation metadata as the same `decorations` object the old table cast to (`DecorationData`), and carry `type` (derived from the group for categories) — so frontend consumers keep their field names.
