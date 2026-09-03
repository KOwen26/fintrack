# Console Commands

Rules for `app/Console/Commands/**`.

## Delegation depends on command scope

Glob: `app/Console/Commands/**`

Commands that execute business/domain work delegate their logic to a service (or action
class) and return `self::SUCCESS` / `self::FAILURE`. Developer-tooling commands —
generators and codegen such as `make:service` or `app:generate-permission-types` — are
exempt: they may be self-contained.
