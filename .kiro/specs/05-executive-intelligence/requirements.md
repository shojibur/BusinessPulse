# Requirements Document

## Introduction

S05 is the Executive Dashboard and AI Briefing spec of BusinessPulse. It gives the Owner and Managers a "Today" dashboard of deterministic business metrics and an on-demand executive Brief of up to five prioritized findings, optionally read aloud through generated voice audio. Deterministic code is authoritative: every figure comes from persisted data, S03 `ReportingCoverage` and S04 evidence. AI supplies only the title and explanation wording of each finding, and never calculates, forecasts or infers causes.

This spec follows `docs/stage-1-refinements.md` (authoritative): R1 tiers, R2 calendar and freshness, R5 focused tests, R6 queue and retry layering, R7 dependency simplification.

### Priority tiers (R1)

- **[PoV Core]**: needed for the 22-step demonstration. Dashboard KPIs (revenue, change, target achievement, open and high-priority Issue counts; no task KPIs), the revenue-vs-target chart, the freshness banner, Brief generate, view and fallback, voice generation and the audio player, the Brief history list, the events `BriefGenerated` and `BriefFailed`, and one small feature test group.
- **[PoV Polish]**: Stage 1 scope built after the Core slice works. The weekly-change chart, regenerate audio, explicit HTTP range-request support, the resolved-in-30-days KPI, the priority actions list, the Issue detail brief links, and the events `BriefRequested` and `BriefAudioGenerated`.
- Requirement criteria that are Polish carry the marker "[PoV Polish]" at the start of the criterion. Criteria without a marker are PoV Core.

### What S05 introduces

| Area | Items |
|---|---|
| Tables | `briefs` and `brief_items` (columns per `docs/data-model.md`) |
| AI services in `app/Services/AI` | `OpenAiClient` (one HTTP attempt per call, `chat` and `speech`, reused later by S06), `BriefGenerator`, `SpeechService`, `NumberAllowListChecker`, `BriefSummaryComposer`, `BriefPresenter` |
| Jobs (queue `ai`) | `GenerateBriefJob`, `GenerateBriefAudioJob` |
| Dashboard service | `DashboardMetrics` (deterministic assembly from S03 and S04 services), which S07 later extends with task KPIs and the Staff summary |
| Pages and endpoints | Today page, Briefs history, Brief detail, generate, retry, audio generate, authorized audio streaming |
| Issue detail extension | A `briefs` prop appended to `IssueController::show` and a Vue component on the Issue detail page [PoV Polish] |
| Events | Core: `BriefGenerated`, `BriefFailed`. Polish: `BriefRequested`, `BriefAudioGenerated` |
| Configuration | `config/ai.php` provider settings (extends the S01 skeleton) and the `.env.example` entries |
| Feature tests | One test group, `ExecutiveIntelligenceTest`, with no external provider call |

S05 introduces no `TaskMetricsReader`, no null reader and no panel registry (R7).

### Dependencies

- S01: `AppShell`, `PageHeader`, `KpiCard`, `EChart`, `EmptyState`, `ErrorState`, `StatusBadge`, `SeverityBadge`, toast helper, `BusinessClock::today()` and `BusinessClock::now()`, `config/ai.php` skeleton, database queue with the `ai` queue, private `local` disk, test harness. S05 builds `InsufficientDataNotice` and `AudioPlayer` in this spec, because S05 is their first consumer (R1).
- S02: `Company`, `Department`, `User`, roles, policies, department scoping, `departments.settings.expected_branches`.
- S03: `ReportingCoverage` (`latestClosedWeek`, `weekState`, `weekStates`, `freshness`, `latestSalesDate`), `WeekState`, `Freshness`, `sales_records`.
- S04: `MetricCalculator`, `IssueEvidence` read model, `Issue`, `RuleEvaluation`, `IssueController::show`, `Issues/Show.vue`, the `rule_evaluations` table.
- Consumers: S07 (extends `DashboardMetrics` with open and overdue task KPIs and the Staff task summary), S08 (records activity from the S05 Core events), S06 (reuses `OpenAiClient`).

### Assumptions

- A1. Stage 1 has one company with one Sales department, so a company-wide brief and a department-scoped brief cover the same Issues. A Manager's brief is built only from Issues of the Manager's department, and is stored with the company's `company_id` because `briefs` has no `department_id` column. Both Owner and Manager can view every brief of the company. Dashboard sales figures are computed for the Sales department.
- A2. Freshness is `behind` or `current` exactly as `ReportingCoverage::freshness` reports it from the company calendar (trading days, cutoff time, cutoff lag). The earlier 7-day stale threshold is removed.
- A3. A week is the ISO Monday-to-Sunday week. The Latest_Closed_Week is the week returned by `ReportingCoverage::latestClosedWeek`. S05 does not search backward for an older complete week, so the dashboard and S04 evaluate the same week.
- A4. "Resolved issues" on the dashboard [PoV Polish] counts Issues resolved within the 30 days before `BusinessClock::today()`.
- A5. Brief ranking uses Open_Issues only (status other than `resolved`).
- A6. The Brief uses the Issues and evaluations that already exist. S05 never triggers rule evaluation. The freshness banner and the Brief state when intelligence was last refreshed.
- A7. S05 does not translate text. Brief and audio language follow the English text produced under the configured models.
- A8. Provider HTTP timeouts are 30 seconds for text and 60 seconds for speech. Job timeouts and retries follow R6 (Requirement 25).
- A9. Staff users have no dashboard content in S05. S07 adds the Staff task summary.
- A10. Automated verification follows R5: Laravel feature tests on the PostgreSQL scratch database, no browser automation, no property-based framework, and no external AI call. S05 contributes one small test group. Everything else is verified manually against the demo fixtures.
- A11. Scheduled briefs and delivery by email or WhatsApp are Stage 2 and are excluded (`scope-control.md` steering).

## Glossary

- **Application**: The BusinessPulse Laravel and Inertia application.
- **Dashboard**: The Today page, the first page an authenticated user sees.
- **Dashboard_Metrics**: Service that assembles every Dashboard figure from persisted data and deterministic services.
- **Latest_Closed_Week**: The ISO week returned by `ReportingCoverage::latestClosedWeek` for the department (assumption A3).
- **Closed_Complete_Week**: A Latest_Closed_Week whose completeness is `complete`.
- **Comparison_Week**: The ISO week immediately before the Latest_Closed_Week.
- **Latest_Sales_Date**: Latest `sales_date` among Active_Records of a department, from `ReportingCoverage::latestSalesDate`.
- **Freshness**: The `ReportingCoverage::freshness` result with `latestSalesDate`, `lastDueDate`, state `current` or `behind`, `overdueBranchDays`, `overdueFrom` and `overdueTo`.
- **Behind**: A Freshness whose state is `behind`.
- **Last_Evaluation_Time**: The most recent `evaluated_at` among the `rule_evaluations` rows of the department, or none when no evaluation exists.
- **Active_Record**: A `sales_records` row with `is_active=true`.
- **Open_Issue**: An S04 Issue whose status is not `resolved`.
- **High_Priority_Issue**: An Open_Issue whose severity is `high`.
- **Brief**: A row in `briefs`. Statuses: `queued`, `generating`, `completed`, `failed`.
- **Brief_Item**: A row in `brief_items`, one finding of a Brief (position 1 to 5).
- **Finding**: The user-visible form of a Brief_Item.
- **Brief_Generator**: Service `BriefGenerator` that selects findings, requests wording from the provider, validates it, and persists the Brief.
- **Brief_Job**: The queued job `GenerateBriefJob`.
- **Audio_Job**: The queued job `GenerateBriefAudioJob`.
- **Speech_Service**: Service `SpeechService` that converts Brief text to audio through the provider.
- **OpenAi_Client**: The shared thin HTTP client in `app/Services/AI`, built on the Laravel `Http` facade, making one HTTP attempt per call.
- **Recoverable_Provider_Exception**: `RecoverableProviderException`, thrown by the OpenAi_Client for a timeout, a connection failure, an HTTP 429 response or an HTTP 5xx response.
- **Provider_Exception**: `ProviderException`, thrown by the OpenAi_Client for every other failure.
- **Provider**: The OpenAI-compatible HTTP API configured in `config/ai.php`.
- **Evidence_Set**: The set of decimal strings, dates and integers present in a Brief_Item's `evidence` JSON.
- **Number_Check**: The server-side allow-list check, implemented by `NumberAllowListChecker`, that every number in AI-written title and explanation text appears in the Evidence_Set.
- **Fallback_Brief**: A Brief with `source = fallback`, built from deterministic text without the Provider.
- **Coverage_Status**: Brief field with values `sufficient`, `partial` or `stale`. `sufficient`: the Latest_Closed_Week is complete and Freshness is current. `partial`: the Latest_Closed_Week is incomplete or absent and Freshness is current. `stale`: Freshness is Behind, or no sales data exists.
- **Limitation_Text**: Brief text that states explicitly that data is incomplete, absent or behind.
- **Audio_Status**: Brief field with values `none`, `queued`, `generating`, `ready` or `failed`.
- **Owner / Manager / Staff**: Roles defined by S02.
- **Actor**: The user performing an action.

## Requirements

### Requirement 1: Dashboard metric set

**User Story:** As an Owner, I want one page that shows the current business position, so that I can see what matters without opening several reports.

#### Acceptance Criteria

1. WHEN an Owner or Manager opens the Dashboard, THE Dashboard_Metrics SHALL return revenue for the Latest_Closed_Week, revenue change against the Comparison_Week, target achievement for the Latest_Closed_Week, Open_Issue count, High_Priority_Issue count, Freshness, and the latest completed Brief.
2. THE Dashboard_Metrics SHALL compute revenue, revenue change and target achievement by calling `MetricCalculator` and `ReportingCoverage`, with no duplicate calculation logic in S05.
3. THE Dashboard_Metrics SHALL return money values and percentages as decimal strings, with percentages shown at 2 decimal places using the `MetricCalculator` display formatter.
4. THE Application SHALL render server-provided metric values in Vue components without computing authoritative metrics in the browser.
5. THE Dashboard_Metrics SHALL count Open_Issues and High_Priority_Issues from the `issues` table within the Actor's department scope.
6. [PoV Polish] WHEN the Dashboard_Metrics counts resolved Issues, THE Dashboard_Metrics SHALL include only Issues whose `resolved_at` falls within the 30 days before `BusinessClock::today()`.
7. THE Dashboard SHALL display no open task count and no overdue task count in S05, and S07 SHALL add those KPIs by extending Dashboard_Metrics.
8. THE Dashboard SHALL label the period of every revenue, change and target figure with its start and end dates.
9. THE Dashboard_Metrics SHALL use `BusinessClock::today()` and `BusinessClock::now()` as the business date and time for every date comparison.

### Requirement 2: Latest closed week and insufficient data

**User Story:** As an Owner, I want partial or open weeks never presented as complete results, so that I do not make decisions on misleading numbers.

#### Acceptance Criteria

1. THE Dashboard_Metrics SHALL take the Latest_Closed_Week from `ReportingCoverage::latestClosedWeek` and SHALL take the Comparison_Week state from `ReportingCoverage::weekState` for the preceding ISO week.
2. IF the Latest_Closed_Week completeness is `incomplete`, THEN THE Dashboard_Metrics SHALL return revenue, revenue change and target achievement as insufficient data with the reason `incomplete_period`.
3. IF the Latest_Closed_Week completeness is `absent`, THEN THE Dashboard_Metrics SHALL return revenue, revenue change and target achievement as insufficient data with the reason `period_absent`.
4. IF the Comparison_Week completeness is not `complete`, THEN THE Dashboard_Metrics SHALL return revenue change as insufficient data with the reason `comparison_week_incomplete`.
5. IF the comparison revenue is zero, THEN THE Dashboard_Metrics SHALL return revenue change as insufficient data with the reason `zero_denominator`.
6. IF the weekly target is zero, THEN THE Dashboard_Metrics SHALL return target achievement as insufficient data with the reason `zero_denominator`.
7. THE Dashboard_Metrics SHALL NOT substitute zero for any missing branch-day.
8. WHEN a metric is insufficient data, THE Dashboard SHALL display the `InsufficientDataNotice` in a neutral style that names the missing data, instead of a numeric value.
9. THE Dashboard_Metrics SHALL NOT use the open current week for any revenue, change or target figure.

### Requirement 3: Data freshness and coverage display

**User Story:** As an Owner, I want to see how recent and how complete the sales data is and whether intelligence reflects it, so that I do not mistake old data for current performance.

#### Acceptance Criteria

1. THE Dashboard SHALL display the Latest_Sales_Date from `ReportingCoverage::freshness`.
2. IF no Latest_Sales_Date exists, THEN THE Dashboard SHALL display "No sales data yet" and an action link to the Data Imports page for Owner and Manager.
3. WHILE the Freshness is Behind and a Latest_Sales_Date exists, THE Dashboard SHALL display a freshness banner of the form "Sales for {overdueFrom}–{overdueTo} not yet received: {overdueBranchDays} branch-days overdue", using the `overdueFrom`, `overdueTo` and `overdueBranchDays` values of the Freshness.
4. WHILE the Freshness is Behind, THE Dashboard SHALL state in the freshness banner that intelligence has not been refreshed since the Last_Evaluation_Time, or that no evaluation has run yet when no Last_Evaluation_Time exists.
5. WHILE the Latest_Closed_Week completeness is `incomplete`, THE Dashboard SHALL display a coverage notice that states the number of missing branch-days from the `gaps` of the Latest_Closed_Week.
6. THE Application SHALL NOT create an Issue, an alert or a notification from Freshness in S05.

### Requirement 4: Sales charts

**User Story:** As an Owner, I want to see weekly revenue against target and the weekly change trend, so that I can see direction at a glance.

#### Acceptance Criteria

1. THE Dashboard SHALL display a chart of weekly revenue against weekly target for up to the 8 most recent closed ISO weeks that contain Active_Records, in chronological order.
2. [PoV Polish] THE Dashboard SHALL display a chart of weekly change percentage for the same weeks, where a week has a plotted value only when that week and its preceding week are both closed and complete.
3. WHEN a closed week is not complete, THE Dashboard SHALL show that week as a gap with the label "Incomplete", not as a zero value.
4. THE Dashboard_Metrics SHALL supply the chart series as decimal strings computed by `MetricCalculator`, with week states from `ReportingCoverage::weekStates`.
5. THE Dashboard SHALL render each chart with the `EChart` component and SHALL provide a text table alternative for each chart.
6. THE Dashboard SHALL render each chart with a minimum height of 240 pixels and resize the chart with its container.
7. [PoV Polish] IF fewer than 2 closed weeks of Active_Records exist, THEN THE Dashboard SHALL display the `InsufficientDataNotice` in place of the weekly change chart.
8. IF no closed week with Active_Records exists, THEN THE Dashboard SHALL display the `InsufficientDataNotice` in place of the revenue-vs-target chart.

### Requirement 5: Priority actions and navigation

**User Story:** As an Owner, I want a path from every figure to its source and a short list of the Issues that need attention, so that I can act and verify.

#### Acceptance Criteria

1. [PoV Polish] THE Dashboard SHALL display a priority actions list of up to 5 Open_Issues ordered by severity descending, then `last_detected_at` descending.
2. [PoV Polish] WHEN a priority action row is displayed, THE Dashboard SHALL show the Issue title, `SeverityBadge`, `StatusBadge` and the reporting period, and SHALL link to the Issue detail page.
3. WHEN the Open_Issue count is zero, THE Dashboard SHALL display an `EmptyState` that states no open Issues exist.
4. THE Dashboard SHALL link the Open_Issue count and High_Priority_Issue count to the Issues list with the matching filter applied.
5. THE Dashboard SHALL link the revenue, change and target achievement figures to the Data Imports page for the supporting sales data.
6. THE Dashboard SHALL link the latest Brief card to the Brief detail page.

### Requirement 6: Dashboard states and responsiveness

**User Story:** As a user, I want the Dashboard to stay understandable while loading, empty or failing, so that I always know what the page is doing.

#### Acceptance Criteria

1. WHILE Dashboard data reloads, THE Dashboard SHALL display skeletons that match the layout of the KPI row, the charts and the lists.
2. IF the Dashboard data request fails, THEN THE Dashboard SHALL display an `ErrorState` with a retry action.
3. THE Dashboard SHALL arrange the KPI cards in 4 columns at 1280 pixels, 2 columns at 768 pixels, and 1 column at 360 pixels.
4. THE Dashboard SHALL provide a visible keyboard focus indicator and keyboard access for every link and control.
5. THE Dashboard SHALL show severity through an icon and a text label in addition to color.

### Requirement 7: Role scoping and Staff view

**User Story:** As an Owner, I want each role to see only what that role is allowed to see, so that data stays within its department.

#### Acceptance Criteria

1. WHEN an Owner opens the Dashboard, THE Dashboard_Metrics SHALL compute every figure across the company.
2. WHEN a Manager opens the Dashboard, THE Dashboard_Metrics SHALL compute every figure only for the Manager's department.
3. WHEN a Staff user opens the Dashboard, THE Dashboard SHALL display an `EmptyState` that states no dashboard content is available for the Staff role yet.
4. WHEN a Staff user opens the Dashboard, THE Application SHALL NOT include sales figures, Issue data, Brief data or Freshness in the response payload.

### Requirement 8: Brief generation request

**User Story:** As an Owner or Manager, I want to request a brief on demand, so that I receive a current summary when I need one.

#### Acceptance Criteria

1. WHEN an Owner or Manager requests a Brief, THE Application SHALL create a Brief with status `queued`, `requested_by` set to the Actor, `as_of_date` set from `BusinessClock::today()`, and dispatch the Brief_Job after the creating transaction commits.
2. [PoV Polish] WHEN a Brief is created, THE Application SHALL dispatch the `BriefRequested` event after the creating transaction commits.
3. IF a Brief for the company has status `queued` or `generating`, THEN THE Application SHALL reject a new generation request with a message that a Brief is already in progress and SHALL create no new Brief.
4. THE Brief_Job SHALL implement `ShouldBeUnique` with the company id as the unique key.
5. THE Application SHALL reject a generation request from a Staff user with a 403 response.
6. THE Application SHALL throttle Brief generation requests per user.
7. WHEN the Brief_Job starts, THE Brief_Job SHALL set the Brief status to `generating`, and SHALL exit without changes when the Brief status is already `completed` or `failed`.
8. THE Brief page SHALL poll the Brief status every 3 seconds WHILE the status is `queued` or `generating`, and SHALL display the status with a `StatusBadge` and an `aria-live` region.
9. THE Application SHALL set `period_start` and `period_end` of the Brief to the Monday and Sunday of the Latest_Closed_Week, or to null when no Latest_Sales_Date exists.

### Requirement 9: Finding selection and ranking

**User Story:** As an Owner, I want the brief to cover the most important issues first, so that I read the highest priority items first.

#### Acceptance Criteria

1. THE Brief_Generator SHALL select Open_Issues within the Actor's department scope as candidate findings.
2. THE Brief_Generator SHALL rank candidate findings by severity descending, then by `last_detected_at` descending, then by Issue id ascending.
3. THE Brief_Generator SHALL keep the first 5 ranked candidates as Brief_Items, with `position` from 1 to 5 in rank order.
4. THE Brief_Generator SHALL build the `evidence` of each Brief_Item from `IssueEvidence::forIssue`, and SHALL read no `sales_records` rows directly.
5. THE Brief_Generator SHALL set `issue_id` and `rule_evaluation_id` of each Brief_Item to the Issue and to its latest breached evaluation, and SHALL set `severity` and `period_label` from the Issue.
6. THE Brief_Generator SHALL set `source_ref` of each Brief_Item to a reference string that identifies the Issue id and the evaluation id.
7. THE Brief_Generator SHALL copy the Brief_Item `evidence` at generation time, so that a later change to the Issue does not change a completed Brief.
8. THE Brief_Generator SHALL create no Brief_Item for a Rule_Evaluation whose result is `not_breached` or `insufficient_data`.

### Requirement 10: Brief content and authoritative figures

**User Story:** As an Owner, I want every number in a brief to match the stored evidence, so that I can trust and verify what I read.

#### Acceptance Criteria

1. THE Brief_Generator SHALL give each Finding a concise title, a severity, a reporting period, verified supporting figures, a source reference, and a short explanation.
2. THE Application SHALL render the supporting figures of each Finding from the structured `evidence` field and SHALL NOT render them from AI-written text.
3. THE Brief_Generator SHALL set the Brief `headline` and `summary_text` from deterministic text built by `BriefSummaryComposer` from the Brief_Items and the Coverage_Status.
4. THE Brief_Generator SHALL set `coverage_status` to `stale` WHEN the Freshness is Behind or no Latest_Sales_Date exists, to `partial` WHEN the Freshness is current and the Latest_Closed_Week completeness is `incomplete` or `absent`, and to `sufficient` otherwise.
5. THE Brief_Generator SHALL store in `input_snapshot` the verified metrics and issue evidence sent to the Provider, together with the Latest_Sales_Date and the Last_Evaluation_Time, without any personal contact data.
6. THE Application SHALL display each Finding with links to its Issue detail page and to its source evaluation.
7. THE Application SHALL label the Brief with "AI generated" WHEN `source = ai`, and with "AI unavailable" WHEN `source = fallback`.
8. WHEN the Brief is displayed, THE Application SHALL display the `as_of_date`, `generated_at`, and the Brief period.

### Requirement 11: AI wording and validation

**User Story:** As an Owner, I want AI limited to wording, so that it cannot invent figures, causes or forecasts.

#### Acceptance Criteria

1. THE Brief_Generator SHALL request from the Provider a JSON response that conforms to a defined JSON schema, which contains a `title` and an `explanation` for each supplied Brief_Item identified by its position.
2. THE Brief_Generator SHALL send to the Provider an instruction that prohibits invented figures, causes, forecasts and recommendations that are not in the supplied evidence.
3. THE Brief_Generator SHALL validate the Provider response with the Laravel Validator before persisting any Brief_Item text, and SHALL reject a response that has a missing position, a duplicate position, an unknown position, an empty title, or an empty explanation.
4. THE Brief_Generator SHALL reject a title longer than 120 characters and an explanation longer than 400 characters.
5. THE Number_Check SHALL reject a title or explanation WHEN any number in the text does not appear in the Evidence_Set of the same Brief_Item.
6. IF the Provider response fails validation or the Number_Check, THEN THE Brief_Generator SHALL send one corrective request that states the failed constraint, within the same job attempt.
7. IF the corrective request also fails validation or the Number_Check, THEN THE Brief_Generator SHALL build a Fallback_Brief instead of persisting any AI text.
8. THE Brief_Generator SHALL persist a Brief and all of its Brief_Items in one database transaction, and SHALL persist no partial AI output.
9. THE Brief_Generator SHALL pass issue evidence to the Provider as delimited data and SHALL state in the instruction that the data is untrusted.
10. THE Brief_Generator SHALL NOT allow AI output to change an Issue, a rule, a task or a notification.

### Requirement 12: Brief outcomes and limitations

**User Story:** As an Owner, I want the brief to tell me plainly when all is normal and when the data is not good enough, so that I do not assume the wrong thing.

#### Acceptance Criteria

1. WHEN no Open_Issue exists in scope and the Coverage_Status is `sufficient`, THE Brief_Generator SHALL create a normal-status Brief with zero Brief_Items and a headline that states no active issues were found.
2. WHILE the Coverage_Status is `partial`, THE Brief_Generator SHALL set `summary_text` to include Limitation_Text that states the number of missing branch-days of the Latest_Closed_Week, or that the Latest_Closed_Week has no data, and that results cover incomplete data.
3. WHILE the Coverage_Status is `stale` and a Latest_Sales_Date exists, THE Brief_Generator SHALL set `summary_text` to include Limitation_Text that states the Latest_Sales_Date, the overdue period "{overdueFrom}–{overdueTo}", the number of overdue branch-days, and the Last_Evaluation_Time or that no evaluation has run.
4. IF no Latest_Sales_Date exists, THEN THE Brief_Generator SHALL create a Brief with zero Brief_Items, `coverage_status` set to `stale`, and Limitation_Text that states no sales data exists.
5. THE Brief_Generator SHALL NOT call the Provider WHEN the Brief has zero Brief_Items, and SHALL set `source` to `fallback` in that case.
6. WHILE `source = fallback` and the Brief has zero Brief_Items, THE Application SHALL NOT display the "AI unavailable" label, and SHALL display "Generated from stored data".
7. THE Application SHALL display Limitation_Text in an `Alert` above the Findings.

### Requirement 13: Deterministic fallback

**User Story:** As an Owner, I want a usable brief even when the AI provider is down, so that I am never left without a summary.

#### Acceptance Criteria

1. WHEN text generation ultimately fails, namely a Recoverable_Provider_Exception on the final job attempt, a Provider_Exception, or a failed corrective request, THE Brief_Generator SHALL build a Fallback_Brief with `source = fallback`.
2. THE Brief_Generator SHALL set the title of each Fallback_Brief item to the Issue title, and the explanation to a template sentence built only from the evidence values.
3. THE Brief_Generator SHALL complete a Fallback_Brief with status `completed` and SHALL record a sanitized reason in `error_message`.
4. THE Application SHALL display the label "AI unavailable" on a Fallback_Brief that has Brief_Items.
5. WHERE `OPENAI_API_KEY` or the text model is not configured, THE Brief_Generator SHALL build a Fallback_Brief without calling the Provider.
6. WHEN a Brief is completed, THE Application SHALL dispatch `BriefGenerated` after the transaction commits, with the Brief id, source and Brief_Item count.

### Requirement 14: Brief job failure and retry

**User Story:** As an Owner, I want clear status and a retry when generation fails, so that I can recover without developer help.

#### Acceptance Criteria

1. IF the Brief_Job fails for a reason other than a Provider failure, THEN THE Brief_Job SHALL set the Brief status to `failed`, store a sanitized `error_message`, and dispatch `BriefFailed` after commit.
2. THE Brief_Job SHALL implement a `failed()` handler that sets the status to `failed` and dispatches `BriefFailed` WHEN the job exhausts its attempts.
3. WHEN a Brief has status `failed`, THE Application SHALL display the sanitized reason and a Retry action for Owner and Manager.
4. WHEN the Actor selects Retry, THE Application SHALL reset the same Brief to `queued`, clear `error_message`, and dispatch the Brief_Job, subject to the in-progress check of Requirement 8.
5. THE Brief_Job SHALL be idempotent, so that dispatching the job twice for one Brief yields one set of Brief_Items.
6. THE Application SHALL keep a `failed` Brief visible in the Brief history.

### Requirement 15: Provider client

**User Story:** As a developer, I want one thin, safe provider client, so that every AI call has the same timeouts and error handling and retries live in one layer.

#### Acceptance Criteria

1. THE OpenAi_Client SHALL call the Provider with the Laravel `Http` facade and SHALL NOT use an AI SDK or framework.
2. THE OpenAi_Client SHALL read `AI_BASE_URL`, `AI_TEXT_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE` and `OPENAI_API_KEY` through `config/ai.php`, and SHALL contain no hardcoded model name.
3. THE OpenAi_Client SHALL apply a timeout of 30 seconds to `chat` requests and 60 seconds to `speech` requests.
4. THE OpenAi_Client SHALL make exactly one HTTP attempt per call, and SHALL contain no retry loop and no sleep.
5. IF a Provider request ends with a timeout, a connection failure, an HTTP 429 response or an HTTP 5xx response, THEN THE OpenAi_Client SHALL throw a Recoverable_Provider_Exception.
6. IF a Provider request ends with any other failure, including an HTTP 4xx response other than 429 or an unreadable response body, THEN THE OpenAi_Client SHALL throw a Provider_Exception.
7. THE OpenAi_Client SHALL convert every failure into an exception with a code and a sanitized message that contains no API key, no request body, no response body and no evidence values.
8. THE Application SHALL NOT write the API key to logs, Inertia props, browser responses, `input_snapshot` or `error_message`.
9. THE Application SHALL list every AI environment variable in `.env.example` without a real value.
10. THE OpenAi_Client SHALL log only the request type, the Brief id, the status code and the duration.
11. THE OpenAi_Client SHALL expose `chat` and `speech` in S05, and S06 SHALL reuse the same client.

### Requirement 16: Provider data minimization

**User Story:** As an Owner, I want to know exactly what leaves the system, so that I can judge privacy risk.

#### Acceptance Criteria

1. WHEN the Brief_Generator calls the Provider, THE Brief_Generator SHALL send only the structured verified metrics, the issue evidence of the selected Brief_Items, the Coverage_Status and the response schema.
2. THE Brief_Generator SHALL NOT send user emails, user names, passwords, session data, CSV file content, file paths or API keys to the Provider.
3. WHEN the Speech_Service calls the Provider, THE Speech_Service SHALL send only the Brief text that the Brief page displays.
4. THE Application SHALL store the Brief text, the `input_snapshot`, the sanitized `error_message`, the model identifier, and the audio file locally.
5. THE Application SHALL document what is sent to the Provider and what is stored in `docs/architecture.md`, and SHALL state that fictional data is used until the client authorizes real data.
6. THE Application SHALL display a notice on the Brief detail page that identifies the Provider-generated parts of the Brief.

### Requirement 17: Brief history and detail

**User Story:** As an Owner, I want to review past briefs and open the latest one, so that I can compare what was reported over time.

#### Acceptance Criteria

1. THE Application SHALL display a Briefs page that lists Briefs in `created_at` descending order with status, source, headline, as-of date, requester and Audio_Status.
2. THE Briefs page SHALL paginate the list at 15 Briefs per page.
3. WHEN no Brief exists, THE Briefs page SHALL display an `EmptyState` with a Generate Brief action for Owner and Manager.
4. WHEN the Actor opens a Brief, THE Application SHALL display the headline, summary, Limitation_Text, up to 5 Findings, labels and Audio player.
5. THE Dashboard SHALL display the latest completed Brief as a card with the headline, `generated_at`, source label and a link to the Brief detail page.
6. WHEN no completed Brief exists, THE Dashboard SHALL display an `EmptyState` on the Brief card with a Generate Brief action for Owner and Manager.
7. THE Application SHALL return a 404 response for a Brief id that does not belong to the Actor's company.
8. THE Application SHALL return a 403 response to a Staff user for every Briefs route.

### Requirement 18: Voice generation

**User Story:** As an Owner, I want to listen to the brief, so that I can take it in while I am busy.

#### Acceptance Criteria

1. WHEN an Owner or Manager requests audio for a completed Brief with Audio_Status `none` or `failed`, THE Application SHALL set Audio_Status to `queued` and dispatch the Audio_Job after commit.
2. THE Audio_Job SHALL set Audio_Status to `generating` when it starts, and SHALL exit without changes WHEN Audio_Status is `ready` and the request is not a regeneration.
3. THE Speech_Service SHALL build the spoken text from the Brief headline, summary text and the title and explanation of each Brief_Item, and SHALL include the supporting figures from the structured evidence formatted by the server.
4. THE Speech_Service SHALL use the model and voice from `config/ai.php`.
5. WHEN the Provider returns audio, THE Speech_Service SHALL store the audio file on the private `local` disk under a random server-generated filename, and SHALL set `audio_path`, `audio_model`, `audio_voice` and Audio_Status `ready`.
6. [PoV Polish] WHEN audio is stored, THE Application SHALL dispatch `BriefAudioGenerated` after the transaction commits.
7. THE Audio_Job SHALL implement `ShouldBeUnique` with the Brief id as the unique key.
8. [PoV Polish] WHEN Audio_Status is `ready`, THE Application SHALL display a Regenerate action and SHALL regenerate audio only WHEN the Actor selects it.
9. [PoV Polish] WHEN the Actor selects Regenerate, THE Audio_Job SHALL write the new file first, and SHALL delete the previous audio file only after the new file is stored and `audio_path` is updated.
10. IF the Provider fails on the final job attempt or throws a Provider_Exception, THEN THE Audio_Job SHALL set Audio_Status to `failed`, store a sanitized error, and display a manual Retry action for Owner and Manager.
11. [PoV Polish] IF the Provider fails during a Regenerate and a previous audio file exists, THEN THE Audio_Job SHALL keep the previous audio file and `audio_path`, and SHALL set Audio_Status to `ready` with a visible message that regeneration failed.
12. THE Audio_Job SHALL implement a `failed()` handler that sets Audio_Status to `failed` WHEN no previous audio file exists.
13. IF a Brief has status other than `completed`, THEN THE Application SHALL reject an audio request with a validation error.
14. WHERE the speech model setting is empty, THE Application SHALL hide the audio actions and display "Voice is not configured".

### Requirement 19: Audio streaming and playback

**User Story:** As an Owner, I want to play the audio in the browser through a protected link, so that only authorized users can hear the brief.

#### Acceptance Criteria

1. THE Application SHALL stream Brief audio through an authenticated route that authorizes the Actor against the Brief, and SHALL expose no public URL to the audio file.
2. THE Application SHALL return a 404 response WHEN the Brief does not belong to the Actor's company or has no stored audio file.
3. THE Application SHALL return a 403 response to a Staff user for the audio route.
4. [PoV Polish] THE Application SHALL support HTTP range requests on the audio route so that the browser can seek.
5. WHEN Audio_Status is `ready`, THE Brief detail page SHALL display the HTML audio player with play and pause controls and keyboard access.
6. WHILE Audio_Status is `queued` or `generating`, THE Brief detail page SHALL poll every 3 seconds and display a progress indicator with an `aria-live` status message.
7. THE Brief detail page SHALL provide a text version of the Brief next to the audio player.
8. THE Application SHALL throttle the audio route and the audio request route per user.

### Requirement 20: Issue detail brief links

**User Story:** As a Manager, I want to see which briefs mention an issue from the issue page, so that I can trace how it was reported.

#### Acceptance Criteria

1. [PoV Polish] THE Application SHALL append a `briefs` prop to `IssueController::show` and SHALL add a Vue component for that prop to `Issues/Show.vue`, with no registry and no provider interface.
2. [PoV Polish] WHEN the Issue detail page is displayed, THE `briefs` prop SHALL contain the most recent 5 completed Briefs that contain a Brief_Item for that Issue, each with Brief id, headline, `generated_at`, source and a link.
3. [PoV Polish] IF no completed Brief contains a Brief_Item for the Issue, THEN THE Issue detail page SHALL display a short empty text in the brief panel.
4. [PoV Polish] THE `briefs` prop SHALL contain only Briefs of the Actor's company and SHALL be null for a Staff user.

### Requirement 21: Events for activity recording

**User Story:** As an Owner, I want briefing actions traceable in the activity record, so that I can see what was generated and what failed.

#### Acceptance Criteria

1. THE Application SHALL define the events `BriefGenerated` and `BriefFailed` in `App\Events\Briefing` as PoV Core events.
2. [PoV Polish] THE Application SHALL define the events `BriefRequested` and `BriefAudioGenerated` in `App\Events\Briefing`.
3. THE Application SHALL include in each event the Brief id, the company id, `actorUserId` (null for the system) and `occurredAt`.
4. THE Application SHALL dispatch each event only after the producing transaction commits.
5. THE Application SHALL include in `BriefGenerated` the source and Brief_Item count, and in `BriefFailed` the sanitized error code.
6. THE Application SHALL NOT write `activity_logs` rows in S05.
7. THE Application SHALL NOT include figures, evidence values or text of a Brief in any event payload.

### Requirement 22: Authorization and security

**User Story:** As an Owner, I want briefing and dashboard access controlled by role, so that sensitive figures stay within permitted users.

#### Acceptance Criteria

1. THE Application SHALL authorize Briefs, generate, retry, audio request and audio stream actions through a `BriefPolicy` and S02 policies, and SHALL select the Dashboard payload by role.
2. THE Application SHALL allow an Owner to view and generate Briefs across the company, and a Manager to view and generate Briefs scoped to the Manager's department.
3. THE Application SHALL protect every S05 route with session authentication and CSRF validation.
4. THE Application SHALL validate every S05 request with a Form Request.
5. THE Application SHALL throttle every AI-calling route.
6. THE Application SHALL escape every Brief text, title and explanation value rendered in Vue, and SHALL NOT use `v-html` for Provider-generated text.
7. THE Application SHALL write no figures, evidence values, Brief text or API key to application logs.

### Requirement 23: Data model and documentation

**User Story:** As a developer, I want the data model and contracts documented and constrained, so that later specs build on a stable base.

#### Acceptance Criteria

1. THE Application SHALL create the tables `briefs` and `brief_items` with the columns, types and indexes defined in `docs/data-model.md`.
2. THE Application SHALL enforce, with database check constraints, the values of `briefs.status`, `briefs.source`, `briefs.coverage_status`, `briefs.audio_status`, `brief_items.position` (1 to 5) and `brief_items.severity`.
3. THE Application SHALL enforce a unique constraint on `(brief_id, position)` in `brief_items`.
4. THE Application SHALL delete `brief_items` WHEN the owning Brief is deleted, using cascading deletion.
5. THE Application SHALL provide migrations that roll back cleanly.
6. THE Application SHALL document the S05 events, the `briefs` prop on the Issue detail page, the AI data flow, the retry layering and the Coverage_Status definition in `docs/architecture.md` and `docs/data-model.md`.
7. THE Application SHALL use `BusinessClock::today()` for the Brief `as_of_date` and every Dashboard date, so that the demo date-time stored in the demo clock file written by `demo:set-date` applies only WHEN `DEMO_MODE` is true.

### Requirement 24: Scope exclusions and verification

**User Story:** As a project owner, I want S05 limited to the agreed scope and verified with the agreed method, so that Stage 1 stays predictable.

#### Acceptance Criteria

1. THE Application SHALL NOT provide scheduled Brief generation, delivery of Briefs by email or WhatsApp, forecasting, anomaly detection, a chatbot, task KPIs or a Staff task summary in S05.
2. THE Application SHALL include one feature test group, `ExecutiveIntelligenceTest`, run on the PostgreSQL scratch test database, that makes no external Provider call, and SHALL include no browser automation and no property-based framework.
3. THE `ExecutiveIntelligenceTest` group SHALL verify that the Number_Check rejects a number that is not in the Evidence_Set, that the deterministic fallback summary composer output uses only evidence figures, that Brief_Item evidence is copied from the Issue's rule evaluations and stays unchanged after a later evaluation, that a Staff user cannot generate a Brief, and that the audio route denies unauthorized users.
4. THE Application SHALL verify S05 manually with the demo fixtures of `docs/demo-scenario.md`, comparing every Dashboard figure and Brief_Item evidence value with the expected values.
5. THE Application SHALL verify manually the authorization matrix of Owner, Manager and Staff for every S05 route, including cross-company and direct URL access to audio.
6. THE Application SHALL verify manually the provider failure drills, namely an invalid API key, a timeout, an HTTP 429 response, an HTTP 5xx response, invalid JSON, and a response that contains an invented number.
7. THE Application SHALL verify the Dashboard and Briefs pages at 360, 768 and 1280 pixels.
8. THE Application SHALL verify the concurrent-generation rule by submitting two generation requests within one second and confirming that only one Brief is created.

### Requirement 25: Queue execution and retry layering

**User Story:** As a developer, I want retries handled in exactly one layer, so that a provider outage never multiplies calls or hides a failure.

#### Acceptance Criteria

1. THE Brief_Job and THE Audio_Job SHALL run on the `ai` queue, and SHALL be dispatched after the creating transaction commits.
2. THE Brief_Job SHALL declare `$tries` 3, `backoff()` returning 10, 30 and 60 seconds, a `$timeout` of 60 seconds, and `uniqueFor` of 900 seconds.
3. THE Audio_Job SHALL declare `$tries` 3, `backoff()` returning 10, 30 and 60 seconds, a `$timeout` of 90 seconds, and `uniqueFor` of 900 seconds.
4. IF a Recoverable_Provider_Exception occurs before the final attempt, THEN THE Brief_Job and THE Audio_Job SHALL rethrow the exception so that the queue retries the job with the declared backoff.
5. IF a Provider_Exception occurs, THEN THE Brief_Job and THE Audio_Job SHALL NOT retry the provider call.
6. WHEN the final Brief_Job attempt ends with a Recoverable_Provider_Exception, THE Brief_Job SHALL build the Fallback_Brief and complete the Brief.
7. WHEN the final Audio_Job attempt ends with a Recoverable_Provider_Exception, THE Audio_Job SHALL set Audio_Status to `failed` and display a manual Retry action, subject to Requirement 18.11.
8. THE corrective request of Requirement 11.6 SHALL NOT consume a job attempt.
9. THE Application SHALL make at most 3 Provider calls for one user action on the initial request path, excluding corrective requests.
