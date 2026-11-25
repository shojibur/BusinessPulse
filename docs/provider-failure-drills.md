# Provider failure drills (manual)

Provider failures are never automated. Induce each failure by changing environment values only, run `php artisan config:clear` and `php artisan queue:restart`, and restore afterwards.

| # | Drill | Induce | Expected behaviour | Recovery | Final state |
|---|---|---|---|---|---|
| 1 | Text provider (brief) | Wrong `OPENAI_API_KEY` or empty `AI_TEXT_MODEL` | Brief completes as "Generated from stored data" / "AI unavailable" with the deterministic figures; a 4xx falls back at once, 429, 5xx and timeouts are retried by the queue (3 tries, backoff 10, 30, 60 s) then fall back | Fix the key, generate again | New brief with AI wording |
| 2 | Speech provider | Empty `AI_SPEECH_MODEL` or wrong key | "Voice is not configured." or audio "failed" with a retry button; never a fake player | Fix, press Retry audio | Audio ready |
| 3 | Transcription | Empty `AI_TRANSCRIPTION_MODEL` or wrong key | Meeting `failed` at transcription with a fixed message, Retry and the manual transcript path | Fix and Retry, or paste the transcript | Transcript stored |
| 4 | Extraction | Wrong key or an unreachable base URL | Meeting `failed` at extraction; no drafts; Retry works; invalid AI output gives one corrective request then a fixed error | Fix and Retry | Four drafts |
| 5 | No internet | Disconnect the network | All AI actions fail visibly (timeouts after the retry layer); imports, rules, tasks and outcomes still work (no AI there) | Reconnect, Retry | Normal |

Timing reference: 3 tries, backoff 10, 30, 60 s; job timeouts 90 s (brief), 90 s (audio), 150 s (transcription), 90 s (extraction); one HTTP attempt per call. Check that no key and no raw provider message appears in any page, log or prop. Restore: original values, `config:clear`, `queue:restart`, then a smoke test (generate a brief).
