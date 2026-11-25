# Requirements Document

## Introduction

S03 Sales Data Import and Validation lets an Owner or a Manager load daily branch sales into BusinessPulse from a CSV file, check the file before anything is saved, confirm the import, and trace every import afterwards. It owns the `imports`, `import_errors` and `sales_records` tables, the CSV format, the validation and preview flow, the queued `ProcessImportJob`, the import history and detail screens, live data freshness, the reusable `ReportingCoverage` service (reporting calendar, due days, closed weeks, completeness, missing due branch-days), the deterministic demo fixtures with their `FixtureGenerator`, and the commands `demo:import` and `demo:check-fixtures`. S03 reads the expected-branches setting that S02 stores and seeds. The Settings "Import template" tab with the expected-branches editor is PoV Polish.

S03 implements decisions D1 to D10 and D26 of `docs/business-workflows.md` and refinements R1, R2, R5, R6, R7 and R9 of `docs/stage-1-refinements.md`, which win over any older text. The grain is one row per branch per calendar day. The record identity is `(department_id, branch_code, sales_date)`. A re-imported identity with different values supersedes the active row and never deletes history. Incomplete data is never treated as zero, and repeating an import never inflates revenue.

S03 does not evaluate rules, create issues, build metrics, write activity logs, or send notifications. `ReportingCoverage` returns calendar and coverage facts only (due days, week states, gaps, freshness). S04 decides what those facts mean for rules (`insufficient_data`, `missing_sales_data`). After an import completes, S03 dispatches the `ImportCompleted` event, which S04 consumes to start `EvaluateRulesJob`. S08 owns the `activity_logs` table and the `ActivityLogger` service, so S03 writes no activity log rows and calls no logger. S03 dispatches events, and S08 listeners record the activity.

S03 follows `docs/data-model.md`, `docs/architecture.md` (Imports module), `docs/ui-ux-design-system.md`, `docs/demo-scenario.md` section 2 (fixtures), and the steering files in `.kiro/steering`. Stage 2 features (Excel files, automatic sync, column-mapping screens) are excluded.

### Priority tiers (R1)

- **[PoV Core]**: upload, file and row validation, preview, confirm, `ProcessImportJob`, supersede, history list, import detail, `ReportingCoverage` with calendar and cutoff, the fixtures with `FixtureGenerator`, `demo:import`, `demo:check-fixtures`, the Core events `ImportCompleted` and `ImportFailed`, and the focused feature tests.
- **[PoV Polish]**: the expected-branches settings UI (editor and `UpdateExpectedBranches`), the Template_Tab beyond a template download link, the source CSV download and its `ImportFileDownloaded` event, the events `ImportValidated`, `ImportConfirmed` and `ImportCancelled`, and rate-limit tuning beyond the default.
- Requirement headings and individual criteria carry the tag. An untagged criterion inherits the tag of its requirement heading.

### What S03 introduces

| Kind | Items |
|---|---|
| Tables | `imports`, `import_errors`, `sales_records` as defined in `docs/data-model.md`. S03 adds no column to `departments`; it reads (and, as PoV Polish, writes) only the key `settings.expected_branches` of the S02 column `departments.settings`. |
| Configuration | `config/imports.php` (column specification, limits, MIME types, throttle) |
| Enums | `App\Enums\ImportStatus`, `App\Enums\ImportRowResult` (`new`, `corrected`, `unchanged`, `invalid`), `App\Enums\WeekStatus` (`open`, `closed`), `App\Enums\WeekCompleteness` (`complete`, `incomplete`, `absent`), `App\Enums\FreshnessState` (`current`, `behind`) |
| Models | `Import`, `ImportError`, `SalesRecord` (`Import` and `SalesRecord` use `ScopedToDepartment`) |
| Policies | `ImportPolicy`, `SalesRecordPolicy` |
| Services [PoV Core] | `App\Services\Imports\{CsvImportReader, ImportRowValidator, ImportClassifier, ImportPreviewBuilder, ReportingCoverage, ReportingCalendar, ExpectedBranches}`, `App\Services\Demo\FixtureGenerator` |
| Actions | [PoV Core] `App\Actions\Imports\{CreateImportFromUpload, ConfirmImport, CancelImport, RetryImport}`. [PoV Polish] `UpdateExpectedBranches` |
| Jobs | [PoV Core] `App\Jobs\ProcessImportJob` on the `default` queue |
| Events | [PoV Core] `App\Events\Imports\{ImportCompleted, ImportFailed}`. [PoV Polish] `{ImportValidated, ImportConfirmed, ImportCancelled, ImportFileDownloaded}` |
| Form Requests | [PoV Core] `UploadImportRequest`. [PoV Polish] `UpdateExpectedBranchesRequest` |
| Controllers | [PoV Core] `ImportController`, `ImportTemplateController` (template download). [PoV Polish] `ImportFileController` (source CSV download), `ImportTemplateSettingsController` |
| Commands | [PoV Core] `demo:import {fixture}`, `demo:check-fixtures` |
| Fixtures | [PoV Core] `database/demo/{history, week8-decline, week8-with-errors, week8-correction, week9-interim, week10-recovery}.csv` and `database/demo/expected.json` |
| Pages | [PoV Core] Data Imports history, Upload with preview, Import detail. [PoV Polish] Settings "Import template" tab |
| Components | [PoV Core] `FileDropzone` and a status polling composable (S03 is the first consumer; S01 does not provide them) |
| Tests | [PoV Core] Feature tests in `tests/Feature/Imports` on the S01 harness |
| Changes to S02 | [PoV Polish] The Template_Tab is registered in the S02 Settings extension point. S03 adds no migration to S02 tables. |
| Changes to docs | `docs/data-model.md` (import counter semantics), `docs/architecture.md` (events, `ReportingCoverage` contract, fixtures and commands) |

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

- S01: `AppShell`, `PageHeader`, `StatusBadge`, `EmptyState`, `ErrorState`, the flash toast helper, error pages (403, 404, 419), `BusinessClock` (`now()` and `today()`, demo clock file, `demo:set-date`), `config/demo.php` (`DEMO_MODE`), the database queue with the `default` queue and `retry_after` 300, the private `local` disk, and the test harness (`composer test`, PostgreSQL database `businesspulse_testing`). S01 does not provide `FileDropzone` or a polling composable; S03 builds both as the first consumer.
- S02: `Company` (`timezone`, `trading_days`, `reporting_cutoff_time`, `reporting_cutoff_lag_days`), `Department` (`settings` JSONB created by S02, `expectedBranches()` accessor), `DemoIdentitySeeder` (Sales department with Expected_Branches `KL`, `PG`, `JB`, trading days Monday to Saturday, cutoff `12:00`, lag 1, demo users), `User`, `Role`, `ScopedToDepartment` and `visibleTo()`, policy conventions, `CurrentCompany`, and (PoV Polish) the Settings tab layout.
- Consumed by S04 (`ImportCompleted`, `ReportingCoverage`, `sales_records`, and `expected.json` which S04 extends with evaluation expectations), S05 (freshness, `latestClosedWeek`), S08 (`ReportingCoverage`, import events for activity logging), S09 (fixtures, `FixtureGenerator`, `demo:import` for `demo:seed` and `demo:reset`).

### Assumptions

- A1: Stage 1 has one company and one Sales department. An Owner (who has no department) uploads into the Company's single department. A Manager uploads into the own department.
- A2: Expected_Branches is stored by S02 in `departments.settings` under the key `expected_branches` (an array of upper-case branch codes) and seeded explicitly for the demo (`KL`, `PG`, `JB`). S03 does not create the column and never auto-fills the list. The editor on the Template_Tab is PoV Polish; until it exists, the list is changed by seeding or database update.
- A3: Branch codes in an upload that are not in Expected_Branches are rejected row by row with the code `unknown_branch`. The user adds the branch in Settings and uploads again. This prevents a typo from creating a branch that would leave every week incomplete.
- A4: An upload into a department whose Expected_Branches is empty is rejected before any row is read, with the code `no_expected_branches`. No import row is created and no file is stored (decisions D3 and R2).
- A5: Coverage is always computed from the current Expected_Branches, the current Trading_Days, the current cutoff settings and `BusinessClock::now()`. Evaluation evidence snapshotted by S04 keeps historical results unchanged.
- A6: Rows dated on a non-trading day are valid, stored, and ignored for completeness. The preview reports their count as a notice.
- A7: The last confirmed import wins. A confirmed file with older figures supersedes newer figures for the same identity. The preview shows the corrected count so the user can see this before confirming.
- A8: File-level failures (extension, MIME, size, encoding, header, row limit) and `no_expected_branches` create no `imports` row and store no file. The user sees the message with the expected template.
- A9: Validation of a file runs synchronously during upload because the limits (5 MB, 10,000 rows) keep it within a normal request. Processing after confirmation is queued.
- A10: Preview counts are persisted in the counter columns of `imports`. The row-level pending identities and coverage list are rebuilt on demand by re-reading the stored file, which is immutable.
- A11: An import left in `validated` status stays until the user confirms or cancels it. Automatic expiry is not part of Stage 1.
- A12: A confirmed import with zero changed rows still completes and dispatches `ImportCompleted`, because S04 evaluation is idempotent.
- A13: Import limits (5 MB, 10,000 rows, 10 uploads per minute per user) are demonstration defaults and are flagged for client confirmation together with the real sales file layout. Tuning the throttle beyond the default is PoV Polish.
- A14: Public holidays are not modelled (D3). A holiday that falls on a trading day appears as a coverage gap once the day is due.
- A15: Imports produce no in-app notification in Stage 1.
- A16: The calendar defaults (Monday to Saturday, cutoff `12:00`, lag 1 day) are demonstration defaults from D3 and are flagged for client confirmation.
- A17: Fixture data is fictional (Meridian Home Supplies, branches KL, PG, JB). The `order_count` column is present in every fixture and left empty. Fixtures must be imported in the clock order of `docs/demo-scenario.md` section 2, because dates after the business today are rejected as `future_date`.
- A18: S03 evaluates no rule. The only "missing" facts it reports are coverage gaps for due branch-days. Missing data is never converted to zero.

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
- **Business_Clock**: The S01 helper `BusinessClock`. `now()` returns the business date-time in the Company timezone and honours the demo clock file when `DEMO_MODE` is true.
- **Reporting_Coverage**: The service `ReportingCoverage` that answers calendar and coverage questions from Active_Records, the Company calendar and the Business_Clock.
- **Expected_Branches**: The list of branch codes stored in `departments.settings.expected_branches`.
- **Trading_Days**: The ISO weekday integers in `companies.trading_days`.
- **Reporting_Cutoff**: The pair `companies.reporting_cutoff_time` (time of day) and `companies.reporting_cutoff_lag_days` (number of days after the trading day).
- **Due_Day**: A trading day D for which `Business_Clock::now()` is at or after (D plus the lag days) at the cutoff time in the Company timezone. A future day, a non-trading day, and a trading day whose cutoff has not passed are not Due_Days.
- **Last_Due_Date**: The latest Due_Day.
- **Reporting_Week**: An ISO week from Monday to Sunday.
- **Closed_Week**: A Reporting_Week in which every trading day is a Due_Day.
- **Open_Week**: A Reporting_Week that is not a Closed_Week. Open_Weeks are informational only.
- **Complete_Week**: A Closed_Week in which every Expected_Branch has an Active_Record for every trading day, and Expected_Branches is not empty.
- **Incomplete_Week**: A Closed_Week that has at least one Active_Record counted toward completeness and at least one missing expected branch-day.
- **Absent_Week**: A Closed_Week that has no Active_Record counted toward completeness, or whose department has no Expected_Branches.
- **Week_State**: The result of `weekState` for one Reporting_Week: its status (`open` or `closed`) and, for a Closed_Week, its completeness (`complete`, `incomplete` or `absent`).
- **Coverage_Gap**: One Expected_Branch and Due_Day combination that has no Active_Record.
- **Overdue_Branch_Day**: A Coverage_Gap whose date is later than the latest sales date and not later than the Last_Due_Date.
- **Pending_Identities**: The Record_Identity values and amounts of the valid rows of an Import that is not yet processed, used as an optional overlay as if they were Active_Records.
- **Freshness**: The latest sales date among Active_Records, the Last_Due_Date, the state `current` or `behind`, and the count of Overdue_Branch_Days.
- **Import_Policy**: The policy `ImportPolicy` for the `Import` model.
- **Import_Pages**: The Data Imports history, Upload with preview, and Import detail pages.
- **Template_Tab**: The Settings tab "Import template".
- **Event_Bus**: The Laravel event dispatcher used for the events listed in "What S03 introduces".
- **Demo_Company**: The company with `is_demo` true that S02 seeds.
- **Fixture_Generator**: The class `FixtureGenerator` that produces the demo CSV files deterministically.
- **Demo_Fixtures**: The six CSV files and `expected.json` under `database/demo/`.
- **Demo_Import_Command**: The Artisan command `demo:import {fixture}`.
- **Fixture_Checker**: The Artisan command `demo:check-fixtures`.

## Requirements

### Requirement 1: Import data model [PoV Core]

**User Story:** As a developer, I want the import tables and enums defined once, so that later specs can read sales data with consistent identities and history.

#### Acceptance Criteria

1. THE Application SHALL provide migrations that create `imports`, `import_errors` and `sales_records` with the columns, foreign keys and indexes defined in `docs/data-model.md`.
2. THE Application SHALL define a partial unique index on `sales_records` over `(department_id, branch_code, sales_date)` WHERE `is_active` is true.
3. THE Application SHALL define check constraints that restrict `imports.status` to the seven values of Import_Status, and that restrict `sales_records.revenue` and `sales_records.target_revenue` to values of zero or more.
4. THE Application SHALL store `revenue` and `target_revenue` as `numeric(14,2)` and `order_count` as a nullable integer.
5. THE Application SHALL NOT create or alter `departments.settings` in any S03 migration, because S02 creates that column.
6. THE Application SHALL define the `Import`, `ImportError` and `SalesRecord` models with the relationships `Import` belongs to Department and uploader User, `Import` has many ImportError and SalesRecord, and `SalesRecord` belongs to Department, source Import and superseding Import.
7. THE Application SHALL cast `ImportStatus` on `imports.status`, and SHALL cast `raw_row` on `import_errors` to an array.
8. THE Application SHALL create the migrations so that they run after the S02 migrations without modifying them.
9. THE Application SHALL define `Import` and `SalesRecord` with the `ScopedToDepartment` trait from S02.
10. THE Application SHALL document the import counter semantics in `docs/data-model.md`, and the `ReportingCoverage` contract, the S03 events and the demo commands in `docs/architecture.md`.

### Requirement 2: Authorization and department scoping [PoV Core]

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

### Requirement 3: CSV template and format specification [PoV Core for 1, 5 and 6, PoV Polish for 2 to 4]

**User Story:** As a Manager, I want to download a template and see the exact column rules, so that my file passes validation the first time.

#### Acceptance Criteria

1. WHEN an Owner or Manager requests the template download, THE Application SHALL return a CSV file named `sales-import-template.csv` that contains only the header row `sales_date,branch_code,revenue,target_revenue,order_count`, and THE upload page SHALL display a link to this download.
2. [PoV Polish] THE Template_Tab SHALL display a table of the five columns with required flag, data type, rule and example value, matching the CSV format section of this document.
3. [PoV Polish] THE Template_Tab SHALL display the file limits: 5 MB, 10,000 data rows, UTF-8, comma delimiter.
4. [PoV Polish] THE Template_Tab SHALL display the current Trading_Days and Company timezone as read-only values with a link to the Company tab for the Owner.
5. THE Application SHALL derive the displayed limits from one configuration source (`config/imports.php`) that the Import_Reader also reads.
6. THE Application SHALL define the template header and the column specification once in code and SHALL use that single definition for the template download, the Template_Tab and header validation.

### Requirement 4: Upload and file-level validation [PoV Core for 1 to 11 and 13, PoV Polish for 12]

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
11. THE Application SHALL limit upload requests to 10 per minute for each user. Tuning of this limit beyond the default is PoV Polish.
12. [PoV Polish] IF the upload limit is exceeded, THEN THE Application SHALL respond with HTTP 429 and display the seconds remaining.
13. THE Import_Reader SHALL parse the file in a streaming manner so that memory use does not grow with row count beyond the 10,000-row limit.

### Requirement 5: Row-level validation [PoV Core]

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

### Requirement 6: Expected branches and branch handling [PoV Core for 1 to 6, PoV Polish for 7 to 12]

**User Story:** As a Manager, I want the system to know which branches must report, so that missing branch data is detected instead of ignored.

#### Acceptance Criteria

1. THE Application SHALL read Expected_Branches from `departments.settings.expected_branches`, a sorted array of unique upper-case branch codes that S02 stores and the demo seeder sets to `JB`, `KL` and `PG`, and SHALL use that array as the only source of expected branches.
2. WHEN the Row_Validator reads a `branch_code`, THE Import_Service SHALL trim the value and convert it to upper case before comparison and storage.
3. IF the Expected_Branches of the target department is empty, THEN THE Import_Service SHALL reject the upload with the code `no_expected_branches` and a message that expected branches must be configured before sales data can be imported, create no `imports` row, and store no file.
4. IF a row has a branch code outside Expected_Branches, THEN THE Row_Validator SHALL mark the row invalid with the code `unknown_branch`.
5. THE Import_Service SHALL leave Expected_Branches unchanged during upload, validation, confirmation and processing, and SHALL NOT derive Expected_Branches from the content of any import.
6. IF the Process_Job starts and Expected_Branches is empty, THEN THE Process_Job SHALL fail the Import with the sanitized message "Expected branches are not configured" and change no Sales_Record.
7. [PoV Polish] WHEN an Owner or Manager submits the expected-branches form on the Template_Tab, THE Application SHALL validate the codes with the branch format rule, remove duplicates, convert to upper case and save the sorted list under `settings.expected_branches` while preserving every other key of `departments.settings`.
8. [PoV Polish] IF the submitted expected-branches list is empty, THEN THE Application SHALL reject the change with a field error.
9. [PoV Polish] IF the submitted expected-branches list contains more than 50 codes, THEN THE Application SHALL reject the change with a field error.
10. [PoV Polish] WHEN an Owner or Manager removes a branch code that has Sales_Records, THE Application SHALL keep those Sales_Records unchanged.
11. [PoV Polish] WHILE an Import in `processing` status exists for the department, THE Application SHALL reject changes to Expected_Branches with a message that an import is in progress.
12. [PoV Polish] THE Template_Tab SHALL display the current Expected_Branches and the coverage of the latest Closed_Week per branch only as supplied by Reporting_Coverage.

### Requirement 7: Row classification, duplicates and same-file detection [PoV Core]

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

### Requirement 8: Validation preview before confirmation [PoV Core]

**User Story:** As a Manager, I want a full preview before anything is saved, so that I can confirm or cancel with confidence.

#### Acceptance Criteria

1. WHEN file-level validation passes, THE Import_Service SHALL store the file privately, create an Import with status `uploaded`, validate and classify all rows, save the `import_errors` rows and counters, and set the status to `validated`.
2. WHEN the Import reaches `validated`, THE Application SHALL display the preview page for that Import.
3. THE preview page SHALL display the file name, file size, uploader, `total_rows`, and the counts of new, corrected, unchanged and invalid rows.
4. THE preview page SHALL display the minimum and maximum `sales_date` among valid rows and the distinct branch codes found in valid rows.
5. THE preview page SHALL display the row-level errors in a table with row number, column, code and message, 50 rows per page, ordered by row number.
6. THE preview page SHALL display the number of valid rows dated on a non-trading day as a notice.
7. THE preview page SHALL list every Reporting_Week that overlaps the valid-row date range, with its Week_State (open or closed and, for a Closed_Week, complete, incomplete or absent) computed by Reporting_Coverage as if the Pending_Identities were already Active_Records, and for each Incomplete_Week the Coverage_Gaps, up to 50 gaps per week plus a count of any further gaps. THE preview page SHALL label an Open_Week as informational and SHALL list no gap for a day that is not a Due_Day.
8. WHEN the file contains zero valid rows, THE preview page SHALL display a message that nothing can be imported and SHALL disable the confirm action.
9. THE preview page SHALL display an informational notice that incomplete weeks stay incomplete and are not treated as zero.
10. THE preview page SHALL display the Import_Status and a Cancel action for an Import in `validated` status.
11. WHILE the preview data is being loaded, THE Application SHALL display skeleton placeholders that match the layout.
12. THE Preview_Builder SHALL rebuild the Pending_Identities and coverage list from the stored file WHEN a user opens a `validated` Import, and SHALL produce the same counts as those persisted at validation time unless Active_Records changed in the meantime.
13. IF Active_Records changed between validation and viewing so that counts differ, THEN THE preview page SHALL display the refreshed counts and a notice that the preview was refreshed.
14. THE Application SHALL save no Sales_Record and change no Expected_Branches while an Import is in `uploaded` or `validated` status.
15. THE preview page SHALL state whether the file leaves due days uncovered, by displaying the number of Overdue_Branch_Days that remain after the Pending_Identities are overlaid (due days after the latest sales date and up to the Last_Due_Date with no row), or a statement that no due branch-day remains uncovered, and SHALL display the Last_Due_Date used.

### Requirement 9: Confirmation and queued processing [PoV Core]

**User Story:** As a Manager, I want confirming an import to be safe against double clicks and failures, so that data is never saved twice or half saved.

#### Acceptance Criteria

1. WHEN an authorized user confirms an Import in `validated` status that has at least one valid row, THE `ConfirmImport` Action SHALL lock the Import row, set the status to `processing`, set `confirmed_at`, and dispatch the Process_Job on the `default` queue after the transaction commits.
2. IF the Import is not in `validated` status when a confirm request arrives, THEN THE `ConfirmImport` Action SHALL return the current Import without dispatching a second Process_Job.
3. THE Process_Job SHALL run on the `default` queue, SHALL implement `ShouldBeUnique` keyed by the Import id with `uniqueFor` of 900 seconds, SHALL declare `$timeout` of 120 seconds, `$tries` of 3 and `backoff()` of 10, 30 and 60 seconds, SHALL be dispatched after commit, and SHALL define a `failed()` handler.
4. WHEN the Process_Job starts and the Import status is not `processing`, THE Process_Job SHALL exit without writing any data.
5. WHEN the Process_Job runs, THE Process_Job SHALL re-read the stored file, re-validate and re-classify every row against the Active_Records visible at that moment, and use the results for the writes.
6. WHEN the Process_Job writes data, THE Process_Job SHALL perform all Sales_Record changes, `import_errors` replacement, counter updates and status change in one database transaction.
7. WHILE a Process_Job for a department is running, THE Application SHALL prevent a second Process_Job for the same department from writing, by acquiring a per-department lock and releasing the second job back to the queue.
8. WHEN the Process_Job succeeds with zero invalid rows, THE Process_Job SHALL set the status `completed` and set `completed_at`.
9. WHEN the Process_Job succeeds with one or more invalid rows, THE Process_Job SHALL set the status `completed_with_errors` and set `completed_at`.
10. WHEN the Process_Job reaches `completed` or `completed_with_errors`, THE Process_Job SHALL dispatch `ImportCompleted` after the transaction commits.
11. IF the Process_Job throws an exception during the transaction, THEN THE Process_Job SHALL roll back every Sales_Record change of that run.
12. IF the Process_Job exhausts its attempts or fails, THEN THE `failed()` handler SHALL set the status `failed`, save a sanitized `error_message` without file paths, stack traces or cell values, and dispatch `ImportFailed`.
13. THE Application SHALL write valid rows in chunks of at most 1,000 rows per statement inside the transaction.
14. WHEN the Process_Job re-validates and finds that the file now yields zero valid rows, THE Process_Job SHALL set the status `failed` with the message "No valid rows to import".

### Requirement 10: Corrections, supersede behavior and no revenue inflation [PoV Core]

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

### Requirement 11: Import lifecycle, cancel and retry [PoV Core]

**User Story:** As a Manager, I want to cancel an import I do not want and retry a failed one, so that I stay in control.

#### Acceptance Criteria

1. THE Application SHALL permit only these status transitions: `uploaded` to `validated`, `uploaded` to `failed`, `validated` to `processing`, `validated` to `cancelled`, `uploaded` to `cancelled`, `processing` to `completed`, `processing` to `completed_with_errors`, `processing` to `failed`, and `failed` to `processing`.
2. WHEN an authorized user cancels an Import in `uploaded` or `validated` status, THE `CancelImport` Action SHALL set the status `cancelled`.
3. IF a cancel request arrives for an Import in `processing`, `completed`, `completed_with_errors`, `failed` or `cancelled` status, THEN THE `CancelImport` Action SHALL reject the request with a message that names the current status.
4. WHEN an authorized user retries an Import in `failed` status that has a stored file, THE `RetryImport` Action SHALL lock the Import row, set the status `processing`, clear `error_message`, and dispatch a new Process_Job.
5. IF an Import fails during file validation after the row was created, THEN THE Import_Service SHALL set the status `failed` with a sanitized `error_message` and dispatch `ImportFailed`.
6. THE Application SHALL retain the uploaded file of an Import in every status, including `cancelled` and `failed`, for traceability.
7. THE Application SHALL NOT permit deletion of an Import or an `import_errors` row through any route.

### Requirement 12: Private file storage and download [PoV Core for 1, 2 and 7, PoV Polish for 3 to 6]

**User Story:** As an Owner, I want the original file kept and downloadable only by authorized people, so that every figure traces back to its source.

#### Acceptance Criteria

1. THE Application SHALL store each uploaded file on the private `local` disk under `imports/{import_id}/` with a random file name, and SHALL save the path in `stored_path`.
2. THE Application SHALL store the sanitized client file name in `original_filename` for display only and SHALL NOT use it in a storage path.
3. WHEN an authorized user requests the file download for an Import, THE Application SHALL stream the stored file with `Content-Type: text/csv`, `Content-Disposition: attachment` and the sanitized original file name.
4. IF an unauthorized or out-of-scope user requests the file download, THEN THE Application SHALL respond with HTTP 403 for an in-scope Import and HTTP 404 for an out-of-scope Import.
5. IF the stored file is missing from disk, THEN THE Application SHALL respond with HTTP 404 and display an error message that the source file is unavailable.
6. WHEN a file download completes the authorization check, THE Application SHALL dispatch `ImportFileDownloaded`.
7. THE Application SHALL NOT expose `stored_path` or any absolute path in any Inertia prop or response.

### Requirement 13: Data Imports history [PoV Core]

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

### Requirement 14: Import detail [PoV Core for 1 to 7 and 9 to 10, PoV Polish for 8]

**User Story:** As a Manager, I want one page that shows what an import contained and what it changed, so that I can answer questions about any figure.

#### Acceptance Criteria

1. WHEN an authorized user opens an Import, THE Application SHALL display the status badge, file name, file size, `file_hash`, uploader, upload time, `confirmed_at`, `completed_at`, and the date range.
2. THE Import detail page SHALL display `total_rows`, `valid_rows`, `invalid_rows`, `inserted_rows`, `corrected_rows` and `unchanged_rows`.
3. THE Import detail page SHALL display the `import_errors` table with row number, column, code and message, 50 rows per page, ordered by row number.
4. WHEN the Import has status `validated`, THE Import detail page SHALL display the preview content and the Confirm and Cancel actions defined in Requirements 8, 9 and 11.
5. WHEN the Import has status `failed`, THE Import detail page SHALL display the sanitized `error_message` and a Retry action.
6. WHEN the Import has status `completed` or `completed_with_errors`, THE Import detail page SHALL display the Week_State (open or closed and, for a Closed_Week, complete, incomplete or absent) of each Reporting_Week that overlaps the Import date range, computed by Reporting_Coverage from Active_Records at the current Business_Clock time.
7. WHILE the Import has status `processing`, THE Import detail page SHALL poll the Import status every 3 seconds and update the page when the status changes.
8. [PoV Polish] THE Import detail page SHALL display a "Download source file" action for authorized users.
9. THE Import detail page SHALL display the Import_Status through an icon and a text label and SHALL NOT use color alone.
10. WHEN `invalid_rows` is greater than zero on a completed Import, THE Import detail page SHALL display a notice that the invalid rows were not imported.

### Requirement 15: Data freshness [PoV Core]

**User Story:** As an Owner, I want to see live how current the sales data is, so that I do not mistake old data for current performance.

#### Acceptance Criteria

1. THE Reporting_Coverage SHALL return the latest sales date as the latest `sales_date` among Active_Records of the department, or null when no Active_Record exists.
2. THE Reporting_Coverage SHALL return the Last_Due_Date defined in Requirement 16.
3. THE Reporting_Coverage SHALL return the Freshness state `current` WHEN the latest sales date is on or after the Last_Due_Date, and the state `behind` WHEN the latest sales date is before the Last_Due_Date or is null.
4. THE Reporting_Coverage SHALL return the number of Overdue_Branch_Days (Expected_Branches multiplied by the Due_Days after the latest sales date and up to the Last_Due_Date) together with the first and last overdue dates, and SHALL return a null count WHEN no Active_Record exists.
5. WHEN the Data Imports page is displayed, THE Application SHALL display the latest sales date and the Freshness state, and WHILE the state is `behind` with a count, THE Application SHALL display a message of the form "Sales for {first}–{last} not yet received: {n} branch-days overdue", WHILE the state is `current` THE Application SHALL display "Sales received up to {Last_Due_Date}", and WHEN no Active_Record exists THE Application SHALL display "No sales data yet".
6. THE Application SHALL leave the creation of issues and notifications from Freshness to S04 and SHALL create none in S03.
7. THE Application SHALL compute Freshness on every request from Active_Records and `Business_Clock::now()` and SHALL persist no Freshness value.

### Requirement 16: ReportingCoverage service [PoV Core]

**User Story:** As a developer of S04 and S08, I want one tested definition of due days, closed weeks and completeness, so that rules and outcomes never compare partial data or report future days as missing.

#### Acceptance Criteria

1. THE Reporting_Coverage SHALL read Trading_Days, the Reporting_Cutoff and the timezone from the Company, Expected_Branches from the Department, and the current time from `Business_Clock::now()`, and SHALL read no other clock.
2. THE Reporting_Coverage SHALL expose `isDue(date)` that returns true only WHEN the date is a trading day and `Business_Clock::now()` is at or after (the date plus `reporting_cutoff_lag_days`) at `reporting_cutoff_time` in the Company timezone.
3. THE Reporting_Coverage SHALL expose `lastDueDate()` that returns the latest Due_Day.
4. THE Reporting_Coverage SHALL expose `tradingDates(weekStart)` that returns the dates in the Reporting_Week whose ISO weekday is in Trading_Days.
5. THE Reporting_Coverage SHALL expose `weekState(Department, weekStart, ?pendingIdentities)` that returns a structure with `weekStart`, `weekEnd`, `status`, `completeness`, `expectedBranchDays`, `presentBranchDays`, `dueBranchDays`, `coveragePct` and the list of Coverage_Gaps.
6. THE Reporting_Coverage SHALL return the status `closed` WHEN every trading day of the week is a Due_Day, and the status `open` otherwise.
7. WHEN the status is `closed`, THE Reporting_Coverage SHALL return the completeness `absent` WHEN `presentBranchDays` is zero or Expected_Branches is empty, the completeness `incomplete` WHEN at least one expected branch-day has no Active_Record, and the completeness `complete` otherwise.
8. WHEN the status is `open`, THE Reporting_Coverage SHALL return no completeness value, SHALL list only gaps for Due_Days, and SHALL mark the week as informational so that S04 never evaluates it with the decline or target rules.
9. THE Reporting_Coverage SHALL ignore Active_Records on non-trading days and Active_Records of branch codes outside Expected_Branches when computing `presentBranchDays`, and SHALL count an Active_Record with `revenue` of zero as present.
10. THE Reporting_Coverage SHALL NOT substitute zero for a Coverage_Gap in any returned figure.
11. THE Reporting_Coverage SHALL compute `coveragePct` as `presentBranchDays` divided by `expectedBranchDays` using decimal arithmetic at 4 decimal places with round half up, and SHALL return null WHEN `expectedBranchDays` is zero.
12. THE Reporting_Coverage SHALL expose `latestClosedWeek(Department)` that returns the Week_State of the most recent Closed_Week, whatever its completeness.
13. THE Reporting_Coverage SHALL expose `missingDueBranchDays(Department, windowStart, windowEnd, ?pendingIdentities)` that returns every Expected_Branch and Due_Day combination in the window with no Active_Record, with a `kind` of `inside_period` WHEN the date is not later than the latest sales date and `after_latest` WHEN the date is later than the latest sales date, and SHALL return no combination for a date later than the Last_Due_Date.
14. THE Reporting_Coverage SHALL expose `freshness(Department, ?pendingIdentities)` that returns the values defined in Requirement 15.
15. WHEN `weekState`, `missingDueBranchDays` or `freshness` receives Pending_Identities, THE Reporting_Coverage SHALL evaluate as if those identities were Active_Records, and SHALL write nothing.
16. THE Reporting_Coverage SHALL perform read-only queries, SHALL hold no state between requests, and SHALL answer each call with a bounded number of database queries that does not depend on the number of rows in the window.
17. THE Reporting_Coverage SHALL return the Reporting_Week start as the ISO Monday of the supplied date.
18. THE Reporting_Coverage SHALL return facts only (states, gaps, counts) and SHALL leave every breach, `insufficient_data` and issue decision to S04.
19. FOR ALL Closed_Weeks, the completeness SHALL be `complete` if and only if `presentBranchDays` equals `expectedBranchDays` and `expectedBranchDays` is greater than zero (completeness invariant).
20. FOR ALL calls, no returned Coverage_Gap SHALL carry a date that is not a Due_Day, and no Open_Week SHALL be reported as missing data for a day whose cutoff has not passed (due-day invariant).
21. THE Reporting_Coverage SHALL accept an optional `now` override that defaults to `Business_Clock::now()`, and SHALL expose a database-free `weekStateForRows(calendar, expectedBranches, weekStart, rows)` that `weekState` also uses, so that `demo:check-fixtures` and tests compute states from supplied rows.

### Requirement 17: Events and module boundaries [PoV Core for 1 to 3 and 5 to 9, PoV Polish for 4]

**User Story:** As a developer of S04 and S08, I want stable events from the import module, so that evaluation and activity logging attach without S03 knowing about them.

#### Acceptance Criteria

1. THE Application SHALL define the events `ImportCompleted` and `ImportFailed` [PoV Core] and the events `ImportValidated`, `ImportConfirmed`, `ImportCancelled` and `ImportFileDownloaded` [PoV Polish] in `App\Events\Imports`.
2. THE Application SHALL give each event the public properties `importId`, `departmentId`, `actorUserId` (null for system-originated events) and `occurredAt`.
3. THE `ImportCompleted` event SHALL additionally carry `status`, `insertedRows`, `correctedRows`, `unchangedRows`, `invalidRows`, `minDate` and `maxDate`.
4. [PoV Polish] WHEN the Import_Service sets the status `validated`, WHEN `ConfirmImport` sets `processing`, WHEN `CancelImport` sets `cancelled`, or WHEN an authorized source file download completes, THE Application SHALL dispatch `ImportValidated`, `ImportConfirmed`, `ImportCancelled` or `ImportFileDownloaded` respectively after the transaction commits.
5. THE Application SHALL dispatch every event after the database transaction that changed the data has committed.
6. THE Application SHALL NOT write any `activity_logs` row, call any `ActivityLogger`, or reference the `activity_logs` table from S03 code.
7. THE Application SHALL NOT evaluate rules, calculate weekly metrics, create issues, or dispatch `EvaluateRulesJob` from S03 code.
8. WHERE no listener is registered for an event, THE Application SHALL continue normal operation without error.
9. THE Application SHALL document the event names and payloads in `docs/architecture.md` so that S04 and S08 register listeners without changing S03 code.

### Requirement 18: Decimal precision and calendar handling [PoV Core]

**User Story:** As an Owner, I want amounts stored and compared exactly, so that totals match the source file to the sen.

#### Acceptance Criteria

1. THE Application SHALL store, compare and sum money values as decimal strings or `numeric` values and SHALL NOT use floating-point arithmetic for money in S03 code.
2. WHEN the Import_Classifier compares values, THE Import_Classifier SHALL compare `revenue` and `target_revenue` after normalizing to two decimal places, so that `100`, `100.0` and `100.00` are equal.
3. THE Application SHALL treat `sales_date` as a calendar date with no time component, independent of the Company timezone.
4. THE Application SHALL use `BusinessClock::today()` as the business today for the `future_date` check.
5. THE Application SHALL store `branch_code` in upper case in `sales_records`.
6. FOR ALL valid CSV rows, reading a row, saving it as a Sales_Record and formatting it back to CSV cells SHALL produce the same `sales_date`, upper-case `branch_code`, two-decimal `revenue` and `target_revenue`, and `order_count` (round-trip property).

### Requirement 19: Settings "Import template" tab [PoV Polish]

**User Story:** As a Manager, I want the template, the column rules and the branch list in one Settings place, so that I can prepare correct files.

#### Acceptance Criteria

1. THE Application SHALL register the Template_Tab in the S02 Settings tab extension point for the Owner and Manager roles.
2. IF a Staff user opens the Template_Tab, THEN THE Application SHALL respond with HTTP 403.
3. THE Template_Tab SHALL contain the template download action, the column specification table, the file limits and the Expected_Branches editor.
4. WHEN the Expected_Branches editor is saved, THE Application SHALL display a success toast through the S01 flash helper.
5. IF validation of the Expected_Branches form fails, THEN THE Application SHALL redisplay the form with field errors and the submitted values.
6. THE Template_Tab SHALL display the branches that appear in Active_Records but are missing from Expected_Branches as a notice, so that the user can add them.
7. THE Template_Tab SHALL be keyboard operable and every input SHALL have a visible label.

### Requirement 20: Errors, security and logging [PoV Core]

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

### Requirement 21: Scope boundaries [PoV Core]

**User Story:** As a project owner, I want S03 to stay inside Stage 1, so that the demonstration stays achievable.

#### Acceptance Criteria

1. THE Application SHALL accept only `.csv` files in S03.
2. THE Application SHALL provide no column-mapping screen, no Excel or other spreadsheet import, and no automatic or scheduled data sync.
3. THE Application SHALL provide no sales data entry form, edit form or delete action for individual Sales_Records.
4. THE Application SHALL provide no per-branch trading calendar, public holiday calendar or additional department in S03.
5. THE Application SHALL define S03 so that rule evaluation, metrics, dashboards, briefs, issues and activity logging remain owned by S04, S05 and S08.
6. THE Application SHALL verify S03 with the focused feature tests of Requirement 23, the fixture checks of Requirement 22 and the manual acceptance checks of Requirement 23, and SHALL use no browser automation, no property-based framework and no external AI call.

### Requirement 22: Demo fixtures, generator and demo commands [PoV Core]

**User Story:** As a presenter and a verifier, I want deterministic fictional CSV files and commands that load and check them through the real import path, so that the demonstration data is identical on every machine.

#### Acceptance Criteria

1. THE Fixture_Generator SHALL produce byte-identical CSV content on every run, using no random value, no clock read and no database read.
2. THE Fixture_Generator SHALL produce `history.csv` (Monday 2026-08-03 to Saturday 2026-09-19, weeks W1 to W7 with weekly totals 150,000.00, 152,000.00, 149,000.00, 153,000.00, 151,000.00, 154,000.00 and 155,000.00 MYR), `week8-decline.csv` (Monday 2026-09-21 to Saturday 2026-09-26, 124,000.00), `week8-with-errors.csv`, `week8-correction.csv`, `week9-interim.csv` (Monday 2026-09-28 to Saturday 2026-10-03, 138,000.00) and `week10-recovery.csv` (Monday 2026-10-05 to Saturday 2026-10-10, 150,000.00), exactly as in `docs/demo-scenario.md` section 2.
3. THE Fixture_Generator SHALL derive each daily total from the weekly total by dividing by six trading days and adding 1 MYR to each day from Monday until the remainder is used, and SHALL split each daily total D into PG equal to floor(D × 8 / 26), JB equal to floor(D × 6 / 26) and KL equal to D minus PG minus JB, in whole MYR written with two decimals.
4. THE Fixture_Generator SHALL write `target_revenue` as 12000.00 for KL, 8000.00 for PG and 6000.00 for JB on every row, SHALL write the header `sales_date,branch_code,revenue,target_revenue,order_count`, and SHALL leave `order_count` empty.
5. THE Fixture_Generator SHALL produce `history.csv` with 126 valid rows, `week8-decline.csv`, `week9-interim.csv` and `week10-recovery.csv` with 18 valid rows each, and `week8-correction.csv` with the 18 rows of `week8-decline.csv` where exactly two rows (KL Wednesday 2026-09-23 and PG Thursday 2026-09-24) carry revenue increased by 1,000.00 MYR, so that the corrected W8 total is 126,000.00.
6. THE Fixture_Generator SHALL produce `week8-with-errors.csv` with 20 data rows: 17 valid W8 rows (all W8 branch-days except JB Saturday 2026-09-26), a JB Saturday row with a non-numeric `target_revenue` (`invalid_number`), a row with a calendar-invalid `sales_date` (`invalid_date`), and a Sunday 2026-09-27 row with a negative `revenue` (`negative_value`), so that 17 rows are valid, 3 rows are invalid, and JB Saturday is missing.
7. THE Demo_Fixtures SHALL include `database/demo/expected.json` containing the demo calendar (timezone, trading days, cutoff time, lag), the Expected_Branches, and for each fixture: its file name, the clock date-time at which it is imported, the fixtures assumed to be imported before it, `totalRows`, `validRows`, `invalidRows`, the expected row error codes, the expected weekly totals by week start, the expected branch-day count by week start, and the expected Week_State by week start, as literal values that the generator does not produce.
8. WHEN `demo:import {fixture}` is run, THE Demo_Import_Command SHALL refuse with a non-zero exit code and a message naming `DEMO_MODE` unless `config('demo.mode')` is true.
9. WHEN `demo:import {fixture}` is run with a fixture name from the six files (with or without the `.csv` extension), THE Demo_Import_Command SHALL upload the file as the Owner of the Demo_Company through `CreateImportFromUpload`, confirm it through `ConfirmImport`, and process it through `ProcessImportJob` run inline on the `sync` queue connection, and SHALL insert no Sales_Record directly.
10. IF the upload is rejected, the Import does not reach `completed` or `completed_with_errors`, or the counters differ from the values of that fixture in `expected.json`, THEN THE Demo_Import_Command SHALL print the error codes and counters and SHALL exit non-zero.
11. WHEN `demo:check-fixtures` is run, THE Fixture_Checker SHALL verify for each fixture that the file on disk equals the Fixture_Generator output, that the real Import_Reader and Row_Validator report the expected valid and invalid counts and error codes at the fixture clock, that the weekly totals and branch-day counts of valid rows equal `expected.json`, and that Reporting_Coverage `weekStateForRows` returns the expected Week_State at the fixture clock after the rows of the fixtures listed in `after` are applied.
12. THE Fixture_Checker SHALL read no database row, write nothing, exit with code zero WHEN every check passes, and exit non-zero with one line per mismatch (fixture, check, expected, actual) otherwise.
13. WHERE the option `--write` is given, THE Fixture_Checker SHALL first rewrite the six CSV files from the Fixture_Generator and SHALL NOT modify `expected.json`.
14. THE Application SHALL keep `expected.json` extensible so that S04 adds evaluation expectations and S09 reuses the same files without changing S03 code.

### Requirement 23: Automated tests and acceptance verification [PoV Core]

**User Story:** As a verifier, I want a small set of deterministic tests and a defined acceptance checklist, so that import correctness can be shown the same way every time.

#### Acceptance Criteria

1. THE Application SHALL provide feature tests in `tests/Feature/Imports` that run with `composer test` on the S01 harness (PostgreSQL database `businesspulse_testing`, `RefreshDatabase`), use the Demo_Fixtures or small in-test CSV strings, call no external AI provider and use no browser automation.
2. THE test suite SHALL include a duplicate import test: importing the same file twice classifies every row of the second import as `unchanged`, shows the "File already imported" notice, and leaves the Active_Record count and the sum of Active_Record revenue unchanged.
3. THE test suite SHALL include a correction test: importing `week8-correction.csv` after `week8-decline.csv` reports 16 unchanged and 2 corrected rows, leaves 18 Active_Records, keeps the 2 replaced rows with `is_active` false and `superseded_by_import_id` set, and leaves the replaced rows' values readable.
4. THE test suite SHALL include a duplicate-in-file test: two rows with the same Record_Identity are both invalid with `duplicate_in_file`, and the counters satisfy the invariants of Requirement 7.
5. THE test suite SHALL include a reporting cutoff test with `history.csv` imported: at the clock 2026-09-21 09:00 no W8 day is a Due_Day, W8 is `open`, `missingDueBranchDays` for W8 returns no gap and Freshness is `current`; at the clock 2026-09-28 09:00 Saturday 2026-09-26 is a Due_Day, Monday 2026-09-28 is not, W8 is `closed` and `absent`, and Freshness is `behind` with 18 Overdue_Branch_Days.
6. THE test suite SHALL include an incomplete coverage test: after `history.csv` and `week8-with-errors.csv` at the clock 2026-09-28 09:00, W8 is `closed` and `incomplete` with exactly one gap (JB, 2026-09-26) of kind `inside_period`, and no revenue sum includes a zero for that day.
7. THE test suite SHALL include a branch configuration test: a row with a branch code outside Expected_Branches is invalid with `unknown_branch`, and an upload into a department with empty Expected_Branches is rejected with `no_expected_branches` with no `imports` row and no stored file.
8. THE test suite SHALL include an authorization test: a Manager of another department receives 404 and a Staff user receives 403 for the Import_Pages routes, confirm and cancel actions, and the Owner and a Manager of the own department are permitted.
9. [PoV Polish] WHERE the source file download exists, THE test suite SHALL include a test that a Staff user receives 403 and a Manager of another department receives 404.
10. THE test suite SHALL include a test that `demo:check-fixtures` exits with code zero.
11. THE manual acceptance SHALL include: `demo:import history` at the clock 2026-09-21 followed by a UI upload of `week8-decline.csv` at the clock 2026-09-28 showing W8 as closed and complete in the preview and `ImportCompleted` dispatched on confirm; a double-submitted Confirm producing one set of Sales_Record changes; a stored file deleted before confirmation failing the Import with a sanitized message and no Sales_Record change; a generated 6,000-row file completing with the counter invariants satisfied; checks at 360, 768 and 1280 px width; and a keyboard-only upload and confirm run.
12. THE verification SHALL run `migrate:fresh` and a rollback of the S03 migrations on a scratch database, `composer test`, `composer pint-test`, and `npm run verify`.
