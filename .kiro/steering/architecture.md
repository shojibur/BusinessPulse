---
inclusion: auto
name: architecture
description: Architecture, module boundaries, AI integration design, background jobs, and deployment rules. Use when designing or implementing backend services, jobs, AI integrations, or deployment.
---
# Architecture (summary of `docs/architecture.md`)

- Modular monolith, one Laravel app, one PostgreSQL DB, Inertia/Vue UI.
- AI classes: `BriefGenerator`, `MeetingTaskExtractor`, `TranscriptionService`, `SpeechService` over one thin HTTP client. Structured output, server-side validation, timeouts (30/120/60s), 3 attempts with backoff 10/30/60s, sanitized errors.
- AI never calculates metrics, assigns tasks, edits rules, or notifies externally. Authoritative figures are rendered from stored evidence, not AI text.
- Metrics are computed from active `sales_records`; evidence is snapshotted in `rule_evaluations.evidence`. Issues point to a preserved baseline evaluation.
- Jobs: ProcessImport, EvaluateRules, GenerateBrief, GenerateBriefAudio, TranscribeMeeting, ExtractMeetingTasks.
- Business clock: `BusinessClock::today()` (company timezone; `DEMO_FIXED_DATE` only when `DEMO_MODE=true`).
- Read `docs/architecture.md` and `docs/data-model.md` for detail.
