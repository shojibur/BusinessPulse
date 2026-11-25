# S08 verification

Fictional Meridian data only. Results as observed on 2026-11-26.

## Demo calculations (real imports, real task approvals and completions, real queue worker, demo clock)

| Step | Observed (stored outcome rows) |
|---|---|
| W8 baseline | MYR 124,000.00 (-20.00% against W7 155,000.00), target achievement 79.49% |
| 5 Oct, `week9-interim` | phase `during_action` (not used for verification), improved, MYR 138,000.00, +MYR 14,000.00, +11.29%, recovered 89.03%; condition not applied; issue stays `monitoring` |
| 12 Oct, `week10-recovery` | phase `post_action`, improved, MYR 150,000.00, +MYR 26,000.00, +20.97%, recovered 96.77%; condition limit MYR 131,750.00 (155,000 x 0.85) met; issue `resolved`, `system_verified`, `resolved_by` null, trigger `outcome_verified` |
| Target issue (both issues moved to monitoring in the automated test) | baseline 79.49%, W10 96.15%, `+16.66 pts`, resolved |

The week-over-week change (-20.00% at detection, +8.70% in W10) appears only as rule evidence. Boundary: total 131,750.00 resolves, 131,749.99 does not. Worsening (W10 120,000: -3.23%) reopens the issue (`outcome_worsened`) and keeps `baseline_evaluation_id`. 125,000 is `unchanged`. A W10 file missing one branch-day is `not_evaluable` (`followup_insufficient_data`, all value columns null). A rebound to 128,000 (+6.67% week over week) does not meet the level condition. A pending linked task blocks verified resolution.

## Automated
`npm run verify` passes (512 tests). S08 tests (`tests/Feature/Outcomes`, 42): phase rules, demo values, boundaries, percentage points, comparability checks and not-evaluable rows, DB constraints, immutable evidence and baseline after a corrected import, idempotency (re-upload, repeated jobs), resolution gate with open tasks, S07 `reconcile`, reopen scenarios, feedback (one vote per target, replaces, no rule change, authorization, throttle), outcome access (Staff 403, other department and company 404 identical to a missing id), activity recording (allow-list, row counts, no row for an evaluation run, failing listener isolation on the database queue), trace accuracy and no invented history.

## Manual (browser, mock provider for the meeting step only)
Outcomes list, comparison page (values, periods, phase label, condition and limit, week-over-week evidence label, chart with table alternative, checks), feedback vote stored, issue page panels (outcome, feedback, trace with the seven stages and "No activity yet" for the Brief stage), no horizontal scroll at 360, 768 and 1280 px, Staff 403 on `/outcomes`, `/outcomes/{id}` and `/issues/{id}`.

## Not verified / outstanding
- Live AI provider (real transcription and extraction) is still unverified; S08 itself uses no AI.
- Fixture scenarios F1 to F13 were covered by the automated tests, not all by hand; the activity privacy audit and the simulated manual-resolve race were covered by tests only. Task 12.1 stays unchecked.
- Recording retention purge and Owner deletion (S06 8.4) are still outstanding and must exist before any real recording is accepted.
- The activity feed page, manual outcome note, typed property schema and the 17 Polish listeners are deferred.
- `database/demo/expected.json` holds evaluation expectations only (no outcome entries); S09 owns adding them. No mismatch with `docs/demo-scenario.md` section 6 was found.

## Deviations
- One queued `RecordActivity` listener driven by `ActivityMap` instead of one class per event (same 15 Core events, registered explicitly in `OutcomesServiceProvider`).
- System-verified resolution also requires the S07 task condition (at least one completed linked task, none open), rechecked under the issue lock, and `OutcomeVerifier` runs `IssueLifecycleBridge::reconcile` first (the recovery path).
- Event timestamps now come from `BusinessClock` so the trace is consistent under the demo clock.
