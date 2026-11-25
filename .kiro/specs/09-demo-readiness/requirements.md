# Requirements Document

## Introduction

S09 Demonstration Readiness and Deployment makes the finished Stage 1 application (S01 to S08) demonstrable, repeatable and deployable. It adds two demo orchestration commands (`demo:seed`, `demo:reset`), the demonstration guards, a small set of focused automated tests for those commands, the acceptance checklist that maps the 26 Definition of Done items to automated tests and manual steps, the 22-step walkthrough, the rehearsal protocol, provider failure drills, recording production instructions, local setup and deployment documents, a retention procedure and, as PoV Polish items, the authorization and responsive write-ups, the personal-data checklist and the risks register.

S09 introduces no product feature. Every business behavior shown in the demonstration is produced by the real S01 to S08 workflows: history and weekly files enter through the S03 `demo:import` command or the upload screen, evaluation runs through S04, briefs, meetings and tasks run through S05 to S07, and outcomes through S08. S09 never inserts sales records, evaluations, issues, outcomes or activity logs directly. Gaps found in S01 to S08 are recorded as explicit readiness fixes (Requirement 1) and are not changed silently.

S09 implements decisions D24, D25 and D26 of `docs/business-workflows.md`, the scenario in `docs/demo-scenario.md` (22 steps, clock timeline, fixtures, outcomes), the Definition of Done in `docs/stage-1-scope.md` section 10, and refinements R1, R5, R6, R8 and R9 of `docs/stage-1-refinements.md`, which win over any older text. Automated verification follows R5 and `.kiro/steering/verification.md`: focused Laravel feature tests on the PostgreSQL database `businesspulse_testing`, run with `composer test`, plus manual acceptance, static and build checks, and rehearsals.

### Priority tiers (R1)

- **[PoV Core]**: `demo:seed`, `demo:reset`, the guards, the focused feature tests for them, the acceptance checklist with its responsive section for the demonstration path and its build verification section, the walkthrough, the rehearsal protocol, the provider failure drills, the recording production instructions, the local setup and deployment documents, the retention document, the readiness fix log and the environment template review.
- **[PoV Polish]**: `docs/pdpa-considerations.md` and `docs/risks.md` (both still required before real data is processed), the full authorization matrix `docs/security-verification.md`, and the full per-screen responsive record beyond the acceptance checklist.
- Requirement headings carry the tag. An untagged criterion inherits the tag of its requirement heading. `tasks.md` tags every task.

### What S09 introduces

| Kind | Items |
|---|---|
| Artisan commands | [PoV Core] `demo:seed` and `demo:reset` in `app/Console/Commands/Demo` |
| Services | [PoV Core] `App\Services\Demo\{DemoGuard, DemoSeeder, DemoResetter, DemoResetFlag}` |
| Config | [PoV Core] additive key `allow_production` (`DEMO_ALLOW_PRODUCTION_DEMO`) in `config/demo.php` and the matching `.env.example` entry |
| Tests | [PoV Core] Feature tests in `tests/Feature/Demo` on the S01 harness |
| Documents | [PoV Core] `docs/acceptance-checklist.md`, `docs/demo-walkthrough.md`, `docs/rehearsal-protocol.md`, `docs/provider-failure-drills.md`, `docs/demo-recording.md`, `docs/local-setup.md`, `docs/deployment.md`, `docs/retention-and-deletion.md`, `docs/readiness-fixes.md`. [PoV Polish] `docs/security-verification.md`, `docs/pdpa-considerations.md`, `docs/risks.md` |
| Deployment files | Example configuration blocks inside `docs/deployment.md` for Nginx, PHP-FPM and php.ini, two Supervisor programs and cron. No Docker files. |
| Changes to S01 to S08 | None, except items listed in `docs/readiness-fixes.md`. The register starts empty. |

### Items S09 does not own (R9)

| Item | Owner |
|---|---|
| `BusinessClock`, the Demo_Clock_File and `demo:set-date` | S01 |
| `DemoIdentitySeeder` (company, Sales department with Expected_Branches, users) | S02 |
| `FixtureGenerator`, the six CSV fixtures, `database/demo/expected.json` weekly totals and import counters, `demo:import`, `demo:check-fixtures` | S03 |
| Default rule seeding and the expected evaluations in `expected.json` | S04 |
| `meeting-transcript.txt` | S06 |
| Expected outcome values | S08 |

### Dependencies

- S01: `BusinessClock`, `config/demo.php` (`DEMO_MODE` as `config('demo.mode')`, `clock_file`), `demo:set-date {date} [--time=09:00]`, the database queue with the `default` and `ai` queues and `retry_after` 300, the private `local` disk, `.env.example`, `config/ai.php`, `composer test`, `composer pint-test`, `npm run verify`, the README.
- S02: `DemoIdentitySeeder`, `Company` with `is_demo`, Sales department with `settings.expected_branches`, roles, `DEMO_PASSWORD` handling (minimum 12 characters).
- S03: `demo:import {fixture}`, `demo:check-fixtures`, the six fixtures and `expected.json`, `CreateImportFromUpload`, `ConfirmImport`, `ProcessImportJob`, `ReportingCoverage`, `ImportCompleted`.
- S04: default rule seeding, `business_rules.params`, `EvaluateRulesJob`, `EvaluationRunCompleted`, issues, urgent notification, evaluation expectations in `expected.json`.
- S05: brief generation, deterministic fallback, voice brief.
- S06: meetings, consent, transcription, manual transcript, task extraction, `meeting-transcript.txt`, `recordings:purge`, early recording deletion.
- S07: draft approval, tasks, My Tasks.
- S08: `VerifyOutcomesJob`, outcomes, feedback, activity log, trace.
- Test folders: `tests/Feature/Identity` (S02), `tests/Feature/Imports` (S03), `tests/Feature/Intelligence` (S04), `tests/Feature/ExecutiveIntelligenceTest.php` (S05), `tests/Feature/Meetings` (S06), `tests/Feature/Tasks` (S07), `tests/Feature/Outcomes` (S08).

### Assumptions

- A1: Fixture and demo values are fictional demonstration defaults (Meridian Home Supplies Sdn Bhd, MYR, `Asia/Kuala_Lumpur`). They are not client data and are flagged for client confirmation together with the real file layout (`docs/stage-1-scope.md` section 8).
- A2: Fixtures, their generator and their checker belong to S03. S09 reads them and never edits them.
- A3: The demo date and time are set only through the S01 command `demo:set-date`, which writes the Demo_Clock_File `storage/app/private/demo/fixed-datetime` in the format `YYYY-MM-DD HH:MM`. All clock times in the demo are 09:00 company time. S09 adds no clock mechanism.
- A4: The clock timeline of `docs/demo-scenario.md` section 2 is authoritative: 2026-09-21 09:00 while history is imported and evaluated, 2026-09-28 09:00 at the start of the demonstration, then 2026-09-30, 2026-10-05 and 2026-10-12 set by the presenter.
- A5: `demo:seed` and `demo:reset` run the history import and the S04 evaluation synchronously by setting the queue connection to `sync` for the command process only. The same Actions, listeners and job classes run as in production.
- A6: `demo:reset` refuses to run while any row exists in the `jobs` table (pending or reserved), because the command cannot safely delete records that queued jobs still reference (R8).
- A7: The Stage 1 Definition of Done is the 26 numbered items of `docs/stage-1-scope.md` section 10. The checklist copies them in order.
- A8: The recording `meeting-recording.mp3` is a synthetic voice or an actor recording of the fictional transcript of S06, mono, up to 3 minutes and 5 MB. It is committed to the repository only when its size is 2 MB or less. Otherwise it is excluded through `.gitignore`, distributed out of band, and its SHA-256 is recorded in `docs/demo-recording.md`.
- A9: The demo operates with exactly one Company in the database (Stage 1 scope A9). Production-data protection is enforced by refusing when any Company has `is_demo` false (Requirement 2).
- A10: Automated verification follows R5: S09 adds a small set of feature tests for its own commands and the checklist maps the 26 Definition of Done items to the existing automated tests of S02 to S08 and to manual steps. No browser automation, no property-based framework and no external AI call is used. Provider failure drills remain manual.
- A11: Provider failure drills use temporarily invalid configuration (for example a wrong `AI_BASE_URL` or key) on a scratch environment and never edit database rows.
- A12: Expected values in S09 documents come from `docs/demo-scenario.md` and the owning `expected.json` entries. The owning specs are the source of truth.
- A13: Backups and restore are documented and rehearsed on a scratch database. S09 adds no scheduled backup job to the application.
- A14: The reset flag of R8 is a cache entry that S03 to S08 jobs check at the start for the demo company. S09 provides the flag. If an earlier spec's job does not check it, that gap is recorded as a Readiness_Fix (Requirement 4, criterion 15).

## Glossary

- **Application**: The BusinessPulse Laravel 13 and Vue 3 Inertia web application.
- **Demo_Commands**: The two Artisan commands `demo:seed` and `demo:reset`.
- **Earlier_Demo_Commands**: The commands owned by earlier specs that S09 calls or documents: `demo:set-date` (S01), `demo:import` (S03) and `demo:check-fixtures` (S03).
- **Demo_Guard**: The component `DemoGuard` that decides whether a Demo_Command may run.
- **Demo_Company**: The Company with `is_demo` true that the S02 `DemoIdentitySeeder` creates.
- **Demo_Mode**: The state in which `config('demo.mode')` (from `DEMO_MODE`) is true.
- **Demo_Seeder**: The component `DemoSeeder` that orchestrates the seeding sequence of Requirement 3.
- **Demo_Resetter**: The component `DemoResetter` that deletes Demo_Company data and files and then reseeds.
- **Reset_Flag**: The cache entry set by the Demo_Resetter while a reset runs, which makes demo-company jobs exit without effect.
- **Reset_Lock**: The cache lock named `demo-reset`.
- **Demo_Clock_File**: The file `storage/app/private/demo/fixed-datetime` defined by S01.
- **Fixture_Set**: The files in `database/demo/` owned by S03 and S06.
- **Expected_Results**: The values in `database/demo/expected.json` and in `docs/demo-scenario.md` sections 3 and 6.
- **Scratch_Database**: A PostgreSQL database that holds no real data and may be dropped. The test database `businesspulse_testing` is one.
- **Readiness_Fix**: A defect or gap in S01 to S08 found during S09 verification and listed in `docs/readiness-fixes.md`.
- **Checklist**: `docs/acceptance-checklist.md`.
- **Security_Matrix**: `docs/security-verification.md`.
- **Walkthrough**: `docs/demo-walkthrough.md`.
- **Rehearsal**: One complete execution of the Walkthrough from `demo:reset --force` to step 22 without a direct database edit.
- **Drill**: A scripted provider failure exercise.
- **Operator**: The person who prepares and runs the demonstration or deploys the Application.
- **Owner**, **Manager**, **Staff**: Users with the S02 roles `owner`, `manager` and `staff`.
- **Reporting_Week**: An ISO week from Monday to Sunday, numbered W1 to W10 as in `docs/demo-scenario.md`.
- **DoD_Item**: One of the 26 items of `docs/stage-1-scope.md` section 10, numbered DoD-01 to DoD-26.

## Requirements

### Requirement 1: Scope control and readiness fixes [PoV Core]

**User Story:** As a project owner, I want S09 to add no product features and to report gaps in earlier specs openly, so that the demonstration shows exactly what S01 to S08 deliver.

#### Acceptance Criteria

1. THE Application SHALL receive from S09 no new business table, no new route that serves business data to end users, no new rule, no new page and no new notification.
2. THE Application SHALL receive from S09 only the Demo_Commands, their supporting services, the additive `allow_production` key in `config/demo.php`, the feature tests of Requirement 6 and documents.
3. WHEN a verification step of S09 fails because of behavior in S01 to S08, THE Operator SHALL record the failure in `docs/readiness-fixes.md` with an identifier (R1, R2 and so on), the owning spec, the observed behavior, the expected behavior, the verification step that found the failure, and a status of `open` or `fixed`.
4. THE `docs/readiness-fixes.md` file SHALL start with an empty register and SHALL state the rule of criterion 5.
5. WHERE a Readiness_Fix is applied, THE project SHALL change the owning spec's code in a separate, labelled change that references the fix identifier, and SHALL NOT change the behavior of S01 to S08 in any S09 command or document.
6. IF a Readiness_Fix would add a product feature, THEN THE project SHALL record the item as a risk (in `docs/risks.md` once it exists, otherwise in `docs/readiness-fixes.md`) instead of implementing the item.
7. THE S09 verification SHALL consist of the focused feature tests of Requirement 6 run with `composer test`, the automated tests of S02 to S08 listed in the Checklist, manual acceptance steps, static and build checks, and Rehearsals, according to `docs/stage-1-refinements.md` R5 and `.kiro/steering/verification.md`.

### Requirement 2: Demo safety guards [PoV Core]

**User Story:** As an Owner, I want destructive demo commands to refuse to touch real data, so that a mistaken command cannot erase production records.

#### Acceptance Criteria

1. THE Demo_Guard SHALL permit `demo:seed` and `demo:reset` only WHILE Demo_Mode is true.
2. IF Demo_Mode is false, THEN THE Demo_Guard SHALL stop the command with exit code 2 and the message "Demo commands run only when DEMO_MODE=true", and SHALL change no data and no file.
3. IF `APP_ENV` is `production` and `DEMO_ALLOW_PRODUCTION_DEMO` is not true, THEN THE Demo_Guard SHALL stop `demo:seed` and `demo:reset` with exit code 2.
4. IF any Company with `is_demo` false exists, THEN THE Demo_Guard SHALL stop `demo:seed` and `demo:reset` with exit code 2 and the message that real company data exists, and SHALL change no data and no file.
5. WHEN `demo:reset` runs, THE Demo_Guard SHALL require that exactly one Company exists and that the Company has `is_demo` true.
6. WHEN `demo:reset` runs without the `--force` option, THE Demo_Guard SHALL require the Operator to type the exact Demo_Company name, and SHALL stop with exit code 2 when the typed text differs.
7. WHEN `demo:reset` runs without an interactive terminal and without `--force`, THE Demo_Guard SHALL stop with exit code 2.
8. THE Demo_Guard SHALL run before any other statement of the command and SHALL evaluate all conditions, so that the message names every failed condition.
9. THE Demo_Commands SHALL NOT be registered in the Laravel scheduler.
10. THE Demo_Commands SHALL write no company name, password, API key or file content into any log line, and SHALL write the command name, the Company id and the outcome only.
11. THE Application SHALL expose no HTTP route that runs a Demo_Command.
12. THE `.env.example` file SHALL set `DEMO_MODE=false` and `DEMO_ALLOW_PRODUCTION_DEMO=false`, and `docs/deployment.md` SHALL state that production deployments keep `DEMO_MODE=false`.

### Requirement 3: Demo seeding (`demo:seed`) [PoV Core]

**User Story:** As an Operator, I want one command that prepares the fictional company through real workflows, so that the demonstration starts from a believable state.

#### Acceptance Criteria

1. WHEN the Operator runs `demo:seed` and the Demo_Guard passes, THE Demo_Seeder SHALL run the S02 `DemoIdentitySeeder`, which creates the Demo_Company, the Sales department with Expected_Branches `KL`, `PG` and `JB`, and the six users of `docs/demo-scenario.md` section 1, and SHALL create companies, departments and users by no other code path.
2. IF `DEMO_PASSWORD` is empty or shorter than 12 characters, THEN THE Demo_Seeder SHALL stop with a non-zero exit code and a message to set `DEMO_PASSWORD`, and SHALL create no user.
3. THE Demo_Seeder SHALL NOT print, log or commit the demo password.
4. THE Demo_Seeder SHALL seed the default business rules by calling the S04 default rule seeding and SHALL then set `high_drop_pct` to 15 in the `params` of the weekly decline rule, so that a 20.00% decline has severity high (D25). This override is the only demo-specific rule change.
5. THE Demo_Seeder SHALL set the demo clock to 2026-09-21 09:00 by calling `demo:set-date 2026-09-21 --time=09:00` before any import runs.
6. WHEN the Demo_Seeder loads history, THE Demo_Seeder SHALL import `history.csv` by calling `demo:import history`, which uploads the file as the Owner through `CreateImportFromUpload`, confirms it through `ConfirmImport` and processes it through `ProcessImportJob`.
7. THE Demo_Seeder SHALL run the history import and the resulting evaluation with the queue connection set to `sync` for the command process only, so that `ProcessImportJob` and `EvaluateRulesJob` complete before the command returns, and SHALL restore the configured connection when the command ends.
8. WHEN the history import completes, THE Demo_Seeder SHALL verify that the real S04 evaluation produced the evaluations expected after the seed in `expected.json` (decline W7 vs W6 `not_breached` at +0.65%, target W7 `not_breached` at 99.36%, missing data `not_breached`) and that no issue exists, and SHALL report each mismatch with a non-zero exit code.
9. WHEN the verification of criterion 8 passes, THE Demo_Seeder SHALL set the demo clock to 2026-09-28 09:00 by calling `demo:set-date 2026-09-28 --time=09:00`.
10. THE Demo_Seeder SHALL NOT trigger "Refresh intelligence" or any evaluation after setting the clock to 2026-09-28 09:00, so that the Walkthrough starts with W8 overdue and no evaluation of W8.
11. THE Demo_Seeder SHALL NOT insert, update or delete any row of `sales_records`, `imports`, `import_errors`, `rule_evaluations`, `issues`, `outcomes` or `activity_logs` by any statement outside the S03 to S08 code.
12. IF the history import ends with a status other than `completed`, or `demo:import` exits non-zero, THEN THE Demo_Seeder SHALL stop with a non-zero exit code and the sanitized error message of the import.
13. THE `demo:seed` command SHALL be idempotent: WHEN the Demo_Company already holds the seeded users, the demo rule override and a `completed` history import, and holds no other business data (no issue, brief, meeting, task, outcome or later import), THE Demo_Seeder SHALL create no duplicate and SHALL exit with code zero and a message that nothing changed.
14. IF the Demo_Company holds data other than the seeded state, THEN THE Demo_Seeder SHALL stop with a message to use `demo:reset` and SHALL change nothing.
15. THE `demo:seed` command SHALL print a summary of created users, the rule override, the import counters and the clock date-time, without secrets.

### Requirement 4: Demo reset (`demo:reset`) [PoV Core]

**User Story:** As an Operator, I want to return the demo to its starting state in one command, so that each rehearsal and each client demonstration begins identically.

#### Acceptance Criteria

1. WHEN the Operator runs `demo:reset` and the Demo_Guard passes, THE Demo_Resetter SHALL acquire the Reset_Lock, and SHALL stop with a non-zero exit code and a message that another reset is running IF the lock cannot be acquired.
2. IF any row exists in the `jobs` table (pending or reserved), THEN THE Demo_Resetter SHALL release the Reset_Lock, SHALL stop before changing anything, SHALL print the number of rows, and SHALL tell the Operator to wait for the queue workers to drain the queue and run the command again.
3. WHEN the check of criterion 2 passes, THE Demo_Resetter SHALL set the Reset_Flag, and SHALL check the `jobs` table a second time; IF a row exists, THEN THE Demo_Resetter SHALL clear the Reset_Flag, release the Reset_Lock and stop as in criterion 2.
4. WHILE the Reset_Flag is set, THE Application SHALL make a job that belongs to the Demo_Company exit without effect.
5. THE Demo_Resetter SHALL delete the rows of the Demo_Company in one database transaction, selecting rows only through `company_id`, `department_id` or a parent record that belongs to the Demo_Company, in foreign-key-safe order (children before parents).
6. THE Demo_Resetter SHALL delete the rows of the Demo_Company in these groups: activity logs, notifications, task comments, tasks, task drafts, meeting decisions and meeting-issue links, meetings, feedback, outcomes, brief items and briefs, issues, rule evaluations, sales records, import errors, imports, business rules, users, departments and the Company, using the actual table names defined by S01 to S08.
7. THE Demo_Resetter SHALL delete the private files under `imports/`, `recordings/` and brief audio paths only for ids that belong to the Demo_Company, and SHALL NOT delete any other path.
8. THE Demo_Resetter SHALL delete the Demo_Clock_File.
9. IF the database transaction fails, THEN THE Demo_Resetter SHALL roll back, leave the files untouched, clear the Reset_Flag, release the Reset_Lock, and exit with a non-zero exit code and a sanitized message.
10. THE Demo_Resetter SHALL delete files only after the database transaction commits, and SHALL report each file it could not delete.
11. WHEN the deletion has finished, THE Demo_Resetter SHALL clear the Reset_Flag and then run the same sequence as `demo:seed` (Requirement 3) while still holding the Reset_Lock, so that the seed jobs are not suppressed.
12. THE Demo_Resetter SHALL release the Reset_Lock and clear the Reset_Flag in every exit path, and the Reset_Flag SHALL expire after at most 15 minutes if the process is killed.
13. THE Demo_Resetter SHALL leave sessions, cache rows other than its own, migration rows and tables unchanged, and SHALL delete no row of `failed_jobs`; IF `failed_jobs` holds rows, THEN THE command SHALL print their count.
14. WHEN the command finishes, THE `demo:reset` command SHALL print the number of rows and files deleted per group, the seed summary of Requirement 3 and the clock date-time.
15. WHEN a verification step runs, THE Operator SHALL confirm that the jobs of S03 to S08 exit without effect for the Demo_Company while the Reset_Flag is set, and SHALL record a Readiness_Fix for each job class that does not.
16. WHEN `demo:reset --force` runs twice in a row, THE Application SHALL reach the same seeded state both times, with the same counts of users, rules, sales records and active records and the same revenue sum (idempotence).
17. THE `demo:reset` command SHALL take less than 60 seconds on the reference environment, and THE verification SHALL record the measured time.
18. THE `demo:reset` command SHALL NOT be scheduled and SHALL NOT restore the database from a dump or insert sales rows directly.

### Requirement 5: Reuse of earlier-spec demo assets [PoV Core]

**User Story:** As a project owner, I want each demo asset to have one owner, so that S09 does not duplicate or contradict the fixtures, clock and expectations of earlier specs.

#### Acceptance Criteria

1. THE S09 deliverables SHALL contain no fixture generator, no CSV fixture, no fixture checker, no `demo:import`, no `demo:set-date`, no demo clock store, no transcript fixture, no evaluation expectation file and no outcome expectation file.
2. THE Demo_Seeder SHALL set the clock only through the S01 command `demo:set-date` and SHALL import files only through the S03 command `demo:import`.
3. THE `docs/rehearsal-protocol.md` pre-flight SHALL include `php artisan demo:check-fixtures`, which SHALL exit with code zero before every Rehearsal.
4. THE S09 documents SHALL take every expected figure from `docs/demo-scenario.md` and from the owning `expected.json` entries, and SHALL state the figures of Requirement 12 identically to `docs/demo-scenario.md`.
5. WHEN a figure in an S09 document differs from `docs/demo-scenario.md` or `expected.json`, THE Operator SHALL correct the S09 document, and SHALL record a Readiness_Fix against the owning spec only when the owning source is itself inconsistent.
6. THE Walkthrough SHALL use the six S03 fixtures `history.csv`, `week8-decline.csv`, `week8-with-errors.csv`, `week8-correction.csv`, `week9-interim.csv` and `week10-recovery.csv` and the S06 transcript `meeting-transcript.txt` by name, and SHALL create no other data file.

### Requirement 6: Focused automated tests for the demo commands [PoV Core]

**User Story:** As a developer, I want a small automated test set for the destructive commands, so that the guards cannot silently break.

#### Acceptance Criteria

1. THE Application SHALL include feature tests in `tests/Feature/Demo` that run with `composer test` on the S01 harness (PostgreSQL database `businesspulse_testing`, `RefreshDatabase`), call no external AI provider and use no browser automation, no property-based framework and no SQLite.
2. THE tests SHALL verify that `demo:seed` and `demo:reset` exit with code 2 and change nothing when Demo_Mode is false, when `APP_ENV` is `production` without `DEMO_ALLOW_PRODUCTION_DEMO`, and when a Company with `is_demo` false exists, and that the failure message names every failed condition.
3. THE tests SHALL verify that `demo:reset` refuses without `--force` when the typed name differs and when no terminal is available, and refuses when the Company count is not exactly one.
4. THE tests SHALL verify that `demo:reset` stops before deleting anything when the `jobs` table holds a row, and that the Reset_Lock and Reset_Flag are released and cleared after every refusal and failure.
5. THE tests SHALL verify that `demo:seed` produces the Demo_Company, six users, the `high_drop_pct` override of 15, 126 active sales records from `history.csv`, the evaluations of Requirement 3 criterion 8, no issue, and the clock 2026-09-28 09:00, using a fixed Demo_Clock_File and a fake private disk.
6. THE tests SHALL verify that a second `demo:seed` changes nothing and that a `demo:reset --force` after additional imported data returns the same counts as the first seed (idempotence).
7. THE tests SHALL verify that `demo:reset` deletes the private files of the Demo_Company, deletes no file outside those paths and leaves no foreign-key error, after a seed followed by the `week8-decline.csv` import and its evaluation.
8. THE tests SHALL use the S03 fixtures through `demo:import` and SHALL write no sales record directly.

### Requirement 7: Acceptance checklist [PoV Core]

**User Story:** As a project owner, I want every Definition of Done item mapped to automated tests and concrete manual steps, so that readiness is judged against evidence.

#### Acceptance Criteria

1. THE Checklist SHALL contain one entry for each of the 26 items of `docs/stage-1-scope.md` section 10, numbered DoD-01 to DoD-26 in the same order and with the same wording.
2. THE Checklist SHALL give each entry: the item text, the owning specs, the automated tests that cover it (the test folder or test group of S02 to S08 or S09, or "none"), the manual steps as numbered steps (or "none"), the expected observable result, the evidence to capture (screenshot, command output or record id) and a result field with PASS, FAIL or BLOCKED.
3. THE Checklist SHALL give for each entry at least one of an automated test reference and manual steps, and SHALL give both for every entry that has an existing automated test in S02 to S08.
4. THE Checklist SHALL cover through its entries at least: deployment without Docker, login and roles, responsiveness, CSV import with preview, invalid rows, duplicate imports, corrected imports, calculations against the prepared data, the three rules, issue deduplication, missing and incomplete data, the dashboard, the AI brief with fallback, the voice brief, meeting recording upload and transcription, task drafts, human approval, staff task updates, authorization, outcome evaluation after imports, the difference between task completion and issue resolution, traceability, provider failure recovery, demo repeatability, verification and build checks, and documentation.
5. THE Checklist SHALL reference the Expected_Results where a numeric result is checked.
6. THE Checklist SHALL state for each entry whether the check needs a real AI provider.
7. THE Checklist SHALL state that provider-dependent items (DoD-13, DoD-14, DoD-15, DoD-16, DoD-23) are verified manually because no automated test calls the provider.
8. THE Checklist SHALL end with a signed summary block that lists the totals of PASS, FAIL and BLOCKED, the result of `composer test`, the open Readiness_Fixes and the date and Operator name.
9. IF an entry has the result FAIL or BLOCKED, THEN THE Checklist SHALL require a reference to a Readiness_Fix or to a risk entry.
10. THE Checklist SHALL state in its introduction that passing the checks does not make the Application production-ready.
11. THE Checklist SHALL include a check that no business record of the Demo_Company was created by a direct database statement, using the import records, `activity_logs` and the event chain as evidence.
12. THE Checklist SHALL include the check that `demo:reset` refuses to run in each guard condition of Requirement 2 and when the `jobs` table is not empty, referencing the tests of Requirement 6.
13. THE Checklist SHALL include a log check after the Rehearsal that confirms the absence of cell values, transcript text, API keys and absolute storage paths.

### Requirement 8: Responsive and accessibility verification [PoV Core for the demonstration path, PoV Polish for the full record]

**User Story:** As a client attendee on a laptop or phone, I want the demonstration screens to be usable at common widths, so that the product looks dependable on any device.

#### Acceptance Criteria

1. THE Checklist SHALL contain a responsive section [PoV Core] that lists the demonstration-path screens: login, dashboard, Data Imports, upload with preview, import detail, issues list and detail, brief, meeting detail, draft review, My Tasks, task detail, and the outcome and trace panels.
2. THE responsive section [PoV Core] SHALL require each listed screen to be checked at widths 360, 768 and 1280 px.
3. THE responsive section [PoV Core] SHALL require the checks: no horizontal scrolling of the page, tables that stack as cards where specified, touch targets of at least 40 px, no text overlap, and charts with a text-table alternative.
4. THE responsive section [PoV Core] SHALL require one keyboard-only run of the path: log in, upload a CSV, review the preview, confirm, open the issue, with a visible focus indicator on each interactive element and a logical tab order.
5. THE responsive section [PoV Core] SHALL require a check that status and severity are conveyed by icon or text and not by color alone.
6. THE responsive section [PoV Core] SHALL require a loading, empty and error state check on each Import page.
7. THE responsive section [PoV Core] SHALL record the browser name and version, the operating system and the viewport used.
8. WHEN a check fails, THE Operator SHALL record the screen, the width and a screenshot as evidence in `docs/readiness-fixes.md`.
9. THE Checklist SHALL contain a full per-screen responsive record [PoV Polish] as an appendix that adds the remaining screens (meetings list, activity feed, Settings tabs, brief history) with the same checks.

### Requirement 9: Build and baseline verification [PoV Core]

**User Story:** As a developer, I want the exact baseline commands in one place with expected outcomes, so that a clean build is demonstrated before every rehearsal.

#### Acceptance Criteria

1. THE Checklist SHALL contain a build verification section that lists these commands with the expected result: `composer validate --strict`, `composer pint-test`, `composer test` (PostgreSQL test database `businesspulse_testing`, all tests of S01 to S09 pass), `php -l` for every PHP file under `app`, `database`, `routes` and `config` (through a documented `find` pipe), `npm run type-check`, `npm run lint`, `npm run format:check` (or `npm run verify`, which chains these with `npm run build` and `composer test`), `npm run build`, `php artisan demo:check-fixtures` and `php artisan migrate:fresh --seed` on a Scratch_Database.
2. THE build verification section SHALL include a rollback check: `php artisan migrate:rollback` to zero batches and `php artisan migrate` again, on a Scratch_Database, with no error.
3. THE build verification section SHALL state that `migrate:fresh` is never run against a database that holds real data and SHALL name the check of the database name before running.
4. THE build verification section SHALL include `php artisan route:list` and `php artisan config:cache`, `route:cache` and `view:cache` as a production-readiness smoke check, followed by `config:clear`.
5. THE build verification section SHALL include `npm audit --omit=dev` and `composer audit` with the rule that high or critical findings are recorded as risks.
6. THE build verification section SHALL require a record of the verified versions of PHP, Laravel, Node, PostgreSQL, Inertia, Vue, Tailwind, shadcn-vue and ECharts, and SHALL require that `docs/architecture.md` section 11 contains these values.
7. IF a build command or `composer test` fails, THEN THE Operator SHALL stop the Rehearsal until the cause is recorded as a Readiness_Fix or fixed.

### Requirement 10: Provider failure drills [PoV Core]

**User Story:** As an Operator, I want to practice each provider failure, so that a live outage does not stop the demonstration.

#### Acceptance Criteria

1. THE `docs/provider-failure-drills.md` file SHALL define one Drill for each failure of `docs/demo-scenario.md` section 9: text AI down, speech down, transcription down, extraction down and no internet.
2. THE Drill description SHALL state for each Drill: how to induce the failure on a non-production environment (for example a wrong `AI_BASE_URL` or key and `config:clear`), the expected user-visible behavior, the recovery action and the expected final state.
3. THE text Drill SHALL verify that the brief shows the deterministic fallback labelled "AI unavailable", that the retry action is present, and that the retry succeeds after the configuration is restored.
4. THE speech Drill SHALL verify that the text brief remains, that the voice shows a failed state with a retry action, and that the retry succeeds after the configuration is restored.
5. THE transcription Drill SHALL verify that the Operator can enter the content of `meeting-transcript.txt` as a manual transcript and that extraction then continues.
6. THE extraction Drill SHALL verify that a failure produces a failed status with a retry action and that no task draft exists in the failed state.
7. THE no-internet Drill SHALL verify that import, evaluation, issue, trace and outcome screens work without a provider.
8. THE `docs/provider-failure-drills.md` file SHALL state the retry timing of `docs/stage-1-refinements.md` R6: three tries per job with backoff of 10, 30 and 60 seconds, and the per-job timeouts (brief 60 s, audio 90 s, transcription 150 s, extraction 90 s), so that the Operator knows how long to wait.
9. THE Drills SHALL verify that no API key and no raw provider error appear in the user interface or the logs.
10. THE Drills SHALL be performed manually with edits to environment values only and SHALL NOT edit database rows (assumption A11).
11. WHEN a Drill ends, THE Operator SHALL restore the environment values, run `php artisan config:clear` and `php artisan queue:restart`, and run a one-step smoke check of the provider.

### Requirement 11: Rehearsal protocol [PoV Core]

**User Story:** As a project owner, I want two clean rehearsals before the client demonstration, so that surprises are found early.

#### Acceptance Criteria

1. THE `docs/rehearsal-protocol.md` file SHALL require two complete Rehearsals, each starting with `php artisan demo:reset --force` and ending after step 22 of the Walkthrough.
2. THE `docs/rehearsal-protocol.md` file SHALL state that a Rehearsal passes only WHEN every Walkthrough step produces its expected result without a manual database edit, without a code change and without a manual restart of the web server.
3. THE `docs/rehearsal-protocol.md` file SHALL require the Operator to start the queue workers as in production (the two Supervisor programs `bp-default` and `bp-ai`, or `composer dev`) before the Rehearsal.
4. THE `docs/rehearsal-protocol.md` file SHALL list the pre-flight checks: the build checks of Requirement 9 including `composer test`, `php artisan demo:check-fixtures`, provider key presence, the clock date-time (2026-09-28 09:00 after the reset), the queue workers running, free disk space and a browser window set to 1280 px.
5. THE Rehearsal checks SHALL include the S04 and S08 chain on each import: WHEN an import completes, THE Operator SHALL verify that `ImportCompleted` is followed by `EvaluateRulesJob`, that `EvaluationRunCompleted` is dispatched once after the evaluation commits, and that `VerifyOutcomesJob` runs for the department.
6. THE Rehearsal checks SHALL verify that after `week8-decline.csv` exactly one decline issue with severity high and one target shortfall issue with severity medium exist, that exactly one urgent notification exists, and that no outcome exists.
7. THE Rehearsal checks SHALL verify that after `demo:set-date 2026-10-05` and `week9-interim.csv` the W9 outcome row is informational (phase `during_action`, 138,000.00, +14,000.00, +11.29%, 89.03% of the pre-decline week) and the issue stays in monitoring.
8. THE Rehearsal checks SHALL verify that after `demo:set-date 2026-10-12` and `week10-recovery.csv` the outcome has phase `post_action`, result `improved`, 150,000.00, +26,000.00, +20.97% relative change and 96.77% of the pre-decline week, and that the decline issue is resolved with `system_verified`.
9. THE Rehearsal checks SHALL verify that a repeated Re-evaluate action creates no duplicate evaluation, issue or outcome (idempotence).
10. THE Rehearsal checks SHALL verify that the activity feed or trace shows the full trace from import to outcome with one row per event as defined in S08.
11. THE `docs/rehearsal-protocol.md` file SHALL include the optional segments (validation demo with `week8-with-errors.csv`, correction demo with `week8-correction.csv`, trailing-missing demo with a refresh at 2026-09-28 before any upload), each on a fresh reset, with the expected counters of `docs/demo-scenario.md` section 3 and S03.
12. THE Rehearsal SHALL record for each step: start time, duration, result PASS or FAIL, and any deviation, in a table at the end of `docs/rehearsal-protocol.md` or an attached copy.
13. THE Rehearsal protocol SHALL require the Operator to record the elapsed time of each Rehearsal and SHALL state that the target time for the Walkthrough is 25 minutes or less, excluding provider waiting time.
14. IF a Rehearsal fails, THEN THE Operator SHALL record the cause as a Readiness_Fix or a risk, fix it, and restart the count of consecutive passing Rehearsals at zero.
15. THE Rehearsal protocol SHALL require that the two passing Rehearsals are consecutive and that the second runs on the build that will be demonstrated.
16. THE Rehearsal protocol SHALL state that the final state after the second Rehearsal is discarded by running `php artisan demo:reset --force` before the client demonstration, after the queue has drained.

### Requirement 12: Demonstration walkthrough [PoV Core]

**User Story:** As a presenter, I want a step-by-step script with talking points, so that the 22 steps run smoothly and the story is clear.

#### Acceptance Criteria

1. THE Walkthrough SHALL contain exactly the 22 steps of `docs/demo-scenario.md` section 7, numbered 1 to 22 in the same order.
2. THE Walkthrough SHALL give each step: the actor and login, the action, the expected screen result with the expected values from the Expected_Results, a talking point of at most 60 words, and the fallback if the step fails.
3. THE Walkthrough SHALL start with the setup command `php artisan demo:reset --force` and SHALL state that the command leaves the clock at 2026-09-28 09:00.
4. THE Walkthrough SHALL include the presenter commands `php artisan demo:set-date 2026-09-30` at step 15, `php artisan demo:set-date 2026-10-05` before the upload of `week9-interim.csv` at step 16, and `php artisan demo:set-date 2026-10-12` before the upload of `week10-recovery.csv` at step 18.
5. THE Walkthrough SHALL warn the presenter, at the beginning and at step 3, not to click "Refresh intelligence" before uploading `week8-decline.csv`, and SHALL state why: the refresh at 2026-09-28 evaluates a W8 with no rows as `period_absent` and creates a missing data issue.
6. THE Walkthrough SHALL state at step 2 that the freshness banner shows W8 as overdue (6 trading days across 3 branches, 18 branch-days not received) and that the dashboard shows W7 at 155,000.00.
7. THE Walkthrough SHALL state at steps 5 and 6 the expected results: decline W8 vs W7 breached with severity high at −20.00% (124,000.00 vs 155,000.00, −31,000.00), target shortfall breached with severity medium at 79.49% (124,000.00 / 156,000.00), missing data not breached (18 of 18), and one urgent notification for the decline issue only.
8. THE Walkthrough SHALL cover the meeting steps 10 to 13 with the expected 4 task drafts: approval of drafts 1 and 2, the assignee (Daniel Tan) and deadline edit and approval of draft 3, the rejection of draft 4, the issue becoming `action_in_progress`, and the point that the injection line has no effect and no task is assigned to the Owner.
9. THE Walkthrough SHALL state at step 17 that W9 (`week9-interim.csv`, 138,000.00, +14,000.00, +11.29% against the W8 baseline, 89.03% of the pre-decline week) is shown as "during action, not used for verification" and that the issue stays in monitoring.
10. THE Walkthrough SHALL state at steps 19 and 20 the expected outcome: W10 (`week10-recovery.csv`, 150,000.00) is `post_action`, the result is improved with +26,000.00 absolute, +20.97% relative and 96.77% of the pre-decline week, the resolution condition is met (150,000.00 is at least 155,000.00 × 0.85 = 131,750.00), and the issue is resolved with `system_verified`.
11. THE Walkthrough SHALL state that the week-over-week change of W10 vs W9 (+8.70%) is evidence only and not the outcome metric, and SHALL present the outcome with the wording "Observed change after actions" without a claim that an action caused the improvement.
12. THE Walkthrough SHALL state the target shortfall outcome as optional: the Owner moves that issue to monitoring with a reason, and W10 shows 96.15%, +16.66 percentage points, improved.
13. THE Walkthrough SHALL state at the beginning that the data is fictional and that the AI output is reviewed by a human before any task exists.
14. THE Walkthrough SHALL state the expected waiting times for transcription, extraction, brief and speech steps and the talking points to use while waiting.
15. THE Walkthrough SHALL list the logins and the roles of the six demo users without printing the password and SHALL state that the password comes from the Operator's `DEMO_PASSWORD` value.
16. THE Walkthrough SHALL link each failure contingency to the matching Drill in `docs/provider-failure-drills.md`, and SHALL state that the manual transcript path (pasting `meeting-transcript.txt`) is the fallback when transcription fails.
17. THE Walkthrough SHALL list the optional segments (validation, correction, trailing-missing) and SHALL require a `demo:reset --force` before each.
18. THE Walkthrough SHALL end with a closing section that lists what is out of Stage 1 scope (for example Excel import, multiple companies and external messaging) and the items requiring client confirmation from `docs/stage-1-scope.md` section 8.

### Requirement 13: Local setup documentation [PoV Core]

**User Story:** As a developer, I want native setup instructions that work without Docker, so that I can run and verify the Application on my own machine.

#### Acceptance Criteria

1. THE `docs/local-setup.md` file SHALL describe the native setup on macOS and Ubuntu with the prerequisites PHP (version verified in S01), Composer, Node, npm and PostgreSQL, and SHALL state that Docker is not used.
2. THE `docs/local-setup.md` file SHALL give the numbered steps: clone, `composer install`, `npm ci`, copy `.env.example` to `.env`, `php artisan key:generate`, create the PostgreSQL role and the two databases (the application database and `businesspulse_testing`), set the database variables, `php artisan migrate`, and start the development processes.
3. THE `docs/local-setup.md` file SHALL describe how to run the web server, the Vite server and the queue workers with the exact commands (the S01 `composer dev` command, or separate terminals with `php artisan queue:work database --queue=default` and `php artisan queue:work database --queue=ai`), and SHALL state that the Operator starts them manually.
4. THE `docs/local-setup.md` file SHALL describe how to configure the demo: set `DEMO_MODE=true`, `DEMO_PASSWORD` (at least 12 characters), the AI variables, run `php artisan demo:reset --force`, and log in as a demo user.
5. THE `docs/local-setup.md` file SHALL describe the Earlier_Demo_Commands: `demo:set-date {date} [--time=09:00]`, `demo:import {fixture}` and `demo:check-fixtures`, and how deleting the Demo_Clock_File returns the clock to real time.
6. THE `docs/local-setup.md` file SHALL describe how to create and use a Scratch_Database for verification, including that `composer test` uses `businesspulse_testing` and that `migrate:fresh` runs only on a Scratch_Database.
7. THE `docs/local-setup.md` file SHALL list the verification commands of Requirement 9.
8. THE `docs/local-setup.md` file SHALL list common problems and fixes: missing PHP extensions (`pdo_pgsql`, `mbstring`, `fileinfo`), queue workers not running (imports stay in `processing`), stale cached config, file permissions on `storage` and `bootstrap/cache`, upload size errors, and `demo:reset` refusing because the `jobs` table is not empty.
9. THE `docs/local-setup.md` file SHALL state the local upload limits needed for 20 MB recordings and 5 MB CSV files (`upload_max_filesize` 20M, `post_max_size` 25M) and how to check them.

### Requirement 14: Deployment documentation [PoV Core]

**User Story:** As an Operator, I want complete native deployment instructions for an Ubuntu VPS, so that the demonstration can be hosted over HTTPS with background jobs working.

#### Acceptance Criteria

1. THE `docs/deployment.md` file SHALL describe the architecture of `docs/architecture.md` section 9: Ubuntu VPS, Nginx, PHP-FPM, local PostgreSQL, two Supervisor-run queue workers, cron scheduler, Let's Encrypt HTTPS, private storage outside the web root, nightly backups, and no Docker.
2. THE `docs/deployment.md` file SHALL list the server preparation steps: packages with the PHP version verified in S01 and its extensions, Nginx, PostgreSQL, Supervisor, Certbot, Node (build only), Composer, a non-root deploy user, a firewall that allows ports 22, 80 and 443 only, and automatic security updates.
3. THE `docs/deployment.md` file SHALL give an Nginx server block that: redirects HTTP to HTTPS, sets the web root to `public`, passes PHP to PHP-FPM only for `index.php`, denies dotfiles except `.well-known`, sets `client_max_body_size 25m`, sets security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`) and enables HSTS after HTTPS is verified.
4. THE `docs/deployment.md` file SHALL give PHP-FPM and `php.ini` settings: `upload_max_filesize = 20M`, `post_max_size = 25M`, `memory_limit` of at least 256M, `max_execution_time` at least 60 for web requests, OPcache enabled, and a statement that the 25 MB Nginx limit and the 25M PHP post limit cover the 20 MB recording limit, and that the CSV limit of S03 is 5 MB.
5. THE `docs/deployment.md` file SHALL give two Supervisor programs: `bp-default` running `php artisan queue:work database --queue=default --tries=3 --timeout=180 --max-time=3600` and `bp-ai` running `php artisan queue:work database --queue=ai --tries=3 --timeout=180 --max-time=3600`, each as the deploy user with `autorestart=true`, `stopwaitsecs=200`, `stopasgroup=true`, `killasgroup=true` and log files outside the web root.
6. THE `docs/deployment.md` file SHALL state the timing rule of R6: `retry_after` is 300 in `config/queue.php` for the database connection, which is greater than the longest job timeout (150 seconds for transcription) plus margin; the worker `--timeout=180` is a ceiling above every job `$timeout`; and `retry_after` must be re-checked if any job timeout rises above 150 seconds.
7. THE `docs/deployment.md` file SHALL give the cron line `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1` for the deploy user and SHALL state that the scheduler runs the S06 `recordings:purge` command daily and no demo command.
8. THE `docs/deployment.md` file SHALL describe HTTPS with Let's Encrypt through Certbot, including automatic renewal and the renewal test command.
9. THE `docs/deployment.md` file SHALL describe the private storage: `storage/app/private` is outside `public`, files are served only through authorized streaming, permissions allow the deploy user and the PHP-FPM user (group) to write, and no symlink to private storage exists in `public`.
10. THE `docs/deployment.md` file SHALL provide a production `.env` table with: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` with HTTPS, `APP_KEY`, database variables, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_SECURE_COOKIE=true`, `SESSION_DRIVER` as defined by S01, `LOG_LEVEL`, the AI variables, `DEMO_MODE=false` (or `true` only on the dedicated demo server with its own database), and the rule that secrets are never committed.
11. THE `docs/deployment.md` file SHALL describe the backups: a nightly `pg_dump` in custom format to a directory outside the web root, an `rsync` of `storage/app/private` to a second location, a retention of 14 daily backups, file permissions 600, and a restore procedure verified on a Scratch_Database.
12. THE `docs/deployment.md` file SHALL give the deployment steps in order: maintenance mode, `git pull`, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan config:cache`, `route:cache`, `view:cache`, `php artisan queue:restart`, leaving maintenance mode, and a post-deployment smoke test.
13. THE `docs/deployment.md` file SHALL give a rollback procedure: restore the previous release commit, restore the database from the last backup when a migration is not reversible, run `queue:restart` and repeat the smoke test.
14. THE `docs/deployment.md` file SHALL give the post-deployment smoke test: the home page over HTTPS, login, a CSV import completing through the `bp-default` worker, a brief generation through the `bp-ai` worker (provider reachable), a file download that requires authentication, and a request for `/.env` that returns 403 or 404.
15. THE `docs/deployment.md` file SHALL state that `demo:*` commands are not run on a server that holds real data (Requirement 2), and SHALL describe the dedicated demo server option with `DEMO_MODE=true` and `DEMO_ALLOW_PRODUCTION_DEMO=true`.
16. THE `docs/deployment.md` file SHALL list the server log locations and the commands to read them without printing secrets.
17. THE `docs/deployment.md` file SHALL state that the document describes a demonstration deployment and that the passing checks do not make the Application production-ready.

### Requirement 15: Environment template review [PoV Core]

**User Story:** As a developer, I want `.env.example` to list every variable the Application reads, so that a new environment never fails on a missing setting.

#### Acceptance Criteria

1. WHEN S09 verification runs, THE project SHALL compare every `env(...)` call in `config/*.php` with the keys of `.env.example` and `.env.testing.example` and SHALL list each variable that is missing from `.env.example` in `docs/readiness-fixes.md`.
2. THE `.env.example` file SHALL contain the variables `AI_BASE_URL`, `AI_TEXT_MODEL`, `AI_TRANSCRIPTION_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE`, `OPENAI_API_KEY`, `DEMO_MODE`, `DEMO_PASSWORD`, `QUEUE_CONNECTION`, `CACHE_STORE`, the database variables and `APP_*` variables.
3. WHERE S09 adds a configuration key, THE `.env.example` file SHALL contain the matching variable with a safe default and a comment, which is `DEMO_ALLOW_PRODUCTION_DEMO=false`.
4. THE `.env.example` file SHALL contain no real secret, key, password or personal data.
5. THE `.env.example` file SHALL set `DEMO_MODE=false` and leave `DEMO_PASSWORD` and `OPENAI_API_KEY` empty, and SHALL contain no `DEMO_FIXED_DATE` variable (S01 assumption A4).
6. THE project SHALL document each variable in `docs/local-setup.md` or `docs/deployment.md` with its purpose, whether it is required, and its default.
7. THE project SHALL verify that no variable is read through `env()` outside `config/*.php`.

### Requirement 16: Recording production instructions [PoV Core]

**User Story:** As an Operator, I want clear instructions to produce the demo recording, so that the meeting step works the same way every time.

#### Acceptance Criteria

1. THE `docs/demo-recording.md` file SHALL document two ways to produce `meeting-recording.mp3` from the S06 `meeting-transcript.txt`: a read-aloud recording by volunteers, and a synthetic voice generated with the speech model configured in `AI_SPEECH_MODEL`.
2. THE `docs/demo-recording.md` file SHALL state the recording requirements: format mp3, mono, English, up to 3 minutes, size up to 5 MB (within the 20 MB product limit), clear voices, speakers Aisyah, Priya, Farid, Daniel and Wei Han, and a spoken recording consent statement at the start.
3. THE `docs/demo-recording.md` file SHALL state that the content of the recording matches items 1 to 8 of `docs/demo-scenario.md` section 4, including the ambiguous "Daniel", the unassigned stock check and the injection line.
4. IF the size of `meeting-recording.mp3` is 2 MB or less, THEN THE project SHALL commit the file to `database/demo/`.
5. IF the size of `meeting-recording.mp3` is greater than 2 MB, THEN THE project SHALL exclude the file through `.gitignore`, and THE `docs/demo-recording.md` file SHALL record its SHA-256 hash and the location where the Operator obtains it.
6. THE project SHALL commit no other binary file larger than 2 MB for S09 and no audio file other than `meeting-recording.mp3`.
7. THE `docs/demo-recording.md` file SHALL state that the manual transcript path (Drill for transcription failure) is the fallback when the recording or transcription fails.
8. THE `docs/demo-recording.md` file SHALL contain only fictional content and no real person, company or bank identifier.

### Requirement 17: Retention and deletion procedure [PoV Core]

**User Story:** As an Owner, I want a documented way to delete recordings and demo data, so that data does not stay longer than agreed.

#### Acceptance Criteria

1. THE `docs/retention-and-deletion.md` file SHALL state the Stage 1 retention default of 30 days for meeting recordings (`companies.recording_retention_days`, Stage 1 scope A8), that the value is a demonstration default, and the name of the S06 command `recordings:purge`.
2. THE `docs/retention-and-deletion.md` file SHALL describe how `recordings:purge` is scheduled (`schedule:run` through cron, Requirement 14 criterion 7) and how to run the command manually.
3. THE `docs/retention-and-deletion.md` file SHALL describe the Owner's early deletion of a recording through the Application, and the expected result in the database (the recording file removed, `recording_path` null and `recording_deleted_at` set, the transcript kept).
4. THE `docs/retention-and-deletion.md` file SHALL list the stored data classes with location and the deletion method: recordings, imported CSV files (retained for traceability, S03), brief audio, transcripts, logs, backups, and the database.
5. THE `docs/retention-and-deletion.md` file SHALL state that backups retain deleted data until they expire (14 days) and how to remove a specific backup on request.
6. THE `docs/retention-and-deletion.md` file SHALL describe how to remove the Demo_Company with `demo:reset` and how to remove all demo data and files before a real deployment.
7. THE `docs/retention-and-deletion.md` file SHALL include a verification procedure: on a Scratch_Database, create a recording through the meeting workflow, move the clock more than 30 days ahead with `demo:set-date`, run `recordings:purge`, and check that the file is removed and no other file is removed.
8. IF the S06 retention command does not support the verification of criterion 7 without a direct database edit, THEN THE project SHALL record a Readiness_Fix instead of using a direct edit.

### Requirement 18: Authorization verification matrix [PoV Polish]

**User Story:** As an Owner, I want proof that each role sees only what it should, so that the demonstration cannot expose data across departments or roles.

#### Acceptance Criteria

1. THE Security_Matrix SHALL list every route of S01 to S08 that serves an authenticated page, a state-changing action or a file, using `php artisan route:list` as the source, with the columns route name, method, URL, the expected result for each actor, and the automated test that covers the cell, if any.
2. THE Security_Matrix SHALL cover these actors: unauthenticated visitor, inactive user, Owner, Manager of the department, Manager of another department (using a scratch second department), Staff who is the assignee, and Staff who is not the assignee.
3. THE Security_Matrix SHALL state the expected result per cell as 200, 302 to login, 403, 404, 419, 422 or 429, with the rule reference in the owning spec's requirements.
4. THE Security_Matrix SHALL include direct URL access to guessed ids, to files (CSV source, recording, brief audio) and to Inertia partial reloads for each role.
5. THE Security_Matrix SHALL include CSRF checks (missing token returns 419) on at least one state-changing route per module.
6. THE Security_Matrix SHALL include the throttling checks: login, uploads and AI endpoints, with the expected 429 and Retry-After behavior.
7. THE Security_Matrix SHALL include checks that no Inertia prop or response contains `stored_path`, absolute paths, `OPENAI_API_KEY`, `DEMO_PASSWORD` or password hashes, using the browser's page props view.
8. THE Security_Matrix SHALL include the second department scratch setup steps and SHALL state that this setup runs only on a Scratch_Database.
9. THE Security_Matrix SHALL require a record of each cell as PASS or FAIL with the observed status code.
10. IF a cell fails, THEN THE Security_Matrix SHALL require an entry in `docs/readiness-fixes.md` and SHALL treat the matrix as not passed.

### Requirement 19: Personal data and third-party processing checklist [PoV Polish]

**User Story:** As an Owner, I want a plain list of personal-data and third-party questions, so that nothing real is processed before they are answered.

#### Acceptance Criteria

1. THE `docs/pdpa-considerations.md` file SHALL state in its introduction that Stage 1 uses fictional data only, that the document is a checklist and not legal advice, that the project makes no claim of compliance with the Malaysia Personal Data Protection Act 2010 or any other law, and that the document is required before real data is processed.
2. THE `docs/pdpa-considerations.md` file SHALL inventory the personal data the Application processes: user names, emails and password hashes, staff voice recordings, transcripts that contain staff names and statements, task assignments and comments, and activity logs.
3. THE `docs/pdpa-considerations.md` file SHALL inventory the data sent to the third-party AI provider per feature as in `docs/architecture.md` section 6: structured metrics and issue evidence for briefs, the audio file for transcription, transcript text, meeting date, timezone and staff display names for extraction, and brief text for speech.
4. THE `docs/pdpa-considerations.md` file SHALL give a checklist table with the columns principle, question, current Stage 1 behavior, evidence in the Application, and status (`open`, `answered`, `needs client decision`), covering the general, notice and choice, disclosure, security, retention, data integrity, access, and cross-border transfer considerations.
5. THE `docs/pdpa-considerations.md` file SHALL list the open client decisions of `docs/stage-1-scope.md` section 8: recording consent policy, data residency and the position on sending data to a third-party AI provider, retention periods, language of meetings and the user roster.
6. THE `docs/pdpa-considerations.md` file SHALL state the in-application controls that exist: the consent checkbox persisted per meeting, private storage, authorized downloads, role-based access, log redaction and the retention command, each referencing the owning spec requirement.
7. THE `docs/pdpa-considerations.md` file SHALL list the actions needed before real data is processed: a data processing agreement with the provider, a recording notice for participants, a retention decision, an access and correction procedure, a breach response contact and a cross-border transfer assessment.
8. THE `docs/pdpa-considerations.md` file SHALL state that provider data-use and retention settings are to be confirmed from the provider's current terms, and SHALL cite no unverified provider claim.

### Requirement 20: Known risks register [PoV Polish]

**User Story:** As a project owner, I want one register of known risks and limits, so that the client demonstration is honest about what Stage 1 does not do.

#### Acceptance Criteria

1. THE `docs/risks.md` file SHALL list each risk with an identifier, description, likelihood (low, medium or high), impact (low, medium or high), mitigation or contingency, owner and status.
2. THE `docs/risks.md` file SHALL include these risks: AI provider outage or latency during the demonstration, transcription accuracy with accents and background noise, extraction variability of task titles, prompt-injection attempts through transcripts, third-party processing of personal data, demonstration defaults that differ from the client's real data (file layout, branches, holidays, thresholds), the database queue throughput, single-server deployment without high availability, backups not yet restored in production, the demo clock file differing from the real date, the ambiguity of ISO-week reporting for holidays, the recording retention command being PoV Polish in S06, and a presenter clicking "Refresh intelligence" before the week 8 upload.
3. THE `docs/risks.md` file SHALL include the cost and rate limits of the AI provider as a risk with the mitigation of rehearsal budgeting.
4. THE `docs/risks.md` file SHALL include an explicit statement of the Stage 1 limits: fictional data only, no production certification, no compliance claim and no support for Stage 2 features.
5. THE `docs/risks.md` file SHALL cross-reference each risk that came from a failed verification step with the identifier of its Readiness_Fix.
6. WHEN the last Rehearsal ends, THE Operator SHALL review the register and set the status of each risk to `open`, `mitigated` or `accepted`.

### Requirement 21: Documentation quality and honest reporting [PoV Core]

**User Story:** As a reviewer, I want documents that agree with each other and with the code, so that following them gives the described result.

#### Acceptance Criteria

1. THE S09 documents SHALL use the same names for roles, statuses, commands, environment variables and file paths as `docs/data-model.md`, `docs/architecture.md`, `docs/demo-scenario.md` and the S01 to S08 specs.
2. WHEN S09 is implemented, THE project SHALL update `docs/architecture.md` with a short "Demonstration tooling" section that lists the Demo_Commands, the Earlier_Demo_Commands, the Demo_Clock_File, the Reset_Lock, the Reset_Flag and the guard conditions, and SHALL update `docs/demo-scenario.md` only to add references to the S09 files.
3. THE S09 documents SHALL contain no password, API key, real email address or real personal data.
4. THE S09 documents SHALL use numbered steps with an expected result for each command, and SHALL mark every command that deletes data with the text "DESTRUCTIVE" and its guard.
5. THE verification record SHALL report results honestly, including failures and blocked checks, and SHALL NOT state that the Application is production-ready.
6. WHEN a command or setting in a document differs from the implemented command or setting, THE project SHALL correct the document in the same change.
7. THE S09 documents SHALL be written in plain English that a non-developer Operator can follow for the Walkthrough, and SHALL define each technical term used in the Operator sections at first use.
8. THE S09 documents SHALL refer to the Walkthrough as having 22 steps and SHALL contain no other step count for it.

### Requirement 22: Command behavior and output standards [PoV Core]

**User Story:** As an Operator, I want predictable command results and messages, so that I know whether a command worked.

#### Acceptance Criteria

1. THE Demo_Commands SHALL exit with code zero on success, code 2 on a guard refusal, and another non-zero code on any other failure.
2. THE Demo_Commands SHALL print plain-text messages that state what was done or why the command refused, and SHALL print no stack trace unless the `-v` option is given.
3. THE Demo_Commands SHALL print no value of `DEMO_PASSWORD`, `OPENAI_API_KEY`, session data or file contents, even with the `-v` option.
4. WHEN a Demo_Command is run with the `--help` option, THE Demo_Commands SHALL describe the purpose, options, guard conditions and the DESTRUCTIVE marking for `demo:reset`.
5. THE Demo_Commands SHALL be registered through Laravel's command discovery and SHALL appear in `php artisan list` under the `demo` namespace together with the Earlier_Demo_Commands.
6. THE Demo_Commands SHALL follow the Actions-for-multi-step rule of `docs/architecture.md` section 10 and SHALL perform every multi-write operation of the reset inside one database transaction, except file deletion, which follows the transaction.
7. THE `demo:seed` and `demo:reset` commands SHALL signal `php artisan queue:restart` through the Laravel cache restart key when they finish, so that a running worker loads the current state.
8. THE Demo_Commands SHALL perform authorization by environment guard only and SHALL NOT bypass any policy of the Application when they call S02 to S08 Actions.

### Requirement 23: Scope boundaries [PoV Core]

**User Story:** As a project owner, I want S09 to stay inside Stage 1 readiness, so that no new feature slips in before the demonstration.

#### Acceptance Criteria

1. THE S09 deliverables SHALL include no multi-company support, no extra department, no Excel import, no automated data sync, no external email or WhatsApp, no chatbot and no scheduled demo reset.
2. THE S09 deliverables SHALL include no admin screen for demo control, no web button for reset, and no web form for the demo date.
3. THE S09 deliverables SHALL include no Docker file, no Kubernetes file, no Redis configuration and no continuous-integration pipeline.
4. THE S09 deliverables SHALL include no browser automation suite, no property-based framework and no test that calls an external AI provider.
5. THE S09 deliverables SHALL include no change that makes the Application production-certified, and SHALL NOT state such a claim in any document.
6. THE S09 deliverables SHALL include no monitoring service, no error-tracking service and no log-shipping setup, and SHALL describe only the log files and commands for reading them.
