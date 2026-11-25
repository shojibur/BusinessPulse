# Implementation Plan: S03 Sales Data Import and Validation

## Overview

The implementation language is PHP (Laravel 13) with Vue 3 and Inertia, as used in the design. The plan builds bottom-up: configuration and data model, then the pure services (reader, validator, expected branches, reporting calendar and coverage, classifier, preview), the fixtures with their generator and checker, then events, actions and the queued job, then `demo:import`, policies, HTTP layer and Vue pages, and last the documentation updates and the focused feature tests.

Per `docs/stage-1-refinements.md` R5, this plan includes focused automated feature tests on the S01 harness (`composer test`, PostgreSQL `businesspulse_testing`, `RefreshDatabase`, no SQLite, no browser automation, no property-based framework, no external AI call). Manual acceptance (Requirement 23.11), static analysis and build checks still apply.

### Tier legend (R1)

- **[PoV Core]**: required for the 22-step demonstration. Build and finish these first.
- **[PoV Polish]**: Stage 1 scope built after the Core slice works: the expected-branches settings UI, the Settings "Import template" tab beyond the template download link, the source CSV download with `ImportFileDownloaded`, the events `ImportValidated`, `ImportConfirmed` and `ImportCancelled`, and rate-limit tuning.

## Tasks

- [ ] 1. Foundation: configuration, enums, migrations and models
  - [ ] 1.1 [PoV Core] Create `config/imports.php` with the single column specification and limits
    - Define the five template columns (name, required flag, type, rule, regex, example), the template header string, max file size 5,242,880 bytes, max 10,000 rows, allowed MIME types, minimum date 2000-01-01 and the upload throttle of 10 per minute
    - This is the only source for the template download, the Template tab table and header validation
    - _Requirements: 3.5, 3.6, 4.3, 4.4, 4.9, 4.11, 5.5_

  - [ ] 1.2 [PoV Core] Create enums `ImportStatus`, `ImportRowResult`, `WeekStatus`, `WeekCompleteness` and `FreshnessState` in `App\Enums`
    - `ImportStatus`: uploaded, validated, processing, completed, completed_with_errors, failed, cancelled, with an allowed-transition map and a label/icon accessor
    - `ImportRowResult`: new, corrected, unchanged, invalid
    - `WeekStatus`: open, closed. `WeekCompleteness`: complete, incomplete, absent. `FreshnessState`: current, behind
    - _Requirements: 1.7, 11.1, 14.9, 15.3, 16.6, 16.7_

  - [ ] 1.3 [PoV Core] Create the migrations for `imports`, `import_errors` and `sales_records`
    - Columns, foreign keys and indexes as in the design Data Models section; `numeric(14,2)` money, nullable integer `order_count`
    - Partial unique index on `sales_records (department_id, branch_code, sales_date) WHERE is_active`
    - Check constraints for `imports.status` and for non-negative `revenue` and `target_revenue`
    - Add no migration for `departments`: `departments.settings` is created by S02
    - Implement `down()` for every migration so rollback works; order them after the S02 migrations without editing those
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.8_

  - [ ] 1.4 [PoV Core] Create the models `Import`, `ImportError` and `SalesRecord`
    - Relationships as in Requirement 1.6; `ImportStatus` cast on `imports.status`; `array` cast for `raw_row`; `decimal:2` casts for money
    - Apply the `ScopedToDepartment` trait to `Import` and `SalesRecord`
    - Do not change the `Department` model; read expected branches through its S02 `expectedBranches()` accessor
    - _Requirements: 1.6, 1.7, 1.9, 2.3, 11.7_

- [ ] 2. Core services, fixtures and fixture checker
  - [ ] 2.1 [PoV Core] Implement `CsvImportReader` and `ImportRejectedException`
    - File-level checks in order: extension, MIME via `finfo`, size, empty or no header, UTF-8 after BOM removal, missing, unexpected and duplicate header columns, row limit
    - Streaming `rows()` generator using `SplFileObject`, header names case-insensitive and trimmed, column order free, blank lines ignored, any line ending
    - Throws a coded exception carrying the missing or offending column names; creates no DB row and stores no file
    - _Requirements: 3.6, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10, 4.13, 20.7_

  - [ ] 2.2 [PoV Core] Implement `ImportRowValidator` and its DTOs (`RowValidationResult`, `RowError`, `ValidRow`)
    - Row numbering from 2; codes `missing_value`, `invalid_date`, `future_date`, `date_out_of_range`, `invalid_branch_format`, `negative_value`, `too_many_decimals`, `value_out_of_range`, `invalid_number`, `invalid_integer`, `wrong_column_count`, `unknown_branch`
    - One error per problem in column order, plain-language messages, `raw_row` kept as an object of strings
    - Branch trimmed and upper-cased and checked against the Expected_Branches array supplied by the caller (a branch outside the list is `unknown_branch`); money kept as 2-decimal strings, never floats; zero is a valid value; future date uses `BusinessClock::today()` (or the date supplied by the caller for `demo:check-fixtures`)
    - `detectDuplicateIdentities()` second pass that adds `duplicate_in_file` to every colliding row and names the other row numbers
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 5.10, 5.11, 5.12, 5.13, 5.14, 5.15, 5.16, 6.2, 6.4, 7.4, 18.1, 18.4, 18.5_

  - [ ] 2.3 [PoV Core] Implement `ExpectedBranches` (read path)
    - `get(Department)` returns the sorted unique upper-case `settings.expected_branches` array or `[]`; no `initializeFromImport`, no auto-fill from any import
    - The write method `set()` is added by task 4.5
    - _Requirements: 6.1, 6.5_

  - [ ] 2.4 [PoV Core] Implement `ReportingCalendar`, `ReportingCoverage` and their value objects (`WeekState`, `CoverageGap`, `Freshness`, `PendingRows`)
    - `ReportingCalendar` (pure, no database): `forCompany(Company, now)`, `isDue(date)` (trading day and now at or after date plus lag days at cutoff time in the company timezone), `lastDueDate()`, `tradingDates(weekStart)`, `isWeekClosed(weekStart)`
    - `ReportingCoverage`: `calendar`, `expectedBranches`, `isDue`, `lastDueDate`, `tradingDates`, `weekState` and `weekStates` (one range query, optional `PendingRows` overlay), `weekStateForRows` (database-free, shared by `weekState`), `latestClosedWeek`, `missingDueBranchDays` (kinds `inside_period` and `after_latest`, never a not-yet-due date), `latestSalesDate`, `freshness`; every method accepts an optional `now` override that defaults to `BusinessClock::now()`
    - Week rules: `closed` iff every trading day is due, else `open`; closed week `absent` iff no present branch-day or no Expected_Branches, `incomplete` iff any expected branch-day is missing, else `complete`; open week has null completeness and lists only due gaps
    - Ignore non-trading days and branch codes outside Expected_Branches; count zero revenue as present; never substitute zero for a gap; `coveragePct` with bcmath at 4 dp round half up, null when expected is zero
    - Freshness: `current` iff latest sales date is on or after `lastDueDate`, else `behind` with the overdue branch-day count (null when no Active_Record), first and last overdue dates
    - Read-only, stateless, bounded queries; return facts only and make no rule decision; week start is the ISO Monday
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.7, 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 16.7, 16.8, 16.9, 16.10, 16.11, 16.12, 16.13, 16.14, 16.15, 16.16, 16.17, 16.18, 16.19, 16.20, 16.21_

  - [ ] 2.5 [PoV Core] Implement `ImportClassifier`
    - Label valid rows `new`, `corrected` or `unchanged` against Active_Records loaded in one indexed lookup scoped to the department
    - Compare money after normalizing to 2 decimals with bcmath; null `order_count` equals only null
    - _Requirements: 7.1, 7.2, 7.3, 18.1, 18.2_

  - [ ] 2.6 [PoV Core] Implement `ImportPreviewBuilder` and `ImportPreviewData`
    - Counts, min and max date of valid rows, distinct branches, paginated errors (50 per page, ordered by row number), non-trading-day notice count
    - Per affected week the Week_State (open or closed, and complete, incomplete or absent) via `ReportingCoverage::weekStates()` with the pending overlay, gaps for due days only (max 50 per week plus remaining count), open weeks labelled informational
    - Overdue branch-day count and Last_Due_Date after the overlay (from `freshness()` with pending rows), shown as whether the file leaves due days uncovered
    - "File already imported" notice from `file_hash` matches on completed imports, and the "preview refreshed" flag when rebuilt counts differ from persisted counts
    - Counter invariants: `total_rows = valid_rows + invalid_rows` and `valid_rows = inserted + corrected + unchanged`; blank lines and header excluded
    - Single code path used for both initial validation and later rebuilds from the stored file
    - _Requirements: 7.5, 7.6, 7.8, 7.9, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.12, 8.13, 8.14, 8.15_

  - [ ] 2.7 [PoV Core] Implement `FixtureGenerator`, the six CSV fixtures and `expected.json`
    - `App\Services\Demo\FixtureGenerator`: `files()`, `dailyTotals()` (floor of weekly / 6 plus 1 MYR per day from Monday until the remainder is used), `split()` (PG = floor(D×8/26), JB = floor(D×6/26), KL the rest); integer arithmetic only; LF line endings, no BOM, header from `config/imports.php`, empty `order_count`, targets 12000.00, 8000.00 and 6000.00
    - Commit `database/demo/history.csv` (W1 to W7, 126 rows), `week8-decline.csv` (124,000), `week8-with-errors.csv` (20 rows: 17 valid, JB Saturday `invalid_number` on row 19, `invalid_date` on row 20, Sunday 2026-09-27 `negative_value` on row 21), `week8-correction.csv` (KL Wednesday and PG Thursday +1,000.00, total 126,000), `week9-interim.csv` (138,000) and `week10-recovery.csv` (150,000) exactly per `docs/demo-scenario.md` section 2
    - Author `database/demo/expected.json` by hand with literal values: calendar, Expected_Branches, and per fixture the clock, `after` list, row counts, error `(row, column, code)` list, weekly totals, branch-day counts and Week_State per week start
    - _Requirements: 22.1, 22.2, 22.3, 22.4, 22.5, 22.6, 22.7, 22.14_

  - [ ] 2.8 [PoV Core] Implement `demo:check-fixtures`
    - `App\Console\Commands\DemoCheckFixtures` with the option `--write` (rewrites only the CSV files from the generator, never `expected.json`)
    - For each fixture in `expected.json`: file bytes equal the generator output; the real `CsvImportReader` and `ImportRowValidator` (business date from the fixture clock, Expected_Branches from `expected.json`) give the expected valid and invalid counts and error list; weekly totals and branch-day counts match; `ReportingCoverage::weekStateForRows` at the fixture clock returns the expected Week_State after overlaying the fixtures in `after`
    - Read no database row; print one line per mismatch (fixture, check, expected, actual); exit 0 only when everything matches
    - _Requirements: 22.11, 22.12, 22.13_

- [ ] 3. Checkpoint - Ensure services, fixtures and migrations are sound
  - Run `php artisan migrate:fresh` and a rollback of the S03 migrations on a scratch database, `php artisan demo:check-fixtures`, then `composer pint-test`. Ensure all checks pass, ask the user if questions arise.
  - _Requirements: 23.12_

- [ ] 4. Events, actions, queued job and demo import
  - [ ] 4.1 [PoV Core] Create the Core events `ImportCompleted` and `ImportFailed` in `App\Events\Imports`
    - Public properties `importId`, `departmentId`, `actorUserId` (null for system events) and `occurredAt`; `ImportCompleted` also carries `status`, `insertedRows`, `correctedRows`, `unchangedRows`, `invalidRows`, `minDate`, `maxDate`
    - No listeners registered, no activity-log or rule-evaluation code in S03
    - _Requirements: 17.1, 17.2, 17.3, 17.6, 17.7, 17.8_

  - [ ] 4.2 [PoV Core] Implement the `CreateImportFromUpload` action
    - First check the department has Expected_Branches; if empty, throw `ImportRejectedException('no_expected_branches')` before reading the file (no row, no file)
    - Run the reader validation, then store the file on the private `local` disk under `imports/{import_id}/` with a random name, save a sanitized `original_filename` and the SHA-256 `file_hash`
    - Create the Import as `uploaded`, validate and classify all rows, save `import_errors` and counters, set `validated`
    - Set `company_id`, `department_id` and `uploaded_by` from the authenticated user and Company; on a post-creation failure set `failed` with a sanitized message and dispatch `ImportFailed`
    - Write no `sales_records` and never change Expected_Branches
    - _Requirements: 2.6, 2.7, 2.8, 4.10, 6.3, 6.5, 7.5, 8.1, 8.14, 11.5, 12.1, 12.2, 17.5_

  - [ ] 4.3 [PoV Core] Implement the `ConfirmImport`, `CancelImport` and `RetryImport` actions
    - `ConfirmImport`: row lock, require `validated` and at least one valid row, set `processing` and `confirmed_at`, dispatch `ProcessImportJob` on the `default` queue after commit; return the current Import without a second dispatch if the status is not `validated`
    - `CancelImport`: allow only `uploaded` and `validated`, otherwise reject with a message naming the current status
    - `RetryImport`: allow only `failed` with a stored file present, lock, set `processing`, clear `error_message`, dispatch a new job
    - Enforce the status transition map; keep the file in every status
    - _Requirements: 9.1, 9.2, 11.1, 11.2, 11.3, 11.4, 11.6, 17.5_

  - [ ] 4.4 [PoV Core] Implement `ProcessImportJob`
    - `ShouldQueue` and `ShouldBeUnique` keyed by Import id with `uniqueFor` 900; queue `default`; `$timeout` 120, `$tries` 3, `backoff()` 10, 30, 60; `afterCommit()`; a `failed()` handler that sets `failed` with a sanitized message (no paths, stack traces or cell values) and dispatches `ImportFailed`
    - Exit without writes unless status is `processing`; acquire a per-department `Cache::lock` and release the job back to the queue if it is held
    - Fail with "Expected branches are not configured" when Expected_Branches is empty; re-read the stored file, re-validate and re-classify; fail with "No valid rows to import" on zero valid rows; fail with a sanitized message and no writes if the file is missing (each early failure dispatches `ImportFailed`)
    - One transaction: insert `new` rows, supersede and insert `corrected` rows, skip `unchanged`, never delete, write in chunks of at most 1,000, replace `import_errors`, update counters and set `completed` or `completed_with_errors` with `completed_at`; never change Expected_Branches
    - Map unique-index violations to "Data changed during import. Please upload again"; dispatch `ImportCompleted` after commit
    - _Requirements: 6.6, 7.7, 7.8, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 9.10, 9.11, 9.12, 9.13, 9.14, 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 17.3, 17.5, 20.4_

  - [ ] 4.5 [PoV Polish] Implement `UpdateExpectedBranches` and `ExpectedBranches::set()`
    - Validate branch format, remove duplicates, upper-case and sort; reject an empty list and a list over 50 codes; reject while an Import is `processing` for the department
    - Save only `settings.expected_branches` and preserve every other key of `departments.settings`; leave existing Sales_Records unchanged when a code is removed
    - _Requirements: 6.7, 6.8, 6.9, 6.10, 6.11_

  - [ ] 4.6 [PoV Polish] Create the Polish events and dispatch them
    - `ImportValidated`, `ImportConfirmed`, `ImportCancelled`, `ImportFileDownloaded` with the base properties; dispatch from `CreateImportFromUpload`, `ConfirmImport`, `CancelImport` (after commit) and the source file download action
    - _Requirements: 17.1, 17.2, 17.4, 17.5, 17.8_

  - [ ] 4.7 [PoV Core] Implement `demo:import {fixture}`
    - `App\Console\Commands\DemoImport`: refuse with a non-zero exit unless `config('demo.mode')` is true; accept the six fixture names with or without `.csv`
    - Act as the Owner of the demo company; set `config(['queue.default' => 'sync'])` for the process; wrap `database/demo/{fixture}.csv` in a test-mode `UploadedFile`, call `CreateImportFromUpload`, then `ConfirmImport` outside any transaction so `ProcessImportJob` runs inline; insert nothing directly
    - Print status, counters and error codes; exit non-zero on rejection, a non-completed Import, or counters that differ from `expected.json`
    - _Requirements: 22.8, 22.9, 22.10_

- [ ] 5. Authorization
  - [ ] 5.1 [PoV Core] Implement `ImportPolicy` and `SalesRecordPolicy` and register them
    - `ImportPolicy`: `viewAny`, `view`, `create`, `confirm`, `cancel`, `retry`, `download` for an active Owner, and for an active Manager within the own department; Staff and inactive users denied
    - `SalesRecordPolicy`: `viewAny` and `view` only for Owner and own-department Manager, no create, update or delete ability
    - `ImportError` rows are reachable only through the parent Import authorization
    - _Requirements: 2.1, 2.2, 2.4, 2.5, 2.10, 2.11_

- [ ] 6. HTTP layer
  - [ ] 6.1 [PoV Core] Create the Form Request `UploadImportRequest`
    - Validate before any write; ignore `company_id`, `department_id` and `uploaded_by` from the body; map reader rejection codes and `no_expected_branches` to a 422 response with the expected header and template link
    - _Requirements: 2.8, 4.10, 6.3, 20.1_

  - [ ] 6.2 [PoV Core] Create `ImportController`
    - Actions: index (status filter, 25 per page, live Freshness, polling data), create (upload page with the template download link), store, show (preview or detail data, error pagination 50 per page, Week_State per affected week for completed imports), confirm, cancel, retry
    - Resolve Imports through `Import::visibleTo()` (404 out of scope), call the policy explicitly in every action, pass only safe props (never `stored_path`), use S01 flash toasts
    - Freshness is computed per request through `ReportingCoverage::freshness()` and never stored
    - No destroy route or delete action
    - _Requirements: 2.2, 2.3, 2.4, 2.9, 8.2, 8.13, 8.15, 11.7, 12.7, 13.1, 13.2, 13.3, 13.6, 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.10, 15.5, 15.6, 15.7, 20.2, 20.5, 20.6_

  - [ ] 6.3 [PoV Core] Create `ImportTemplateController`
    - Template download: policy check, stream `sales-import-template.csv` containing only the header row from `config/imports.php`
    - _Requirements: 3.1, 2.2_

  - [ ] 6.4 [PoV Polish] Create `ImportTemplateSettingsController`, `UpdateExpectedBranchesRequest` and register the Settings tab
    - Register "Import template" in the S02 Settings tab extension point for Owner and Manager; Staff gets 403
    - Provide the column table, limits, Trading_Days and timezone, Expected_Branches editor data, latest closed week coverage per branch from `ReportingCoverage`, and the notice of branches present in Active_Records but missing from Expected_Branches
    - Save through `UpdateExpectedBranches` with a success toast and field errors via `UpdateExpectedBranchesRequest`
    - _Requirements: 3.2, 3.3, 3.4, 6.7, 6.8, 6.9, 6.12, 19.1, 19.2, 19.3, 19.4, 19.5, 19.6_

  - [ ] 6.5 [PoV Core] Register the routes
    - Imports index, create, store, show, confirm, cancel, retry and template download, plus (PoV Polish) the source file download, the Settings tab and the expected-branches update once tasks 6.4 and 6.6 exist
    - `auth` and active-user middleware, CSRF, and `throttle` of 10 per minute per user on upload; the 429 response with seconds remaining is PoV Polish
    - _Requirements: 2.5, 4.11, 4.12, 20.2_

  - [ ] 6.6 [PoV Polish] Create `ImportFileController`
    - Source file download: policy check, 404 when the file is missing with a "source file unavailable" message, stream `text/csv` as an attachment with the sanitized name, dispatch `ImportFileDownloaded`
    - _Requirements: 12.3, 12.4, 12.5, 12.6, 12.7_

- [ ] 7. Frontend pages
  - [ ] 7.1 [PoV Core] Create shared import UI components and types
    - `FileDropzone` and a status polling composable with an `aria-live` region (S03 is the first consumer; S01 does not provide them)
    - Import status badge (icon plus text label), preview panel (counts, date range, branches, notices, Week_State per week with due-day gaps, uncovered due days notice), error table (row, column, code, message, escaped text only), skeleton, `EmptyState` and `ErrorState` wrappers
    - _Requirements: 5.17, 8.3, 8.4, 8.5, 8.6, 8.7, 8.9, 8.11, 8.15, 13.8, 14.9, 20.9_

  - [ ] 7.2 [PoV Core] Build the Data Imports history page (`resources/js/pages/Imports/Index.vue`)
    - Live freshness block (messages of Requirement 15.5), status filter, table with status, file name, date range, total and invalid rows, uploader and upload date in company timezone, empty states, "Upload sales data" action, 3-second polling while any Import is `processing`, cards below 768 px
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.9, 13.10, 15.5, 20.10_

  - [ ] 7.3 [PoV Core] Build the Upload with preview page (`resources/js/pages/Imports/Upload.vue`)
    - `FileDropzone` upload with file-level rejection message (including `no_expected_branches`), expected header and template download link, preview display after validation, Confirm (disabled with no valid rows) and Cancel actions, "File already imported" and "preview refreshed" notices
    - _Requirements: 4.10, 6.3, 8.2, 8.8, 8.10, 8.13, 9.1, 11.2, 20.9, 20.10_

  - [ ] 7.4 [PoV Core] Build the Import detail page (`resources/js/pages/Imports/Show.vue`)
    - Metadata, counters, paginated errors, preview with Confirm and Cancel for `validated`, error message and Retry for `failed`, Week_State per affected week for completed imports, "invalid rows were not imported" notice, 3-second status polling while `processing`
    - [PoV Polish] "Download source file" action
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7, 14.8, 14.9, 14.10, 20.9, 20.10_

  - [ ] 7.5 [PoV Polish] Build the Settings "Import template" tab (`resources/js/pages/Settings/Tabs/ImportTemplate.vue`)
    - Template download, column specification table, file limits, read-only Trading_Days and timezone with Company tab link for the Owner, Expected_Branches editor with visible labels, field errors with submitted values, branch coverage and missing-branch notices; keyboard operable
    - _Requirements: 3.2, 3.3, 3.4, 6.12, 19.3, 19.4, 19.5, 19.6, 19.7, 20.10_

- [ ] 8. Documentation and focused tests
  - [ ] 8.1 [PoV Core] Update `docs/data-model.md` and `docs/architecture.md`
    - `docs/data-model.md`: document the `imports` counter semantics and invariants (`departments.settings` is already documented)
    - `docs/architecture.md`: document the `ReportingCoverage` and `ReportingCalendar` contract (`isDue`, `lastDueDate`, `weekState`, `latestClosedWeek`, `missingDueBranchDays`, `freshness`, pending overlay, `now` override), the S03 events with payloads and tiers (S04 listens to `ImportCompleted`, S08 to `ImportCompleted` and `ImportFailed`), and the commands `demo:import` and `demo:check-fixtures` with the fixture files
    - _Requirements: 1.10, 17.9_

  - [ ] 8.2 [PoV Core] Write focused feature tests for import behavior and authorization
    - Under `tests/Feature/Imports` with `RefreshDatabase` on PostgreSQL, the S02 demo identity data or equivalent factories, and the Demo_Fixtures or small in-test CSV strings; no external AI call
    - Duplicate import: the same file twice gives all `unchanged` on the second import, the "File already imported" notice, and unchanged Active_Record count and revenue sum (Property 1)
    - Correction: `week8-correction.csv` after `week8-decline.csv` gives 16 unchanged and 2 corrected, 18 Active_Records, 2 preserved inactive rows with `superseded_by_import_id` and readable old values (Property 2)
    - Duplicate in file: both rows `duplicate_in_file` and the counter invariants hold (Property 3)
    - Branch configuration: `unknown_branch` row error, and `no_expected_branches` rejection with no `imports` row and no stored file (Property 6)
    - Authorization: a Manager of another department (second test-only department) gets 404 and Staff gets 403 on the Import_Pages routes, confirm and cancel; Owner and own Manager are permitted (Property 8)
    - _Requirements: 23.1, 23.2, 23.3, 23.4, 23.7, 23.8_

  - [ ] 8.3 [PoV Core] Write focused feature tests for coverage, cutoff and fixtures
    - With `history.csv` imported: at clock 2026-09-21 09:00 W8 is `open`, `missingDueBranchDays` returns no gap and Freshness is `current`; at clock 2026-09-28 09:00 Saturday 2026-09-26 is due and Monday 2026-09-28 is not, W8 is `closed` and `absent`, and Freshness is `behind` with 18 overdue branch-days (Property 4)
    - After `week8-with-errors.csv` at 2026-09-28 09:00 W8 is `closed` and `incomplete` with exactly one `inside_period` gap (JB, 2026-09-26), and no sum includes a zero for that day; a `0.00` revenue row counts as present (Property 5)
    - Set the clock through the S01 demo clock file or the `now` override; run `demo:check-fixtures` and assert exit code 0 (Property 7)
    - _Requirements: 23.1, 23.5, 23.6, 23.10_

  - [ ] 8.4 [PoV Polish] Write the source download authorization test
    - Staff gets 403 and a Manager of another department gets 404 on the source file download; the Owner and own Manager receive the file (Property 9)
    - _Requirements: 23.9_

- [ ] 9. Final checkpoint - Ensure all checks pass
  - Run `composer test`, `composer pint-test` and `npm run verify`, and confirm the migration rollback still works. Run the manual acceptance checks of Requirement 23.11 (`demo:import history` then the UI upload of `week8-decline.csv`, double Confirm, missing stored file, 6,000-row file, 360/768/1280 px and keyboard-only run). Ensure all checks pass, ask the user if questions arise.
  - _Requirements: 23.11, 23.12_

## Notes

- Focused automated tests (tasks 8.2 to 8.4) replace the earlier manual-only verification, per `docs/stage-1-refinements.md` R5. They use no browser automation, no property-based framework and no external AI call. Manual acceptance, static analysis and build checks remain.
- Fixtures, `FixtureGenerator`, `demo:import` and `demo:check-fixtures` are owned by S03 (R9). S04 extends `expected.json` with evaluation expectations and S09 reuses the files and commands.
- S03 writes no `activity_logs` rows and does not evaluate rules. `ReportingCoverage` returns facts only. S04 and S08 attach through the events and the service.
- `departments.settings` is created by S02. S03 only reads `expected_branches` and never auto-fills it.
- Each task lists the granular requirement clauses it covers.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3", "1.4"] },
    { "id": 2, "tasks": ["2.1", "2.2", "2.3", "2.7", "4.1", "5.1"] },
    { "id": 3, "tasks": ["2.4", "2.5"] },
    { "id": 4, "tasks": ["2.6", "2.8"] },
    { "id": 5, "tasks": ["3"] },
    { "id": 6, "tasks": ["4.2", "4.3", "4.4", "4.5", "8.1"] },
    { "id": 7, "tasks": ["4.6", "4.7", "6.1", "6.2", "6.3", "6.4", "6.6"] },
    { "id": 8, "tasks": ["6.5"] },
    { "id": 9, "tasks": ["7.1"] },
    { "id": 10, "tasks": ["7.2", "7.3", "7.4", "7.5"] },
    { "id": 11, "tasks": ["8.2", "8.3", "8.4"] },
    { "id": 12, "tasks": ["9"] }
  ]
}
```
