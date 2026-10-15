---
paths:
  - 'app/Enums/**'
---

# Enums

## String-backed enums as catalog + behavior home
String-backed, TitleCase cases with snake_case values. Put static case-subset helpers (bookable()), match-based presentation (label(), group()), and resolution methods (toPeriod() returning a DatePeriod) on the enum; write predicates explicitly (isInflow()), never magic is*() traits.
