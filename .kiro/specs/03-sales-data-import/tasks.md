# Implementation Plan: S03 Sales Data Import and Validation

## Overview

The implementation language is PHP (Laravel 13) with Vue 3 and Inertia, as used in the design. The plan builds bottom-up: configuration and data model, then the pure services (reader, validator, expected branches, coverage, classifier, preview), then events, actions and the queued job, then policies, HTTP layer and Vue pages, and last the documentation updates.

Per `.kiro/steering/verification.md` and Requirement 21.6, this plan contains no unit, property or end-to-end test tasks. The design has no Correctness Properties section. Verification is manual and fixture-based (Requirement 22) and is covered by the checkpoint tasks, which run static checks (`composer pint-test`, `npm run verify`) and the migration check.

## Tasks

- [ ] 1. Foundation: configuration, enums, migrations and models
  - [ ] 1.1 Create `config/imports.php` with the single column specification and limits
    - Define the five template columns (name, required flag, type, rule, regex, example), the template header string, max file size 5,242,880 bytes, max 10,000 rows, allowed MIME types, minimum date 2000-01-01 and the upload throttle of 10 per minute
    - This is the only source for the template download, the Template tab table and header validation
    - _Requirements: 3.5, 3.6, 4.3, 4.4, 4.9, 4.11, 5.5_

  - [ ] 1.2 Create enums `ImportStatus`, `ImportRowResult` and `CoverageStatus` in `App\Enums`
    - `ImportStatus`: uploaded, validated, processing, completed, completed_with_errors, failed, cancelled, with an allowed-transition map and a label/icon accessor
    - `ImportRowResult`: new, corrected, unchanged, invalid
    - `CoverageStatus`: complete, partial, absent
    - _Requirements: 1.7, 11.1, 14.9_

  - [ ] 1.3 Create the migrations for `imports`, `import_errors`, `sales_records` and `departments.settings`
    - Columns, foreign keys and indexes as in the design Data Models section; `numeric(14,2)` money, nullable integer `order_count`
    - Partial unique index on `sales_records (department_id, branch_code, sales_date) WHERE is_active`
    - Check constraints for `imports.status` and for non-negative `revenue` and `target_revenue`
    - Additive `departments.settings` JSONB column with default `{}`
    - Implement `down()` for every migration so rollback works; order them after the S02 migrations without editing those
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.8_

  - [ ] 1.4 Create the models `Import`, `ImportError`, `SalesRecord` and update `Department`
    - Relationships as in Requirement 1.6; `ImportStatus` cast on `imports.status`; `array` casts for `raw_row` and `departments.settings`; `decimal:2` casts for money
    - Apply the `ScopedToDepartment` trait to `Import` and `SalesRecord`
    - _Requirements: 1.6, 1.7, 1.9, 2.3, 11.7_

- [ ] 2. Core services
  - [ ] 2.1 Implement `CsvImportReader` and `ImportRejectedException`
    - File-level checks in order: extension, MIME via `finfo`, size, empty or no header, UTF-8 after BOM removal, missing, unexpected and duplicate header columns, row limit
    - Streaming `rows()` generator using `SplFileObject`, header names case-insensitive and trimmed, column order free, blank lines ignored, any line ending
    - Throws a coded exception carrying the missing or offending column names; creates no DB row and stores no file
    - _Requirements: 3.6, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10, 4.13, 20.7_

  - [ ] 2.2 Implement `ImportRowValidator` and its DTOs (`RowValidationResult`, `RowError`, `ValidRow`)
    - Row numbering from 2; codes `missing_value`, `invalid_date`, `future_date`, `date_out_of_range`, `invalid_branch_format`, `negative_value`, `too_many_decimals`, `value_out_of_range`, `invalid_number`, `invalid_integer`, `wrong_column_count`, `unknown_branch`
    - One error per problem in column order, plain-language messages, `raw_row` kept as an object of strings
    - Branch trimmed and upper-cased; money kept as 2-decimal strings, never floats; zero is a valid value; future date uses `BusinessClock::today()`
    - `detectDuplicateIdentities()` second pass that adds `duplicate_in_file` to every colliding row and names the other row numbers
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 5.10, 5.11, 5.12, 5.13, 5.14, 5.15, 5.16, 6.2, 6.3, 6.4, 7.4, 18.1, 18.4, 18.5_

  - [ ] 2.3 Implement `ExpectedBranches`
    - `get()`, `set()` (validate format, dedupe, upper-case, sort, save to `departments.settings.expected_branches`) and `initializeFromImport()` (only when the list is empty, safe to call inside a transaction)
    - _Requirements: 6.1, 6.3, 6.5, 6.6, 6.9_

  - [ ] 2.4 Implement `ReportingCoverage` and its value objects (`WeekCoverage`, `CoverageGap`, `Freshness`)
    - Methods `expectedBranches`, `tradingDates`, `weekCoverage` (with optional pending identities overlay), `missingBranchDays`, `latestCompleteWeek`, `asOfDate`, `freshness`
    - Status rule: complete iff expected branch-days is above zero and equals present branch-days; partial if any Active_Record exists in the week; otherwise absent
    - Ignore non-trading days and branch codes outside Expected_Branches; count zero revenue as present; never substitute zero for a gap; `coveragePct` with bcmath at 4 dp round half up, null when expected is zero
    - Read-only, stateless, one bounded query per `weekCoverage` call; week start is the ISO Monday; freshness uses `BusinessClock::today()` so the demo fixed date applies
    - _Requirements: 15.1, 15.2, 15.6, 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 16.7, 16.8, 16.9, 16.10, 16.11, 16.12, 16.13, 16.14, 16.15, 16.16, 16.17_

  - [ ] 2.5 Implement `ImportClassifier`
    - Label valid rows `new`, `corrected` or `unchanged` against Active_Records loaded in one indexed lookup scoped to the department
    - Compare money after normalizing to 2 decimals with bcmath; null `order_count` equals only null
    - _Requirements: 7.1, 7.2, 7.3, 18.1, 18.2_

  - [ ] 2.6 Implement `ImportPreviewBuilder` and `ImportPreviewData`
    - Counts, min and max date of valid rows, distinct branches, paginated errors (50 per page, ordered by row number), non-trading-day notice count, per-week coverage with gaps (max 50 per week plus remaining count) via `ReportingCoverage::weekCoverage()` with pending identities
    - "File already imported" notice from `file_hash` matches on completed imports, branches that the first import will set as expected, and the "preview refreshed" flag when rebuilt counts differ from persisted counts
    - Counter invariants: `total_rows = valid_rows + invalid_rows` and `valid_rows = inserted + corrected + unchanged`; blank lines and header excluded
    - Single code path used for both initial validation and later rebuilds from the stored file
    - _Requirements: 6.11, 7.5, 7.6, 7.8, 7.9, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.12, 8.13, 8.14_

- [ ] 3. Checkpoint - Ensure services and migrations are sound
  - Run `php artisan migrate:fresh` and a rollback of the S03 migrations on a scratch database, then `composer pint-test`. Ensure all checks pass, ask the user if questions arise.
  - _Requirements: 22.15_

- [ ] 4. Events, actions and queued job
  - [ ] 4.1 Create the six events in `App\Events\Imports`
    - `ImportValidated`, `ImportConfirmed`, `ImportCancelled`, `ImportCompleted`, `ImportFailed`, `ImportFileDownloaded` with `importId`, `departmentId`, `actorUserId`, `occurredAt`
    - `ImportCompleted` also carries `status`, `insertedRows`, `correctedRows`, `unchangedRows`, `invalidRows`, `minDate`, `maxDate`
    - No listeners registered, no activity-log or rule-evaluation code in S03
    - _Requirements: 17.1, 17.2, 17.3, 17.6, 17.7, 17.8_

  - [ ] 4.2 Implement the `CreateImportFromUpload` action
    - Run the reader validation first, then store the file on the private `local` disk under `imports/{import_id}/` with a random name, save a sanitized `original_filename` and the SHA-256 `file_hash`
    - Create the Import as `uploaded`, validate and classify all rows, save `import_errors` and counters, set `validated`, dispatch `ImportValidated` after commit
    - Set `company_id`, `department_id` and `uploaded_by` from the authenticated user and Company; on a post-creation failure set `failed` with a sanitized message and dispatch `ImportFailed`
    - Write no `sales_records` and change no Expected_Branches
    - _Requirements: 2.6, 2.7, 2.8, 4.10, 7.5, 8.1, 8.14, 11.5, 12.1, 12.2, 17.4, 17.5_

  - [ ] 4.3 Implement the `ConfirmImport`, `CancelImport` and `RetryImport` actions
    - `ConfirmImport`: row lock, require `validated` and at least one valid row, set `processing` and `confirmed_at`, dispatch `ProcessImportJob` and `ImportConfirmed` after commit; return the current Import without a second dispatch if the status is not `validated`
    - `CancelImport`: allow only `uploaded` and `validated`, otherwise reject with a message naming the current status; dispatch `ImportCancelled`
    - `RetryImport`: allow only `failed` with a stored file present, lock, set `processing`, clear `error_message`, dispatch a new job
    - Enforce the status transition map; keep the file in every status
    - _Requirements: 9.1, 9.2, 11.1, 11.2, 11.3, 11.4, 11.6, 17.5_

  - [ ] 4.4 Implement `ProcessImportJob`
    - `ShouldQueue` and `ShouldBeUnique` keyed by Import id, `tries` 3, backoff 10, 30, 60 and a `failed()` handler that sets `failed` with a sanitized message (no paths, stack traces or cell values) and dispatches `ImportFailed`
    - Exit without writes unless status is `processing`; acquire a per-department `Cache::lock` and release the job back to the queue if it is held
    - Re-read the stored file, re-validate and re-classify; fail with "No valid rows to import" on zero valid rows; fail with a sanitized message and no writes if the file is missing
    - One transaction: insert `new` rows, supersede and insert `corrected` rows, skip `unchanged`, never delete, write in chunks of at most 1,000, replace `import_errors`, initialize Expected_Branches when empty, update counters and set `completed` or `completed_with_errors` with `completed_at`
    - Map unique-index violations to "Data changed during import. Please upload again"; dispatch `ImportCompleted` after commit
    - _Requirements: 6.5, 7.7, 7.8, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 9.10, 9.11, 9.12, 9.13, 9.14, 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 17.3, 17.5, 20.4_

  - [ ] 4.5 Implement the `UpdateExpectedBranches` action
    - Validate branch format, remove duplicates, upper-case and sort; reject an empty list when codes existed and a list over 50 codes; reject while an Import is `processing` for the department
    - Leave existing Sales_Records unchanged when a code is removed
    - _Requirements: 6.6, 6.7, 6.8, 6.9, 6.10_

- [ ] 5. Authorization
  - [ ] 5.1 Implement `ImportPolicy` and `SalesRecordPolicy` and register them
    - `ImportPolicy`: `viewAny`, `view`, `create`, `confirm`, `cancel`, `retry`, `download` for an active Owner, and for an active Manager within the own department; Staff and inactive users denied
    - `SalesRecordPolicy`: `viewAny` and `view` only for Owner and own-department Manager, no create, update or delete ability
    - `ImportError` rows are reachable only through the parent Import authorization
    - _Requirements: 2.1, 2.2, 2.4, 2.5, 2.10, 2.11_

- [ ] 6. HTTP layer
  - [ ] 6.1 Create the Form Requests `UploadImportRequest` and `UpdateExpectedBranchesRequest`
    - Validate before any write; ignore `company_id`, `department_id` and `uploaded_by` from the body; map reader rejection codes to a 422 response with the expected header and template link
    - _Requirements: 2.8, 4.10, 6.6, 6.7, 6.8, 19.5, 20.1_

  - [ ] 6.2 Create `ImportController`
    - Actions: index (status filter, 25 per page, freshness, polling data), create (upload page), store, show (preview or detail data, error pagination 50 per page, coverage weeks for completed imports), confirm, cancel, retry
    - Resolve Imports through `Import::visibleTo()` (404 out of scope), call the policy explicitly in every action, pass only safe props (never `stored_path`), use S01 flash toasts
    - No destroy route or delete action
    - _Requirements: 2.2, 2.3, 2.4, 2.9, 8.2, 8.13, 11.7, 12.7, 13.1, 13.2, 13.3, 13.6, 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.10, 15.3, 15.4, 15.5, 20.2, 20.5, 20.6_

  - [ ] 6.3 Create `ImportFileController` and `ImportTemplateController`
    - File download: policy check, 404 when the file is missing with a "source file unavailable" message, stream `text/csv` as an attachment with the sanitized name, dispatch `ImportFileDownloaded`
    - Template download: `sales-import-template.csv` containing only the header row from `config/imports.php`
    - _Requirements: 3.1, 12.3, 12.4, 12.5, 12.6, 12.7, 2.2_

  - [ ] 6.4 Create `ImportTemplateSettingsController` and register the Settings tab
    - Register "Import template" in the S02 Settings tab extension point for Owner and Manager; Staff gets 403
    - Provide the column table, limits, Trading_Days and timezone, Expected_Branches editor data, last-week coverage per branch from `ReportingCoverage`, and the notice of branches present in Active_Records but missing from Expected_Branches
    - Save through `UpdateExpectedBranches` with a success toast
    - _Requirements: 3.2, 3.3, 3.4, 6.12, 19.1, 19.2, 19.3, 19.4, 19.6_

  - [ ] 6.5 Register the routes
    - Imports index, create, store, show, confirm, cancel, retry, file download, template download, Settings tab and expected-branches update
    - `auth` and active-user middleware, CSRF, and `throttle` of 10 per minute per user on upload with a 429 response showing seconds remaining
    - _Requirements: 2.5, 4.11, 4.12, 20.2_

- [ ] 7. Frontend pages
  - [ ] 7.1 Create shared import UI components and types
    - Import status badge (icon plus text label), preview panel (counts, date range, branches, notices, coverage weeks with gaps), error table (row, column, code, message, escaped text only), skeleton, `EmptyState` and `ErrorState` wrappers, polling helper with an `aria-live` region
    - _Requirements: 5.17, 8.3, 8.4, 8.5, 8.6, 8.7, 8.9, 8.11, 13.8, 14.9, 20.9_

  - [ ] 7.2 Build the Data Imports history page (`resources/js/pages/Imports/Index.vue`)
    - Freshness block, status filter, table with status, file name, date range, total and invalid rows, uploader and upload date in company timezone, empty states, "Upload sales data" action, 3-second polling while any Import is `processing`, cards below 768 px
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.9, 13.10, 15.3, 15.4, 20.10_

  - [ ] 7.3 Build the Upload with preview page (`resources/js/pages/Imports/Upload.vue`)
    - `FileDropzone` upload with file-level rejection message, expected header and template link, 429 handling, preview display after validation, Confirm (disabled with no valid rows) and Cancel actions, "File already imported" and "preview refreshed" notices
    - _Requirements: 4.10, 4.12, 8.2, 8.8, 8.10, 8.13, 9.1, 11.2, 20.9, 20.10_

  - [ ] 7.4 Build the Import detail page (`resources/js/pages/Imports/Show.vue`)
    - Metadata, counters, paginated errors, preview with Confirm and Cancel for `validated`, error message and Retry for `failed`, coverage per week for completed imports, "invalid rows were not imported" notice, "Download source file" action, 3-second status polling while `processing`
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7, 14.8, 14.9, 14.10, 20.9, 20.10_

  - [ ] 7.5 Build the Settings "Import template" tab (`resources/js/pages/Settings/Tabs/ImportTemplate.vue`)
    - Template download, column specification table, file limits, read-only Trading_Days and timezone with Company tab link for the Owner, Expected_Branches editor with visible labels, field errors with submitted values, branch coverage and missing-branch notices; keyboard operable
    - _Requirements: 3.2, 3.3, 3.4, 6.12, 19.3, 19.4, 19.5, 19.6, 19.7, 20.10_

- [ ] 8. Documentation updates
  - [ ] 8.1 Update `docs/data-model.md` and `docs/architecture.md`
    - `docs/data-model.md`: add `departments.settings` (JSONB, default `{}`) with the `expected_branches` contract (sorted unique upper-case codes), and document the `imports` counter semantics and invariants
    - `docs/architecture.md`: document the `ReportingCoverage` method contract (`weekCoverage`, `missingBranchDays`, `latestCompleteWeek`, `asOfDate`, `freshness`) and the six S03 events with their payloads, noting that S04 listens to `ImportCompleted` and S08 listens to all six
    - _Requirements: 1.10, 17.9_

- [ ] 9. Final checkpoint - Ensure all static checks pass
  - Run `composer pint-test` and `npm run verify`, and confirm the migration rollback still works. Manual fixture verification F1 to F12, the authorization matrix and the responsive and keyboard checks follow `.kiro/steering/verification.md` and Requirement 22. Ensure all checks pass, ask the user if questions arise.
  - _Requirements: 22.13, 22.14, 22.15_

## Notes

- No automated unit, property or end-to-end test tasks are included (Requirements 21.6; `.kiro/steering/verification.md`). The design has no Correctness Properties section.
- Fixture CSV files F1 to F12 are authored in S09. S03 verification consumes them.
- S03 writes no `activity_logs` rows and does not evaluate rules. S04 and S08 attach through the events.
- Each task lists the granular requirement clauses it covers.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3", "1.4"] },
    { "id": 2, "tasks": ["2.1", "2.2", "2.3", "4.1", "5.1"] },
    { "id": 3, "tasks": ["2.4", "2.5"] },
    { "id": 4, "tasks": ["2.6"] },
    { "id": 5, "tasks": ["3"] },
    { "id": 6, "tasks": ["4.2", "4.3", "4.4", "4.5", "8.1"] },
    { "id": 7, "tasks": ["6.1", "6.2", "6.3", "6.4"] },
    { "id": 8, "tasks": ["6.5"] },
    { "id": 9, "tasks": ["7.1"] },
    { "id": 10, "tasks": ["7.2", "7.3", "7.4", "7.5"] },
    { "id": 11, "tasks": ["9"] }
  ]
}
```
