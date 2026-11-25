# BusinessPulse

BusinessPulse (Inventa Studio) is a Business Intelligence and Decision Execution Platform: Detect → Understand → Decide → Execute → Verify. This repository is **Stage 1, a functional proof of value**, built as a Laravel 13 + Inertia + Vue 3 modular monolith on PostgreSQL.

Current state: **S01 Application Foundation, S02 Identity and Access and S03 Sales Data Import**. Sign-in, the three fixed roles (Owner, Manager, Staff), department scoping, read-only Company, Staff and Department settings, the shell, design system, queue, BusinessClock and test harness exist. Owners and Managers can upload, preview, confirm and trace daily branch sales CSV files under Data Imports. The navigation pages Today, Issues, Briefs, Meetings, Tasks and Outcomes are **placeholders** that show no business data; the features behind them arrive in later specifications (`.kiro/specs/`).

- Docker is not used anywhere. Local development is native.
- Use fictional data only. Do not load real client data until the client has authorized it.
- Supported versions are recorded in [`docs/architecture.md`](docs/architecture.md) section 11.

## 1. Prerequisites

PHP 8.3 or newer (verified on 8.5.2) with the `pdo_pgsql`, `mbstring`, `intl`, `xml`, `zip` and `curl` extensions, Composer 2, Node.js 22 or newer with npm (verified on Node 24), and PostgreSQL 15 or newer (verified on 17).

## 2. Clone and install

```bash
git clone <repository-url> businesspulse && cd businesspulse
composer install
npm install
```

## 3. Environment and key

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`: set `DB_USERNAME` (and `DB_PASSWORD` if your PostgreSQL role needs one). Every variable is documented in `.env.example`. Never commit `.env`.

## 4. Create the databases (no Docker)

Create the development database and a separate test database with your local PostgreSQL role (shown here as `postgres`; use your own role):

```bash
createdb -O postgres businesspulse
createdb -O postgres businesspulse_testing
```

If you need a dedicated role first: `psql -d postgres -c "CREATE ROLE businesspulse LOGIN PASSWORD 'choose-a-password'"`, then use `-O businesspulse` above and set the same name in `.env`.

Create the test settings file (git-ignored) and set the same role:

```bash
cp .env.testing.example .env.testing
```

The test database is wiped by every test run. The test harness refuses to run unless the connection is PostgreSQL database `businesspulse_testing`. Never point it at the development database or any other project's database.

## 5. Migrate

```bash
php artisan migrate
```

## 5a. Create the first Owner (production setup)

There is no registration page. The first company, its Sales department and its Owner are created once with:

```bash
php artisan identity:create-owner
```

It prompts for the company name, the Owner's name and email, and a hidden password (at least 12 characters). Options `--company`, `--name`, `--email` and `--password` exist for scripting, but a password on the command line stays in shell history, so prefer the prompt. The command refuses to run if a company already exists. All other accounts are created by the Owner.

## 5b. Demonstration accounts (fictional data)

For a demonstration or local development database only. Set `DEMO_MODE=true` and a `DEMO_PASSWORD` of at least 12 characters in `.env` (never commit it), then:

```bash
php artisan db:seed --class=DemoIdentitySeeder
```

This creates Meridian Home Supplies Sdn Bhd (fictional), its Sales department (branches KL, PG, JB) and six users, all on the `meridian.demo` domain and sharing `DEMO_PASSWORD`: `owner@meridian.demo` (Owner), `aisyah@meridian.demo` (Manager), `daniel.tan@`, `daniel.lee@`, `priya@` and `farid@meridian.demo` (Staff). It is safe to run again. It refuses to run unless `DEMO_MODE=true`, if the password is empty or short, or if a real (non-demo) company exists.

## 5c. Sales imports and the demonstration data

Data Imports takes a CSV with the header `sales_date,branch_code,revenue,target_revenue,order_count` (download the template from the upload page). Files are checked first (type, size up to 5 MB, header, up to 10,000 rows), then every row; you see a preview and confirm before anything is saved. Confirmation queues the import, so **a queue worker must be running** (`composer dev` starts one; otherwise `php artisan queue:work --queue=default,ai`). Only branches listed in the department's expected branches are accepted (the demo seeds `KL`, `PG`, `JB`; nothing is derived from an upload).

PHP must allow the upload size: set `upload_max_filesize = 6M` and `post_max_size = 8M` in `php.ini` (the PHP default of 2 MB rejects larger files), and `client_max_body_size 6m;` in Nginx.

Load the fictional demonstration data through the real import workflow (requires `DEMO_MODE=true` and the demo identity from 5b). Dates after the business today are rejected, so set the demo clock before each step:

```bash
php artisan demo:check-fixtures                 # verify the CSV fixtures (no database needed)
php artisan demo:set-date 2026-09-21 && php artisan demo:import history
php artisan demo:set-date 2026-09-28            # then upload database/demo/week8-decline.csv in the UI,
                                                # or: php artisan demo:import week8-decline
php artisan demo:set-date 2026-10-05 && php artisan demo:import week9-interim
php artisan demo:set-date 2026-10-12 && php artisan demo:import week10-recovery
```

Other fixtures: `week8-with-errors` (3 invalid rows, JB Saturday missing) and `week8-correction` (two corrected rows). Delete `storage/app/private/demo/fixed-datetime` to return to real time.

## 5d. Passwords and access

- **Password recovery is a manual Owner action.** There is no email reset and no self-service password change in Stage 1. This is an assumption pending client confirmation (S02 A2, A3).
- A Manager can see the staff of their own department read-only; only the Owner administers accounts (S02 A4, also pending client confirmation).
- A deactivated user is signed out on their next request.

## 6. Run the application

```bash
composer dev
```

This starts the web server (`php artisan serve`), one queue worker for both queues, and the Vite dev server together. If port 8000 is busy, `artisan serve` prints the port it chose. Open the URL it prints (default <http://localhost:8000>); `/` redirects to **Today**.

To run the pieces separately: `php artisan serve`, `npm run dev`, and `php artisan queue:work --queue=default,ai`. For a built frontend without the dev server, run `npm run build` once. The queue worker does not reload code; restart it after changing job code.

### Queues

Two named queues on the database connection:

- `default`: imports, evaluation, outcomes, activity, notifications.
- `ai`: brief, audio, transcription, extraction.

`retry_after` is 300 seconds and `after_commit` is true (`config/queue.php`). `retry_after` must exceed the longest job timeout (150 s with margin); re-check it whenever a job timeout rises above 150 seconds.

### Deployment queue workers (Supervisor)

Two programs, each with `--tries=3 --timeout=180 --max-time=3600`, `stopwaitsecs=200`, `stopasgroup=true`, `killasgroup=true`:

- `bp-default`: `php artisan queue:work --queue=default --tries=3 --timeout=180 --max-time=3600`
- `bp-ai`: `php artisan queue:work --queue=ai --tries=3 --timeout=180 --max-time=3600`

Run `php artisan queue:restart` after every deployment.

## 7. Verification commands

| Command | Checks |
|---|---|
| `composer test` | Clears cached config, then runs the Pest suite on `businesspulse_testing` (refuses any other database, confirmed with `select current_database()`) |
| `composer pint-test` | PHP code style (Laravel Pint, test mode); `composer pint` fixes |
| `npm run type-check` | Generates Wayfinder route types, then `vue-tsc --noEmit` (strict TypeScript) |
| `npm run lint` | ESLint (no warnings allowed) |
| `npm run format:check` | Prettier check (`npm run format` fixes) |
| `npm run build` | Vite production build |
| `npm run verify` | `type-check`, `lint`, `format:check`, `build`, then `composer test`, stopping at the first failure |

Passing these commands does **not** demonstrate production readiness. They verify the foundation behaves as specified. Manual acceptance, provider drills with real credentials and demo rehearsals are separate.

## 8. Demo clock

`BusinessClock` (`App\Support\BusinessClock`) is the single source of the business date and time (timezone from `BUSINESS_TIMEZONE`, default `Asia/Kuala_Lumpur`, a demo default). With `DEMO_MODE=true` it reads a demo date-time from `storage/app/private/demo/fixed-datetime`:

```bash
php artisan demo:set-date 2025-09-22              # 09:00
php artisan demo:set-date 2025-09-22 --time=14:30
```

`demo:set-date` refuses to run unless `DEMO_MODE=true`. **Delete `storage/app/private/demo/fixed-datetime` to return the clock to real time.** Keep `DEMO_MODE=false` outside dedicated demo environments.

## 9. Storage and security notes

- Uploads and generated files use the private `local` disk at `storage/app/private`. No `public/storage` symlink is created and `/storage/*` returns 404. Downloads from the private disk require an authorized streamed response, implemented by later specifications.
- AI settings live in `config/ai.php` and the environment. No provider call is made in S01. Provider keys stay server-side.
- Every application route requires a signed-in, active user. Navigation is filtered by role (Owner and Manager see all eight sections, Staff sees Today and Tasks) but navigation is display only: the server returns 403 for a section a role does not see, and 404 for a record outside the user's department. See `docs/architecture.md` section 13.
- The Laravel starter kit's Fortify package, registration, email verification, password reset and two-factor features were removed.

## 10. Documentation

| File | Contents |
|---|---|
| `docs/product-vision.md` | Product purpose, users and principles |
| `docs/stage-1-scope.md` | Stage 1 scope, exclusions and definition of done |
| `docs/stage-1-refinements.md` | Authoritative refinements R1–R9 (priorities, calendar, outcomes, queues) |
| `docs/architecture.md` | System design, modules, AI integration, deployment, verified versions |
| `docs/data-model.md` | Database schema |
| `docs/business-workflows.md` | Business rules and decisions D1–D26 |
| `docs/ui-ux-design-system.md` | Design tokens, components, screens, accessibility |
| `docs/demo-scenario.md` | Fictional company data and the 22-step demonstration |
| `docs/roadmap.md` | Specification order and ownership |

Implementation specifications are in `.kiro/specs/` and steering documents in `.kiro/steering/`. Claude Code instructions are in `CLAUDE.md`.
