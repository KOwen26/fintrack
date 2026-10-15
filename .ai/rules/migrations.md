---
paths:
  - 'database/migrations/**'
---

# Database — Migrations

## Foreign key declaration style

Always `$table->foreignId(...)->constrained()` — pass the table name explicitly when the
column doesn't follow convention naming. Always declare an onDelete action:
`cascadeOnDelete()` for required dependencies, `nullOnDelete()` for nullable references.
Never `foreignIdFor()` or manual `->foreign()->references()->on()`.

## Deletion posture: softDeletes + separate archived_at

Domain tables get `softDeletes()` plus an explicit `$table->index('deleted_at')`.
User-facing archiving is a separate nullable, indexed `archived_at` timestamp with a
`notArchived()` scope — never overload soft delete for archiving.

## JSON metadata columns

Arbitrary per-record metadata is attached via a `$table->json('...')->nullable()` column
with the model casting it to the matching Spatie Data DTO class, never a plain
`'array'` cast.

## Correlation IDs are UUIDs without FK constraints

Link/correlation columns (e.g. `correlation_id`) are `uuid()` string columns with an
index and **no** foreign key constraint; services assign `(string) Str::uuid()` to them.
An existing correlation column carrying a bigint FK is drift to migrate — fix the
column, don't switch the service to integer IDs.

## Enum storage: string column + PHP enum cast, never ->enum()
Store enumerated values as string columns with the matching PHP enum cast on the model; never $table->enum().
