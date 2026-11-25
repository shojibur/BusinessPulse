# Requirements Document

## Introduction

S09 Demonstration Readiness and Deployment makes the finished Stage 1 application (S01 to S08) demonstrable, repeatable and deployable. It adds demo orchestration commands, CSV and transcript fixtures, verification data and scripts, rehearsal and walkthrough documents, native deployment and local setup documents, a personal-data (PDPA) checklist, a retention procedure and a risks register.

S09 introduces no product feature. Every business behavior shown in the demonstration is produced by the real S01 to S08 workflows: history and weekly files enter through the S03 import Actions and `ProcessImportJob`, evaluation runs through S04, briefs, meetings and tasks run through S05 to S07, and outcomes through S08. S09 never inserts sales records, evaluations, issues, outcomes or activity logs directly. Gaps found in S01 to S08 are recorded as explicit readiness fixes (Requirement 1) and are not changed silently.

S09 implements decisions D24, D25 and D26 of `docs/business-workflows.md`, the scenario in `docs/demo-scenario.md`, the Stage 1 scope in `docs/stage-1-scope.md`, and the steering file `.kiro/steering/verification.md` (no automated test suites in Stage 1).

### What S09 introduces

| Kind | Items |
|---|---|
| Artisan commands | `demo:seed`, `demo:reset`, `demo:set-date {date}`, `demo:check-fixtures` in `app/Console/Commands/Demo` |
| Services | `App\Services\Demo\{DemoGuard, DemoSeeder, DemoResetter, DemoClockStore, FixtureChecker, FixtureGenerator}` |
| Seeders | `Database\Seeders\DemoSeeder` (orchestration only, reuses the S02 demo user seeder and the S04 default rule seeding) |
| Fixtures | `database/demo/history.csv`, `week8-decline.csv`, `week8-with-errors.csv`, `week8-correction.csv`, `week9-recovery.csv`, `meeting-transcript.txt`, `expected.json`, `README.md`; recording `meeting-recording.mp3` (Requirement 7) |
| Documents | `docs/acceptance-checklist.md`, `docs/security-verification.md`, `docs/demo-walkthrough.md`, `docs/rehearsal-protocol.md`, `docs/provider-failure-drills.md`, `docs/local-setup.md`, `docs/deployment.md`, `docs/pdpa-considerations.md`, `docs/retention-and-deletion.md`, `docs/risks.md`, `docs/readiness-fixes.md` |
| Deployment files | Example configuration blocks inside `docs/deployment.md` for Nginx, PHP-FPM and php.ini, Supervisor and cron. No Docker files. |
| Config | Additive keys in `config/demo.php` (`company_name`, `default_date`, `clock_file`) |
| Changes to S01 to S08 | None, except items listed in `docs/readiness-fixes.md`. The assumed fix R1 (A5) touches `BusinessClock`. |

### Dependencies

- S01: `BusinessClock`, `config/demo.php`, `.env.example`, `config/ai.php`, database queue, private `local` disk, verification commands (`composer pint-test`, `npm run verify`), setup README.
- S02: Company with `is_demo`, Department, the idempotent demo user seeder, roles, `DEMO_PASSWORD` handling.
- S03: CSV format, `CreateImportFromUpload`, `ConfirmImport`, `ProcessImportJob`, `ReportingCoverage`, `ImportCompleted`, expected-branches initialization.
- S04: default rule seeding, `business_rules.params`, `EvaluateRulesJob`, `EvaluationRunCompleted`, issues, urgent notification.
- S05: brief generation, deterministic fallback, voice brief.
- S06: meetings, consent, transcription, manual transcript, task extraction.
- S07: draft approval, tasks, My Tasks.
- S08: `VerifyOutcomesJob`, outcomes, activity log, trace.

### Assumptions

- A1: Fixture and demo values are fictional demonstration defaults (Meridian Home Supplies Sdn Bhd, MYR, `Asia/Kuala_Lumpur`). They are not client data and are flagged for client confirmation together with the real file layout (`docs/stage-1-scope.md` section 8).
- A2: Daily branch figures are derived by one deterministic rule (Requirement 6) so that independent regeneration produces byte-identical files. The generator is a development aid (`FixtureGenerator`, used by `demo:check-fixtures --regenerate-to`) and the committed CSV files are authoritative.
- A3: Weekly totals are not always divisible by six trading days. The remainder is distributed one whole ringgit per day starting on Monday. Daily revenue is always a whole-ringgit amount written with two decimals.
- A4: `week8-with-errors.csv` contains 17 valid rows and 3 invalid extra rows (20 data rows), so that the 17 of 18 branch-days of `docs/demo-scenario.md` section 3 hold and the three invalid rows do not collide with valid identities (Requirement 6, criterion 6).
- A5: The persistence mechanism of `demo:set-date` is a one-line text file `storage/app/private/demo/fixed-date` holding `YYYY-MM-DD`. A file is chosen because the queue worker is a long-running process that must see a new date without a restart, config cache or redeploy, and because Stage 1 has no settings table. `BusinessClock` reads the file (readiness fix R1) with precedence: file, then `DEMO_FIXED_DATE`, then the real date. The file is ignored when `DEMO_MODE` is false.
- A6: Demo commands run seed imports with the `sync` queue connection set for the command process, so that `ProcessImportJob` and the S04 evaluation chain complete before the command returns. The same Actions and job classes run as in production.
- A7: `demo:seed` and `demo:reset` refuse to run while rows exist in the `jobs` table for the demo company's work, because the commands cannot safely delete files that queued jobs still reference. The operator waits or clears the queue first.
- A8: The Stage 1 Definition of Done (26 items) is defined in the product brief section 20, which is not stored in this repository. The checklist copies the 26 items in the brief's order. If the brief text is unavailable at implementation time, this is recorded as readiness fix R-DoD and the items are taken from `docs/stage-1-scope.md` and the Stage 1 roadmap criteria in `docs/roadmap.md`, with the gap stated in the checklist.
- A9: The recording `meeting-recording.mp3` is a synthetic voice or an actor recording of fictional content, mono, up to 3 minutes and 5 MB. It is committed to the repository only when its size is 2 MB or less. Otherwise it is excluded through `.gitignore`, distributed out of band, and its SHA-256 is recorded in `database/demo/README.md`.
- A10: The demo operates with at most one Company in the database. Production-data protection is enforced by refusing when any Company has `is_demo` false (Requirement 2).
- A11: Verification is manual and scripted in documents. No PHPUnit, Pest or end-to-end suite is added (`verification.md` steering). `demo:check-fixtures` is a verification command, not a test suite.
- A12: Provider failure drills use temporarily invalid configuration (for example a wrong `AI_BASE_URL` or key) on a scratch environment and never edit database rows.
- A13: The `expected.json` file is the machine-readable form of the expected results in `docs/demo-scenario.md` sections 3 and 6. The documents and the file must agree. A disagreement is reported by `demo:check-fixtures`.
- A14: Backups and restore are documented and rehearsed on a scratch database. S09 does not add a scheduled backup job to the application.

## Glossary

- **Application**: The BusinessPulse Laravel 13 and Vue 3 Inertia web application.
- **Demo_Commands**: The four Artisan commands `demo:seed`, `demo:reset`, `demo:set-date` and `demo:check-fixtures`.
- **Demo_Guard**: The component `DemoGuard` that decides whether a destructive Demo_Command may run.
- **Demo_Company**: The Company with `is_demo` true, named by `config('demo.company_name')`, default "Meridian Home Supplies Sdn Bhd".
- **Demo_Mode**: The state in which `config('demo.enabled')` (from `DEMO_MODE`) is true.
- **Demo_Seeder**: The component that orchestrates creation of the Demo_Company, users, rules and history.
- **Demo_Resetter**: The component that deletes Demo_Company data and files and then reseeds.
- **Demo_Clock_Store**: The component that reads and writes the fixed-date file described in assumption A5.
- **Fixture_Checker**: The component behind `demo:check-fixtures`.
- **Fixture_Set**: The files in `database/demo/`.
- **History_File**: `database/demo/history.csv`.
- **Week_Files**: `week8-decline.csv`, `week8-with-errors.csv`, `week8-correction.csv` and `week9-recovery.csv`.
- **Expected_Results**: The file `database/demo/expected.json` and the tables of `docs/demo-scenario.md` it mirrors.
- **Scratch_Database**: A PostgreSQL database that holds no real data and may be dropped.
- **Readiness_Fix**: A defect or gap in S01 to S08 found during S09 verification and listed in `docs/readiness-fixes.md`.
- **Checklist**: `docs/acceptance-checklist.md`.
- **Security_Matrix**: `docs/security-verification.md`.
- **Walkthrough**: `docs/demo-walkthrough.md`.
- **Rehearsal**: One complete execution of the Walkthrough from `demo:reset` to the last step without direct database edits.
- **Drill**: A scripted provider failure exercise.
- **Operator**: The person who prepares and runs the demonstration or deploys the Application.
- **Owner**, **Manager**, **Staff**: Users with the S02 roles `owner`, `manager` and `staff`.
- **Reporting_Week**: An ISO week from Monday to Sunday, numbered W1 to W9 as in `docs/demo-scenario.md`.

## Requirements

### Requirement 1: Scope control and readiness fixes

**User Story:** As a project owner, I want S09 to add no product features and to report gaps in earlier specs openly, so that the demonstration shows exactly what S01 to S08 deliver.

#### Acceptance Criteria

1. THE Application SHALL receive from S09 no new business table, no new route that serves business data to end users, no new rule, no new page and no new notification.
2. THE Application SHALL receive from S09 only the Demo_Commands, their supporting services, the additive `config/demo.php` keys, the Fixture_Set and documents.
3. WHEN a verification step of S09 fails because of behavior in S01 to S08, THE Operator SHALL record the failure in `docs/readiness-fixes.md` with an identifier (R1, R2 and so on), the owning spec, the observed behavior, the expected behavior, the verification step that found the failure, and a status of `open` or `fixed`.
4. THE `docs/readiness-fixes.md` file SHALL list readiness fix R1: `BusinessClock` reads the Demo_Clock_Store file under Demo_Mode (assumption A5).
5. WHERE a Readiness_Fix is applied, THE project SHALL change the owning spec's code in a separate, labelled change that references the fix identifier, and SHALL NOT change the behavior of S01 to S08 in any S09 command or document.
6. IF a Readiness_Fix would add a product feature, THEN THE project SHALL record the item as a risk in `docs/risks.md` instead of implementing the item.
7. THE S09 verification SHALL contain no automated unit or end-to-end test suite and SHALL be performed according to `.kiro/steering/verification.md`.

### Requirement 2: Demo safety guards

**User Story:** As an Owner, I want destructive demo commands to refuse to touch real data, so that a mistaken command cannot erase production records.

#### Acceptance Criteria

1. THE Demo_Guard SHALL permit `demo:seed`, `demo:reset` and `demo:set-date` only WHILE Demo_Mode is true.
2. IF Demo_Mode is false, THEN THE Demo_Guard SHALL stop the command with a non-zero exit code and the message "Demo commands run only when DEMO_MODE=true", and SHALL change no data and no file.
3. IF `APP_ENV` is `production` and `DEMO_ALLOW_PRODUCTION_DEMO` is not true, THEN THE Demo_Guard SHALL stop `demo:seed` and `demo:reset` with a non-zero exit code.
4. IF any Company with `is_demo` false exists, THEN THE Demo_Guard SHALL stop `demo:seed` and `demo:reset` with a non-zero exit code and the message that real company data exists, and SHALL change no data and no file.
5. WHEN `demo:reset` runs, THE Demo_Guard SHALL require that a Company with `is_demo` true exists.
6. WHEN `demo:reset` runs without the `--force` option, THE Demo_Guard SHALL require the Operator to type the exact Demo_Company name, and SHALL stop with a non-zero exit code when the typed text differs.
7. WHEN `demo:reset` runs without an interactive terminal and without `--force`, THE Demo_Guard SHALL stop with a non-zero exit code.
8. THE Demo_Guard SHALL run before any other statement of the command and SHALL evaluate all conditions, so that the message names every failed condition.
9. THE Demo_Commands SHALL NOT be registered in the Laravel scheduler.
10. THE Demo_Commands SHALL write no company name, password, API key or file content into any log line, and SHALL write the command name, the Company id and the outcome only.
11. THE Application SHALL expose no HTTP route that runs a Demo_Command.
12. THE `.env.example` file SHALL set `DEMO_MODE=false`, and `docs/deployment.md` SHALL state that production deployments keep `DEMO_MODE=false`.

### Requirement 3: Demo seeding (`demo:seed`)

**User Story:** As an Operator, I want one command that prepares the fictional company with real workflows, so that the demonstration starts from a believable state.

#### Acceptance Criteria

1. WHEN the Operator runs `demo:seed` and the Demo_Guard passes, THE Demo_Seeder SHALL create the Demo_Company with currency MYR, timezone `Asia/Kuala_Lumpur`, trading days Monday to Saturday, `is_demo` true and the single Sales department.
2. THE Demo_Seeder SHALL create the six users of `docs/demo-scenario.md` section 1 by calling the idempotent S02 demo user seeder, and SHALL NOT create users by any other code path.
3. IF `DEMO_PASSWORD` is empty, THEN THE Demo_Seeder SHALL stop with a non-zero exit code and a message to set `DEMO_PASSWORD`, and SHALL create no user.
4. THE Demo_Seeder SHALL hash the demo password through the standard Laravel hasher and SHALL NOT print, log or commit the password.
5. THE Demo_Seeder SHALL create the default business rules by calling the S04 default rule seeding and SHALL then set `high_drop_pct` to 15 in the `params` of the weekly decline rule, so that a 20.00% decline has severity high (D25).
6. THE Demo_Seeder SHALL write the clock date through the Demo_Clock_Store with the value of `config('demo.default_date')` (default `2026-09-28`) before any import runs, if no clock file exists.
7. WHEN the Demo_Seeder loads history, THE Demo_Seeder SHALL create the import through `CreateImportFromUpload`, confirm it through `ConfirmImport`, and process it through `ProcessImportJob`, as the Owner user, using the History_File.
8. THE Demo_Seeder SHALL NOT insert, update or delete any row of `sales_records`, `imports`, `import_errors`, `rule_evaluations`, `issues`, `outcomes` or `activity_logs` by any statement outside the S03 to S08 code.
9. IF the history import ends with a status other than `completed`, THEN THE Demo_Seeder SHALL stop with a non-zero exit code and the sanitized import error message.
10. WHEN the history import completes, THE Demo_Seeder SHALL confirm that Expected_Branches equals `JB, KL, PG` and that the S04 evaluation of W7 produced the results of the Expected_Results for the "history import" row, and SHALL report each mismatch.
11. THE `demo:seed` command SHALL be idempotent: WHEN the Demo_Company already has the seeded users, rules and an `completed` history import with the same file hash, THE Demo_Seeder SHALL create no duplicate user, rule or sales record and SHALL exit with code zero and a message that nothing changed.
12. IF the Demo_Company has data other than the seeded state (for example any issue, meeting or task), THEN THE Demo_Seeder SHALL stop with a message to use `demo:reset` and SHALL change nothing.
13. THE Demo_Seeder SHALL run the history import with the queue connection set to `sync` for the command process only (assumption A6) and SHALL restore the configured connection when the command ends.
14. THE `demo:seed` command SHALL print a summary of created users, rule parameters, import counters and the clock date, without secrets.

### Requirement 4: Demo reset (`demo:reset`)

**User Story:** As an Operator, I want to return the demo to its starting state in one command, so that each rehearsal and each client demonstration begins identically.

#### Acceptance Criteria

1. WHEN the Operator runs `demo:reset` and the Demo_Guard passes, THE Demo_Resetter SHALL delete every database row that belongs to the Demo_Company and every private file that belongs to the Demo_Company, and SHALL then run the Demo_Seeder.
2. THE Demo_Resetter SHALL select rows to delete only through `company_id`, `department_id` or a parent record that belongs to the Demo_Company, and SHALL execute the deletes in one database transaction in dependency order.
3. THE Demo_Resetter SHALL delete the rows of the Demo_Company in these groups: activity logs, notifications, task comments, tasks, task drafts, meeting decisions, meetings, feedback, outcomes, issue evidence and issues, rule evaluations, briefs, sales records, import errors, imports, business rules, users, departments and the Company, using the actual table names defined by S01 to S08.
4. THE Demo_Resetter SHALL delete the private files under `imports/`, `recordings/` and brief audio paths only for ids that belong to the Demo_Company, and SHALL NOT delete any other path.
5. THE Demo_Resetter SHALL delete the Demo_Clock_Store file and let the Demo_Seeder recreate the file with the default date.
6. IF the `jobs` table contains a job or the `failed_jobs` table contains a row that references a Demo_Company record, THEN THE Demo_Resetter SHALL stop before deleting anything (assumption A7) and print how many jobs block the reset.
7. IF the database transaction fails, THEN THE Demo_Resetter SHALL roll back, leave the files untouched, and exit with a non-zero exit code and a sanitized message.
8. THE Demo_Resetter SHALL delete files only after the database transaction commits, and SHALL report each file it could not delete.
9. THE Demo_Resetter SHALL leave sessions, cache rows, migration rows and tables of any other Company unchanged.
10. WHEN the Demo_Resetter finishes, THE `demo:reset` command SHALL print the number of rows and files deleted per group, the seed summary of Requirement 3, and the clock date.
11. WHEN `demo:reset --force` runs twice in a row, THE Application SHALL reach the same seeded state both times, with the same counts of users, rules, sales records and the same revenue sum (idempotence property).
12. THE `demo:reset` command SHALL import the History_File through the same Action and job path as Requirement 3, criterion 7, and SHALL NOT restore the database from a dump or insert sales rows directly.
13. WHILE the reset runs, THE Application SHALL display no partial state to a user who is logged in: IF a request arrives for a deleted user, THEN THE Application SHALL respond with the S02 unauthenticated behavior.
14. THE `demo:reset` command SHALL take less than 60 seconds on the reference environment for the 126-row History_File, and THE verification SHALL record the measured time.

### Requirement 5: Demo clock (`demo:set-date`)

**User Story:** As an Operator, I want to move the demo date between steps, so that week 9 data is not future-dated and the demonstration is repeatable.

#### Acceptance Criteria

1. WHEN the Operator runs `demo:set-date {date}` with a date in the format `YYYY-MM-DD` and the Demo_Guard passes, THE Demo_Clock_Store SHALL write the date to the fixed-date file and the command SHALL print the date that `BusinessClock::today()` now returns.
2. IF the date is not a real calendar date in the format `YYYY-MM-DD`, THEN THE `demo:set-date` command SHALL exit with a non-zero exit code and write nothing.
3. IF the date is earlier than 2000-01-01, THEN THE `demo:set-date` command SHALL exit with a non-zero exit code and write nothing.
4. WHILE Demo_Mode is true, THE `BusinessClock::today()` method SHALL return the date in the fixed-date file when the file exists and holds a valid date, and otherwise the value of `DEMO_FIXED_DATE`, and otherwise the real date in the Company timezone.
5. WHILE Demo_Mode is false, THE `BusinessClock::today()` method SHALL ignore the fixed-date file and `DEMO_FIXED_DATE`.
6. THE Demo_Clock_Store SHALL write the file atomically (write to a temporary file in the same directory, then rename), so that a reader never reads a partial value.
7. WHEN the fixed-date file changes, THE queue worker and the web process SHALL use the new date for the next call of `BusinessClock::today()` without restart.
8. IF the fixed-date file holds an invalid value, THEN THE `BusinessClock::today()` method SHALL fall back to `DEMO_FIXED_DATE` and THE Application SHALL write a log line that names the file and not its content.
9. THE `demo:set-date` command SHALL NOT change any database row, any `.env` value or any cached configuration.
10. THE `demo:set-date` command SHALL print a warning when the date is more than 30 days away from the latest `sales_date` of the Demo_Company, because such a date makes Freshness and coverage show old or future data.
11. FOR ALL valid dates, writing a date and then reading it back through `BusinessClock::today()` SHALL return the same date (round-trip property).

### Requirement 6: CSV fixtures

**User Story:** As a verifier, I want exact fixture files that follow the real CSV format, so that the demonstration data passes through the real import and gives known results.

#### Acceptance Criteria

1. THE Fixture_Set SHALL contain `history.csv`, `week8-decline.csv`, `week8-with-errors.csv`, `week8-correction.csv` and `week9-recovery.csv` in `database/demo/`.
2. THE Fixture_Set files SHALL follow the S03 CSV format: header `sales_date,branch_code,revenue,target_revenue,order_count`, comma delimiter, UTF-8 without byte-order mark, line feed line endings, dates as `YYYY-MM-DD`, revenue and target values with exactly two decimals, upper-case branch codes `KL`, `PG` and `JB`.
3. THE History_File SHALL contain one row per branch per Monday-to-Saturday day from 2026-08-03 to 2026-09-19, which is 7 weeks, 42 trading days and 126 data rows.
4. THE `week8-decline.csv` file SHALL contain 18 rows for 2026-09-21 to 2026-09-26, and THE `week9-recovery.csv` file SHALL contain 18 rows for 2026-09-28 to 2026-10-03.
5. THE Fixture_Set SHALL set `target_revenue` to 12000.00 for KL, 8000.00 for PG and 6000.00 for JB on every row, and SHALL set `order_count` to a deterministic whole number for every row.
6. THE `week8-with-errors.csv` file SHALL contain 20 data rows (assumption A4): 17 valid rows for the W8 branch-days except JB on 2026-09-26, one row with an impossible date (`2026-13-45`) and branch KL, one row with branch PG, date 2026-09-27 and revenue `-100.00`, and one row with branch JB, date 2026-09-26 and target value `abc`, so that no invalid row repeats the identity of a valid row.
7. THE `week8-correction.csv` file SHALL contain the 18 rows of W8 in which exactly 2 rows (KL and PG on 2026-09-26) have a changed revenue, with the new W8 total stated in `database/demo/README.md`.
8. THE Fixture_Set SHALL derive the daily revenue of each file by this rule: the weekly total is split over the six days as `floor(total / 6)` whole ringgit per day, with the remainder `total mod 6` added as one ringgit to each of the first days starting on Monday.
9. THE Fixture_Set SHALL derive the branch revenue of each day from the daily total D by `PG = floor(D * 8 / 26)`, `JB = floor(D * 6 / 26)` and `KL = D - PG - JB`, so that the remainder goes to KL and the three branches sum exactly to D.
10. THE weekly revenue totals of active rows SHALL be W1 150000.00, W2 152000.00, W3 149000.00, W4 153000.00, W5 151000.00, W6 154000.00, W7 155000.00, W8 124000.00 and W9 150000.00.
11. THE `week8-correction.csv` file SHALL produce 16 `unchanged` rows and 2 `corrected` rows when imported after `week8-decline.csv`.
12. WHEN `history.csv` is imported into an empty Demo_Company, THE Application SHALL report 126 new, 0 corrected, 0 unchanged and 0 invalid rows.
13. WHEN `week8-decline.csv` is imported after the History_File, THE Application SHALL report 18 new rows and no invalid row.
14. WHEN `week9-recovery.csv` is imported after `week8-decline.csv`, THE Application SHALL report 18 new rows and no invalid row.
15. WHEN `week8-with-errors.csv` is imported into the state after the History_File, THE Application SHALL report 17 valid rows, 3 invalid rows with the codes `invalid_date`, `negative_value` and `invalid_number`, and the W8 coverage status `partial` with one Coverage_Gap for JB on 2026-09-26.
16. THE `database/demo/README.md` file SHALL state for each fixture its purpose, its row count, its weekly total, the import order and the expected counters.
17. THE Fixture_Set SHALL contain only fictional data and SHALL contain no real person, company or bank identifier.

### Requirement 7: Meeting transcript and recording fixtures

**User Story:** As an Operator, I want a ready transcript and clear instructions for the recording, so that the meeting step works even when transcription fails.

#### Acceptance Criteria

1. THE Fixture_Set SHALL contain `meeting-transcript.txt` in English, UTF-8, at most 3,000 words, containing the content items 1 to 8 of `docs/demo-scenario.md` section 4, including the ambiguous "Daniel" instruction, the unassigned stock check and the prompt-injection line.
2. THE `meeting-transcript.txt` file SHALL identify the speakers Aisyah, Priya, Farid, Daniel and Wei Han by name labels at the start of each turn.
3. THE `database/demo/README.md` file SHALL document two ways to produce `meeting-recording.mp3`: a read-aloud recording by volunteers, and a synthetic voice generated from `meeting-transcript.txt` with the speech model configured in `AI_SPEECH_MODEL`.
4. THE `database/demo/README.md` file SHALL state the recording requirements: format mp3, mono, English, up to 3 minutes, size up to 5 MB, clear voices, and a meeting consent statement spoken at the start.
5. IF the size of `meeting-recording.mp3` is 2 MB or less, THEN THE project SHALL commit the file to `database/demo/`.
6. IF the size of `meeting-recording.mp3` is greater than 2 MB, THEN THE project SHALL exclude the file through `.gitignore`, and THE `database/demo/README.md` file SHALL record its SHA-256 hash and the location where the Operator obtains it.
7. THE `docs/demo-walkthrough.md` file SHALL state that the manual transcript path (pasting `meeting-transcript.txt`) is the fallback when transcription fails.
8. THE Fixture_Set SHALL NOT contain any audio file other than `meeting-recording.mp3`, and no file larger than 5 MB.
9. THE project SHALL NOT commit any other binary file larger than 2 MB for S09.

### Requirement 8: Fixture verification (`demo:check-fixtures`)

**User Story:** As a verifier, I want a command that proves the fixtures and the seeded results match the documented values, so that I do not depend on eyeballing numbers.

#### Acceptance Criteria

1. THE `demo:check-fixtures` command SHALL run without a database connection for the file checks of criteria 2 to 8, and SHALL run the evaluation checks of criterion 9 only when the `--evaluate` option is given.
2. WHEN the command runs, THE Fixture_Checker SHALL parse every fixture CSV with the S03 `CsvImportReader` format rules and report each file-level or row-level error, except for the three rows of `week8-with-errors.csv` that the Expected_Results declare invalid.
3. THE Fixture_Checker SHALL verify the row counts of Requirement 6 for each file.
4. THE Fixture_Checker SHALL verify the weekly revenue totals of Requirement 6 criterion 10 by decimal arithmetic and SHALL report each difference with the week and both values.
5. THE Fixture_Checker SHALL verify, for every day of `history.csv`, `week8-decline.csv` and `week9-recovery.csv`, that the three branch values sum to the daily total of the rule in Requirement 6 criteria 8 and 9, and that KL receives the remainder.
6. THE Fixture_Checker SHALL verify that each branch has exactly one row per trading day in each complete-week file, and that `week8-with-errors.csv` has 17 valid branch-days with JB on 2026-09-26 absent.
7. THE Fixture_Checker SHALL verify that `week8-correction.csv` differs from `week8-decline.csv` in exactly 2 rows.
8. THE Fixture_Checker SHALL verify that the values in `expected.json` equal the values in `docs/demo-scenario.md` sections 3 and 6, and SHALL report each mismatch.
9. WHERE the `--evaluate` option is given, THE Fixture_Checker SHALL require a Scratch_Database and Demo_Mode, SHALL call the Demo_Guard, and SHALL execute the sequence in Requirement 9 through the real import Actions and jobs, comparing the evaluation results to the Expected_Results after each step.
10. IF the connected database name does not end with `_scratch`, `_demo` or `_test` and the `--evaluate` option is given, THEN THE `demo:check-fixtures` command SHALL stop with a non-zero exit code and execute no import.
11. THE `demo:check-fixtures` command SHALL print one line per check with the label PASS or FAIL and the compared values, and SHALL exit with code zero only when all checks pass.
12. THE `demo:check-fixtures` command SHALL compare money values as decimal strings and percentages after rounding to two decimals with round half up, and SHALL NOT use floating-point arithmetic for money.
13. WHERE the `--regenerate-to {dir}` option is given, THE Fixture_Checker SHALL write regenerated CSV files to the given directory, and SHALL NOT overwrite files in `database/demo/`.
14. FOR ALL regenerated files, THE regenerated content SHALL be byte-identical to the committed file of the same name (determinism property).

### Requirement 9: Expected evaluations and business calculations

**User Story:** As a verifier, I want the expected rule results and calculations stored as data, so that every run of the demonstration is checked against the same values.

#### Acceptance Criteria

1. THE `expected.json` file SHALL contain the expected evaluations of `docs/demo-scenario.md` section 3 with, for each step: the rule key, the result, the severity where the rule is breached, the metric values and the percentage values.
2. THE `expected.json` file SHALL contain after the history import (as-of 2026-09-19): weekly decline W7 vs W6 `not_breached` at +0.65% (155000.00 vs 154000.00), target shortfall W7 `not_breached` at 99.36%, and missing data `not_breached` at 100% coverage.
3. THE `expected.json` file SHALL contain after `week8-decline.csv`: weekly decline W8 vs W7 `breached` with severity `high` at −20.00% and difference −31000.00, target shortfall W8 `breached` with severity `medium` at 79.49% (124000.00 / 156000.00), and missing data `not_breached` at 100% coverage.
4. THE `expected.json` file SHALL contain after `week8-with-errors.csv` on a fresh reset: missing data `breached` with severity `medium` at 17 of 18 branch-days (94.44%), and decline and target shortfall results `insufficient_data` because W8 is partial.
5. THE `expected.json` file SHALL contain after `week9-recovery.csv`: weekly decline W9 vs W8 `not_breached` at +20.97% (150000.00 vs 124000.00), and target shortfall W9 `not_breached` at 96.15%.
6. THE `expected.json` file SHALL contain the expected outcome calculations of `docs/demo-scenario.md` section 6: for the weekly revenue decline issue in Monitoring the baseline −20.00%, the follow-up +20.97%, the change +40.97 percentage points, the result `improved`, and the resolution `resolved` with `system_verified`; for the target shortfall issue the baseline 79.49%, the follow-up 96.15% and the change +16.66 percentage points, evaluated only when the issue is in Monitoring.
7. THE `expected.json` file SHALL state the expected notification behavior: the high-severity decline issue creates one urgent in-app notification, and the medium-severity target shortfall issue creates none.
8. THE `expected.json` file SHALL state the expected meeting results of `docs/demo-scenario.md` section 5: 4 task drafts, two with resolved assignee and deadline, one with an ambiguous assignee and no deadline that cannot be approved until both are set, one without assignee and deadline, and no task assigned to the Owner.
9. THE Expected_Results SHALL be accompanied by the decimal rule: percentages are computed from decimal values and rounded to two decimals with round half up.
10. THE Expected_Results for the verification sequence SHALL list the observable S04 and S08 chain for each import: `ImportCompleted`, `EvaluateRulesJob`, `EvaluationRunCompleted`, `VerifyOutcomesJob` and the resulting rows, with the expectation that `VerifyOutcomesJob` writes no outcome while no issue is in Monitoring.
11. WHEN a verification step compares an observed value to an Expected_Result, THE Operator SHALL record the observed value, the expected value and the verdict PASS or FAIL in the verification record.

### Requirement 10: Acceptance checklist

**User Story:** As a project owner, I want every Definition of Done item mapped to a concrete check, so that readiness is judged against evidence.

#### Acceptance Criteria

1. THE Checklist SHALL contain one entry for each of the 26 items of the Stage 1 Definition of Done (assumption A8), numbered DoD-01 to DoD-26 in the order of the product brief.
2. THE Checklist SHALL give each entry: the item text, the owning specs, the check procedure as numbered steps, the expected observable result, the evidence to capture (screenshot, command output or record id) and a result field with PASS, FAIL or BLOCKED.
3. THE Checklist SHALL cover at least these areas through its entries: login and roles, CSV import with preview and corrections, completeness handling, the three rules and their evaluations, issue creation and deduplication, the urgent notification, the AI brief with fallback, the voice brief, meeting consent and recording upload, transcription with the manual fallback, task draft extraction and human approval, My Tasks for Staff, outcome verification, feedback, the activity trace, authorization and department scoping, private file download, demo reset, and deployment.
4. THE Checklist SHALL reference the expected values of the Expected_Results where a numeric result is checked.
5. THE Checklist SHALL state for each entry whether the check needs a real AI provider.
6. THE Checklist SHALL end with a signed summary block that lists totals of PASS, FAIL and BLOCKED, the open Readiness_Fixes and the date and Operator name.
7. IF an entry has the result FAIL or BLOCKED, THEN THE Checklist SHALL require a reference to a Readiness_Fix or a risk in `docs/risks.md`.
8. THE Checklist SHALL state in its introduction that passing the checks does not make the Application production-ready.
9. THE Checklist SHALL include a check that no business record in the Demo_Company was created by a direct database statement, using the import records, `activity_logs` and the event chain as evidence.
10. THE Checklist SHALL include the check that `demo:reset` refuses to run when Demo_Mode is false, when the Company has `is_demo` false, and without `--force` or the typed name (Requirement 2).

### Requirement 11: Authorization verification matrix

**User Story:** As an Owner, I want proof that each role sees only what it should, so that the demonstration cannot expose data across departments or roles.

#### Acceptance Criteria

1. THE Security_Matrix SHALL list every route of S01 to S08 that serves an authenticated page, a state-changing action or a file, using `php artisan route:list` as the source, with the columns route name, method, URL, and the expected result for each actor.
2. THE Security_Matrix SHALL cover these actors: unauthenticated visitor, inactive user, Owner, Manager of the department, Manager of another department (using a scratch second department), Staff who is the assignee, and Staff who is not the assignee.
3. THE Security_Matrix SHALL state the expected result per cell as 200, 302 to login, 403, 404, 419, 422 or 429, with the rule reference in the owning spec's requirements.
4. THE Security_Matrix SHALL include direct URL access to guessed ids, to files (CSV source, recording, brief audio) and to Inertia partial reloads for each role.
5. THE Security_Matrix SHALL include CSRF checks (missing token returns 419) on at least one state-changing route per module.
6. THE Security_Matrix SHALL include the throttling checks: login, uploads (10 per minute per user, S03) and AI endpoints, with the expected 429 and Retry-After behavior.
7. THE Security_Matrix SHALL include checks that no Inertia prop or response contains `stored_path`, absolute paths, `OPENAI_API_KEY`, `DEMO_PASSWORD` or password hashes, using the browser's page props view.
8. THE Security_Matrix SHALL include the second department scratch setup steps and SHALL state that this setup runs only on a Scratch_Database.
9. THE Security_Matrix SHALL require a record of each cell as PASS or FAIL with the observed status code.
10. IF a cell fails, THEN THE Security_Matrix SHALL require an entry in `docs/readiness-fixes.md` and SHALL treat the matrix as not passed.
11. THE Security_Matrix SHALL include a check of log files after the Rehearsal that confirms the absence of cell values, transcript text, API keys and absolute storage paths (S03 Requirement 20, S06 and S07 equivalents).

### Requirement 12: Responsive and accessibility verification

**User Story:** As a client attendee on a laptop or phone, I want the demonstration screens to be usable at common widths, so that the product looks dependable on any device.

#### Acceptance Criteria

1. THE Checklist SHALL contain a responsive verification section that lists the screens: login, dashboard, Data Imports, upload with preview, import detail, issues list and detail, brief, meetings list and detail, draft review, My Tasks, task detail, outcomes, activity feed and Settings tabs.
2. THE responsive section SHALL require each listed screen to be checked at widths 360, 768 and 1280 px.
3. THE responsive section SHALL require the checks: no horizontal scrolling of the page, tables that stack as cards where specified, touch targets of at least 40 px, no text overlap, and charts with a text-table alternative.
4. THE responsive section SHALL require one keyboard-only run of the path: log in, upload a CSV, review the preview, confirm, open the issue, with a visible focus indicator on each interactive element and a logical tab order.
5. THE responsive section SHALL require a check that status and severity are conveyed by icon or text and not by color alone.
6. THE responsive section SHALL require a loading, empty and error state check on each Import page.
7. THE responsive section SHALL record the browser name and version, the operating system and the viewport used.
8. WHEN a check fails, THE Operator SHALL record the screen, the width and a screenshot as evidence in `docs/readiness-fixes.md`.

### Requirement 13: Build and baseline verification

**User Story:** As a developer, I want the exact baseline commands in one place with expected outcomes, so that a clean build is demonstrated before every rehearsal.

#### Acceptance Criteria

1. THE Checklist SHALL contain a build verification section that lists these commands with the expected result: `composer validate --strict`, `composer pint-test`, `php -l` for every PHP file under `app`, `database`, `routes` and `config` (through a documented `find` pipe), `npm run type-check`, `npm run lint`, `npm run format:check` (or the equivalents defined in S01 `npm run verify`), `npm run build` and `php artisan migrate:fresh --seed` on a Scratch_Database.
2. THE build verification section SHALL include a rollback check: `php artisan migrate:rollback` to zero batches and `php artisan migrate` again, on a Scratch_Database, with no error.
3. THE build verification section SHALL state that `migrate:fresh` is never run against a database that holds real data and SHALL name the check of the database name before running.
4. THE build verification section SHALL include `php artisan route:list` and `php artisan config:cache`, `route:cache` and `view:cache` as a production-readiness smoke check, followed by `config:clear`.
5. THE build verification section SHALL include `npm audit --omit=dev` and `composer audit` with the rule that high or critical findings are recorded in `docs/risks.md`.
6. THE build verification section SHALL require a record of the verified versions of PHP, Laravel, Node, PostgreSQL, Inertia, Vue, Tailwind, shadcn-vue and ECharts, and SHALL require that `docs/architecture.md` section 11 contains these values.
7. IF a build command fails, THEN THE Operator SHALL stop the Rehearsal until the cause is recorded as a Readiness_Fix or fixed.

### Requirement 14: Provider failure drills

**User Story:** As an Operator, I want to practice each provider failure, so that a live outage does not stop the demonstration.

#### Acceptance Criteria

1. THE `docs/provider-failure-drills.md` file SHALL define one Drill for each failure of `docs/demo-scenario.md` section 9: text AI down, speech down, transcription down, extraction down and no internet.
2. THE Drill description SHALL state for each Drill: how to induce the failure on a non-production environment (for example a wrong `AI_BASE_URL` or key and `config:clear`), the expected user-visible behavior, the recovery action and the expected final state.
3. THE text Drill SHALL verify that the brief shows the deterministic fallback labelled "AI unavailable", that the retry button is present, and that the retry succeeds after the configuration is restored.
4. THE speech Drill SHALL verify that the text brief remains, that the voice shows a failed state with a retry action, and that the retry succeeds after the configuration is restored.
5. THE transcription Drill SHALL verify that the Operator can enter the content of `meeting-transcript.txt` as a manual transcript and that extraction then continues.
6. THE extraction Drill SHALL verify that a failure produces a failed status with a retry action and that no task draft exists in the failed state.
7. THE no-internet Drill SHALL verify that import, evaluation, issue, trace and outcome screens work without a provider.
8. THE `docs/provider-failure-drills.md` file SHALL state the retry timing of `docs/architecture.md` section 6 (3 attempts, 10, 30 and 60 seconds backoff) so that the Operator knows how long to wait.
9. THE Drills SHALL verify that no API key and no raw provider error appear in the user interface or the logs.
10. THE Drills SHALL be performed with edits to environment values only and SHALL NOT edit database rows (assumption A12).
11. WHEN a Drill ends, THE Operator SHALL restore the environment values, run `php artisan config:clear` and `php artisan queue:restart`, and run a one-step smoke check of the provider.

### Requirement 15: Rehearsal protocol

**User Story:** As a project owner, I want two clean rehearsals before the client demonstration, so that surprises are found early.

#### Acceptance Criteria

1. THE `docs/rehearsal-protocol.md` file SHALL require two complete Rehearsals, each starting with `demo:reset --force` and ending after the trace step of the Walkthrough.
2. THE `docs/rehearsal-protocol.md` file SHALL state that a Rehearsal passes only WHEN every Walkthrough step produces its expected result without a direct database edit, without a code change and without a manual restart of the web server.
3. THE `docs/rehearsal-protocol.md` file SHALL require the Operator to start the queue worker as in production (Supervisor or `php artisan queue:work --tries=3 --timeout=180`) before the Rehearsal.
4. THE `docs/rehearsal-protocol.md` file SHALL list the pre-flight checks: the Requirement 13 build checks, `demo:check-fixtures`, provider key presence, the clock date, the queue worker running, free disk space and a browser window set to 1280 px.
5. THE Rehearsal checks SHALL include the S04 and S08 chain on each import: WHEN an import completes, THE Operator SHALL verify that `ImportCompleted` is followed by `EvaluateRulesJob`, that `EvaluationRunCompleted` is dispatched once after the evaluation commits, and that `VerifyOutcomesJob` runs for the department.
6. THE Rehearsal checks SHALL verify that after `week8-decline.csv` exactly one decline issue with severity high and one target shortfall issue with severity medium exist, that exactly one urgent notification exists, and that no outcome exists.
7. THE Rehearsal checks SHALL verify that after the issue is in Monitoring and `week9-recovery.csv` is imported with the date set to 2026-10-05, `VerifyOutcomesJob` creates the outcome with result `improved` and the values of Requirement 9 criterion 6, and that the decline issue is resolved with `system_verified`.
8. THE Rehearsal checks SHALL verify that a repeated Re-evaluate action creates no duplicate evaluation, issue or outcome (idempotence).
9. THE Rehearsal checks SHALL verify that the activity feed shows the full trace from import to outcome and that the trace contains one row per event as defined in S08.
10. THE `docs/rehearsal-protocol.md` file SHALL include the optional segments (validation demo with `week8-with-errors.csv`, correction demo with `week8-correction.csv`) each on a fresh reset, with the expected counters of Requirement 6.
11. THE Rehearsal SHALL record for each step: start time, duration, result PASS or FAIL, and any deviation, in a table at the end of `docs/rehearsal-protocol.md` or an attached copy.
12. THE Rehearsal protocol SHALL require the Operator to record the elapsed time of each Rehearsal and SHALL state that the target time for the Walkthrough is 25 minutes or less, excluding provider waiting time.
13. IF a Rehearsal fails, THEN THE Operator SHALL record the cause as a Readiness_Fix or a risk, fix it, and restart the count of consecutive passing Rehearsals at zero.
14. THE Rehearsal protocol SHALL require that the two passing Rehearsals are consecutive and that the second runs on the build that will be demonstrated.
15. THE Rehearsal protocol SHALL state that the final state after the second Rehearsal is discarded by running `demo:reset --force` before the client demonstration.

### Requirement 16: Demonstration walkthrough

**User Story:** As a presenter, I want a step-by-step script with talking points, so that the 20 steps run smoothly and the story is clear.

#### Acceptance Criteria

1. THE Walkthrough SHALL contain the 20 steps of the demonstration sequence in `docs/demo-scenario.md` section 7 in the same order.
2. THE Walkthrough SHALL give each step: the actor and login, the action, the expected screen result with the expected values from Expected_Results, a talking point of at most 60 words, and the fallback if the step fails.
3. THE Walkthrough SHALL start with the setup commands `php artisan demo:reset --force` and `php artisan demo:set-date 2026-09-28`, and SHALL include `php artisan demo:set-date 2026-10-05` before the upload of `week9-recovery.csv`.
4. THE Walkthrough SHALL state at the beginning that the data is fictional and that the AI output is reviewed by a human before any task exists.
5. THE Walkthrough SHALL present the outcome with the wording "Observed change after actions" and SHALL NOT claim that an action caused the improvement.
6. THE Walkthrough SHALL cover the meeting steps with the expected 4 task drafts, the approval of drafts 1 and 2, the assignee and deadline edit on draft 3, the rejection of draft 4, and the point that the injection line has no effect.
7. THE Walkthrough SHALL state the expected waiting times for transcription, extraction, brief and speech steps and the talking points to use while waiting.
8. THE Walkthrough SHALL list the logins and the roles of the six demo users without printing the password and SHALL state that the password comes from the Operator's `DEMO_PASSWORD` value.
9. THE Walkthrough SHALL link each failure contingency to the matching Drill in `docs/provider-failure-drills.md`.
10. THE Walkthrough SHALL end with a closing section that lists what is out of Stage 1 scope (for example Excel import, multiple companies and external messaging) and the items requiring client confirmation from `docs/stage-1-scope.md` section 8.

### Requirement 17: Local setup documentation

**User Story:** As a developer, I want native setup instructions that work without Docker, so that I can run and verify the Application on my own machine.

#### Acceptance Criteria

1. THE `docs/local-setup.md` file SHALL describe the native setup on macOS and Ubuntu with the prerequisites PHP (version verified in S01), Composer, Node, npm and PostgreSQL, and SHALL state that Docker is not used.
2. THE `docs/local-setup.md` file SHALL give the numbered steps: clone, `composer install`, `npm ci`, copy `.env.example` to `.env`, `php artisan key:generate`, create the PostgreSQL database and role, set the database variables, `php artisan migrate`, `php artisan storage:link` only if S01 requires it, and start the development processes.
3. THE `docs/local-setup.md` file SHALL describe how to run the web server, the Vite server and the queue worker as separate terminal processes with the exact commands, and SHALL state that the Operator starts them manually.
4. THE `docs/local-setup.md` file SHALL describe how to configure the demo: set `DEMO_MODE=true`, `DEMO_PASSWORD`, the AI variables, run `php artisan demo:reset --force`, and log in as a demo user.
5. THE `docs/local-setup.md` file SHALL describe how to create and use a Scratch_Database for verification, including the `_scratch` name rule of Requirement 8 criterion 10.
6. THE `docs/local-setup.md` file SHALL list the verification commands of Requirement 13.
7. THE `docs/local-setup.md` file SHALL list common problems and fixes: missing PHP extensions (`pdo_pgsql`, `mbstring`, `fileinfo`), queue worker not running (imports stay in `processing`), stale cached config, file permissions on `storage` and `bootstrap/cache`, and upload size errors.
8. THE `docs/local-setup.md` file SHALL state the local upload limits needed for 20 MB recordings and 5 MB CSV files (`upload_max_filesize` 20M, `post_max_size` 25M) and how to check them.

### Requirement 18: Deployment documentation

**User Story:** As an Operator, I want complete native deployment instructions for an Ubuntu VPS, so that the demonstration can be hosted over HTTPS with background jobs working.

#### Acceptance Criteria

1. THE `docs/deployment.md` file SHALL describe the architecture of `docs/architecture.md` section 9: Ubuntu VPS, Nginx, PHP-FPM, local PostgreSQL, Supervisor-run queue worker, cron scheduler, Let's Encrypt HTTPS, private storage outside the web root, nightly backups, and no Docker.
2. THE `docs/deployment.md` file SHALL list the server preparation steps: packages with the PHP version verified in S01 and its extensions, Nginx, PostgreSQL, Supervisor, Certbot, Node (build only), Composer, a non-root deploy user, a firewall that allows ports 22, 80 and 443 only, and automatic security updates.
3. THE `docs/deployment.md` file SHALL give an Nginx server block that: redirects HTTP to HTTPS, sets the web root to `public`, passes PHP to PHP-FPM only for `index.php`, denies dotfiles except `.well-known`, sets `client_max_body_size 25m`, sets security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`) and enables HSTS after HTTPS is verified.
4. THE `docs/deployment.md` file SHALL give PHP-FPM and `php.ini` settings: `upload_max_filesize = 20M`, `post_max_size = 25M`, `memory_limit` of at least 256M, `max_execution_time` at least 60 for web requests, OPcache enabled, and a statement that the 20 MB limit matches the recording limit and the 5 MB limit matches the CSV limit of S03.
5. THE `docs/deployment.md` file SHALL give a Supervisor program that runs `php artisan queue:work --tries=3 --timeout=180` as the deploy user with `autorestart=true`, `stopwaitsecs` of at least 200 and log files outside the web root.
6. THE `docs/deployment.md` file SHALL state the timing rule that the worker `--timeout` (180 seconds) is greater than the longest job timeout (the 120 seconds of transcription), and that the queue connection `retry_after` in `config/queue.php` is greater than the worker `--timeout` (at least 240 seconds), so that a running job is not released and processed twice.
7. THE `docs/deployment.md` file SHALL give the cron line `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1` for the deploy user and SHALL state that the scheduler runs only the retention cleanup.
8. THE `docs/deployment.md` file SHALL describe HTTPS with Let's Encrypt through Certbot, including automatic renewal and the renewal test command.
9. THE `docs/deployment.md` file SHALL describe the private storage: `storage/app/private` is outside `public`, files are served only through authorized streaming, permissions allow the deploy user and the PHP-FPM user (group) to write, and no symlink to private storage exists in `public`.
10. THE `docs/deployment.md` file SHALL provide a production `.env` table with: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` with HTTPS, `APP_KEY`, database variables, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_SECURE_COOKIE=true`, `SESSION_DRIVER` as defined by S01, `LOG_LEVEL`, the AI variables, `DEMO_MODE=false` (or `true` only on the dedicated demo server with its own database), and the rule that secrets are never committed.
11. THE `docs/deployment.md` file SHALL describe the backups: a nightly `pg_dump` in custom format to a directory outside the web root, an `rsync` of `storage/app/private` to a second location, a retention of 14 daily backups, file permissions 600, and a restore procedure verified on a Scratch_Database.
12. THE `docs/deployment.md` file SHALL give the deployment steps in order: maintenance mode, `git pull`, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan config:cache`, `route:cache`, `view:cache`, `php artisan queue:restart`, leaving maintenance mode, and a post-deployment smoke test.
13. THE `docs/deployment.md` file SHALL give a rollback procedure: restore the previous release commit, restore the database from the last backup when a migration is not reversible, run `queue:restart` and repeat the smoke test.
14. THE `docs/deployment.md` file SHALL give the post-deployment smoke test: the home page over HTTPS, login, a CSV import completing through the queue worker, a brief generation (provider reachable), a file download that requires authentication, and a request for `/.env` that returns 403 or 404.
15. THE `docs/deployment.md` file SHALL state that `demo:*` commands are not run on a server that holds real data (Requirement 2), and SHALL describe the dedicated demo server option with `DEMO_MODE=true` and `DEMO_ALLOW_PRODUCTION_DEMO=true`.
16. THE `docs/deployment.md` file SHALL list the server log locations and the commands to read them without printing secrets.
17. THE `docs/deployment.md` file SHALL state that the document describes a demonstration deployment and that the passing checks do not make the Application production-ready.

### Requirement 19: Environment template review

**User Story:** As a developer, I want `.env.example` to list every variable the Application reads, so that a new environment never fails on a missing setting.

#### Acceptance Criteria

1. WHEN S09 verification runs, THE project SHALL compare every `env(...)` call in `config/*.php` with the keys of `.env.example` and SHALL list each variable that is missing from `.env.example` in `docs/readiness-fixes.md`.
2. THE `.env.example` file SHALL contain the variables `AI_BASE_URL`, `AI_TEXT_MODEL`, `AI_TRANSCRIPTION_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE`, `OPENAI_API_KEY`, `DEMO_MODE`, `DEMO_FIXED_DATE`, `DEMO_PASSWORD`, `QUEUE_CONNECTION`, `CACHE_STORE`, the database variables and `APP_*` variables.
3. WHERE S09 adds a configuration key, THE `.env.example` file SHALL contain the matching variable with an empty or safe default and a comment, including `DEMO_ALLOW_PRODUCTION_DEMO=false`.
4. THE `.env.example` file SHALL contain no real secret, key, password or personal data.
5. THE `.env.example` file SHALL set `DEMO_MODE=false`, and leave `DEMO_FIXED_DATE`, `DEMO_PASSWORD` and `OPENAI_API_KEY` empty.
6. THE project SHALL document each variable in `docs/local-setup.md` or `docs/deployment.md` with its purpose, whether it is required, and its default.
7. THE project SHALL verify that no variable is read through `env()` outside `config/*.php`.

### Requirement 20: Personal data and third-party processing checklist

**User Story:** As an Owner, I want a plain list of personal-data and third-party questions, so that nothing real is processed before they are answered.

#### Acceptance Criteria

1. THE `docs/pdpa-considerations.md` file SHALL state in its introduction that Stage 1 uses fictional data only, that the document is a checklist and not legal advice, and that the project makes no claim of compliance with the Malaysia Personal Data Protection Act 2010 or any other law.
2. THE `docs/pdpa-considerations.md` file SHALL inventory the personal data the Application processes: user names, emails and password hashes, staff voice recordings, transcripts that contain staff names and statements, task assignments and comments, and activity logs.
3. THE `docs/pdpa-considerations.md` file SHALL inventory the data sent to the third-party AI provider per feature as in `docs/architecture.md` section 6: structured metrics and issue evidence for briefs, the audio file for transcription, transcript text, meeting date, timezone and staff display names for extraction, and brief text for speech.
4. THE `docs/pdpa-considerations.md` file SHALL give a checklist table with the columns principle, question, current Stage 1 behavior, evidence in the Application, and status (`open`, `answered`, `needs client decision`), covering the general, notice and choice, disclosure, security, retention, data integrity, access, and cross-border transfer considerations.
5. THE `docs/pdpa-considerations.md` file SHALL list the open client decisions of `docs/stage-1-scope.md` section 8: recording consent policy, data residency and the position on sending data to a third-party AI provider, retention periods, language of meetings and the user roster.
6. THE `docs/pdpa-considerations.md` file SHALL state the in-application controls that exist: the consent checkbox persisted per meeting, private storage, authorized downloads, role-based access, log redaction and the retention command, each referencing the owning spec requirement.
7. THE `docs/pdpa-considerations.md` file SHALL list the actions needed before real data is processed: a data processing agreement with the provider, a recording notice for participants, a retention decision, an access and correction procedure, a breach response contact and a cross-border transfer assessment.
8. THE `docs/pdpa-considerations.md` file SHALL state that provider data-use and retention settings are to be confirmed from the provider's current terms, and SHALL cite no unverified provider claim.

### Requirement 21: Retention and deletion procedure

**User Story:** As an Owner, I want a documented way to delete recordings and demo data, so that data does not stay longer than agreed.

#### Acceptance Criteria

1. THE `docs/retention-and-deletion.md` file SHALL state the Stage 1 retention default of 30 days for meeting recordings (D22, A8 of the scope), that the value is a demonstration default, and the name of the S06 retention command and its configuration key as implemented.
2. THE `docs/retention-and-deletion.md` file SHALL describe how the retention command is scheduled (`schedule:run` through cron, Requirement 18 criterion 7) and how to run the command manually.
3. THE `docs/retention-and-deletion.md` file SHALL describe the Owner's early deletion of a recording through the Application, and the expected result in the database (the recording file removed, the meeting and transcript state as defined by S06).
4. THE `docs/retention-and-deletion.md` file SHALL list the stored data classes with location and the deletion method: recordings, imported CSV files (retained for traceability, S03 Requirement 11), brief audio, transcripts, logs, backups, and the database.
5. THE `docs/retention-and-deletion.md` file SHALL state that backups retain deleted data until they expire (14 days) and how to remove a specific backup on request.
6. THE `docs/retention-and-deletion.md` file SHALL describe how to remove the Demo_Company with `demo:reset` and how to remove all demo data and files before a real deployment.
7. THE `docs/retention-and-deletion.md` file SHALL include a verification procedure: create a recording older than the retention period on a Scratch_Database by changing the clock with `demo:set-date`, run the retention command, and check that the file is removed and no other file is removed.
8. IF the S06 retention command does not support the verification of criterion 7 without a direct database edit, THEN THE project SHALL record a Readiness_Fix instead of using a direct edit.

### Requirement 22: Known risks register

**User Story:** As a project owner, I want one register of known risks and limits, so that the client demonstration is honest about what Stage 1 does not do.

#### Acceptance Criteria

1. THE `docs/risks.md` file SHALL list each risk with an identifier, description, likelihood (low, medium or high), impact (low, medium or high), mitigation or contingency, owner and status.
2. THE `docs/risks.md` file SHALL include these risks: AI provider outage or latency during the demonstration, transcription accuracy with accents and background noise, extraction variability of task titles, prompt-injection attempts through transcripts, third-party processing of personal data, demonstration defaults that differ from the client's real data (file layout, branches, holidays, thresholds), the database queue throughput, single-server deployment without high availability, backups not yet restored in production, the demo clock file differing from the real date, and the ambiguity of ISO-week reporting for holidays (A14 of S03).
3. THE `docs/risks.md` file SHALL include a risk for the product brief not being stored in the repository when assumption A8 applies.
4. THE `docs/risks.md` file SHALL include the cost and rate limits of the AI provider as a risk with the mitigation of rehearsal budgeting.
5. THE `docs/risks.md` file SHALL include an explicit statement of the Stage 1 limits: fictional data only, no production certification, no compliance claim and no support for Stage 2 features.
6. THE `docs/risks.md` file SHALL cross-reference each risk that came from a failed verification step with the identifier of its Readiness_Fix.
7. WHEN the last Rehearsal ends, THE Operator SHALL review the register and set the status of each risk to `open`, `mitigated` or `accepted`.

### Requirement 23: Documentation quality and honest reporting

**User Story:** As a reviewer, I want documents that agree with each other and with the code, so that following them gives the described result.

#### Acceptance Criteria

1. THE S09 documents SHALL use the same names for roles, statuses, commands, environment variables and file paths as `docs/data-model.md`, `docs/architecture.md`, `docs/demo-scenario.md` and the S01 to S08 specs.
2. WHEN S09 is implemented, THE project SHALL update `docs/architecture.md` with a short "Demonstration tooling" section that lists the Demo_Commands, the fixed-date file and the guard conditions, and SHALL update `docs/demo-scenario.md` only to add references to the S09 files.
3. THE S09 documents SHALL contain no password, API key, real email address or real personal data.
4. THE S09 documents SHALL use numbered steps with an expected result for each command, and SHALL mark every command that deletes data with the text "DESTRUCTIVE" and its guard.
5. THE verification record SHALL report results honestly, including failures and blocked checks, and SHALL NOT state that the Application is production-ready.
6. WHEN a command or setting in a document differs from the implemented command or setting, THE project SHALL correct the document in the same change.
7. THE S09 documents SHALL be written in plain English that a non-developer Operator can follow for the Walkthrough, and SHALL define each technical term used in the Operator sections at first use.

### Requirement 24: Command behavior and output standards

**User Story:** As an Operator, I want predictable command results and messages, so that I know whether a command worked.

#### Acceptance Criteria

1. THE Demo_Commands SHALL exit with code zero on success and a non-zero code on any refusal or failure.
2. THE Demo_Commands SHALL print plain-text messages that state what was done or why the command refused, and SHALL print no stack trace unless the `-v` option is given.
3. THE Demo_Commands SHALL print no value of `DEMO_PASSWORD`, `OPENAI_API_KEY`, session data or file contents, even with the `-v` option.
4. WHEN a Demo_Command is run with the `--help` option, THE Demo_Commands SHALL describe the purpose, options, guard conditions and the DESTRUCTIVE marking where applicable.
5. THE Demo_Commands SHALL be registered through Laravel's command discovery and SHALL appear in `php artisan list` under the `demo` namespace.
6. THE Demo_Commands SHALL follow the Actions-for-multi-step rule of `docs/architecture.md` section 10 and SHALL perform every multi-write operation inside one database transaction, except file deletion, which follows the transaction.
7. THE `demo:seed` and `demo:reset` commands SHALL run `php artisan queue:restart` signaling through the Laravel cache restart key when they finish, so that a running worker loads the current state.
8. THE Demo_Commands SHALL perform authorization by environment guard only and SHALL NOT bypass any policy of the Application when they call S02 to S08 Actions.

### Requirement 25: Scope boundaries

**User Story:** As a project owner, I want S09 to stay inside Stage 1 readiness, so that no new feature slips in before the demonstration.

#### Acceptance Criteria

1. THE S09 deliverables SHALL include no multi-company support, no extra department, no Excel import, no automated data sync, no external email or WhatsApp, no chatbot and no scheduled demo reset.
2. THE S09 deliverables SHALL include no admin screen for demo control, no web button for reset, and no web form for the demo date.
3. THE S09 deliverables SHALL include no Docker file, no Kubernetes file, no Redis configuration and no continuous-integration pipeline.
4. THE S09 deliverables SHALL include no automated unit or end-to-end test suite (assumption A11).
5. THE S09 deliverables SHALL include no change that makes the Application production-certified, and SHALL NOT state such a claim in any document.
6. THE S09 deliverables SHALL include no monitoring service, no error-tracking service and no log-shipping setup, and SHALL describe only the log files and commands for reading them.
