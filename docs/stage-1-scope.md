# Stage 1 Scope — Functional Proof of Value

## 1. Objectives
Deliver a deployable, browser-accessible, client-demonstrable application that executes the full lifecycle on fictional data through real workflows: real CSV processing, real calculations, real AI (text, transcription, speech), real human review, real outcome evaluation. Seeded data and prepared recordings are allowed only if they pass through the same workflows.

## 2. Included functionality (owning spec)
| Area | Spec |
|---|---|
| Laravel/Vue/Inertia foundation, design system, app shell, queue foundation | S01 |
| Company, departments, users, 3 fixed roles, staff mgmt, authorization | S02 |
| Sales CSV import, validation, preview, duplicates, corrections, history, completeness | S03 |
| Metrics, 3 rules, evaluations, issues, dedup, lifecycle, urgent in-app notification | S04 |
| Executive dashboard, AI brief, deterministic fallback, voice brief | S05 |
| Meetings, recording upload, transcription, transcript edit, decisions, task drafts | S06 |
| Draft review, human approval, tasks, My Tasks, comments, assignment notification | S07 |
| Outcome verification, feedback, history trace, activity log | S08 |
| Demo data, reset, verification, deployment docs | S09 |

## 3. Excluded functionality
Multi-company, billing, extra departments, ERP/CRM/accounting/HR integrations, auto sync, Excel import, WhatsApp, external email, scheduled briefs, reminders, escalation, native mobile, live bots/transcription, forecasting, anomaly detection, cross-department correlation, autonomous agents, chatbot, automatic rule modification, model training, advanced permissions, report builders, workflow builders, advanced analytics. Also: Kanban, subtasks, recurring tasks, attachments, time tracking.

## 4. Demonstration requirements
See `docs/demo-scenario.md`. All 22 steps must work through the real application on fictional data, with transcript-input fallback for transcription outages.

## 5. Acceptance criteria
The 26 items of the Stage 1 Definition of Done in section 10 below are the acceptance criteria. They are verified by manual acceptance, focused automated tests, static and build checks, and rehearsals per `.kiro/steering/verification.md` and S09.

## 6. Assumptions (demonstration defaults, not confirmed client requirements)
- A1 Currency MYR, `numeric(14,2)`, timezone `Asia/Kuala_Lumpur`.
- A2 Sales grain: one row per branch per day. Branches are fictional (KL, Penang, JB).
- A3 Reporting week is ISO Monday–Sunday; trading days Monday–Saturday; reporting cutoff 12:00 the day after (`reporting_cutoff_lag_days=1`). Configurable per company, not per branch. Expected branches are explicit.
- A4 Rule thresholds: decline > 15%, achievement < 80%, outcome change band ±2.0% (revenue, relative) or ±2.0 percentage points (percentage metrics).
- A5 Resolution condition: one closed, complete, post-action week meeting the rule's recovery condition (see `stage-1-refinements.md` R3).
- A6 Recordings: max 20 MB and ~15 minutes, formats mp3/m4a/wav/webm/mp4; confirm against provider limits at implementation.
- A7 Language: English demo recordings. Multilingual (Malay, Mandarin) not guaranteed.
- A8 Recording retention 30 days, deletable by Owner earlier.
- A9 Single company; one active Sales department.

## 7. Dependencies
PostgreSQL, PHP/Composer/Node, OpenAI-compatible API key with access to text, transcription, and speech models, HTTPS-capable Ubuntu VPS, fictional recordings.

## 8. Items requiring client confirmation
Real sales file layout and grain; real branches/channels; trading calendar and public holidays; thresholds; currency; languages in meetings; recording consent policy; data residency and PDPA position on sending data to a third-party AI provider; retention periods; user roster and role mapping.

## 9. PDPA and third-party processing (to confirm before real data)
Malaysia PDPA 2010 considerations: notice and consent for staff voice recordings (personal data), purpose limitation, security principle, retention principle, cross-border transfer (OpenAI processes outside Malaysia), data processor agreements, and data subject access. Stage 1 uses fictional data only. No compliance is claimed.

## 10. Stage 1 Definition of Done (the 26 acceptance items)
Stage 1 is ready for the client demonstration only when all of these hold:
1. The application can be deployed without Docker.
2. Authorized users can log in using appropriate roles.
3. The interface is polished and responsive.
4. A valid CSV can be uploaded and imported.
5. Invalid rows are reported.
6. Duplicate imports do not inflate business metrics.
7. Corrected imports behave according to the documented policy.
8. Business calculations match the prepared source data.
9. The rule engine detects actual business conditions.
10. Repeated detections do not create duplicate unresolved issues.
11. Missing or incomplete data is clearly identified.
12. The executive dashboard displays real calculated information.
13. Executive brief generation works through a real AI integration.
14. Voice brief generation and playback work.
15. Meeting recordings can be uploaded and transcribed.
16. Meeting transcripts produce structured task drafts.
17. Tasks cannot be assigned without explicit human approval.
18. Assigned staff can update their tasks.
19. Unauthorized users cannot access restricted data.
20. Subsequent imports trigger outcome evaluation.
21. The system distinguishes task completion from issue resolution.
22. The issue-to-outcome history is traceable.
23. Provider failures have understandable recovery paths.
24. A complete demonstration can be repeated using fictional data.
25. The application passes the defined manual verification and build checks.
26. Local setup and deployment instructions are documented.

## 11. Implementation priorities
Tasks are tagged PoV Core (needed for the 22-step demo) or PoV Polish (secondary admin, broader history views, extra config). Details and the per-spec split are in `docs/stage-1-refinements.md` R1. Polish stays in Stage 1 scope and is built after the Core slice of each milestone works.
