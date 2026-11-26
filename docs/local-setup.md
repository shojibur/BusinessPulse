# Local setup (native, no Docker)

Prerequisites: PHP 8.5 with extensions pdo_pgsql, bcmath, intl, mbstring, fileinfo, gd or imagick not required; Composer 2; Node.js and npm; PostgreSQL 16 or newer; macOS (Homebrew) or Ubuntu.

## Steps
1. Install PHP, Composer, Node and PostgreSQL (macOS: `brew install php composer node postgresql@16`; Ubuntu: `apt install php-cli php-pgsql php-bcmath php-intl php-mbstring composer nodejs npm postgresql`).
2. Clone the repository, then `composer install` and `npm install`.
3. `cp .env.example .env`, then `php artisan key:generate`.
4. Create two PostgreSQL databases and a role: the application database (default `businesspulse`) and the test database `businesspulse_testing`. Set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`. Tests never run on any other database (a guard checks `current_database()`).
5. `php artisan migrate --seed` (creates the roles and the demo identities when `DEMO_MODE=true` and `DEMO_PASSWORD` is set).
6. Start everything: `composer dev` (web server, the queue worker for `default` and `ai`, Vite). Alternative with separate terminals: `php artisan serve`, `php artisan queue:work --queue=default,ai --tries=3 --timeout=180`, `npm run dev`.
7. Open `http://127.0.0.1:8000` and sign in as `owner@meridian.demo` with `DEMO_PASSWORD`.

## Demonstration setup
In `.env`: `DEMO_MODE=true`, `DEMO_PASSWORD` (8 characters or more; use a longer one on any server other than your own machine). Then `php artisan demo:seed` (first time) or `php artisan demo:reset --force` (DESTRUCTIVE: deletes the demo company and re-seeds; demo database only). Earlier commands: `php artisan demo:set-date YYYY-MM-DD`, `php artisan demo:import <fixture>`, `php artisan demo:check-fixtures`. Use a SCRATCH database for experiments; never point these commands at a real database. Never touch other projects' databases.

## Verification
`composer validate --strict`, `./vendor/bin/pint --test`, `composer test`, `npm run verify` (type-check, lint, format check, build, tests), `php artisan demo:check-fixtures`.

## Troubleshooting
| Symptom | Cause and fix |
|---|---|
| Imports or issues never appear | The queue worker is not running: start `queue:work --queue=default,ai` |
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
| `QUEUE_CONNECTION`, `DB_QUEUE_RETRY_AFTER` | Database queue; `retry_after` must exceed the longest job timeout | yes | `database`, 300 |
| `CACHE_STORE`, `FILESYSTEM_DISK` | Database cache, private local disk | yes | `database`, `local` |
| `BUSINESS_TIMEZONE` | Business timezone | no | `Asia/Kuala_Lumpur` |
| `DEMO_MODE` | Enables the demo clock and demo commands | no | `false` |
| `DEMO_ALLOW_PRODUCTION_DEMO` | Allows demo commands when `APP_ENV=production` (dedicated demo server only) | no | `false` |
| `DEMO_PASSWORD` | Password of the demo users (8+ characters) | demo only | empty |
| `AI_BASE_URL`, `OPENAI_API_KEY` | AI provider endpoint and key (server side only) | for AI | empty |
| `AI_TEXT_MODEL`, `AI_TRANSCRIPTION_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE` | Model and voice names (never hardcoded) | for AI | empty |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL`, `MAIL_MAILER`, `BCRYPT_ROUNDS`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_MAINTENANCE_DRIVER`, `VITE_APP_NAME` | Framework defaults; no mail is sent in Stage 1 | no | see `.env.example` |
