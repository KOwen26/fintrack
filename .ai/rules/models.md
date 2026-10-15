---
paths:
  - 'app/Models/**'
---

# Backend — Models

## Observers registered via `#[ObservedBy]`

Register observers with the `#[ObservedBy([XObserver::class])]` attribute on the model —
never `Model::observe()` in a provider and never `booted()` closures. Observers own
model-lifecycle side effects (e.g. keeping a denormalized derived column in sync);
service-dispatched events own cross-cutting reactions — keep the division.

## Sushi JSON-backed reference models

Reference data that must stay in sync with frontend JSON files uses the trio
`use Sushi` + `HasSushiJsonSource` (from `app/Models/Concerns/`) +
`#[ObservedBy([SushiJsonObserver::class])]`, with `SushiJsonBuilder` covering bulk
`update()`/`delete()` operations that skip model events. Implement `jsonSourcePath()`
and `jsonColumns()` on the model. Never hand-edit the JSON files as a separate write
path — writes flow through the model so the observer mirrors them.

## Unguarded models; validation is the mass-assignment boundary
Models are globally unguarded (Model::unguard() in AppServiceProvider) with preventSilentlyDiscardingAttributes() enabled locally — never hand-maintain $fillable; untrusted input passes through a Form Request before reaching a model.
