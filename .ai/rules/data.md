---
paths:
  - 'app/Data/**'
---

# Data

## No DB queries inside DTOs
DTO classes and their factories must not run DB queries (no query builder/Eloquent calls issuing SQL). Allowed: relational loading (eager `with()`/`loadMissing()`), mapping, and filtering of already-loaded data. Cross-row/cross-model data is resolved by defining a model relation and eager-loading it at the controller/service layer — never by querying inside the DTO. (Example: UserListData::fromUser() reads $user->getRelation('team'), it does not query.)
