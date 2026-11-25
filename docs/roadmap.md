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

## Milestones
- M1 Foundation and access (S01–S02): login, shell, design system.
- M2 Data to issues (S03–S04): CSV in, issues out.
- M3 Intelligence (S05): dashboard and briefs with voice.
- M4 Decide and execute (S06–S07): meetings to tasks.
- M5 Verify (S08): outcomes and trace.
- M6 Demo ready (S09): fixtures, reset, rehearsal, deployment.

## Demo readiness criteria
All 26 Definition-of-Done items pass manual verification; two full rehearsals with no manual database edits; build checks green; deployed over HTTPS on the VPS; transcript fallback rehearsed.

## Stage 2 opportunities (context only)
Additional departments; configurable rules; Excel and advanced imports; scheduled briefs; reminders and escalation; WhatsApp/email; expanded admin; backups automation and monitoring; multi-language transcription; client-specific integrations. Not specified.
