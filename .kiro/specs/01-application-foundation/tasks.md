# Implementation Plan: Application Foundation and Design System (S01)

## Overview

Implementation is TypeScript (Vue 3 + Inertia) and PHP (Laravel 13), as named in design.md. The plan scaffolds from the official Laravel Vue starter kit, adds configuration and `BusinessClock`, then the design tokens, shared contracts, shell, components, error handling and placeholder pages, and finishes with tooling and documentation.

Per `.kiro/steering/verification.md` and Requirement 22.6, no unit or end-to-end test suites are authored. The design has no formal Correctness Properties section for property-based testing, so there are no property test tasks. Verification uses the baseline commands (`npm run verify`, Pint), `migrate`, and the manual fixture checks listed in design.md. Checkpoints run those commands.

## Tasks

- [ ] 1. Project scaffold, configuration and BusinessClock
  - [ ] 1.1 Scaffold the application from the Laravel Vue starter kit
    - Create the Laravel 13 + Inertia + Vue 3 (Composition API) + Vite + Tailwind project in the workspace root without overwriting `docs/`, `.kiro/` or `.git`
    - Create the folders `app/Actions`, `app/Services/{AI,Imports,Intelligence}`, `app/Jobs`, `app/Policies`, `app/Enums`, `app/Support` and `resources/js/{components,layouts,composables,types,pages}` (add `.gitkeep` where empty)
    - Set `"strict": true` in `tsconfig.json`, tighten ESLint to flag unexplained `any`
    - Keep starter-kit auth screens and `User` model only where removal breaks the build; do not extend them (Assumption A1)
    - Ensure no Docker, Redis, GraphQL or Kubernetes files exist; confirm `.env` is in `.gitignore`; commit lock files
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 3.1, 3.4, 15.5, 20.5, 23.5, 23.6_

  - [ ] 1.2 Configure PostgreSQL, queue, cache, sessions and private storage
    - Set `pgsql` as default connection reading only `DB_*` env vars in `config/database.php`
    - Set database defaults for queue (`QUEUE_CONNECTION=database`) and cache (`CACHE_STORE=database`); make sure migrations exist for `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`, `sessions`
    - Configure the default filesystem disk as local, rooted at `storage/app/private`, visibility `private`, with no public URL and no `public/storage` link
    - Create `.env.example` with `APP_DEBUG=false`, `DB_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `FILESYSTEM_DISK`, `SESSION_SECURE_COOKIE` (with HTTPS guidance comment), each with a one-line comment and no secrets
    - Confirm CSRF middleware remains enabled
    - _Requirements: 2.1, 2.2, 2.3, 15.1, 15.2, 15.4, 16.1, 16.2, 16.3, 16.4, 16.8, 17.1, 17.2, 17.3, 23.1, 23.2, 23.3_

  - [ ] 1.3 Add business, demo and AI configuration files
    - Create `config/business.php` (`timezone` from `BUSINESS_TIMEZONE`, default `Asia/Kuala_Lumpur`, comment-tagged as a demo default) and `config/demo.php` (`mode` bool from `DEMO_MODE`, `fixed_date` from `DEMO_FIXED_DATE`)
    - Create `config/ai.php` with `base_url`, `api_key`, `text_model`, `transcription_model`, `speech_model`, `speech_voice` (env-only, `null` when unset, no hardcoded model names), `timeouts` (text 30, transcription 120, speech 60) and `retry` (tries 3, backoff 10/30/60)
    - Append to `.env.example`: `BUSINESS_TIMEZONE`, `FOUNDATION_PLACEHOLDER_ROLE`, `AI_*`, `OPENAI_API_KEY`, `DEMO_MODE=false`, `DEMO_FIXED_DATE=`, `DEMO_PASSWORD=` (all empty or non-secret)
    - Create no AI client class and make no network call
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.6, 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 19.1, 23.4_

  - [ ] 1.4 Implement `App\Support\BusinessClock`
    - `today(?string $timezone = null)` returns a date-only `CarbonImmutable`; `now(?string $timezone = null)` returns date-time
    - Default timezone from `config('business.timezone')`; optional timezone argument overrides it
    - With demo mode on and a valid `YYYY-MM-DD` fixed date, `today()` returns that date and `now()` returns that date at the current local time of day; with demo mode off, ignore the fixed date
    - Throw `InvalidArgumentException` naming `DEMO_FIXED_DATE` when demo mode is on and the date is empty or not a valid calendar date; throw a descriptive exception for an invalid timezone identifier
    - Bind as a singleton in `AppServiceProvider` so it resolves through the container
    - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6, 19.7, 19.8, 19.9, 19.10, 19.11_

- [ ] 2. Checkpoint - Foundation configuration
  - Run `php artisan migrate` on a scratch PostgreSQL database and `php artisan about`; check `BusinessClock` against the design.md fixture dates via `php artisan tinker`. Ensure all checks pass, ask the user if questions arise.

- [ ] 3. Design system base
  - [ ] 3.1 Define design tokens, font and chart palette
    - In `resources/css/app.css` define `--background`, `--surface`, `--border`, `--foreground`, `--muted-foreground`, `--sidebar`, `--sidebar-foreground`, `--primary`, `--severity-high|medium|low`, `--success`, `--info` using the values in `docs/ui-ux-design-system.md` §2; add a darker primary text token if `--primary` on `--surface` is below 4.5:1
    - Map tokens to the Tailwind theme so components use tokens, not hex values
    - Install and import `@fontsource-variable/inter`; use no CDN fonts
    - Apply 8px radius to cards/inputs, 6px to badges, 1px `--border` and `shadow-sm` on cards only; no dark mode variant
    - Export the chart palette constants (`#0F9488`, `#334155`, `#D97706`, `#DC2626`, `#99D6CF`) from `resources/js/lib/chartPalette.ts`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

  - [ ] 3.2 Install shadcn-vue base components and Lucide
    - Install Button, Input, Textarea, Select, Checkbox, Label, Dialog, Sheet, Tabs, Table, Badge, Card, Skeleton, Sonner, DropdownMenu, Popover, Tooltip, Calendar, Progress and Alert under `resources/js/components/ui`
    - Use Lucide as the only icon library
    - Ensure a visible keyboard focus ring on interactive components and a 40px minimum control height below 768px
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [ ] 3.3 Define shared TypeScript types
    - In `resources/js/types` add `Severity`, `StatusTone`, `Role`, `NavItem`, `FlashMessage` (with `id`), `TimelineEvent`, shared Inertia page props (`auth.role`, `flash`, `navigation`) and prop interfaces for KpiCard, SeverityBadge, StatusBadge, EmptyState, ErrorState, InsufficientDataNotice, EChart, FileDropzone, Timeline
    - _Requirements: 3.2, 9.11_

- [ ] 4. Backend shared contracts, routing and error handling
  - [ ] 4.1 Implement the navigation definition, shared props and flash helper
    - Create `app/Support/Navigation.php` as the single definition of the 8 items (label, route, Lucide icon name, allowed roles); owner and manager see all, staff sees Today and Tasks
    - Resolve the placeholder role: `FOUNDATION_PLACEHOLDER_ROLE` (default `owner`) only when `APP_ENV=local`, otherwise `staff`; read via `config()` only
    - Share `auth.role`, role-filtered `navigation` (with `active` computed from current route) and `flash` in `HandleInertiaRequests`; never share `OPENAI_API_KEY` or `DEMO_PASSWORD`
    - Add one server-side flash helper producing `{id, type, message}` with a generated id
    - Add code comments that navigation visibility is display-only and S02 policies enforce authorization
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 14.6, 15.7_

  - [ ] 4.2 Add placeholder routes and controllers
    - Define `Route::get` routes and thin controllers for Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes, Settings that render Inertia pages
    - Redirect `/` to Today
    - Return 403 when the route's item is not in the current role's visible set, using the `Navigation` definition (no Policy)
    - Ensure `/storage/*` returns the 404 page (no public symlink)
    - _Requirements: 8.1, 8.3, 8.4, 17.4_

  - [ ] 4.3 Configure global exception handling
    - In `bootstrap/app.php` `->withExceptions()` render Inertia error pages for 403, 404, 419, 500, 503 inside the app layout, passing status to the `Error` page
    - Keep stack traces, paths, SQL and env values out of responses when `APP_DEBUG=false`
    - Log unhandled exceptions with request path and method only (no bodies, passwords, tokens, API keys)
    - Return Laravel's standard JSON error structure with the same status for JSON/asset requests
    - _Requirements: 2.3, 13.1, 13.5, 13.6, 13.7_

- [ ] 5. Frontend components and shell
  - [ ] 5.1 Build the `AppShell` layout
    - Sidebar at 248px (>1280px), icon rail with tooltips and accessible names (769–1280px), off-canvas Sheet with menu button (≤768px)
    - Top bar with page title, breadcrumbs, non-functional notification bell and user menu placeholders (no data, no requests); content area max 1280px, padding 24px (16px at ≤768px)
    - Semantic landmarks (`nav`, `main`, `header`), skip link to main content, `aria-current="page"` on the active item, render navigation only from the `navigation` prop
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 7.5_

  - [ ] 5.2 Build PageHeader, KpiCard, SeverityBadge and StatusBadge
    - PageHeader: 24px semibold title, optional description and action slot
    - KpiCard: label, 28px semibold tabular-nums value, optional change (icon + signed text, not color alone), optional caption, `loading` renders Skeleton
    - SeverityBadge (`high|medium|low`, icon + label, `--severity-*` tokens) and StatusBadge (label + tone `neutral|info|success|warning|danger`)
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.11, 12.5_

  - [ ] 5.3 Build EmptyState, ErrorState, InsufficientDataNotice and Timeline
    - EmptyState: icon, one-line message, optional primary action
    - ErrorState: icon, message, retry button emitting `retry`
    - InsufficientDataNotice: `--info` neutral style (never red) listing missing items
    - Timeline: ordered events in supplied order with timestamp, title, optional description; EmptyState when empty
    - Provide text alternatives for icon-only meaning
    - _Requirements: 9.6, 9.7, 9.8, 9.9, 9.10, 9.11, 12.5_

  - [ ] 5.4 Build the `EChart` wrapper
    - Install `echarts` and import only registered modules (tree-shaken)
    - Props: `option`, required `ariaLabel`, optional `tableData`, `isEmpty`; min container height 240px
    - `ResizeObserver` resize, update on option change without recreating the container, dispose chart and observer on unmount
    - Disable animation under `prefers-reduced-motion: reduce`; default series colors to the chart palette; render EmptyState when `isEmpty`; render a data table alternative when `tableData` is given
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9_

  - [ ] 5.5 Build `FileDropzone`
    - Drag-and-drop plus keyboard-operable picker button; props for allowed extensions, MIME types and max size in MB
    - Valid file: emit `selected(file)` and show name and size; invalid type or oversize: no event, inline message naming allowed types or max size
    - Remove button clears selection and emits `cleared`; distinct drag-over state
    - Code comment stating server-side validation (S03, S06) is authoritative
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8_

  - [ ] 5.6 Add toast host, `useToast` and flash consumption
    - Mount Sonner toast host in the app layout with `aria-live`; auto-dismiss 3–5s, manual dismiss
    - Show one toast when the `flash` prop carries a new `id`; track last-shown id in memory so back/forward or reload without a new flash does not re-toast
    - Add typed `useToast` composable for client-side toasts
    - _Requirements: 12.4, 14.1, 14.2, 14.3, 14.4, 14.5, 14.7_

  - [ ] 5.7 Add loading states and polling composable
    - Inertia page progress indicator during visits
    - Layout-matching Skeletons for KpiCard and table rows
    - `usePolling(reload, active)` composable: polls every 3s while `active` is true, stops when false or on unmount
    - `aria-live` region for job-status text changes
    - _Requirements: 12.1, 12.2, 12.3, 12.4_

  - [ ] 5.8 Add the page-level render error boundary
    - Use `onErrorCaptured` in the layout (or a wrapper component) to replace a failing page with `ErrorState` and a retry action while the shell stays usable
    - _Requirements: 13.8_

- [ ] 6. Pages
  - [ ] 6.1 Create placeholder Inertia pages
    - One Vue page per section (Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes, Settings) using the AppShell, a PageHeader with the section name, and an EmptyState stating the section is not yet available; no business data
    - _Requirements: 8.1, 8.2_

  - [ ] 6.2 Create the Inertia error page
    - One `Error` page handling 403, 404, 419, 500, 503 inside the app layout: status code, plain-language title, one-sentence explanation, link to Today
    - 419: session-expired text and a reload button; 503: maintenance text suggesting retry later
    - _Requirements: 13.1, 13.2, 13.3, 13.4_

- [ ] 7. Tooling and documentation
  - [ ] 7.1 Add verification and local-run scripts
    - npm scripts `type-check` (vue-tsc), `lint` (ESLint), `format:check` (Prettier) and `build` (Vite); `verify` chains them in order and stops at first failure
    - Composer script running Pint in test mode (e.g. `pint-test`)
    - A single local start command (e.g. `composer dev`) running web server, Vite and `queue:work` together
    - _Requirements: 2.4, 22.1, 22.2, 22.3, 22.4, 22.5, 22.6_

  - [ ] 7.2 Write the README and finalize `.env.example`
    - README order: prerequisites with versions, clone, dependency install, `.env` creation, key generation, PostgreSQL database and role creation (no Docker), migrate, dev server, queue worker, open the app
    - Document `queue:work --tries=3 --timeout=180`, `queue:restart`, the Verification_Commands, the `docs/` file list, and statements: no Docker, no real client data until authorized, private-disk downloads need authorized streamed responses (later specs), nav visibility is display-only, passing checks is not production readiness, reference to architecture §11 for versions
    - Confirm every env var read in `config/*.php` appears in `.env.example`
    - _Requirements: 2.5, 7.7, 15.1, 15.6, 16.7, 17.5, 20.4, 21.1, 21.2, 21.3, 21.4, 21.5, 22.7_

  - [ ] 7.3 Record verified versions in `docs/architecture.md` §11
    - Replace every `TBD` with installed versions of PHP, Laravel, Node, PostgreSQL, Inertia, Vue, Tailwind, shadcn-vue, ECharts, citing the version command or lock-file entry
    - Record the minimum PHP version required by Laravel 13 from the installed framework's `composer.json`
    - _Requirements: 20.1, 20.2, 20.3_

- [ ] 8. Final checkpoint - Ensure all checks pass
  - Run `npm run verify` and the Pint test script, then `php artisan migrate:fresh` on a scratch database. Manually walk through role navigation (owner, manager, staff), error pages, and responsive widths (360, 768, 1280px). Ensure all checks pass, ask the user if questions arise.

## Notes

- No test-suite tasks are included, per Requirement 22.6 and `.kiro/steering/verification.md`; there are therefore no `*` optional tasks.
- Each task references granular requirements for traceability.
- Checkpoints run the baseline commands and manual fixture checks from design.md.
- This workflow only produces planning artifacts. Start execution by opening this file and clicking "Start task" next to a task.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["1.3"] },
    { "id": 3, "tasks": ["1.4"] },
    { "id": 4, "tasks": ["2"] },
    { "id": 5, "tasks": ["3.1", "3.3", "4.1"] },
    { "id": 6, "tasks": ["3.2"] },
    { "id": 7, "tasks": ["4.2", "4.3", "5.2", "5.3", "5.4", "5.5", "5.7"] },
    { "id": 8, "tasks": ["5.1"] },
    { "id": 9, "tasks": ["5.6"] },
    { "id": 10, "tasks": ["5.8"] },
    { "id": 11, "tasks": ["6.1", "6.2"] },
    { "id": 12, "tasks": ["7.1"] },
    { "id": 13, "tasks": ["7.2", "7.3"] },
    { "id": 14, "tasks": ["8"] }
  ]
}
```
