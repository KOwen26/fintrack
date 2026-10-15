---
paths:
  - 'app/**'
---

# App — Cross-cutting

## Rationale-first PHPDoc

Non-obvious classes and methods get multi-paragraph block PHPDoc explaining *why* —
constraints, design tradeoffs, alternatives rejected — not just a summary of *what*.
Inline comments are short narrative markers inside docblocked methods. Use
`array{...}` shapes only for positional or complex returns.

## Date idiom

Get the current time with `now()` for simple values or the `Date::` facade for chained
date-range math (`Date::parse()` for input parsing); never `Carbon::now()`. Import
`Carbon\Carbon` only as a type hint. Match the model cast to the column precision —
day-precision columns cast to `'date'` (`transaction_date`), timestamps to
`'datetime'`.

## Cross-cutting: explicit per-query eager loading

Eager loading is explicit at the query site — `->with([...])` / `->load([...])`.
Model-level `$with` defaults are not the house approach; introduce one only with a
deliberate reason, not as a convenience.

## Period filtering flows through DatePeriodPreset/DatePeriod
Period filtering uses the DatePeriodPreset enum resolved to the CarbonImmutable-backed DatePeriod object; services never take raw Carbon params for windows, and there is no global Date::use override — use now()/today() elsewhere.
