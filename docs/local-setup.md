# Local setup (native, no Docker)

Prerequisites: PHP 8.5 with extensions pdo_pgsql, bcmath, intl, mbstring, fileinfo, gd or imagick not required; Composer 2; Node.js and npm; PostgreSQL 16 or newer; macOS (Homebrew) or Ubuntu.

## Steps
1. Install PHP, Composer, Node and PostgreSQL (macOS: `brew install php composer node postgresql@16`; Ubuntu: `apt install php-cli php-pgsql php-bcmath php-intl php-mbstring composer nodejs npm postgresql`).
2. Clone the repository, then `composer install` and `npm install`.
3. `cp .env.example .env`, then `php artisan key:generate`.
4. Create two PostgreSQL databases and a role: the application database (default `businesspulse`) and the test database `businesspulse_testing`. Set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`. Tests never run on any other database (a guard checks `current_database()`).
5. `php artisan migrate --seed` (or `migrate:fresh --seed` to start over). With `DEMO_MODE=true` and `DEMO_PASSWORD` set, this leaves the demo company populated for testing: company, users, platform admin, the week 7 history and the week 8 decline imported and evaluated by the real pipeline (an open high-severity decline issue and a target shortfall issue, urgent notifications for the Owner and Manager, a draft meeting "Sales recovery huddle" linked to the decline issue), and the demo clock at 28 Sep 2026 09:00. No AI has run, so there are no task drafts, tasks, briefs or outcomes yet: add a provider key in `/platform/ai`, then paste the transcript from `database/demo/meeting-transcript.txt` into the meeting to get real drafts (it runs `demo:seed --populated`).
6. Start everything: `composer dev` (web server, one queue worker for the single `default` queue, Vite). Alternative with separate terminals: `php artisan serve`, `php artisan queue:work --queue=default --tries=3 --timeout=180`, `npm run dev`.
7. Open `http://127.0.0.1:8000` and sign in as `owner@meridian.demo` with `DEMO_PASSWORD`.

## Demonstration setup
Two demo states: **populated** (`migrate:fresh --seed`, `demo:seed --populated`, `demo:reset --force --populated`) for hands-on testing, and **clean** (`demo:seed`, `demo:reset --force`) for the presenter walkthrough, which starts with week 8 not yet uploaded.
In `.env`: `DEMO_MODE=true`, `DEMO_PASSWORD` (8 characters or more; use a longer one on any server other than your own machine). Then `php artisan demo:seed` (first time) or `php artisan demo:reset --force` (DESTRUCTIVE: deletes the demo company and re-seeds; demo database only). Earlier commands: `php artisan demo:set-date YYYY-MM-DD`, `php artisan demo:import <fixture>`, `php artisan demo:check-fixtures`. Use a SCRATCH database for experiments; never point these commands at a real database. Never touch other projects' databases.

## Platform super admin (S10)
The platform owner manages many SMEs from `/platform`, separate from the company sign-in. In demo mode the seeder creates `admin@businesspulse.demo` (password: `DEMO_PASSWORD`). For a real server create the first admin with `php artisan platform:create-admin`. Sign in at `/platform/login`, choose **New company**, and give the company name, time zone, trading days, branch codes and the first Owner. Give the Owner their email and password securely; they sign in at `/login`. Use the company page to suspend or reactivate it and to reset the Owner's password. A platform admin never sees a company's business data. Note: `demo:seed` and `demo:reset` refuse to run once a non-demo company exists, so keep real SMEs in a separate database.

## Verification
Fast loop while you work: `composer test:quick` runs only the test files you changed (about 2 s); `./vendor/bin/pest tests/Feature/Platform` runs one folder; `./vendor/bin/pest --filter="name"` runs one test; `npm run verify:quick` is type-check, lint and format check without the build and tests. Run the full `npm run verify` (about 1.5 minutes, of which the 330 tests take about 36 s) before committing.
`composer validate --strict`, `./vendor/bin/pint --test`, `composer test`, `npm run verify` (type-check, lint, format check, build, tests), `php artisan demo:check-fixtures`.

## Troubleshooting
| Symptom | Cause and fix |
|---|---|
| Imports or issues never appear | The queue worker is not running: start `php artisan queue:work --queue=default --tries=3 --timeout=180` |
| `demo:reset` refuses | The `jobs` table is not empty: wait for the workers to drain it, then run again |
| Upload of a large recording fails | PHP `upload_max_filesize` is 2M by default: set `upload_max_filesize=20M` and `post_max_size=25M` (CSV limit is 5 MB, recording 20 MB) |
| AI actions say "not configured" | Set the AI variables below (empty by default) |
| Dates look wrong | The demo clock file overrides the date: `php artisan demo:set-date` |

## Environment variables
| Variable | Purpose | Required | Default |
|---|---|---|---|
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` | Standard Laravel settings; `APP_DEBUG=false` outside development | yes | see `.env.example` |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL connection | yes | `pgsql`, `127.0.0.1`, 5432 |
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE` | Sessions (secure cookie true on HTTPS) | yes | `database` |
| `AI_QUEUE` | Queue for AI jobs; keep `default` so one worker does everything | no | `default` |
| `QUEUE_CONNECTION`, `DB_QUEUE_RETRY_AFTER` | Database queue; `retry_after` must exceed the longest job timeout | yes | `database`, 300 |
| `CACHE_STORE`, `FILESYSTEM_DISK` | Database cache, private local disk | yes | `database`, `local` |
| `BUSINESS_TIMEZONE` | Business timezone | no | `Asia/Kuala_Lumpur` |
| `TRUSTED_PROXIES` | Proxies trusted for X-Forwarded-* (`*` behind Cloudflare, Ploi or a load balancer; empty for local) | on a server behind a proxy | empty |
| `DEMO_MODE` | Enables the demo clock and demo commands | no | `false` |
| `DEMO_ALLOW_PRODUCTION_DEMO` | Allows demo commands when `APP_ENV=production` (dedicated demo server only) | no | `false` |
| `DEMO_PASSWORD` | Password of the demo users (8+ characters) | demo only | empty |
| `AI_BASE_URL`, `OPENAI_API_KEY` | Fallback AI provider endpoint and key (server side only). A platform admin can set these in the panel at `/platform/ai` instead; a saved panel value wins | for AI | empty |
| `AI_TEXT_PROVIDER`, `ANTHROPIC_API_KEY` | Fallback text provider (`openai` or `anthropic` for Claude) and the Claude key | no | `openai`, empty |
| `AI_TEXT_MODEL`, `AI_TRANSCRIPTION_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE` | Model and voice names (never hardcoded) | for AI | empty |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`, `MAIL_MAILER`, `BCRYPT_ROUNDS`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_MAINTENANCE_DRIVER`, `VITE_APP_NAME` | Framework defaults; no mail is sent in Stage 1 | no | see `.env.example` |
