# Rehearsal protocol

Goal: two consecutive rehearsals of the 22-step walkthrough, each from `php artisan demo:reset --force` to step 22, with no manual database edit, no code change and no web-server restart. Workers run as in production.

## Pre-flight
`composer test` passes; `php artisan demo:check-fixtures` passes; provider variables set (`OPENAI_API_KEY`, `AI_BASE_URL`, text, transcription, speech models and voice); `DEMO_MODE=true`; queue workers for `default` and `ai`; free disk space; browser window 1280 px; the recording file present (`docs/demo-recording.md`) or the manual transcript ready.

## Per-import checks (after each upload)
`ImportCompleted` -> `EvaluateRulesJob` -> `EvaluationRunCompleted` exactly once -> `VerifyOutcomesJob`. Check in `activity_logs` (`import.completed`) and the Issues page.
- After `week8-decline.csv`: one high decline issue, one medium target issue, one urgent notification, no outcome.
- After `week9-interim.csv`: one outcome, `during_action`, 138,000, +14,000, +11.29%, issue still monitoring.
- After `week10-recovery.csv`: one outcome, `post_action`, 150,000, +26,000, +20.97%, issue resolved `system_verified`.
- Press "Re-evaluate" or re-upload the same file: no new issue and no new outcome (idempotent).
- Trace: seven stages populated; Brief and Meeting stages show the real steps.

## Optional segments
Validation, correction and trailing-missing, each on a fresh reset, with the counters in `docs/demo-walkthrough.md`.

## Timing table (record per step, minutes)
Target total 25 minutes. Columns: step, expected, observed.

## Pass, fail and restart
A rehearsal fails on any manual fix, error page, wrong figure or restart; a failure resets the consecutive count to zero and is logged in `docs/readiness-fixes.md`. The second rehearsal runs on the demo build. After the final rehearsal wait for the queue to drain (`jobs` empty), then run `php artisan demo:reset --force` and review `docs/risks.md`.
