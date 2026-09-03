# Backend — Models

Rules for `app/Models/**`.

## Observers registered via `#[ObservedBy]`

Glob: `app/Models/**`

Register observers with the `#[ObservedBy([XObserver::class])]` attribute on the model —
never `Model::observe()` in a provider and never `booted()` closures. Observers own
model-lifecycle side effects (e.g. balance denormalization); service-dispatched events
own cross-cutting reactions (cache invalidation) — keep the division.

## Sushi JSON-backed reference models

Glob: `app/Models/**`

Reference data that must stay in sync with frontend JSON files uses the trio
`use Sushi` + `HasSushiJsonSource` (from `app/Models/Traits/`) +
`#[ObservedBy([SushiJsonObserver::class])]`, with `SushiJsonBuilder` covering bulk
`update()`/`delete()` operations that skip model events. Implement `jsonSourcePath()`
and `jsonColumns()` on the model. Never hand-edit the JSON files as a separate write
path — writes flow through the model so the observer mirrors them.
