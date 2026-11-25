# Implementation Plan: Application Foundation and Design System (S01)

## Overview

Implementation is TypeScript (Vue 3 + Inertia) and PHP (Laravel 13), as named in design.md. The plan scaffolds from the official Laravel Vue starter kit, adds configuration, `BusinessClock` with the file-based demo clock and `demo:set-date`, the test harness, then the design tokens, shared contracts, shell, eight shared components, error handling and placeholder pages, and finishes with tooling and documentation.

Per `docs/stage-1-refinements.md` R5 and `.kiro/steering/verification.md`, S01 sets up the Laravel test framework as shipped by the starter kit (Pest if present) on the PostgreSQL database `businesspulse_testing`, with a `composer test` script and a small smoke test set. No browser automation and no property-based tools are used. Checkpoints run `npm run verify` (which includes `composer test`), Pint, `migrate`, and the manual checks listed in design.md.

Priority legend (`docs/stage-1-refinements.md` R1): `[PoV Core]` tasks are needed for the end-to-end demonstration to run. `[PoV Polish]` tasks are still Stage 1 scope and are done after the Core slice works.

Not built in S01 (the first spec that needs each one builds it, as Core with its consumer; the render error boundary and polling composable as Polish): InsufficientDataNotice, Timeline, FileDropzone, polling composable, frontend render error boundary. No placeholder services, empty directories or unused interfaces are created.

## Tasks

- [ ] 1. Project scaffold, configuration, BusinessClock and test harness
  - [ ] 1.1 [PoV Core] Scaffold the application from the Laravel Vue starter kit
    - Create the Laravel 13 + Inertia + Vue 3 (Composition API) + Vite + Tailwind project in the workspace root without overwriting `docs/`, `.kiro/` or `.git`
    - Create only the folders that receive a file in S01 (`app/Support`, `resources/js/{components,layouts,types,pages}`); create no empty directory and no `.gitkeep`
    - Set `"strict": true` in `tsconfig.json`, tighten ESLint to flag unexplained `any`
    - Keep starter-kit auth screens and `User` model only where removal breaks the build; do not extend them (Assumption A1)
    - Ensure no Docker, Redis, GraphQL or Kubernetes files exist; confirm `.env` is in `.gitignore`; commit lock files
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 3.1, 3.4, 15.5, 20.5, 23.5, 23.6_

  - [ ] 1.2 [PoV Core] Configure PostgreSQL, queue, cache, sessions and private storage
    - Set `pgsql` as default connection reading only `DB_*` env vars in `config/database.php`
    - Set database defaults for queue (`QUEUE_CONNECTION=database`) and cache (`CACHE_STORE=database`); make sure migrations exist for `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `sessions`
    - In `config/queue.php` set the database connection `retry_after` to 300 and `after_commit` to true; define the queue names `default` and `ai` as constants or documented config values with no job class
    - Configure the default filesystem disk as local, rooted at `storage/app/private`, visibility `private`, with no public URL and no `public/storage` link
    - Create `.env.example` with `APP_DEBUG=false`, `DB_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `FILESYSTEM_DISK`, `SESSION_SECURE_COOKIE` (with HTTPS guidance comment), each with a one-line comment and no secrets
    - Confirm CSRF middleware remains enabled
    - _Requirements: 2.1, 2.2, 2.3, 15.1, 15.2, 15.4, 16.1, 16.2, 16.3, 16.4, 16.7, 16.8, 16.12, 17.1, 17.2, 17.3, 23.1, 23.2, 23.3_

  - [ ] 1.3 [PoV Core] Add business, demo and AI configuration files
    - Create `config/business.php` (`timezone` from `BUSINESS_TIMEZONE`, default `Asia/Kuala_Lumpur`, comment-tagged as a demo default) and `config/demo.php` (`mode` bool from `DEMO_MODE`, `clock_file` default `demo/fixed-datetime` relative to the private disk)
    - Create `config/ai.php` with `base_url`, `api_key`, `text_model`, `transcription_model`, `speech_model`, `speech_voice` (env-only, `null` when unset, no hardcoded model names) and `timeouts` (text 30, speech 60, transcription 120, extraction 60); add no retry or backoff key
    - Append to `.env.example`: `BUSINESS_TIMEZONE`, `AI_*`, `OPENAI_API_KEY`, `DEMO_MODE=false`, `DEMO_PASSWORD=` (all empty or non-secret); add no `FOUNDATION_PLACEHOLDER_ROLE` and no `DEMO_FIXED_DATE`
    - Create no AI client class and make no network call
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.6, 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.7, 19.1, 23.4_

  - [ ] 1.4 [PoV Core] Implement `App\Support\BusinessClock`
    - `today(?string $timezone = null)` returns a date-only `CarbonImmutable`; `now(?string $timezone = null)` returns date-time; default timezone from `config('business.timezone')`, optional argument overrides it
    - With `demo.mode` true and the Demo_Clock_File (`storage/app/private/demo/fixed-datetime`, content `YYYY-MM-DD HH:MM` in the business timezone) present and valid, `now()` returns that date-time and `today()` its date; file absent returns real time; `demo.mode` false ignores the file
    - Throw `InvalidArgumentException` naming the Demo_Clock_File when demo mode is on and the content is invalid; throw a descriptive exception for an invalid timezone identifier
    - Read the file on each call; bind as a singleton in `AppServiceProvider` so it resolves through the container
    - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6, 19.7, 19.8, 19.9, 19.10_

  - [ ] 1.5 [PoV Core] Implement the `demo:set-date` command
    - Signature `demo:set-date {date} {--time=09:00}`; refuse with a non-zero exit and a message naming `DEMO_MODE` unless `config('demo.mode')` is true (no other guard)
    - Validate `date` as a real `YYYY-MM-DD` calendar date and `--time` as `HH:MM` 24-hour; on invalid input leave the file unchanged, exit non-zero and name the invalid value
    - Write `YYYY-MM-DD HH:MM` to `demo/fixed-datetime` on the private disk, creating the `demo` directory when absent; print the stored date-time and the business timezone
    - _Requirements: 24.1, 24.2, 24.3, 24.4, 24.5, 24.6, 24.8_

  - [ ] 1.6 [PoV Core] Set up the test harness and smoke tests
    - Use the starter kit's test framework as shipped (Pest if present, otherwise PHPUnit); configure `phpunit.xml` for PostgreSQL database `businesspulse_testing` (no SQLite) and add `.env.testing.example` with non-secret values
    - Add the Composer script `test` running the harness
    - Add the smoke tests: application boots and `/` responds; login page renders; BusinessClock demo mode off ignores the Demo_Clock_File; demo mode on honors it for a few fixed dates and times (round trip via `demo:set-date`); file absent returns real time; invalid file content throws; `demo:set-date` refuses when `DEMO_MODE` is false
    - Use deterministic fixtures, a fake private disk, no external provider call, no browser automation and no property-based tool
    - _Requirements: 15.7, 19.2, 19.3, 19.4, 19.5, 19.9, 22.6, 22.7, 22.8, 22.9, 24.3_

- [ ] 2. [PoV Core] Checkpoint - Foundation configuration
  - Run `php artisan migrate` on a scratch PostgreSQL database, `php artisan about`, `php artisan config:show queue` and `composer test`; run `php artisan demo:set-date` with a fixture date and confirm `BusinessClock` via `php artisan tinker`. Ensure all checks pass, ask the user if questions arise.

- [ ] 3. Design system base
  - [ ] 3.1 [PoV Core] Define design tokens, font and chart palette
    - In `resources/css/app.css` define `--background`, `--surface`, `--border`, `--foreground`, `--muted-foreground`, `--sidebar`, `--sidebar-foreground`, `--primary`, `--severity-high|medium|low`, `--success`, `--info` using the values in `docs/ui-ux-design-system.md` §2; add a darker primary text token if `--primary` on `--surface` is below 4.5:1
    - Map tokens to the Tailwind theme so components use tokens, not hex values
    - Install and import `@fontsource-variable/inter`; use no CDN fonts
    - Apply 8px radius to cards/inputs, 6px to badges, 1px `--border` and `shadow-sm` on cards only; no dark mode variant
    - Export the chart palette constants (`#0F9488`, `#334155`, `#D97706`, `#DC2626`, `#99D6CF`) from `resources/js/lib/chartPalette.ts`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

  - [ ] 3.2 [PoV Core] Install shadcn-vue base components and Lucide
    - Install Button, Input, Textarea, Select, Checkbox, Label, Dialog, Sheet, Tabs, Table, Badge, Card, Skeleton, Sonner, DropdownMenu, Popover, Tooltip, Calendar, Progress and Alert under `resources/js/components/ui`
    - Use Lucide as the only icon library
    - Ensure a visible keyboard focus ring on interactive components and a 40px minimum control height below 768px
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [ ] 3.3 [PoV Core] Define shared TypeScript types
    - In `resources/js/types` add `Severity`, `StatusTone`, `NavItem`, `FlashMessage` (with `id`), shared Inertia page props (`flash`, `navigation`) and prop interfaces for KpiCard, SeverityBadge, StatusBadge, EmptyState, ErrorState, EChart; add no `Role`, `TimelineEvent` or unused prop type (S02 and later specs add them)
    - _Requirements: 3.2, 9.8_

- [ ] 4. Backend shared contracts, routing and error handling
  - [ ] 4.1 [PoV Core] Implement the navigation config list, shared props and flash helper
    - Create one typed navigation config list (e.g. `config/navigation.php`) of the 8 items (label, route, Lucide icon name) in order; apply no role filtering and define no role concept
    - Share `navigation` (with `active` computed from current route) and `flash` in `HandleInertiaRequests`; never share `OPENAI_API_KEY` or `DEMO_PASSWORD`
    - Add one server-side flash helper producing `{id, type, message}` with a generated id
    - Add code comments that S01 shows the full list to every visitor and that S02 adds role-based visibility filtering while policies enforce authorization
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 14.6, 15.8_

  - [ ] 4.2 [PoV Core] Add placeholder routes and controllers
    - Define `Route::get` routes and thin controllers for Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes, Settings that render Inertia pages
    - Redirect `/` to Today
    - Ensure `/storage/*` returns the 404 page (no public symlink)
    - _Requirements: 8.1, 8.3, 17.4_

  - [ ] 4.3 [PoV Core] Configure global exception handling
    - In `bootstrap/app.php` `->withExceptions()` render Inertia error pages for 403, 404, 419, 500, 503 inside the app layout, passing status to the `Error` page
    - Keep stack traces, paths, SQL and env values out of responses when `APP_DEBUG=false`
    - Log unhandled exceptions with request path and method only (no bodies, passwords, tokens, API keys)
    - Return Laravel's standard JSON error structure with the same status for JSON/asset requests
    - _Requirements: 2.3, 13.1, 13.5, 13.6, 13.7_

- [ ] 5. Frontend components and shell
  - [ ] 5.1 [PoV Core] Build the `AppShell` layout
    - Sidebar at 248px (>1280px), icon rail with tooltips and accessible names (769–1280px), off-canvas Sheet with menu button (≤768px)
    - Top bar with page title, breadcrumbs, non-functional notification bell and user menu placeholders (no data, no requests); content area max 1280px, padding 24px (16px at ≤768px)
    - Semantic landmarks (`nav`, `main`, `header`), skip link to main content, `aria-current="page"` on the active item, render navigation only from the `navigation` prop
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 7.2, 7.3_

  - [ ] 5.2 [PoV Core] Build PageHeader, KpiCard, SeverityBadge and StatusBadge
    - PageHeader: 24px semibold title, optional description and action slot
    - KpiCard: label, 28px semibold tabular-nums value, optional change (icon + signed text, not color alone), optional caption, `loading` renders Skeleton
    - SeverityBadge (`high|medium|low`, icon + label, `--severity-*` tokens) and StatusBadge (label + tone `neutral|info|success|warning|danger`)
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.8, 12.4_

  - [ ] 5.3 [PoV Core] Build EmptyState and ErrorState
    - EmptyState: icon, one-line message, optional primary action
    - ErrorState: icon, message, retry button emitting `retry`
    - Provide text alternatives for icon-only meaning
    - InsufficientDataNotice and Timeline are not built here; the first consuming spec builds them
    - _Requirements: 9.6, 9.7, 9.8, 11.1, 12.4_

  - [ ] 5.4 [PoV Core] Build the `EChart` wrapper
    - Install `echarts` and import only registered modules (tree-shaken)
    - Props: `option`, required `ariaLabel`, optional `tableData`, `isEmpty`; min container height 240px
    - `ResizeObserver` resize, update on option change without recreating the container, dispose chart and observer on unmount
    - Disable animation under `prefers-reduced-motion: reduce`; default series colors to the chart palette; render EmptyState when `isEmpty`; render a data table alternative when `tableData` is given
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9_

  - [ ] 5.5 [PoV Core] Add toast host, `useToast` and flash consumption
    - Mount Sonner toast host in the app layout with `aria-live`; auto-dismiss 3–5s, manual dismiss
    - Show one toast when the `flash` prop carries a new `id`; track last-shown id in memory so back/forward or reload without a new flash does not re-toast
    - Add typed `useToast` composable for client-side toasts
    - _Requirements: 12.3, 14.1, 14.2, 14.3, 14.4, 14.5, 14.7_

  - [ ] 5.6 [PoV Polish] Add loading states
    - Inertia page progress indicator during visits
    - Layout-matching Skeleton for table rows (the KpiCard loading Skeleton is part of 5.2)
    - `aria-live` region for toast and status text announcements (shared with 5.5)
    - The polling composable and the frontend render error boundary are not built in S01; the first consuming spec builds them as PoV Polish
    - _Requirements: 12.1, 12.2, 12.3, 11.3_

- [ ] 6. Pages
  - [ ] 6.1 [PoV Core] Create placeholder Inertia pages
    - One Vue page per section (Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes, Settings) using the AppShell, a PageHeader with the section name, and an EmptyState stating the section is not yet available; no business data
    - _Requirements: 8.1, 8.2_

  - [ ] 6.2 [PoV Core] Create the Inertia error page
    - One `Error` page handling 403, 404, 419, 500, 503 inside the app layout: status code, plain-language title, one-sentence explanation, link to Today
    - 419: session-expired text and a reload button; 503: maintenance text suggesting retry later
    - _Requirements: 13.1, 13.2, 13.3, 13.4_

- [ ] 7. Tooling and documentation
  - [ ] 7.1 [PoV Core] Add verification and local-run scripts
    - npm scripts `type-check` (vue-tsc), `lint` (ESLint), `format:check` (Prettier) and `build` (Vite); `verify` chains them in order, then runs `composer test` last, and stops at first failure
    - Composer script running Pint in test mode (e.g. `pint-test`); Composer `test` script from task 1.6
    - A single local start command (e.g. `composer dev`) running web server, Vite and one `queue:work --queue=default,ai` worker together
    - _Requirements: 2.4, 16.11, 22.1, 22.2, 22.3, 22.4, 22.5, 22.6_

  - [ ] 7.2 [PoV Core] Write the README and finalize `.env.example`
    - README order: prerequisites with versions, clone, dependency install, `.env` creation, key generation, PostgreSQL database and role creation for both the app database and `businesspulse_testing` (no Docker), migrate, dev server, queue workers, `composer test`, open the app
    - Document the two queues `default` and `ai`, `retry_after=300`, and the deployment Supervisor programs `bp-default` (`php artisan queue:work --queue=default`) and `bp-ai` (`php artisan queue:work --queue=ai`), each with `--tries=3 --timeout=180 --max-time=3600`, `stopwaitsecs=200`, `stopasgroup=true`, `killasgroup=true`; document `queue:restart` after deploy and re-checking `retry_after` if a job timeout rises above 150 seconds
    - Document `demo:set-date {date} [--time=09:00]` (requires `DEMO_MODE=true`) and that deleting `storage/app/private/demo/fixed-datetime` returns the clock to real time
    - Document the Verification_Commands (including `composer test` and `npm run verify`), the `docs/` file list, and statements: no Docker, no real client data until authorized, private-disk downloads need authorized streamed responses (later specs), S01 navigation is not role-filtered and S02 adds role-based visibility and policies, passing checks is not production readiness, reference to architecture §11 for versions
    - Create `.env.example` and `.env.testing.example` entries for every env var read in `config/*.php` (no `FOUNDATION_PLACEHOLDER_ROLE`, no `DEMO_FIXED_DATE`)
    - _Requirements: 2.5, 7.5, 15.1, 15.6, 15.7, 16.8, 16.9, 16.10, 17.5, 20.4, 21.1, 21.2, 21.3, 21.4, 21.5, 22.10, 24.7_

  - [ ] 7.3 [PoV Core] Record verified versions in `docs/architecture.md` §11
    - Replace every `TBD` with installed versions of PHP, Laravel, Node, PostgreSQL, Inertia, Vue, Tailwind, shadcn-vue, ECharts, citing the version command or lock-file entry
    - Record the minimum PHP version required by Laravel 13 from the installed framework's `composer.json`
    - _Requirements: 20.1, 20.2, 20.3_

- [ ] 8. [PoV Core] Final checkpoint - Ensure all checks pass
  - Run `npm run verify` (type-check, lint, format check, build, `composer test`) and the Pint test script, then `php artisan migrate:fresh` on a scratch database. Manually walk through the navigation list, error pages, `demo:set-date`, and responsive widths (360, 768, 1280px). Ensure all checks pass, ask the user if questions arise.

## Notes

- Only the S01 smoke tests are authored here (Requirement 22.8); business tests belong to later specs per `docs/stage-1-refinements.md` R5. There are no optional `*` tasks.
- Each task references granular requirements for traceability.
- Checkpoints run the baseline commands and manual checks from design.md.
- This workflow only produces planning artifacts. Start execution by opening this file and clicking "Start task" next to a task.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["1.3"] },
    { "id": 3, "tasks": ["1.4"] },
    { "id": 4, "tasks": ["1.5"] },
    { "id": 5, "tasks": ["1.6"] },
    { "id": 6, "tasks": ["2"] },
    { "id": 7, "tasks": ["3.1", "3.3", "4.1"] },
    { "id": 8, "tasks": ["3.2"] },
    { "id": 9, "tasks": ["4.2", "4.3", "5.2", "5.3", "5.4"] },
    { "id": 10, "tasks": ["5.1"] },
    { "id": 11, "tasks": ["5.5", "5.6"] },
    { "id": 12, "tasks": ["6.1", "6.2"] },
    { "id": 13, "tasks": ["7.1"] },
    { "id": 14, "tasks": ["7.2", "7.3"] },
    { "id": 15, "tasks": ["8"] }
  ]
}
```
