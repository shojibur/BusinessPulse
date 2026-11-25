# Business Workflows and Decisions

Process diagrams only. The data model is in `data-model.md`. Values marked **(demo default)** are not confirmed client requirements.

## 0. Business logic decisions
Authoritative refinements: `docs/stage-1-refinements.md` (R2 calendar and freshness, R3 outcomes, R4 lifecycle, R6 queues). Values marked **(demo default)** are not confirmed client requirements.

| # | Topic | Decision |
|---|---|---|
| D1 | Data grain | One `sales_records` row per branch per calendar day: `branch_code, sales_date, revenue, target_revenue, order_count?`. |
| D2 | Reporting frequency | Daily rows, uploaded in files of any size (typically weekly). |
| D3 | Reporting calendar | Company `trading_days` (Mon–Sat), `reporting_cutoff_time` (12:00) and `reporting_cutoff_lag_days` (1) **(demo defaults)**. A trading day is due when `BusinessClock::now()` is at or after cutoff time on day + lag. Expected branches are explicit in `departments.settings.expected_branches` (demo: KL, PG, JB); no auto-fill from an import. Public holidays are not modelled in Stage 1. |
| D4 | Record identity | `(department_id, branch_code, sales_date)`. One active row per identity. |
| D5 | Corrected import | A re-uploaded identity with different values supersedes the active row (old row `is_active=false`, new row inserted). Identical values count as `unchanged`. Duplicate identity inside one file: both rows invalid (`duplicate_in_file`). Same file hash: allowed, flagged, all rows unchanged. History is never deleted. |
| D6 | Week states | ISO week is `closed` when every trading day is due, else `open`. A closed week is `complete` (all expected branch-days present), `incomplete`, or `absent`. Open weeks are informational and never evaluated by decline/target rules. Partial data is never zero-filled. |
| D7 | Comparison | Decline compares the latest closed week to the preceding week; both must be closed and complete, otherwise `insufficient_data`. No fallback to older weeks. |
| D8 | Currency/precision | MYR `numeric(14,2)`; percentages computed at 4 dp, displayed at 2 dp, round half up; decimal math, no floats. |
| D9 | Zero denominators | Zero previous-week revenue or zero target → `insufficient_data` (`zero_denominator`). |
| D10 | Missing and fresh data | Missing-data rule breaches when any due expected branch-day in the current and previous ISO week up to `last_due_date` has no row (rows missing inside the submitted period and due days after the latest sales date both count). Not-yet-due days never alert. Freshness (`current`/`behind`) is shown live and never creates an issue. Evaluation runs after an import completes and on explicit "Refresh intelligence"; there is no scheduler. |
| D11 | Issue identity | `(business_rule_id, department_id, scope_key)`, Stage 1 `scope_key='department'`; one non-resolved issue per identity (partial unique index). |
| D12 | Recurrence | After `resolved`, a new breach creates a new issue with `recurrence_of_issue_id`. Resolved issues are immutable except feedback. |
| D13 | Issue lifecycle | Transition table in `stage-1-refinements.md` R4 (includes `monitoring → action_in_progress` on task reopen or new task, manual reasons in `issues.resolution_note` / `status_history`). |
| D14 | Re-detection | Updates `last_detected_at`, `latest_evaluation_id`, `detection_count`; severity only increases automatically; `baseline_evaluation_id` never changes. |
| D15 | Task lifecycle | `pending → in_progress → completed`; `pending/in_progress ↔ blocked`; `pending/in_progress/blocked → cancelled` (Owner/Manager); `completed → in_progress` reopen (Owner/Manager). Staff set `in_progress/blocked/completed` on their own tasks. Overdue is computed: `due_date < BusinessClock::today()` and status not completed/cancelled. `completed_at` uses `BusinessClock::now()`. |
| D16 | Multiple tasks per issue | Allowed. Monitoring starts when all non-cancelled linked tasks are completed and at least one is completed. A new or reopened linked task returns a monitoring issue to `action_in_progress`. |
| D17 | Outcomes | Phases `pre_action`/`during_action`/`post_action`; only post_action outcomes on closed, complete, comparable weeks can resolve or reopen. Metrics per rule and classification bands: R3 (revenue relative ±2.0%, percentage metrics ±2.0 pp). Actual values, absolute change, relative change and point change are all stored and shown where relevant. |
| D18 | Causation | UI copy: "Observed change after actions". |
| D19 | Notification dedupe | Urgent (severity high) in-app notification to Owner and department Manager on issue creation, severity increase, or recurrence; key `issue_id:severity`. |
| D20 | AI retries | One retry layer: the queue job (3 tries, backoff 10/30/60 s) for recoverable provider errors only. `OpenAiClient` does not retry. One corrective re-request on schema or number-check failure inside the same attempt. After final failure: `failed` with manual Retry. See R6. |
| D21 | Idempotency | Evaluations keyed by `run_key` (rule config, periods, as-of/cutoff context, expected branches, trading days, active record ids). Imports run in a transaction with a status guard. Task approval uses a row lock plus unique `task_drafts.task_id` and `tasks.task_draft_id`. |
| D22 | Recording storage | Private disk `recordings/{meeting_id}/` with random filename, authorized streaming only, retention 30 days **(demo default)**. |
| D23 | Access control | Owner company-wide; Manager own department; Staff own tasks (read-only decision excerpt and issue summary for their tasks). Out-of-scope records return 404; wrong role 403. |
| D24 | Demo reset | Guarded, locked, and refused while any job row exists (R8). Never scheduled. |
| D25 | Severity | In `business_rules.params`: decline `high_drop_pct` (default 25, **demo seed 15**); target `high_below_pct` (default 70); missing data medium. Urgent = high. |
| D26 | Business clock | `BusinessClock::now()/today()` use company timezone, or the demo fixed date-time (default time 09:00) when `DEMO_MODE=true`. Business timestamps (task completion, monitoring start, outcome evaluation) use it. |
| D27 | AI authorization | Meeting audio and transcripts are processed only when consent is recorded, the recording is not deleted, and the actor/department are authorized; jobs re-check at run time. |

## 1. Data import workflow
```mermaid
flowchart TD
  A[Manager/Owner uploads CSV] --> B{File valid? size, ext, header}
  B -- no --> B1[Reject with message]
  B -- yes --> C[Parse rows, validate dates, numbers, branches, duplicates]
  C --> D[Preview: new / corrected / unchanged / invalid, coverage gaps]
  D --> E{User confirms?}
  E -- cancel --> E1[Import cancelled]
  E -- confirm --> F[ProcessImportJob: transaction, supersede, insert]
  F --> G[Completed or completed_with_errors]
  G --> H[EvaluateRulesJob]
  F -- exception --> I[failed, nothing partially applied]
```
Invalid rows never block valid rows (partial import with error report). Header or format failures reject the whole file.

## 2. Rule evaluation workflow
```mermaid
flowchart TD
  A[EvaluateRulesJob after import or Refresh intelligence] --> B[Compute last due date and latest closed week from reporting calendar]
  B --> C{Latest closed week complete?}
  C -- no --> D[Record insufficient_data with reason]
  C -- yes --> E[Compute metric and compare to threshold]
  E --> F{Breached?}
  F -- no --> G[Record not_breached]
  F -- yes --> H[Record breached with severity]
  H --> I{Open issue with same identity?}
  I -- yes --> J[Update issue last_detected, latest evaluation]
  I -- no --> K[Create issue, baseline = this evaluation]
  K --> L{Severity high?}
  L -- yes --> M[In-app notification, deduped]
  G --> N[Outcome check for monitoring issues]
  D --> N
  J --> N
```

## 3. Issue lifecycle
```mermaid
stateDiagram-v2
  [*] --> open
  open --> acknowledged: manager/owner
  open --> action_in_progress: first task approved
  acknowledged --> action_in_progress: first task approved
  action_in_progress --> monitoring: all tasks done or manual
  monitoring --> resolved: resolution condition met or manual with note
  monitoring --> open: post-action outcome worsened, condition not met
  monitoring --> action_in_progress: task reopened or new task
  resolved --> [*]
```

## 4. Executive briefing workflow
```mermaid
sequenceDiagram
  actor O as Owner
  participant App
  participant Q as Queue
  participant AI as AI provider
  O->>App: Generate brief
  App->>Q: GenerateBriefJob (status queued)
  Q->>App: assemble verified metrics, top 5 issues, coverage
  Q->>AI: structured request (verified facts only)
  alt valid response
    AI-->>Q: titles and explanations
    Q->>App: validate, save items with authoritative evidence
  else failure or invalid after retries
    Q->>App: deterministic fallback summary, source=fallback
  end
  O->>App: Generate voice
  App->>Q: GenerateBriefAudioJob
  Q->>AI: speech from summary text
  Q->>App: store audio, status ready
```
No breached rules and sufficient coverage → normal-status brief. Partial/stale data is stated explicitly.

## 5. Meeting intelligence workflow
```mermaid
flowchart TD
  A[Create meeting + link issues] --> B{Audio or manual transcript?}
  B -- audio --> C[Confirm consent, upload]
  C --> D[TranscribeMeetingJob]
  D -- fail --> D1[failed: retry or paste transcript]
  D --> E[Transcript shown and editable]
  B -- manual --> E
  E --> F[ExtractMeetingTasksJob: decisions + task drafts]
  F -- fail --> F1[failed: retry]
  F --> G[ready_for_review]
```
Editing the transcript after extraction allows "Re-extract", which supersedes only `pending` drafts (new `extraction_run`), never approved ones.

## 6. Task approval workflow (swimlanes)
```mermaid
flowchart LR
  subgraph AI_System[System and AI]
    S1[Extract drafts] --> S2[Resolve names against dept staff]
  end
  subgraph Manager[Manager or Owner]
    M1[Review draft] --> M2[Edit title, description, assignee, deadline, issue]
    M2 --> M3{Approve?}
    M3 -- reject --> M4[Draft rejected]
    M3 -- approve --> M5[Valid assignee and deadline?]
  end
  subgraph Staff[Staff]
    T1[Receives in-app notification]
  end
  S2 --> M1
  M5 -- no --> M2
  M5 -- yes --> A1[Create task in transaction, lock draft]
  A1 --> T1
```

## 7. Task execution workflow
```mermaid
stateDiagram-v2
  [*] --> pending
  pending --> in_progress
  in_progress --> blocked
  blocked --> in_progress
  pending --> blocked
  in_progress --> completed
  pending --> completed
  pending --> cancelled
  in_progress --> cancelled
  blocked --> cancelled
  completed --> in_progress: manager/owner reopen
```

## 8. Outcome verification workflow
```mermaid
flowchart TD
  A[EvaluationRunCompleted] --> C[Issues in action_in_progress or monitoring for this department]
  C --> D{Closed, complete, comparable week after baseline?}
  D -- no --> E[Outcome not_evaluable with reason]
  D -- yes --> P{Phase of the week vs action and monitoring dates}
  P -- pre_action or during_action --> I1[Informational outcome, no lifecycle change]
  P -- post_action --> F[Compute actual values, absolute, relative and point change]
  F --> G{Resolution condition met?}
  G -- yes --> R[Resolve issue, system_verified]
  G -- no, worsened --> J[Reopen issue]
  G -- no, otherwise --> K[Stay in monitoring]
  R --> L[Outcome stored, activity logged, feedback allowed]
  J --> L
  K --> L
  E --> L
  I1 --> L
```

## 9. Failure and exception paths
| Failure | Behavior |
|---|---|
| Bad CSV header | Whole file rejected, expected template shown. |
| Row errors | Reported with row number, column, code; valid rows importable. |
| Import job crash | Transaction rolled back, status `failed`, retry available. |
| Provider timeout/429/5xx | Auto retry ×3, then failed state with Retry button. |
| Invalid AI JSON | One corrective retry, then fail (brief → fallback). |
| Transcription failure | Meeting `failed` at stage `transcription`; paste transcript fallback. |
| Ambiguous assignee/deadline | Draft left unresolved; approval blocked until set. |
| Double-click approve | Second request returns existing task, no duplicate. |
| Corrected data after issue | Issue evidence unchanged; new evaluation reflects corrected data. |
| Forbidden access | 403 via policy; department scoping returns 404 for out-of-scope records. |
