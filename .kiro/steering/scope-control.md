---
inclusion: always
---
# Scope Control

- Stage 1 only. **Do not implement or specify Stage 2 features without explicit approval**: extra departments, configurable rule builder, Excel/advanced imports, scheduled briefs, reminders, escalation, WhatsApp, external email, multi-company, billing, integrations, forecasting, anomaly detection, chatbot, agents, Kanban/subtasks/recurring tasks/attachments/time tracking.
- Each feature has exactly one owning spec (see `docs/roadmap.md`). Later specs extend earlier modules; never replace them.
- Do not silently add features. If a decision is uncertain, choose the simplest approach, document the assumption, flag for review.
- Implement only the task you are asked to run. Do not install dependencies or scaffold during planning.
- Demo defaults (thresholds, calendar, retention) are assumptions, not confirmed client requirements.
- Tasks are tagged PoV Core or PoV Polish (`docs/stage-1-refinements.md` R1). Implement Core first within each milestone; Polish stays in Stage 1 but never blocks the demo path.
