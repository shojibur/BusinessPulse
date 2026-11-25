# S04 manual verification checklist

Run after `composer test` and `npm run verify` pass. Use the fictional demo data only. Needs `DEMO_MODE=true`, a
`DEMO_PASSWORD` in `.env`, a running queue worker (`composer dev` starts one) and a freshly seeded scratch or demo
database (`php artisan migrate:fresh --seed`; never a real client database).

## 1. Demo walkthrough steps that involve S04

| Step | Do | Expect |
|---|---|---|
| 1 | `php artisan demo:set-date 2026-09-21` then `php artisan demo:import history` | Import completes. Three evaluations, all `not_breached`: decline +0.65, target 99.36, coverage 18 of 18. No issue, no notification. |
| 2 | `php artisan demo:set-date 2026-09-28`. Sign in as the Owner, open **Issues**, press **Refresh intelligence** | Missing sales data is breached (0 of 18) and opens a medium issue; decline and target are `insufficient_data` (`period_absent`). No bell notification. |
| 3 | Data Imports: upload `database/demo/week8-decline.csv`, confirm | After the import and the evaluation job, **Issues** shows Weekly revenue decline (high, -20.00%) and Sales target shortfall (medium, 79.49%). The bell shows 1 unread for the Owner. |
| 4 | Sign in as `aisyah@meridian.demo` | The bell shows the same urgent item; the issue detail page shows the evidence (RM 124,000.00 vs RM 155,000.00, 18 of 18 due branch-days, source imports). |
| 5 | Upload `week8-decline.csv` again | No new evaluation, issue or notification. |
| 6 | Optional: upload `week8-correction.csv` | Decline is detected again (-18.71%, count 2); target is healthy (80.77%) and its issue is unchanged; no new notification; the baseline evidence on the issue page is unchanged. |
| 7 | As Manager press **Acknowledge**, then try **Resolve issue** with an empty reason | Status becomes Acknowledged with a history entry; the empty reason is refused with "A reason is required." |
| 8 | Sign in as `priya@meridian.demo` (Staff) and open `/issues` | 403 page. No issue data anywhere. |

## 2. Layout and keyboard

- Open Issues, an Issue detail page and Notifications at 360, 768 and 1280 px: no horizontal scrolling, cards below
  768 px and a table from 768 px, every control at least 40 px tall.
- Keyboard only: Tab to **Acknowledge** and press Enter; Tab to the bell, open it with Enter, move through items with the arrow keys and leave with Escape.

## 3. Recovery when the evaluation was not queued

Stop the queue worker, complete an import, restart the worker without processing `EvaluateRulesJob` (or fail the
dispatch). The issue page then shows "The evaluation may be out of date". Press **Refresh intelligence**; the note
disappears once the evaluation has run.

## 4. Evaluation time with 10,000 records

`tests/Feature/Intelligence/EvaluationPerformanceTest.php` measures this automatically (under 10 seconds, bounded
queries). To repeat by hand, insert about 10,000 fictional rows for the demo department and press Refresh intelligence.

## 5. Migration rollback (scratch database only)

`php artisan migrate:rollback --step=5` removes the S04 tables (including the partial unique index
`issues_open_identity_unique`); `php artisan migrate` restores them and backfills the default rules. After a
rollback and migrate, run `php artisan migrate:fresh --seed` to restore the demonstration rule configuration
(`high_drop_pct` 15).
