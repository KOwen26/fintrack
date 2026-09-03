# Database — Migrations

Rules for `database/migrations/**`.

## Foreign key declaration style

Glob: `database/migrations/**`

Always `$table->foreignId(...)->constrained()` — pass the table name explicitly when the
column doesn't follow convention naming. Always declare an onDelete action:
`cascadeOnDelete()` for required dependencies, `nullOnDelete()` for nullable references.
Never `foreignIdFor()` or manual `->foreign()->references()->on()`.

## Deletion posture: softDeletes + separate archived_at

Glob: `database/migrations/**`

Domain tables get `softDeletes()` plus an explicit `$table->index('deleted_at')`.
User-facing archiving is a separate nullable, indexed `archived_at` timestamp with a
`notArchived()` scope — never overload soft delete for archiving.

## Decorations columns

Glob: `database/migrations/**`

Decoration metadata is attached via `$table->json('decorations')->nullable()` with the
model casting to `DecorationData::class` (Spatie Data), never a plain `'array'` cast.

## Correlation IDs are UUIDs without FK constraints

Glob: `database/migrations/**`

Link/correlation columns (e.g. `transfer_link_id`) are `uuid()` string columns with an
index and **no** foreign key constraint; services assign `(string) Str::uuid()` to them.
The existing bigint FK on `transactions.transfer_link_id` is drift to migrate — fix the
column, don't switch the service to integer IDs.
