# Stage 1 acceptance checklist

Passing these checks and `composer test` does not make the application production-ready. They show that the Stage 1 demonstration works on fictional data. Status per item: PASS, FAIL or BLOCKED (a FAIL or BLOCKED needs a Readiness_Fix id from `docs/readiness-fixes.md` or a risk reference). Record the observed value next to the expected one.

Tests folders: `tests/Feature/Identity` (S02), `Imports` (S03), `Intelligence` (S04), `ExecutiveIntelligenceTest`, `BriefGenerationTest`, `DashboardTest` (S05), `Meetings` (S06), `Tasks` (S07), `Outcomes` (S08), `Demo` (S09). Expected values come from `database/demo/expected.json` and `docs/demo-scenario.md`.

## Definition of Done (copied from `docs/stage-1-scope.md` section 10)

| ID | Item | Owning specs | Automated | Manual (walkthrough step) | Provider | Status |
|---|---|---|---|---|---|---|
| DoD-01 | The application can be deployed without Docker. | S01, S09 | none | `docs/deployment.md` steps and smoke test | no | |
| DoD-02 | Authorized users can log in using appropriate roles. | S02 | `Identity/AuthenticationTest`, `AuthorizationTest`, `SharedPropsNavigationTest` | Step 1; log in as each demo role (Owner, Manager, Staff) | no | |
| DoD-03 | The interface is polished and responsive. | S01 to S08 | none | Responsive section below | no | |
| DoD-04 | A valid CSV can be uploaded and imported. | S03 | `Imports/ImportWorkflowTest`, `DemoFixturesTest` | Steps 3 and 4 | no | |
| DoD-05 | Invalid rows are reported. | S03 | `Imports/ImportValidationTest` | Validation segment (`week8-with-errors.csv`: 17 valid, 3 invalid) | no | |
| DoD-06 | Duplicate imports do not inflate business metrics. | S03 | `Imports/ImportWorkflowTest` (same file twice) | Re-upload `week8-decline.csv`; totals stay 124,000 | no | |
| DoD-07 | Corrected imports behave according to the documented policy. | S03, S04 | `Imports/ImportWorkflowTest`, `Intelligence/DemoExpectationsTest` (correction) | Correction segment (16 unchanged, 2 corrected, W8 126,000) | no | |
| DoD-08 | Business calculations match the prepared source data. | S03, S04 | `Intelligence/MetricCalculatorTest`, `DemoExpectationsTest`, `Imports/DemoFixturesTest` | `php artisan demo:check-fixtures`; compare dashboard and evidence values | no | |
| DoD-09 | The rule engine detects actual business conditions. | S04 | `Intelligence/RuleEvaluationTest`, `DemoExpectationsTest` | Steps 5 and 6 (high decline -20.00%, medium target 79.49%) | no | |
| DoD-10 | Repeated detections do not create duplicate unresolved issues. | S04 | `Intelligence/IssueEngineTest` | Press "Refresh intelligence" twice: no new issue, detection count 2 | no | |
| DoD-11 | Missing or incomplete data is clearly identified. | S03, S04, S05 | `Imports/ReportingCoverageTest`, `Intelligence/CoverageAndCutoffTest`, `DashboardTest` | Step 2 banner (18 branch-days); validation and trailing-missing segments | no | |
| DoD-12 | The executive dashboard displays real calculated information. | S05, S07 | `DashboardTest`, `ExecutiveIntelligenceTest` | Step 2 and after step 6; compare with the evidence | no | |
| DoD-13 | Executive brief generation works through a real AI integration. | S05 | `BriefGenerationTest` (fake provider only) | Step 8 with a real provider | yes | |
| DoD-14 | Voice brief generation and playback work. | S05 | `BriefGenerationTest` (audio, access) | Step 9 with a real provider | yes | |
| DoD-15 | Meeting recordings can be uploaded and transcribed. | S06 | `Meetings/RecordingUploadTest`, `TranscriptionJobTest` (fake provider) | Steps 10 and 11 with a real provider | yes | |
| DoD-16 | Meeting transcripts produce structured task drafts. | S06 | `Meetings/ExtractionTest`, `EvidenceBackedSuggestionsTest` | Step 12 with a real provider (4 drafts) | yes | |
| DoD-17 | Tasks cannot be assigned without explicit human approval. | S06, S07 | `Tasks/TaskApprovalRulesTest`, `TaskApprovalIdempotencyTest`, `Meetings/ExtractionTest` (draft only) | Step 13 | no | |
| DoD-18 | Assigned staff can update their tasks. | S07 | `Tasks/TaskExecutionTest`, `TaskAccessTest` | Steps 14 and 15 | no | |
| DoD-19 | Unauthorized users cannot access restricted data. | S02 to S08 | `Identity/AuthorizationTest`, `ImportAuthorizationTest`, `Meetings/MeetingAuthorizationTest`, `RecordingAccessTest`, `Tasks/TaskAccessTest`, `Outcomes/OutcomeAccessFeedbackTest`, `BriefGenerationTest` | Staff and out-of-scope direct URLs return 403/404 | no | |
| DoD-20 | Subsequent imports trigger outcome evaluation. | S04, S08 | `Outcomes/OutcomePhaseTest` | Steps 16 to 19 | no | |
| DoD-21 | The system distinguishes task completion from issue resolution. | S07, S08 | `Tasks/TaskIssueLifecycleTest`, `TaskReopenTest`, `Outcomes/OutcomePhaseTest` | Steps 15 and 20 (monitoring after tasks; resolved only after W10) | no | |
| DoD-22 | The issue-to-outcome history is traceable. | S08 | `Outcomes/ActivityTraceTest` | Step 22 | no | |
| DoD-23 | Provider failures have understandable recovery paths. | S05, S06 | none | `docs/provider-failure-drills.md` (five drills) | yes | |
| DoD-24 | A complete demonstration can be repeated using fictional data. | S09 | `Demo/DemoSeedTest`, `DemoResetTest` | Two consecutive rehearsals (`docs/rehearsal-protocol.md`) | yes | |
| DoD-25 | The application passes the defined manual verification and build checks. | all | `composer test` | Build verification section below | no | |
| DoD-26 | Local setup and deployment instructions are documented. | S01, S09 | none | Follow `docs/local-setup.md` and `docs/deployment.md` on a clean environment | no | |

DoD-13 to DoD-16 and DoD-23 are manual because they need a real provider. They are BLOCKED until credentials and `database/demo/meeting-recording.mp3` exist (RF-04).

## Additional checks
- No direct database insert: every demo record comes from an import, an approval or a job. Confirm `sales_records.import_id` is never null and `demo:seed` output shows the import counters.
- `demo:reset` refusals: with `DEMO_MODE=false` (exit 2), with a real company present (exit 2), with a queued job (refused, prints the count), without `--force` and a wrong typed name (exit 2).
- After a rehearsal, search `storage/logs/laravel.log` for `ERROR` and for the provider key (must be absent).

## Responsive section (demonstration path)
Check Today, Data Imports (upload, preview, result), Issues, Issue detail, Briefs, Meeting detail (drafts), Tasks, My Tasks, Outcomes and the Outcome comparison at 360, 768 and 1280 px: no horizontal scroll at 360, every control reachable and at least 40 px, keyboard-only run of the path (Tab, Enter, Escape), status never conveyed by colour alone, import page states (empty, error, in progress, done). Record the browser and version.

## Build verification
Run and record each: `composer validate --strict`; `./vendor/bin/pint --test`; `composer test`; `npm run type-check`; `npm run lint`; `npm run format:check` (or `npm run verify`, which chains type-check, lint, format check, build and `composer test`); `npm run build`; `php artisan demo:check-fixtures`; `migrate:fresh --seed` on a SCRATCH database (check `current_database()` first) and `migrate:rollback` then `migrate`; a cache smoke check (`php artisan cache:clear` and a page load); `npm audit --omit=dev`; `composer audit`; record the verified versions (`docs/architecture.md` section 11).

## Summary (sign after the rehearsals)
Totals: PASS __ / FAIL __ / BLOCKED __. `composer test`: ____ tests, ____. Open Readiness_Fixes: ____. Date: ____. Operator: ____.
