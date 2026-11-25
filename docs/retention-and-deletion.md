# Retention and deletion

Default retention of meeting recordings: 30 days from upload (`companies.recording_retention_days`), a demonstration default pending client confirmation.

| Mechanism | Status |
|---|---|
| `php artisan recordings:purge`, scheduled daily at 02:00 and runnable by hand: deletes recording files older than the company retention period; transcripts and drafts are kept | NOT BUILT yet (S06 task 8.4, RF-02) |
| Owner early deletion of a recording | NOT BUILT yet (S06 task 8.4, RF-02) |
| `php artisan demo:reset --force` removes every demo record and demo file (demo database only) | Built (S09) |

**Blocker:** both deletion paths must exist and be verified before any real (non-demo) recording is accepted. Until then use fictional recordings only.

| Data class | Retained | Removed by |
|---|---|---|
| Recording files (`storage/app/private/recordings`) | retention days | purge, Owner deletion, `demo:reset` |
| Transcripts, decisions, task drafts | until the company decides otherwise | not automatic in Stage 1 |
| Sales imports and files | business record | `demo:reset` (demo only) |
| Activity logs, outcomes | append-only history | `demo:reset` (demo only) |
| Backups | follow the retention period; expire on the backup schedule | backup rotation |

## Verification (once S06 8.4 is built, on a SCRATCH database)
1. Create a meeting with a fictional recording. 2. `php artisan demo:set-date` is not used for the file age: set `recording_uploaded_at` 31 days back through a test or tinker on the scratch database. 3. Run `php artisan recordings:purge`: it prints `Purged: 1. Failed: 0.`, the file is gone, `recording_deleted_at` is set, the transcript and drafts remain. 4. A meeting that is `transcribing` is skipped. 5. `php artisan schedule:list` shows the daily entry. If the command cannot support these steps, record a Readiness_Fix instead of editing S06 here.
