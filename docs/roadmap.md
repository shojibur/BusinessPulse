# Roadmap

## Specification order and dependencies
| Order | Spec | Depends on |
|---|---|---|
| 1 | S01 application-foundation | none |
| 2 | S02 identity-access | S01 |
| 3 | S03 sales-data-import | S01, S02 |
| 4 | S04 intelligence-issue-engine | S03 |
| 5 | S05 executive-intelligence | S04 |
| 6 | S06 meeting-intelligence | S04, S02 |
| 7 | S07 task-execution | S06 |
| 8 | S08 outcomes-history | S04, S07, S03 |
| 9 | S09 demo-readiness | S01–S08 |

S05 and S06 can proceed in parallel after S04 if two developers are available.

## Milestones (each ends runnable and verifiable; `composer test` passes)
- **M1** S01–S02: login as each role, role navigation, shell, test harness.
- **M2** S03–S04: `demo:import` history, upload week 8 in the UI, issues detected with evidence and notification.
- **M3** S05: dashboard from real data, AI brief with fallback, voice playback.
- **M4** S06–S07: meeting, transcript, drafts, approval, staff completes tasks.
- **M5** S08: interim and post-action outcomes, resolution, trace timeline.
- **M6** S09: seed/reset, VPS deployment, rehearsals, PoV Polish items.

PoV Core/Polish split per spec: `docs/stage-1-refinements.md` R1.

## Demo readiness criteria
All 26 Definition-of-Done items pass verification (manual acceptance plus focused automated tests); two full rehearsals with no manual database edits; build checks green; deployed over HTTPS on the VPS; transcript fallback rehearsed.

## Stage 2 opportunities (context only)
Additional departments; configurable rules; Excel and advanced imports; scheduled briefs; reminders and escalation; WhatsApp/email; expanded admin; backups automation and monitoring; multi-language transcription; client-specific integrations. Not specified.
