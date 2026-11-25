---
inclusion: auto
name: business-rules
description: Authoritative business logic decisions for imports, completeness, rules, issues, tasks, outcomes, and notifications. Use when implementing or changing calculations, rule evaluation, issue lifecycle, task status, or outcome logic.
---
# Business Rules

Authoritative list: `docs/business-workflows.md` section 0 (D1–D26). Key points:
- Grain: branch-day. Identity `(department, branch_code, sales_date)`. Corrections supersede, never delete.
- Complete week = all expected branches × trading days present. Partial weeks are never compared or zero-filled.
- Rules: weekly decline (>15%), target shortfall (<80%), missing data. Results: breached / not_breached / insufficient_data. Zero denominators → insufficient_data.
- Issue identity `(rule, department, scope_key)`; one non-resolved issue per identity; recurrence creates a new linked issue; baseline evaluation never changes.
- Task completion never resolves an issue. Monitoring after all linked tasks complete; resolution via outcome condition or manual with note.
- Outcomes: improved/unchanged/worsened/not_evaluable using ±2.0 pts; wording "observed change", never causation.
- Money: MYR numeric(14,2), decimal math (no floats), round half up.
- Thresholds are demo defaults, not confirmed requirements.
