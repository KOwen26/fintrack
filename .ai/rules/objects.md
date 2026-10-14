---
paths:
  - 'app/Objects/**'
---

# Objects

## Value objects; persist via Castable + inline CastsAttributes
Immutable value objects live in app/Objects. To persist one as JSON, implement Castable and return an inline anonymous CastsAttributes from castUsing(); provide static fromArray()/toArray(); declare the cast with the class name. No app/Casts directory.
