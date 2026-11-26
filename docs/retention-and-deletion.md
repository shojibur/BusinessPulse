# Retention and deletion

Default retention of meeting recordings: 30 days from upload (`companies.recording_retention_days`), a demonstration default pending client confirmation. Retention counts from `meetings.recording_uploaded_at`.

| Mechanism | Status |
|---|---|
| `php artisan recordings:purge`: deletes recording files older than the company retention period, prints `Purged: N. Failed: M.`, never touches a `transcribing` meeting; transcript, decisions and task drafts are kept | Built (S06 task 8.4); scheduled daily at 02:00 (`routes/console.php`, `withoutOverlapping`, `onOneServer`) |
| Owner early deletion: **Delete recording** on the meeting page (`DELETE /meetings/{meeting}/recording`); Managers get 403, rejected while `transcribing` | Built (S06 task 8.4) |
| A missing file still marks the recording deleted; a filesystem error leaves the meeting unchanged, logs only the meeting id, and the run continues | Built |
| `RecordingDeleted` event with reason `retention` or `owner_request` (ids only, no path) | Built |
| `php artisan demo:reset --force` removes every demo record and demo file (demo database only) | Built (S09) |

Automated coverage: `tests/Feature/Meetings/RecordingRetentionTest.php`. The **manual verification below has not been run yet**; run it and record the result before any real (non-demo) recording is accepted. Use fictional recordings until the client confirms retention (`docs/pdpa-considerations.md`).

| Data class | Retained | Removed by |
|---|---|---|
| Recording files (`storage/app/private/recordings`) | retention days | purge, Owner deletion, `demo:reset` |
| Transcripts, decisions, task drafts | until the company decides otherwise | not automatic in Stage 1 |
| Sales imports and files | business record | `demo:reset` (demo only) |
| Activity logs, outcomes | append-only history | `demo:reset` (demo only) |
| Backups | follow the retention period; expire on the backup schedule | backup rotation |

## Manual verification (SCRATCH database only)
The purge compares against the application clock (the same clock that stamps `recording_uploaded_at`), not the demo clock, so `demo:set-date` does not age a recording. Age it directly.
1. On a scratch database (`businesspulse_testing` after `migrate:fresh` and `demo:seed`), create a meeting with a fictional recording through the app.
2. In `php artisan tinker`: `App\Models\Meeting::query()->update(['recording_uploaded_at' => now()->subDays(31)]);`
3. Run `php artisan recordings:purge`. Expect `Purged: 1. Failed: 0.`; the file under `storage/app/private/recordings/` is gone; the meeting page shows "Recording deleted on {date}" and keeps the transcript and drafts.
4. Run it again. Expect `Purged: 0. Failed: 0.`
5. Create a second recording, sign in as Owner and press **Delete recording**; confirm the dialog; the file is gone and the transcript stays. Sign in as Manager: no delete button, and `DELETE` returns 403.
6. `php artisan schedule:list` shows `recordings:purge` daily at 02:00.
7. Record the result and date here: _not yet run_.
