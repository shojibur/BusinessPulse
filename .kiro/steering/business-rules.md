---
inclusion: auto
name: business-rules
description: Authoritative business logic decisions for imports, completeness, rules, issues, tasks, outcomes, and notifications. Use when implementing or changing calculations, rule evaluation, issue lifecycle, task status, or outcome logic.
---
# Business Rules

Authoritative list: `docs/business-workflows.md` section 0 (D1–D26). Key points:
- Authoritative refinements: `docs/stage-1-refinements.md` (R2 calendar/cutoff/freshness, R3 outcomes, R4 lifecycle).
- Grain: branch-day. Identity `(department, branch_code, sales_date)`. Corrections supersede, never delete.
- Due day = cutoff passed. Closed week = all trading days due; complete = all expected branch-days present. Open weeks are never evaluated; not-yet-due days never alert; missing data is never zero.
- Rules: weekly decline (>15%), target shortfall (<80%), missing data. Results: breached / not_breached / insufficient_data. Zero denominators → insufficient_data.
- Issue identity `(rule, department, scope_key)`; one non-resolved issue per identity; recurrence creates a new linked issue; baseline evaluation never changes.
- Task completion never resolves an issue. Monitoring after all linked tasks complete; resolution via outcome condition or manual with note.
- Outcomes: only closed, complete, comparable, post-action weeks can resolve/reopen. Revenue uses relative ±2.0%, percentage metrics ±2.0 pp. Show actual, absolute, relative and point change. Wording "observed change", never causation.
- Task reopen returns a monitoring issue to action_in_progress; resolved issues are unaffected.
- Money: MYR numeric(14,2), decimal math (no floats), round half up.
- Thresholds are demo defaults, not confirmed requirements.
