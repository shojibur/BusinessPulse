# Demonstration walkthrough (22 steps)

Fictional data only (Meridian Home Supplies Sdn Bhd). Run it from a freshly reset demo. Every figure below comes from `docs/demo-scenario.md` and is produced by the real import, evaluation and verification code.

**Setup (DESTRUCTIVE, demo database only):** `php artisan demo:reset --force`. It seeds the history through the real import path and leaves the clock at 2026-09-28 09:00. Workers must run (`composer dev` or the two Supervisor programs). **Do not press "Refresh intelligence" before uploading `week8-decline.csv`**: the demo starts with week 8 overdue and unevaluated, and a refresh would create an unwanted "missing sales data" issue.

**Presenter commands:** `php artisan demo:set-date 2026-09-30` (step 15), `2026-10-05` (before step 16), `2026-10-12` (before step 18).

**Opening statements:** all data is fictional; AI proposes, people approve; the application reports what changed after actions and does not claim the actions caused it. Demo users (Owner Lim Wei Han, Manager Nurul Aisyah, Staff Priya Nair, Farid Ismail, Daniel Tan, Daniel Lee) use the `DEMO_PASSWORD` you set (never read it out).

| # | Actor and action | Expected result | Talking point (short) | Fallback |
|---|---|---|---|---|
| 1 | Owner logs in (`owner@meridian.demo`) | Today page | Role-based access | Check `DEMO_MODE`, run `demo:reset --force` |
| 2 | Dashboard | W7 revenue 155,000, change +0.65%, target 99.36%; banner "Sales for 21-26 Sep not yet received: 18 branch-days overdue" | Missing data is shown, never as zero | Clock must be 09-28 |
| 3 | Upload `database/demo/week8-decline.csv` | Preview: 18 valid rows | Validation before anything is stored | Re-check the file name |
| 4 | Confirm | Import completes (inserted 18) | Real import, idempotent | Check the `default` worker is running |
| 5 | Metrics and rules evaluate | Within seconds | Deterministic rules, no AI | Issues page "Refresh intelligence" now (after the upload) |
| 6 | Issues appear | Decline: high, -20.00% (124,000 vs 155,000, -31,000). Target: medium, 79.49%. One urgent notification (decline only) | Evidence, not opinion | Re-run evaluation from the Issues page |
| 7 | Open the decline issue | Evidence with periods, values, threshold 15% | Every figure is traceable | none |
| 8 | Generate executive brief | Two findings (decline, then shortfall); figures rendered by the application; wording "AI generated" or "Generated from stored data" | AI words only; figures are deterministic | Provider drill 1 (fallback brief) |
| 9 | Play the voice brief | Audio plays; text equals the page | Same text, spoken | Drill 2 |
| 10 | Create a meeting linked to the decline issue, confirm recording permission, upload the recording | Upload accepted, status "transcribing" | Consent is recorded before any audio leaves | Use the manual transcript (below) |
| 11 | Transcript appears | Meeting status moves to extracting | Transcript is editable | Paste `database/demo/meeting-transcript.txt` |
| 12 | Task drafts appear | 4 drafts: Priya (2026-10-02), Farid (2026-10-07), ambiguous "Daniel" with no deadline, unassigned stock check with no deadline; none assigned to the Owner | AI proposes; a person decides | Drill 4 |
| 13 | Approve drafts 1 and 2; choose Daniel Tan and a deadline for draft 3 and approve; reject draft 4 | Three tasks; issue becomes "action in progress" | Nothing is assigned without approval | Edit the draft fields, then approve |
| 14 | Priya logs in | Lands on My Tasks; her task is listed; no meeting, transcript or issue data | Staff see only their work | Check the assignee |
| 15 | `demo:set-date 2026-09-30`; Priya, Farid and Daniel Tan start and complete their tasks | After the last completion the issue is "monitoring", not resolved | Completing tasks is not resolving | Reload the issue page |
| 16 | `demo:set-date 2026-10-05`; Owner uploads `week9-interim.csv` | W9 evaluated | Next period arrives | Check workers |
| 17 | Open the outcome | W9 138,000, +14,000, +11.29%, 89.03% of W7; phase "During action - not used for verification"; issue stays in monitoring | A rebound during action is not a verified recovery | none |
| 18 | `demo:set-date 2026-10-12`; upload `week10-recovery.csv` | W10 evaluated | After-action period | Check workers |
| 19 | The post-action outcome is recorded | W10 150,000; phase "After action - used for verification"; improved | Deterministic comparison | Wait for the `default` queue |
| 20 | Issue resolved by the system | `system_verified`; +26,000 absolute, +20.97% relative, 96.77% of W7; condition 150,000 >= 131,750 met; W10 vs W9 +8.70% is rule evidence only; optional target outcome 96.15%, +16.66 pp | Observed change after actions | none |
| 21 | Owner records "useful" feedback | Counter 1 useful | Feedback never changes a rule | none |
| 22 | Open the trace on the issue | Seven stages from imported data to the outcome | End-to-end history | none |

Waiting times: import and evaluation a few seconds; transcription up to a minute with a real provider; extraction up to a minute.

**Manual transcript fallback (steps 10 to 12):** on the meeting page paste `database/demo/meeting-transcript.txt` and press "Save transcript and extract tasks".

**Optional segments (each after a fresh reset):** validation (`week8-with-errors.csv`: 17 valid, 3 invalid; missing data breached 17 of 18; decline and target insufficient), correction (`week8-decline.csv` then `week8-correction.csv`: 16 unchanged, 2 corrected, W8 126,000), trailing-missing (press Refresh at 09-28 before any upload: missing data breached 0 of 18).

**Closing:** out of scope in Stage 1: forecasting, WhatsApp or email delivery, multi-company, live meeting capture, production certification. Client decisions needed: retention period, thresholds, data processing terms, the real provider.
