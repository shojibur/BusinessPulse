# Requirements Document

## Introduction

S03 Sales Data Import and Validation lets an Owner or a Manager load daily branch sales into BusinessPulse from a CSV file, check the file before anything is saved, confirm the import, and trace every import afterwards. It owns the `imports`, `import_errors` and `sales_records` tables, the CSV format, the validation and preview flow, the queued `ProcessImportJob`, the import history and detail screens, the Settings "Import template" tab, the expected-branches setting, data freshness information, and the reusable `ReportingCoverage` service that decides whether a reporting week is complete.

S03 implements decisions D1 to D8 and D26 of `docs/business-workflows.md`. The grain is one row per branch per calendar day. The record identity is `(department_id, branch_code, sales_date)`. A re-imported identity with different values supersedes the active row and never deletes history. Incomplete data is never treated as zero, and repeating an import never inflates revenue.

S03 does not evaluate rules, create issues, build metrics, write activity logs, or send notifications. After an import completes, S03 dispatches the `ImportCompleted` event. S04 consumes that event to start `EvaluateRulesJob`. S08 owns the `activity_logs` table and the `ActivityLogger` service, so S03 writes no activity log rows and calls no logger. S03 dispatches events, and S08 listeners record the activity.

S03 follows `docs/data-model.md`, `docs/architecture.md` (Imports module), `docs/ui-ux-design-system.md`, and the steering files in `.kiro/steering`. Stage 2 features (Excel files, automatic sync, column-mapping screens) are excluded.

### What S03 introduces

| Kind | Items |
|---|---|
| Tables | `imports`, `import_errors`, `sales_records` as defined in `docs/data-model.md`. Additive column `departments.settings` (JSONB). |
| Enums | `App\Enums\ImportStatus`, `App\Enums\ImportRowResult` (`new`, `corrected`, `unchanged`, `invalid`), `App\Enums\CoverageStatus` (`complete`, `partial`, `absent`) |
| Models | `Import`, `ImportError`, `SalesRecord` (the models `Import` and `SalesRecord` use `ScopedToDepartment`) |
| Policies | `ImportPolicy`, `SalesRecordPolicy` |
| Services | `App\Services\Imports\{CsvImportReader, ImportRowValidator, ImportClassifier, ImportPreviewBuilder}`, `App\Services\Imports\ReportingCoverage`, `App\Services\Imports\ExpectedBranches` |
| Actions | `App\Actions\Imports\{CreateImportFromUpload, ConfirmImport, CancelImport, RetryImport, UpdateExpectedBranches}` |
| Jobs | `App\Jobs\ProcessImportJob` |
| Events | `App\Events\Imports\{ImportValidated, ImportConfirmed, ImportCancelled, ImportCompleted, ImportFailed, ImportFileDownloaded}` |
| Form Requests | `UploadImportRequest`, `UpdateExpectedBranchesRequest` |
| Controllers | `ImportController`, `ImportFileController`, `ImportTemplateController` (template download), `ImportTemplateSettingsController` |
| Pages | Data Imports history, Upload with preview, Import detail, Settings "Import template" tab |
| Changes to S02 | New Settings tab registered in the S02 extension point. Additive migration for `departments.settings`. |
| Changes to docs | `docs/data-model.md` (departments row, import counters), `docs/architecture.md` (events and `ReportingCoverage` contract) |

### CSV format (authoritative)

| Column | Required | Type and rule | Example |
|---|---|---|---|
| `sales_date` | yes | Calendar date `YYYY-MM-DD`, not after the business today, not before 2000-01-01 | `2025-03-03` |
| `branch_code` | yes | 1 to 20 characters from letters, digits, hyphen, underscore. Trimmed. Stored in upper case. | `KL` |
| `revenue` | yes | Decimal `^[0-9]{1,12}(\.[0-9]{1,2})?$`, zero allowed, no sign, no thousands separator, no currency symbol | `4520.50` |
| `target_revenue` | yes | Same rule as `revenue` | `5000.00` |
| `order_count` | no | Integer `^[0-9]{1,9}$`. An empty cell means unknown (stored as null). | `37` |

File rules: comma delimiter, RFC 4180 quoting, UTF-8 with or without byte-order mark, any line ending, one header row, column order free, header names case-insensitive and trimmed, completely blank lines ignored. Maximum 5 MB (5,242,880 bytes) and 10,000 data rows.

### Dependencies

- S01: `AppShell`, `PageHeader`, `FileDropzone`, `EmptyState`, `ErrorState`, `StatusBadge`, `Alert`, toast helper, error pages (403, 404, 419), `BusinessClock`, database queue, private `local` disk.
- S02: `Company` (`timezone`, `trading_days`), `Department`, `User`, `Role`, `ScopedToDepartment` and `visibleTo()`, policy conventions, Settings tab layout, `CurrentCompany`.
- Consumed by S04 (`ImportCompleted`, `ReportingCoverage`, `sales_records`), S05 (freshness, coverage), S08 (`ReportingCoverage`, import events for activity logging), S09 (demo CSV files authored against the format above).

### Assumptions

- A1: Stage 1 has one company and one Sales department. An Owner (who has no department) uploads into the Company's single department. A Manager uploads into the own department.
- A2: The expected-branches setting is stored in `departments.settings` as JSONB under the key `expected_branches` (an array of upper-case branch codes). This is the simplest option because it needs no new table and Stage 1 has one department. It is added to the data model contract in `docs/data-model.md`.
- A3: Branch codes in a later import that are not in a non-empty expected-branches list are rejected row by row with the code `unknown_branch`. The user adds the branch in Settings and re-uploads. This prevents a typo from creating a branch that would make every week partial.
- A4: When the expected-branches list is empty, the first confirmed import sets the list to the distinct branch codes of its valid rows (decision D3).
- A5: Coverage is always computed against the current expected branches and current trading days. Evaluation evidence snapshotted by S04 keeps historical results unchanged.
- A6: Rows dated on a non-trading day are valid, stored, and ignored for completeness. The preview reports their count as a notice.
- A7: The last confirmed import wins. A confirmed file with older figures supersedes newer figures for the same identity. The preview shows the corrected count so the user can see this before confirming.
- A8: File-level failures (extension, MIME, size, encoding, header, row limit) create no `imports` row and store no file. The user sees the message with the expected template.
- A9: Validation of a file runs synchronously during upload because the limits (5 MB, 10,000 rows) keep it within a normal request. Processing after confirmation is queued.
- A10: Preview counts are persisted in the counter columns of `imports`. The row-level pending identities and coverage list are rebuilt on demand by re-reading the stored file, which is immutable.
- A11: An import left in `validated` status stays until the user confirms or cancels it. Automatic expiry is not part of Stage 1.
- A12: A confirmed import with zero changed rows still completes and dispatches `ImportCompleted`, because S04 evaluation is idempotent.
- A13: Import limits (5 MB, 10,000 rows, 10 uploads per minute per user) are demonstration defaults and are flagged for client confirmation together with the real sales file layout.
- A14: Public holidays are not modelled (D3). A holiday that falls on a trading day appears as a coverage gap.
- A15: Imports produce no in-app notification in Stage 1.

## Glossary

- **Application**: The BusinessPulse Laravel 13 and Vue 3 Inertia web application.
- **Owner**: A User with the `owner` Role (S02).
- **Manager**: A User with the `manager` Role (S02).
- **Staff**: A User with the `staff` Role (S02).
- **Import**: One uploaded CSV file and its lifecycle, stored in `imports`.
- **Import_Status**: One of `uploaded`, `validated`, `processing`, `completed`, `completed_with_errors`, `failed`, `cancelled`.
- **Sales_Record**: One row in `sales_records` holding revenue and target revenue for one branch on one date.
- **Record_Identity**: The combination `(department_id, branch_code, sales_date)`.
- **Active_Record**: A Sales_Record with `is_active` true. At most one Active_Record exists for each Record_Identity.
- **Import_Reader**: The component `CsvImportReader` that performs file-level validation and parses rows.
- **Row_Validator**: The component `ImportRowValidator` that validates each parsed row.
- **Import_Classifier**: The component `ImportClassifier` that labels each valid row `new`, `corrected` or `unchanged` against Active_Records.
- **Preview_Builder**: The component `ImportPreviewBuilder` that assembles the preview data.
- **Import_Service**: The collective backend of Import_Reader, Row_Validator, Import_Classifier, Preview_Builder and the Actions in `App\Actions\Imports`.
- **Process_Job**: The queued job `ProcessImportJob`.
- **Reporting_Coverage**: The service `ReportingCoverage` that decides week completeness, data as-of date and freshness.
- **Expected_Branches**: The list of branch codes stored in `departments.settings.expected_branches`.
- **Trading_Days**: The ISO weekday integers in `companies.trading_days`.
- **Reporting_Week**: An ISO week from Monday to Sunday.
- **Complete_Week**: A Reporting_Week in which every Expected_Branch has an Active_Record for every Trading_Day of that week.
- **Partial_Week**: A Reporting_Week that has at least one Active_Record and is not a Complete_Week.
- **Absent_Week**: A Reporting_Week that has no Active_Record, or whose department has no Expected_Branches.
- **Coverage_Gap**: One Expected_Branch and Trading_Day combination in a Reporting_Week that has no Active_Record.
- **Pending_Identities**: The Record_Identity values of the valid rows of an Import that is not yet processed.
- **Freshness**: The latest `sales_date` among Active_Records and the number of days between that date and `BusinessClock::today()`.
- **Import_Policy**: The policy `ImportPolicy` for the `Import` model.
- **Import_Pages**: The Data Imports history, Upload with preview, and Import detail pages.
- **Template_Tab**: The Settings tab "Import template".
- **Event_Bus**: The Laravel event dispatcher used for the events listed in "What S03 introduces".

## Requirements

### Requirement 1: Import data model

**User Story:** As a developer, I want the import tables and enums defined once, so that later specs can read sales data with consistent identities and history.

#### Acceptance Criteria

1. THE Application SHALL provide migrations that create `imports`, `import_errors` and `sales_records` with the columns, foreign keys and indexes defined in `docs/data-model.md`.
2. THE Application SHALL define a partial unique index on `sales_records` over `(department_id, branch_code, sales_date)` WHERE `is_active` is true.
3. THE Application SHALL define check constraints that restrict `imports.status` to the seven values of Import_Status, and that restrict `sales_records.revenue` and `sales_records.target_revenue` to values of zero or more.
4. THE Application SHALL store `revenue` and `target_revenue` as `numeric(14,2)` and `order_count` as a nullable integer.
5. THE Application SHALL provide an additive migration that adds `departments.settings` as a JSONB column with default `{}`.
6. THE Application SHALL define the `Import`, `ImportError` and `SalesRecord` models with the relationships `Import` belongs to Department and uploader User, `Import` has many ImportError and SalesRecord, and `SalesRecord` belongs to Department, source Import and superseding Import.
7. THE Application SHALL cast `ImportStatus` on `imports.status`, and SHALL cast `raw_row` on `import_errors` and `settings` on `departments` to arrays.
8. THE Application SHALL create the migrations so that they run after the S02 migrations without modifying them, except for the additive `departments.settings` column.
9. THE Application SHALL define `Import` and `SalesRecord` with the `ScopedToDepartment` trait from S02.
10. THE Application SHALL document the `departments.settings.expected_branches` contract, the import counter semantics, and the `ReportingCoverage` contract in `docs/data-model.md` and `docs/architecture.md`.

### Requirement 2: Authorization and department scoping

**User Story:** As an Owner, I want only the right people to import and view sales data, so that data stays inside the department that owns it.

#### Acceptance Criteria

1. THE Import_Policy SHALL permit `viewAny`, `view`, `create`, `confirm`, `cancel`, `retry` and `download` for an active Owner, and for an active Manager when the Import belongs to the Manager's own department.
2. IF a Staff user requests any Import_Pages route, the Template_Tab, the template download, or an import file download, THEN THE Application SHALL respond with HTTP 403.
3. IF a Manager requests an Import that belongs to another department, THEN THE Application SHALL respond with HTTP 404 by resolving the Import through `Import::visibleTo()`.
4. IF an in-scope user lacks permission for an action on an in-scope Import, THEN THE Application SHALL respond with HTTP 403.
5. IF a user is inactive or unauthenticated, THEN THE Application SHALL deny every S03 route according to the S02 rules.
6. WHEN an Owner uploads a file, THE Import_Service SHALL assign the Import to the Company's single department.
7. WHEN a Manager uploads a file, THE Import_Service SHALL assign the Import to the Manager's own department.
8. THE Import_Service SHALL set `company_id`, `department_id` and `uploaded_by` from the authenticated user and Company, and SHALL ignore any such value in the request body.
9. THE Application SHALL authorize every S03 controller action with an explicit policy call, and SHALL NOT treat navigation visibility as authorization.
10. THE `SalesRecordPolicy` SHALL permit `viewAny` and `view` only for an active Owner and for an active Manager within the own department, and SHALL define no create, update or delete ability for any user.
11. THE Application SHALL expose `ImportError` rows only through the authorization of the parent Import.

### Requirement 3: CSV template and format specification

**User Story:** As a Manager, I want to download a template and see the exact column rules, so that my file passes validation the first time.

#### Acceptance Criteria

1. WHEN an Owner or Manager requests the template download, THE Application SHALL return a CSV file named `sales-import-template.csv` that contains only the header row `sales_date,branch_code,revenue,target_revenue,order_count`.
2. THE Template_Tab SHALL display a table of the five columns with required flag, data type, rule and example value, matching the CSV format section of this document.
3. THE Template_Tab SHALL display the file limits: 5 MB, 10,000 data rows, UTF-8, comma delimiter.
4. THE Template_Tab SHALL display the current Trading_Days and Company timezone as read-only values with a link to the Company tab for the Owner.
5. THE Application SHALL derive the displayed limits from one configuration source (`config/imports.php`) that the Import_Reader also reads.
6. THE Application SHALL define the template header and the column specification once in code and SHALL use that single definition for the template download, the Template_Tab and header validation.

### Requirement 4: Upload and file-level validation

**User Story:** As a Manager, I want a clear rejection message when the file itself is wrong, so that I can fix the file before any data is read.

#### Acceptance Criteria

1. WHEN an Owner or Manager submits a file on the upload page, THE Import_Reader SHALL validate the file before reading any row.
2. IF the file name does not end with `.csv` in any letter case, THEN THE Import_Reader SHALL reject the file with the code `invalid_extension`.
3. IF the server-detected MIME type is not one of `text/csv`, `text/plain`, `application/csv` or `application/vnd.ms-excel`, THEN THE Import_Reader SHALL reject the file with the code `invalid_mime`.
4. IF the file size exceeds 5,242,880 bytes, THEN THE Import_Reader SHALL reject the file with the code `file_too_large`.
5. IF the file has zero bytes or has no header row, THEN THE Import_Reader SHALL reject the file with the code `empty_file`.
6. IF the file content is not valid UTF-8 after removing an optional byte-order mark, THEN THE Import_Reader SHALL reject the file with the code `invalid_encoding`.
7. IF the header row lacks any of `sales_date`, `branch_code`, `revenue` or `target_revenue`, THEN THE Import_Reader SHALL reject the file with the code `missing_columns` and list the missing column names.
8. IF the header row contains a column name that is not one of the five template columns, or repeats a column name, THEN THE Import_Reader SHALL reject the file with the code `unexpected_columns` or `duplicate_header` and list the offending names.
9. IF the file contains more than 10,000 data rows, THEN THE Import_Reader SHALL reject the file with the code `too_many_rows`.
10. WHEN the Import_Reader rejects a file, THE Application SHALL create no `imports` row, store no file, and display the message with the expected header and a link to the template.
11. THE Application SHALL limit upload requests to 10 per minute for each user.
12. IF the upload limit is exceeded, THEN THE Application SHALL respond with HTTP 429 and display the seconds remaining.
13. THE Import_Reader SHALL parse the file in a streaming manner so that memory use does not grow with row count beyond the 10,000-row limit.

### Requirement 5: Row-level validation

**User Story:** As a Manager, I want each bad row reported with its row number, column and reason, so that valid rows are not blocked by a few mistakes.

#### Acceptance Criteria

1. THE Row_Validator SHALL number data rows starting at 2, so that the row number matches the line a spreadsheet user sees when the header is row 1.
2. IF a required cell is empty after trimming, THEN THE Row_Validator SHALL mark the row invalid with the code `missing_value` and the column name.
3. IF a `sales_date` value is not a real calendar date in the format `YYYY-MM-DD`, THEN THE Row_Validator SHALL mark the row invalid with the code `invalid_date`.
4. IF a `sales_date` value is later than `BusinessClock::today()`, THEN THE Row_Validator SHALL mark the row invalid with the code `future_date`.
5. IF a `sales_date` value is earlier than 2000-01-01, THEN THE Row_Validator SHALL mark the row invalid with the code `date_out_of_range`.
6. IF a `branch_code` value does not match the rule in the CSV format section, THEN THE Row_Validator SHALL mark the row invalid with the code `invalid_branch_format`.
7. IF a `revenue` or `target_revenue` value starts with a minus sign, THEN THE Row_Validator SHALL mark the row invalid with the code `negative_value`.
8. IF a `revenue` or `target_revenue` value has more than two decimal places, THEN THE Row_Validator SHALL mark the row invalid with the code `too_many_decimals`.
9. IF a `revenue` or `target_revenue` value has more than 12 integer digits, THEN THE Row_Validator SHALL mark the row invalid with the code `value_out_of_range`.
10. IF a `revenue` or `target_revenue` value is not a decimal number under the CSV format rule, THEN THE Row_Validator SHALL mark the row invalid with the code `invalid_number`.
11. IF an `order_count` value is not empty and is not a non-negative integer of at most nine digits, THEN THE Row_Validator SHALL mark the row invalid with the code `invalid_integer`.
12. IF a row has a different number of cells than the header, THEN THE Row_Validator SHALL mark the row invalid with the code `wrong_column_count`.
13. IF a row has several problems, THEN THE Row_Validator SHALL record one `import_errors` row for each problem, in column order.
14. THE Row_Validator SHALL treat a `revenue` value of `0` or `0.00` as a valid present value and SHALL NOT treat it as missing data.
15. THE Row_Validator SHALL parse and hold money values as decimal strings and SHALL NOT convert them to floating-point numbers.
16. THE Import_Service SHALL store each error with `row_number`, `column`, `code`, a plain-language `message` and the `raw_row` values as a JSON object.
17. THE Application SHALL render `raw_row` values and the original file name as escaped text only.

### Requirement 6: Expected branches and branch handling

**User Story:** As a Manager, I want the system to know which branches must report, so that missing branch data is detected instead of ignored.

#### Acceptance Criteria

1. THE Application SHALL store Expected_Branches in `departments.settings.expected_branches` as a sorted array of unique upper-case branch codes.
2. WHEN the Row_Validator reads a `branch_code`, THE Import_Service SHALL trim the value and convert it to upper case before comparison and storage.
3. WHILE Expected_Branches is empty, THE Import_Service SHALL accept every branch code that passes format validation.
4. WHILE Expected_Branches is not empty, IF a row has a branch code outside Expected_Branches, THEN THE Row_Validator SHALL mark the row invalid with the code `unknown_branch`.
5. WHEN the Process_Job completes an Import and Expected_Branches is empty, THE Process_Job SHALL set Expected_Branches to the distinct branch codes of the valid rows of that Import within the same database transaction.
6. WHEN an Owner or Manager submits the expected-branches form on the Template_Tab, THE Application SHALL validate the codes with the branch format rule, remove duplicates, convert to upper case and save the sorted list.
7. IF the submitted expected-branches list is empty after previously containing codes, THEN THE Application SHALL reject the change with a field error.
8. IF the submitted expected-branches list contains more than 50 codes, THEN THE Application SHALL reject the change with a field error.
9. WHEN an Owner or Manager removes a branch code that has Sales_Records, THE Application SHALL keep those Sales_Records unchanged.
10. WHILE an Import in `processing` status exists for the department, THE Application SHALL reject changes to Expected_Branches with a message that an import is in progress.
11. WHEN the upload preview is shown and Expected_Branches is empty, THE Application SHALL display the branch codes that the first confirmed import will set as expected.
12. THE Template_Tab SHALL display the current Expected_Branches and the last Reporting_Week coverage per branch only as supplied by Reporting_Coverage.

### Requirement 7: Row classification, duplicates and same-file detection

**User Story:** As a Manager, I want to know which rows are new, corrected or unchanged, so that I understand what the import will change.

#### Acceptance Criteria

1. THE Import_Classifier SHALL label each valid row `new` WHEN no Active_Record exists for the Record_Identity.
2. THE Import_Classifier SHALL label each valid row `unchanged` WHEN an Active_Record exists for the Record_Identity and `revenue`, `target_revenue` and `order_count` are equal, where a null `order_count` equals only a null `order_count`.
3. THE Import_Classifier SHALL label each valid row `corrected` WHEN an Active_Record exists for the Record_Identity and at least one of `revenue`, `target_revenue` or `order_count` differs.
4. IF two or more rows in one file share the same Record_Identity, THEN THE Row_Validator SHALL mark every one of those rows invalid with the code `duplicate_in_file`, and the message SHALL name the other row numbers.
5. THE Import_Service SHALL compute a SHA-256 hash of the stored file content and save it in `imports.file_hash`.
6. WHEN an Import is validated and another Import of the same department with the same `file_hash` has the status `completed` or `completed_with_errors`, THE Preview_Builder SHALL display the notice "File already imported" with links to those Imports.
7. WHEN a file with a previously imported hash is confirmed and processed, THE Process_Job SHALL classify every unchanged row as `unchanged` and SHALL insert no Sales_Record for it.
8. THE Import_Service SHALL satisfy the counter invariants `total_rows = valid_rows + invalid_rows` and `valid_rows = inserted_rows + corrected_rows + unchanged_rows` for every Import in the status `validated`, `completed` or `completed_with_errors`.
9. THE Import_Service SHALL exclude blank lines and the header row from `total_rows`.

### Requirement 8: Validation preview before confirmation

**User Story:** As a Manager, I want a full preview before anything is saved, so that I can confirm or cancel with confidence.

#### Acceptance Criteria

1. WHEN file-level validation passes, THE Import_Service SHALL store the file privately, create an Import with status `uploaded`, validate and classify all rows, save the `import_errors` rows and counters, and set the status to `validated`.
2. WHEN the Import reaches `validated`, THE Application SHALL display the preview page for that Import.
3. THE preview page SHALL display the file name, file size, uploader, `total_rows`, and the counts of new, corrected, unchanged and invalid rows.
4. THE preview page SHALL display the minimum and maximum `sales_date` among valid rows and the distinct branch codes found in valid rows.
5. THE preview page SHALL display the row-level errors in a table with row number, column, code and message, 50 rows per page, ordered by row number.
6. THE preview page SHALL display the number of valid rows dated on a non-trading day as a notice.
7. THE preview page SHALL list every Reporting_Week that overlaps the valid-row date range, with its CoverageStatus computed as if the Pending_Identities were already Active_Records, and for each Partial_Week the Coverage_Gaps, up to 50 gaps per week plus a count of any further gaps.
8. WHEN the file contains zero valid rows, THE preview page SHALL display a message that nothing can be imported and SHALL disable the confirm action.
9. THE preview page SHALL display an informational notice that incomplete weeks stay incomplete and are not treated as zero.
10. THE preview page SHALL display the Import_Status and a Cancel action for an Import in `validated` status.
11. WHILE the preview data is being loaded, THE Application SHALL display skeleton placeholders that match the layout.
12. THE Preview_Builder SHALL rebuild the Pending_Identities and coverage list from the stored file WHEN a user opens a `validated` Import, and SHALL produce the same counts as those persisted at validation time unless Active_Records changed in the meantime.
13. IF Active_Records changed between validation and viewing so that counts differ, THEN THE preview page SHALL display the refreshed counts and a notice that the preview was refreshed.
14. THE Application SHALL save no Sales_Record and change no Expected_Branches while an Import is in `uploaded` or `validated` status.

### Requirement 9: Confirmation and queued processing

**User Story:** As a Manager, I want confirming an import to be safe against double clicks and failures, so that data is never saved twice or half saved.

#### Acceptance Criteria

1. WHEN an authorized user confirms an Import in `validated` status that has at least one valid row, THE `ConfirmImport` Action SHALL lock the Import row, set the status to `processing`, set `confirmed_at`, dispatch the Process_Job after the transaction commits, and dispatch `ImportConfirmed`.
2. IF the Import is not in `validated` status when a confirm request arrives, THEN THE `ConfirmImport` Action SHALL return the current Import without dispatching a second Process_Job.
3. THE Process_Job SHALL implement `ShouldBeUnique` keyed by the Import id, SHALL declare `tries` of 3 with backoff of 10, 30 and 60 seconds, and SHALL define a `failed()` handler.
4. WHEN the Process_Job starts and the Import status is not `processing`, THE Process_Job SHALL exit without writing any data.
5. WHEN the Process_Job runs, THE Process_Job SHALL re-read the stored file, re-validate and re-classify every row against the Active_Records visible at that moment, and use the results for the writes.
6. WHEN the Process_Job writes data, THE Process_Job SHALL perform all Sales_Record changes, `import_errors` replacement, Expected_Branches initialization, counter updates and status change in one database transaction.
7. WHILE a Process_Job for a department is running, THE Application SHALL prevent a second Process_Job for the same department from writing, by acquiring a per-department lock and releasing the second job back to the queue.
8. WHEN the Process_Job succeeds with zero invalid rows, THE Process_Job SHALL set the status `completed` and set `completed_at`.
9. WHEN the Process_Job succeeds with one or more invalid rows, THE Process_Job SHALL set the status `completed_with_errors` and set `completed_at`.
10. WHEN the Process_Job reaches `completed` or `completed_with_errors`, THE Process_Job SHALL dispatch `ImportCompleted` after the transaction commits.
11. IF the Process_Job throws an exception during the transaction, THEN THE Process_Job SHALL roll back every Sales_Record change of that run.
12. IF the Process_Job exhausts its attempts or fails, THEN THE `failed()` handler SHALL set the status `failed`, save a sanitized `error_message` without file paths, stack traces or cell values, and dispatch `ImportFailed`.
13. THE Application SHALL write valid rows in chunks of at most 1,000 rows per statement inside the transaction.
14. WHEN the Process_Job re-validates and finds that the file now yields zero valid rows, THE Process_Job SHALL set the status `failed` with the message "No valid rows to import".

### Requirement 10: Corrections, supersede behavior and no revenue inflation

**User Story:** As an Owner, I want corrected figures to replace old ones without losing history and without double counting, so that totals are always trustworthy.

#### Acceptance Criteria

1. WHEN the Process_Job processes a `new` row, THE Process_Job SHALL insert a Sales_Record with `is_active` true and `import_id` set to the current Import.
2. WHEN the Process_Job processes a `corrected` row, THE Process_Job SHALL set the existing Active_Record to `is_active` false, set its `superseded_by_import_id` to the current Import and `superseded_at` to the current time, and insert a new Active_Record with the new values.
3. WHEN the Process_Job processes an `unchanged` row, THE Process_Job SHALL leave the existing Active_Record unmodified and insert nothing.
4. THE Process_Job SHALL NOT delete any Sales_Record.
5. THE Application SHALL keep at most one Active_Record for each Record_Identity at all times, enforced by the partial unique index.
6. WHEN the same Import file content is confirmed again in a new Import after completion, THE sum of `revenue` over Active_Records SHALL remain equal to its value before the second import.
7. IF a unique-index violation occurs during processing, THEN THE Process_Job SHALL roll back the transaction and fail the Import with the sanitized message "Data changed during import. Please upload again".
8. THE Application SHALL keep rows of a superseded Sales_Record readable through its `import_id` so that the source Import shows which rows it originally contributed.

### Requirement 11: Import lifecycle, cancel and retry

**User Story:** As a Manager, I want to cancel an import I do not want and retry a failed one, so that I stay in control.

#### Acceptance Criteria

1. THE Application SHALL permit only these status transitions: `uploaded` to `validated`, `uploaded` to `failed`, `validated` to `processing`, `validated` to `cancelled`, `uploaded` to `cancelled`, `processing` to `completed`, `processing` to `completed_with_errors`, `processing` to `failed`, and `failed` to `processing`.
2. WHEN an authorized user cancels an Import in `uploaded` or `validated` status, THE `CancelImport` Action SHALL set the status `cancelled` and dispatch `ImportCancelled`.
3. IF a cancel request arrives for an Import in `processing`, `completed`, `completed_with_errors`, `failed` or `cancelled` status, THEN THE `CancelImport` Action SHALL reject the request with a message that names the current status.
4. WHEN an authorized user retries an Import in `failed` status that has a stored file, THE `RetryImport` Action SHALL lock the Import row, set the status `processing`, clear `error_message`, and dispatch a new Process_Job.
5. IF an Import fails during file validation after the row was created, THEN THE Import_Service SHALL set the status `failed` with a sanitized `error_message` and dispatch `ImportFailed`.
6. THE Application SHALL retain the uploaded file of an Import in every status, including `cancelled` and `failed`, for traceability.
7. THE Application SHALL NOT permit deletion of an Import or an `import_errors` row through any route.

### Requirement 12: Private file storage and download

**User Story:** As an Owner, I want the original file kept and downloadable only by authorized people, so that every figure traces back to its source.

#### Acceptance Criteria

1. THE Application SHALL store each uploaded file on the private `local` disk under `imports/{import_id}/` with a random file name, and SHALL save the path in `stored_path`.
2. THE Application SHALL store the sanitized client file name in `original_filename` for display only and SHALL NOT use it in a storage path.
3. WHEN an authorized user requests the file download for an Import, THE Application SHALL stream the stored file with `Content-Type: text/csv`, `Content-Disposition: attachment` and the sanitized original file name.
4. IF an unauthorized or out-of-scope user requests the file download, THEN THE Application SHALL respond with HTTP 403 for an in-scope Import and HTTP 404 for an out-of-scope Import.
5. IF the stored file is missing from disk, THEN THE Application SHALL respond with HTTP 404 and display an error message that the source file is unavailable.
6. WHEN a file download completes the authorization check, THE Application SHALL dispatch `ImportFileDownloaded`.
7. THE Application SHALL NOT expose `stored_path` or any absolute path in any Inertia prop or response.

### Requirement 13: Data Imports history

**User Story:** As a Manager, I want a list of all imports with status and counts, so that I can see what was loaded and when.

#### Acceptance Criteria

1. WHEN an Owner or Manager opens the Data Imports page, THE Application SHALL display a table of Imports visible to the user, newest first, 25 rows per page.
2. THE table SHALL display for each Import the status badge, original file name, date range (`min_date` to `max_date`), `total_rows`, `invalid_rows`, uploader name and upload date in the Company timezone.
3. THE Application SHALL provide a status filter with the values of Import_Status and an "all" option, default "all".
4. WHEN a status filter is set and no Import matches, THE Application SHALL display an `EmptyState` with a clear-filter action.
5. WHEN no Import exists, THE Application SHALL display an `EmptyState` with the primary action "Upload sales data".
6. THE Data Imports page SHALL display the Freshness information defined in Requirement 15 above the table.
7. WHILE any listed Import has the status `processing`, THE Application SHALL refresh the list data every 3 seconds, and SHALL stop refreshing when no listed Import is in `processing`.
8. THE Application SHALL announce status changes during polling through an `aria-live` region.
9. THE Application SHALL stack table rows as cards below 768 px width.
10. THE Application SHALL provide an "Upload sales data" primary action that opens the upload page.

### Requirement 14: Import detail

**User Story:** As a Manager, I want one page that shows what an import contained and what it changed, so that I can answer questions about any figure.

#### Acceptance Criteria

1. WHEN an authorized user opens an Import, THE Application SHALL display the status badge, file name, file size, `file_hash`, uploader, upload time, `confirmed_at`, `completed_at`, and the date range.
2. THE Import detail page SHALL display `total_rows`, `valid_rows`, `invalid_rows`, `inserted_rows`, `corrected_rows` and `unchanged_rows`.
3. THE Import detail page SHALL display the `import_errors` table with row number, column, code and message, 50 rows per page, ordered by row number.
4. WHEN the Import has status `validated`, THE Import detail page SHALL display the preview content and the Confirm and Cancel actions defined in Requirements 8, 9 and 11.
5. WHEN the Import has status `failed`, THE Import detail page SHALL display the sanitized `error_message` and a Retry action.
6. WHEN the Import has status `completed` or `completed_with_errors`, THE Import detail page SHALL display the coverage status of each Reporting_Week that overlaps the Import date range, computed by Reporting_Coverage from Active_Records.
7. WHILE the Import has status `processing`, THE Import detail page SHALL poll the Import status every 3 seconds and update the page when the status changes.
8. THE Import detail page SHALL display a "Download source file" action for authorized users.
9. THE Import detail page SHALL display the Import_Status through an icon and a text label and SHALL NOT use color alone.
10. WHEN `invalid_rows` is greater than zero on a completed Import, THE Import detail page SHALL display a notice that the invalid rows were not imported.

### Requirement 15: Data freshness

**User Story:** As an Owner, I want to see how recent the sales data is, so that I do not mistake old data for current performance.

#### Acceptance Criteria

1. THE Reporting_Coverage SHALL return the as-of date as the latest `sales_date` among Active_Records of the department, or null when no Active_Record exists.
2. THE Reporting_Coverage SHALL return the Freshness `days_since` as the number of whole days from the as-of date to `BusinessClock::today()`.
3. WHEN the Data Imports page is displayed and an as-of date exists, THE Application SHALL display the as-of date and the text "{n} days ago", or "today" when `days_since` is zero.
4. WHEN the Data Imports page is displayed and no as-of date exists, THE Application SHALL display "No sales data yet".
5. THE Application SHALL NOT create an issue or a notification from Freshness in S03.
6. WHERE `DEMO_MODE` is true and `DEMO_FIXED_DATE` is set, THE Reporting_Coverage SHALL compute `days_since` from the fixed date through `BusinessClock::today()`.

### Requirement 16: ReportingCoverage service

**User Story:** As a developer of S04 and S08, I want one tested definition of a complete week, so that rules and outcomes never compare partial data.

#### Acceptance Criteria

1. THE Reporting_Coverage SHALL expose `expectedBranches(Department)` that returns the Expected_Branches array.
2. THE Reporting_Coverage SHALL expose `tradingDates(Company, weekStart)` that returns the dates in the Reporting_Week whose ISO weekday is in Trading_Days.
3. THE Reporting_Coverage SHALL expose `weekCoverage(Department, weekStart, ?pendingIdentities)` that returns a structured result with `weekStart`, `weekEnd`, `status`, `expectedBranchDays`, `presentBranchDays`, `coveragePct` and the list of Coverage_Gaps.
4. WHEN the number of Expected_Branches is greater than zero and every Expected_Branch has an Active_Record for every Trading_Day of the week, THE Reporting_Coverage SHALL return the status `complete`.
5. WHEN at least one Active_Record exists in the week and the week is not complete, THE Reporting_Coverage SHALL return the status `partial`.
6. WHEN no Active_Record exists in the week, or the department has no Expected_Branches, THE Reporting_Coverage SHALL return the status `absent`.
7. THE Reporting_Coverage SHALL count an Active_Record with `revenue` of zero as present.
8. THE Reporting_Coverage SHALL ignore Active_Records on non-trading days and Active_Records of branch codes outside Expected_Branches when computing `presentBranchDays`.
9. THE Reporting_Coverage SHALL NOT substitute zero for a Coverage_Gap in any returned figure.
10. THE Reporting_Coverage SHALL compute `coveragePct` as `presentBranchDays` divided by `expectedBranchDays` using decimal arithmetic at 4 decimal places with round half up, and SHALL return null when `expectedBranchDays` is zero.
11. THE Reporting_Coverage SHALL expose `missingBranchDays(Department, startDate, endDate)` that returns the Coverage_Gaps for the date range, so that S04 can evaluate the missing-data rule over the window from the Monday of the as-of week through the as-of date.
12. THE Reporting_Coverage SHALL expose `latestCompleteWeek(Department)` that returns the most recent Complete_Week starting at or before the Monday of the as-of week, searching back to the earliest Active_Record, or null when none exists.
13. THE Reporting_Coverage SHALL expose `asOfDate(Department)` and `freshness(Department)` as defined in Requirement 15.
14. WHEN `weekCoverage` receives Pending_Identities, THE Reporting_Coverage SHALL evaluate coverage as if those identities were Active_Records, and SHALL write nothing.
15. THE Reporting_Coverage SHALL perform read-only queries, SHALL hold no state between requests, and SHALL answer each `weekCoverage` call with a bounded number of database queries that does not depend on the number of rows in the week.
16. THE Reporting_Coverage SHALL return the Reporting_Week start as the ISO Monday of the supplied date.
17. FOR ALL Reporting_Weeks, the status SHALL be `complete` if and only if `presentBranchDays` equals `expectedBranchDays` and `expectedBranchDays` is greater than zero (completeness invariant).

### Requirement 17: Events and module boundaries

**User Story:** As a developer of S04 and S08, I want stable events from the import module, so that evaluation and activity logging attach without S03 knowing about them.

#### Acceptance Criteria

1. THE Application SHALL define the events `ImportValidated`, `ImportConfirmed`, `ImportCancelled`, `ImportCompleted`, `ImportFailed` and `ImportFileDownloaded` in `App\Events\Imports`.
2. THE Application SHALL give each event the public properties `importId`, `departmentId`, `actorUserId` (null for system-originated events) and `occurredAt`.
3. THE `ImportCompleted` event SHALL additionally carry `status`, `insertedRows`, `correctedRows`, `unchangedRows`, `invalidRows`, `minDate` and `maxDate`.
4. WHEN the Import_Service sets the status `validated`, THE Application SHALL dispatch `ImportValidated` after the transaction commits.
5. THE Application SHALL dispatch every event after the database transaction that changed the data has committed.
6. THE Application SHALL NOT write any `activity_logs` row, call any `ActivityLogger`, or reference the `activity_logs` table from S03 code.
7. THE Application SHALL NOT evaluate rules, calculate weekly metrics, create issues, or dispatch `EvaluateRulesJob` from S03 code.
8. WHERE no listener is registered for an event, THE Application SHALL continue normal operation without error.
9. THE Application SHALL document the event names and payloads in `docs/architecture.md` so that S04 and S08 register listeners without changing S03 code.

### Requirement 18: Decimal precision and calendar handling

**User Story:** As an Owner, I want amounts stored and compared exactly, so that totals match the source file to the sen.

#### Acceptance Criteria

1. THE Application SHALL store, compare and sum money values as decimal strings or `numeric` values and SHALL NOT use floating-point arithmetic for money in S03 code.
2. WHEN the Import_Classifier compares values, THE Import_Classifier SHALL compare `revenue` and `target_revenue` after normalizing to two decimal places, so that `100`, `100.0` and `100.00` are equal.
3. THE Application SHALL treat `sales_date` as a calendar date with no time component, independent of the Company timezone.
4. THE Application SHALL use `BusinessClock::today()` as the business today for the `future_date` check.
5. THE Application SHALL store `branch_code` in upper case in `sales_records`.
6. FOR ALL valid CSV rows, reading a row, saving it as a Sales_Record and formatting it back to CSV cells SHALL produce the same `sales_date`, upper-case `branch_code`, two-decimal `revenue` and `target_revenue`, and `order_count` (round-trip property).

### Requirement 19: Settings "Import template" tab

**User Story:** As a Manager, I want the template, the column rules and the branch list in one Settings place, so that I can prepare correct files.

#### Acceptance Criteria

1. THE Application SHALL register the Template_Tab in the S02 Settings tab extension point for the Owner and Manager roles.
2. IF a Staff user opens the Template_Tab, THEN THE Application SHALL respond with HTTP 403.
3. THE Template_Tab SHALL contain the template download action, the column specification table, the file limits and the Expected_Branches editor.
4. WHEN the Expected_Branches editor is saved, THE Application SHALL display a success toast through the S01 flash helper.
5. IF validation of the Expected_Branches form fails, THEN THE Application SHALL redisplay the form with field errors and the submitted values.
6. THE Template_Tab SHALL display the branches that appear in Active_Records but are missing from Expected_Branches as a notice, so that the user can add them.
7. THE Template_Tab SHALL be keyboard operable and every input SHALL have a visible label.

### Requirement 20: Errors, security and logging

**User Story:** As an Owner, I want uploads handled safely, so that a bad file cannot harm the system or leak data.

#### Acceptance Criteria

1. THE Application SHALL validate every S03 request body with a Form Request class before any write.
2. THE Application SHALL protect every S03 state-changing route with the `auth` and active-user middleware and CSRF validation.
3. THE Application SHALL use Eloquent or the query builder with bound parameters for all S03 queries.
4. THE Application SHALL NOT log cell values, file contents, absolute storage paths or session data in any log line or error message.
5. IF an unexpected exception occurs in an S03 controller, THEN THE Application SHALL display the S01 error page without internal details.
6. IF a request references an Import that does not exist, THEN THE Application SHALL respond with HTTP 404.
7. THE Application SHALL execute no spreadsheet formulas and SHALL treat every cell as text until a typed rule parses it.
8. THE Application SHALL keep uploaded files outside the public web root.
9. THE Application SHALL provide a loading state, an empty state and an error state with retry on each Import_Page.
10. THE Application SHALL render Import_Pages without horizontal scrolling at 360, 768 and 1280 px width, and every touch target SHALL be at least 40 px.

### Requirement 21: Scope boundaries

**User Story:** As a project owner, I want S03 to stay inside Stage 1, so that the demonstration stays achievable.

#### Acceptance Criteria

1. THE Application SHALL accept only `.csv` files in S03.
2. THE Application SHALL provide no column-mapping screen, no Excel or other spreadsheet import, and no automatic or scheduled data sync.
3. THE Application SHALL provide no sales data entry form, edit form or delete action for individual Sales_Records.
4. THE Application SHALL provide no per-branch trading calendar, public holiday calendar or additional department in S03.
5. THE Application SHALL define S03 so that rule evaluation, metrics, dashboards, briefs, issues and activity logging remain owned by S04, S05 and S08.
6. THE Application SHALL include no automated unit or end-to-end test suite in S03 and SHALL be verified according to `.kiro/steering/verification.md`.

### Requirement 22: Fixture-based verification expectations

**User Story:** As a verifier, I want defined fixture files and expected results, so that the import can be checked the same way every time.

#### Acceptance Criteria

1. THE S03 verification SHALL use fixture CSV files that follow the CSV format section exactly, authored in S09, for the cases F1 to F12 in this requirement.
2. WHEN fixture F1 is uploaded (3 branches KL, PG, JB, Monday to Saturday of one ISO week, 18 valid rows, empty Expected_Branches), THE Application SHALL report 18 new, 0 corrected, 0 unchanged, 0 invalid, and after confirmation SHALL set Expected_Branches to `JB, KL, PG` and report the week as `complete`.
3. WHEN fixture F2 (header without `target_revenue`) is uploaded, THE Application SHALL reject the file with `missing_columns` and create no Import.
4. WHEN fixture F3 (18 rows with one invalid date, one negative revenue, one `1,234.50` revenue and one unknown branch after F1 was imported) is uploaded, THE Application SHALL report 14 valid and 4 invalid rows with the codes `invalid_date`, `negative_value`, `invalid_number` and `unknown_branch` and the correct row numbers.
5. WHEN fixture F4 (the F1 rows with revenue changed on 2 rows) is uploaded after F1 was confirmed, THE Application SHALL report 16 unchanged and 2 corrected, and after confirmation SHALL keep 18 Active_Records and 2 superseded rows.
6. WHEN fixture F5 (two rows with the same branch and date) is uploaded, THE Application SHALL mark both rows invalid with `duplicate_in_file`.
7. WHEN fixture F1 is uploaded a second time after confirmation, THE Application SHALL display "File already imported", report 18 unchanged, and the sum of Active_Record revenue SHALL equal its value before the second import.
8. WHEN fixture F6 (F1 without one KL Wednesday row) is imported into an empty department, THE Reporting_Coverage SHALL return `partial` for the week with one Coverage_Gap for KL on that Wednesday, and the revenue sums SHALL NOT include a zero for that day.
9. WHEN fixture F7 (a row with `revenue` of `0.00`) is imported, THE Reporting_Coverage SHALL count that branch-day as present.
10. WHEN fixture F8 (6,000 rows within limits) is imported, THE Application SHALL complete the Import and satisfy the counter invariants in Requirement 7.
11. WHEN the Confirm action is submitted twice in quick succession for one Import, THE Application SHALL produce exactly one set of Sales_Record changes.
12. WHEN the stored file for a `validated` Import is deleted from disk before confirmation, THE Process_Job SHALL fail the Import with a sanitized message and no Sales_Record change.
13. THE verification SHALL include the authorization matrix of Owner, Manager and Staff, and a Manager of another department, against every S03 route and the file download, with the 403 and 404 results defined in Requirement 2 and Requirement 12.
14. THE verification SHALL include checks at 360, 768 and 1280 px width and a keyboard-only upload and confirm run.
15. THE verification SHALL run `migrate:fresh` and a rollback of the S03 migrations on a scratch database, `composer pint-test`, and `npm run verify`.
