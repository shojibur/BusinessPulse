---
inclusion: auto
name: architecture
description: Architecture, module boundaries, AI integration design, background jobs, and deployment rules. Use when designing or implementing backend services, jobs, AI integrations, or deployment.
---
# Architecture (summary of `docs/architecture.md`)

- Modular monolith, one Laravel app, one PostgreSQL DB, Inertia/Vue UI.
- AI classes: `BriefGenerator`, `MeetingTaskExtractor`, `TranscriptionService`, `SpeechService` over one thin HTTP client (one attempt per call). Structured output, server-side validation, HTTP timeouts 30/120/60/60 s, job-level retries only (3 tries, backoff 10/30/60 s), sanitized errors. Queues `default` and `ai`.
- AI never calculates metrics, assigns tasks, edits rules, or notifies externally. Authoritative figures are rendered from stored evidence, not AI text.
- Metrics are computed from active `sales_records`; evidence is snapshotted in `rule_evaluations.evidence`. Issues point to a preserved baseline evaluation.
- Jobs: ProcessImport, EvaluateRules, VerifyOutcomes (default); GenerateBrief, GenerateBriefAudio, TranscribeMeeting, ExtractMeetingTasks (ai). No panel registries or null-object readers; later specs extend pages directly.
- Business clock: `BusinessClock::now()/today()` (company timezone; the demo clock file written by `demo:set-date` is honored only when `DEMO_MODE=true`).
- Read `docs/architecture.md` and `docs/data-model.md` for detail.
