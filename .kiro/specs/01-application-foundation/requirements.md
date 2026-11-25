# Requirements Document

## Introduction

S01 Application Foundation and Design System creates the technical base of BusinessPulse, a Stage 1 modular monolith built on Laravel 13, PostgreSQL, Vue 3, Inertia.js and TypeScript. S01 delivers the project skeleton, native local development setup (no Docker), the application shell with the complete navigation list, the design system tokens and eight shared UI components, global error handling and toasts, environment configuration, database queue and cache with two named queues, private storage, an AI configuration skeleton, the BusinessClock helper with a file-based demo clock and the `demo:set-date` command, a focused automated test harness, recorded verified versions, a setup README and baseline verification commands.

S01 does not own authentication, users, roles or policies (S02), imports (S03), or any business logic (S04 onward). Because authentication does not exist in S01, the shell renders the complete navigation from one typed config list for every visitor. S02 replaces that list with role-based visibility filtering.

Priority tiers (`docs/stage-1-refinements.md` R1): every S01 item is PoV Core except the table-row Skeleton, page progress indicator and `aria-live` region, which are PoV Polish. The InsufficientDataNotice, Timeline and FileDropzone components, the polling composable and the frontend render error boundary are not built in S01. The first spec that needs each of them builds it (the boundary and polling composable as PoV Polish).

### What S01 introduces

| Kind | Items |
|---|---|
| Services | `App\Support\BusinessClock` (`today()`, `now()`), single source of the business date and time, with a file-based demo clock |
| Commands | `demo:set-date {date} [--time=09:00]`, guarded by `DEMO_MODE=true` |
| Contracts (typed) | Inertia shared props `flash` toast payload and `navigation` items; TypeScript types in `resources/js/types` for `Severity`, `StatusTone`, `NavItem`, `FlashMessage` |
| Configuration | `config/ai.php` skeleton (models and HTTP timeouts, no retry settings), `config/demo.php` (demo mode and clock file path), `config/business.php` (default timezone), `config/queue.php` (`retry_after`, `default` and `ai` queues, `afterCommit`), filesystem settings, `.env.example`, `.env.testing.example` |
| UI components | `AppShell`, `PageHeader`, `KpiCard`, `SeverityBadge`, `StatusBadge`, `EmptyState`, `ErrorState`, `EChart`, toast host (Sonner), shadcn-vue base set |
| Pages | Placeholder pages for Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes, Settings; error pages 403, 404, 419, 500, 503 |
| Documentation | README local setup and deployment notes (Supervisor programs `bp-default` and `bp-ai`), `docs/architecture.md` section 11 filled with verified versions |
| Tooling | Scripts for type-check, lint, format check, build, `composer test` and `verify`; PostgreSQL test database `businesspulse_testing`; smoke tests |

### Deferred to the first consuming spec

InsufficientDataNotice, Timeline, FileDropzone, the polling composable and the frontend render error boundary are not part of S01.

### Dependencies

S01 has no dependencies on other specs. S02 to S09 depend on S01.

### Assumptions

- A1: The official Laravel Vue starter kit is used as the scaffold. Its authentication screens and user model are retained only if removal would break the build. S02 owns their final shape, and S01 does not extend them.
- A2: The business timezone default is `Asia/Kuala_Lumpur` (demo default, not a confirmed client requirement). In S01 the timezone comes from `config/business.php`. S02 supplies the company timezone to BusinessClock once the company exists.
- A3: Placeholder navigation pages contain only a PageHeader and an EmptyState. They contain no business data.
- A4: Demo mode is set by `DEMO_MODE` (boolean). The demo date and time live in the Demo_Clock_File `storage/app/private/demo/fixed-datetime`, written by `demo:set-date`, in the format `YYYY-MM-DD HH:MM` in the business timezone. There is no `DEMO_FIXED_DATE` variable.
- A5: Dark mode is not required (per design system).
- A6: Automated verification is a small set of Laravel feature tests (Pest if the starter kit ships it, otherwise PHPUnit) on a PostgreSQL test database. No browser automation and no property-based tools are used (`docs/stage-1-refinements.md` R5).

## Glossary

- **Application**: The BusinessPulse Laravel 13 and Vue 3 Inertia web application.
- **App_Shell**: The Vue layout containing sidebar, top bar and content area.
- **Navigation_Scaffold**: The typed config list of primary navigation items: Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes, Settings.
- **Design_System**: The CSS variable tokens, Tailwind mapping and reusable Vue components defined in `docs/ui-ux-design-system.md`.
- **Component_Library**: The custom components PageHeader, KpiCard, SeverityBadge, StatusBadge, EmptyState, ErrorState and EChart, together with the App_Shell.
- **Error_Handler**: The Laravel exception handling configuration and Inertia error pages.
- **Toast_Host**: The Sonner-based component that renders transient notifications.
- **Env_Template**: The `.env.example` file.
- **AI_Config**: The `config/ai.php` file.
- **BusinessClock**: The PHP helper returning the business date and time.
- **Demo_Clock_File**: The file `storage/app/private/demo/fixed-datetime` on the Private_Disk holding the demo date and time as `YYYY-MM-DD HH:MM` in the business timezone.
- **Test_Harness**: The Laravel test framework as shipped by the starter kit (Pest if present, otherwise PHPUnit), configured against the PostgreSQL database `businesspulse_testing`.
- **Private_Disk**: The Laravel filesystem disk rooted at `storage/app/private`, not web accessible.
- **Queue_System**: The Laravel database queue.
- **Cache_System**: The Laravel database cache store.
- **Verification_Commands**: The npm and Composer scripts for type-check, lint, format check, build and tests.
- **Version_Record**: Section 11 of `docs/architecture.md`.
- **README**: The root `README.md` with local setup instructions.
- **Severity**: One of `high`, `medium`, `low`.

## Requirements

### Requirement 1: Application scaffold

**User Story:** As a developer, I want a Laravel 13 application scaffolded from the official Vue starter kit, so that all later specs build on a conventional base.

#### Acceptance Criteria

1. THE Application SHALL be a Laravel 13 project using Vue 3 with the Composition API, Inertia.js, Vite and Tailwind CSS.
2. WHEN `php artisan about` is run, THE Application SHALL report the Laravel version as 13.x and the environment as configured in `.env`.
3. THE Application SHALL place backend code under `app/Support` and frontend code under `resources/js/{components,layouts,types,pages}` following `docs/architecture.md` section 4, and SHALL create no empty directory, placeholder service or unused interface for a later spec.
4. THE Application SHALL include no Docker, Docker Compose, Redis, GraphQL, Kubernetes or microservice configuration files.
5. THE Application SHALL declare every Composer and npm dependency in `composer.json` or `package.json` respectively.
6. THE Application SHALL include no Stage 2 feature code or placeholder.

### Requirement 2: Native local development and PostgreSQL

**User Story:** As a developer, I want to run the application natively against PostgreSQL, so that I can develop without containers.

#### Acceptance Criteria

1. THE Application SHALL configure `pgsql` as the default database connection with host, port, database, username and password read from `DB_*` environment variables.
2. WHEN `php artisan migrate` is run against an empty PostgreSQL database with valid credentials, THE Application SHALL complete all migrations without error.
3. IF the database connection fails, THEN THE Application SHALL return the 500 error page in non-debug mode and omit connection credentials from the response.
4. WHEN the documented local start command is run, THE Application SHALL serve the web application, the Vite dev server and the queue worker together as a single local process group.
5. THE README SHALL list the native prerequisites with the required versions and the steps to create the PostgreSQL database and role without Docker.

### Requirement 3: TypeScript strict configuration

**User Story:** As a developer, I want strict TypeScript and typed Inertia props, so that frontend defects are caught at build time.

#### Acceptance Criteria

1. THE Application SHALL set `"strict": true` in `tsconfig.json`.
2. THE Application SHALL define shared Inertia page props types in `resources/js/types`, covering `flash`, `navigation` and the Component_Library prop types.
3. WHEN the type-check command is run on a clean checkout, THE Verification_Commands SHALL exit with status 0.
4. THE Application SHALL contain no use of the `any` type in code authored by S01, except where a third-party type declaration requires it and a comment states the reason.

### Requirement 4: Design tokens and styling base

**User Story:** As a designer, I want the design tokens defined once, so that every screen has a consistent look.

#### Acceptance Criteria

1. THE Design_System SHALL define the CSS variables `--background`, `--surface`, `--border`, `--foreground`, `--muted-foreground`, `--sidebar`, `--sidebar-foreground`, `--primary`, `--severity-high`, `--severity-medium`, `--severity-low`, `--success` and `--info` in `resources/css/app.css` with the values in `docs/ui-ux-design-system.md` section 2.
2. THE Design_System SHALL map the CSS variables to Tailwind theme colors so that components reference tokens and not literal hex values.
3. THE Application SHALL load the Inter variable font from the npm package `@fontsource-variable/inter` and request no font from a CDN.
4. THE Design_System SHALL apply an 8px radius to cards and inputs, a 6px radius to badges, and a 1px `--border` border with `shadow-sm` only on cards.
5. THE Design_System SHALL provide the chart palette teal `#0F9488`, slate `#334155`, amber `#D97706`, red `#DC2626` and light teal `#99D6CF` as exported constants.
6. WHEN text uses the `--primary` color on a `--surface` background, THE Design_System SHALL provide a contrast ratio of at least 4.5:1, using a darker primary text token if the base primary value does not reach that ratio.
7. THE Application SHALL set no dark mode variant.

### Requirement 5: shadcn-vue and Lucide base

**User Story:** As a developer, I want the shared UI primitives installed, so that later specs reuse them.

#### Acceptance Criteria

1. THE Application SHALL include the shadcn-vue components Button, Input, Textarea, Select, Checkbox, Label, Dialog, Sheet, Tabs, Table, Badge, Card, Skeleton, Sonner, DropdownMenu, Popover, Tooltip, Calendar, Progress and Alert under `resources/js/components/ui`.
2. THE Application SHALL use Lucide as the only icon library.
3. THE Application SHALL render every interactive shadcn-vue component with a visible keyboard focus ring.
4. THE Application SHALL set the minimum height of interactive controls on viewports narrower than 768px to 40px.

### Requirement 6: Application shell

**User Story:** As a user, I want a consistent application frame, so that I can navigate predictably.

#### Acceptance Criteria

1. THE App_Shell SHALL render a left sidebar with `--sidebar` background, a top bar containing page title, breadcrumbs, a notification bell placeholder and a user menu placeholder, and a content area with a maximum width of 1280px.
2. WHILE the viewport width is greater than 1280px, THE App_Shell SHALL render the sidebar at 248px width with icon and label for each item.
3. WHILE the viewport width is 1280px or less and greater than 768px, THE App_Shell SHALL render the sidebar as an icon rail with each item exposing its label through a tooltip and an accessible name.
4. WHILE the viewport width is 768px or less, THE App_Shell SHALL hide the sidebar and provide a menu button that opens the navigation in an off-canvas Sheet.
5. THE App_Shell SHALL apply 24px content padding, or 16px while the viewport width is 768px or less.
6. THE App_Shell SHALL expose semantic landmarks for navigation, main content and header.
7. THE App_Shell SHALL display the notification bell and user menu as non-functional placeholders that render no data and make no request.
8. WHEN the user presses Tab from the page start, THE App_Shell SHALL provide a skip link that moves focus to the main content.

### Requirement 7: Navigation scaffold

**User Story:** As a user, I want a consistent navigation list, so that I can reach every section from the shell.

#### Acceptance Criteria

1. THE Navigation_Scaffold SHALL list, in order, Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes and Settings, each with a Lucide icon and a route.
2. THE App_Shell SHALL show all eight navigation items to every visitor.
3. WHEN the current route matches a navigation item, THE App_Shell SHALL mark that item as active visually and with `aria-current="page"`.
4. THE Application SHALL define the navigation items in one typed server-side config list shared with the frontend through the Inertia `navigation` prop, so that the items are not duplicated in Vue components.
5. THE Application SHALL document in the README and in code comments that S01 applies no role filtering to the navigation and that S02 replaces the list with role-based visibility filtering while policies enforce authorization.

### Requirement 8: Placeholder pages

**User Story:** As a developer, I want a routable page per navigation item, so that later specs fill in screens without changing the shell.

#### Acceptance Criteria

1. WHEN a user opens the route of any Navigation_Scaffold item, THE Application SHALL render the page inside the App_Shell with a PageHeader showing the section name.
2. THE Application SHALL render each placeholder page with an EmptyState that names the section and states that the section is not yet available.
3. WHEN a user opens the application root path, THE Application SHALL redirect to the Today page.

### Requirement 9: Component library

**User Story:** As a developer, I want reusable status and layout components, so that later specs present data consistently.

#### Acceptance Criteria

1. THE PageHeader SHALL render a title of 24px semibold, an optional description, and an optional action slot.
2. THE KpiCard SHALL render a label, a value in 28px semibold tabular numerals, an optional change indicator with direction, an optional caption, and a loading state rendered as a Skeleton.
3. WHEN a KpiCard receives a change value, THE KpiCard SHALL render the direction as an icon and signed text, and not by color alone.
4. THE SeverityBadge SHALL accept `high`, `medium` or `low` and render the label and a Lucide icon using the matching `--severity-*` foreground and background tokens.
5. THE StatusBadge SHALL accept a label and a tone of `neutral`, `info`, `success`, `warning` or `danger` and render a pill with a matching token color and the label text.
6. THE EmptyState SHALL render an icon, a one-line explanation and an optional primary action.
7. THE ErrorState SHALL render an icon, a message, and a retry button that emits a `retry` event.
8. THE Component_Library SHALL define a typed props interface for every component in `resources/js/types` or in the component file.

### Requirement 10: EChart wrapper

**User Story:** As a developer, I want a single chart wrapper, so that charts are consistent, small and accessible.

#### Acceptance Criteria

1. THE EChart SHALL import only the ECharts modules it registers (tree-shaken) and not the full ECharts bundle.
2. WHEN the EChart receives an option object, THE EChart SHALL render the chart in a container with a minimum height of 240px.
3. WHEN the container is resized, THE EChart SHALL resize the chart to fit the container.
4. WHEN the EChart receives a changed option object, THE EChart SHALL update the chart without recreating the container.
5. WHEN the EChart component is unmounted, THE EChart SHALL dispose the chart instance and remove its resize observer.
6. THE EChart SHALL require a text alternative prop and render it as an accessible description, and render a visually available data table alternative when a table-data prop is supplied.
7. WHILE the user's system setting `prefers-reduced-motion` is `reduce`, THE EChart SHALL disable chart animation.
8. THE EChart SHALL default series colors to the chart palette defined in Requirement 4.
9. IF the EChart receives an empty dataset flag, THEN THE EChart SHALL render an EmptyState in place of the chart.

### Requirement 11: Components deferred to the first consuming spec

**User Story:** As a developer, I want S01 to ship only the shared components that a foundation screen uses, so that no component is built without a consumer.

#### Acceptance Criteria

1. THE Component_Library SHALL contain only the components defined in Requirements 6, 9 and 10 in S01.
2. THE first spec that needs an InsufficientDataNotice, a Timeline or a FileDropzone SHALL build that component together with its consumer.
3. THE first spec that needs a polling composable or a frontend render error boundary SHALL build it as PoV Polish.

### Requirement 12: Loading and state feedback

**User Story:** As a user, I want clear feedback while data loads or is unavailable, so that I know what is happening.

#### Acceptance Criteria

1. WHILE an Inertia visit is in progress, THE Application SHALL display a page progress indicator.
2. THE Application SHALL provide a layout-matching Skeleton for KpiCard and for table rows.
3. THE Application SHALL announce toast messages through an `aria-live` region.
4. THE Application SHALL render a text alternative or equivalent for each state component that conveys meaning through an icon.

### Requirement 13: Global error handling and error pages

**User Story:** As a user, I want clear error pages, so that I know what went wrong and what to do next.

#### Acceptance Criteria

1. WHEN a request results in HTTP status 403, 404, 419, 500 or 503, THE Error_Handler SHALL render an Inertia error page for that status inside the application layout.
2. THE Error_Handler SHALL show on each error page the status code, a plain-language title, a one-sentence explanation and an action link to the Today page.
3. WHEN the status is 419, THE Error_Handler SHALL show a button that reloads the page and explain that the session expired.
4. WHEN the status is 503, THE Error_Handler SHALL explain that the application is in maintenance and suggest trying again later.
5. WHILE `APP_DEBUG` is false, THE Error_Handler SHALL omit stack traces, file paths, SQL text and environment values from the response.
6. WHEN an unhandled exception occurs, THE Error_Handler SHALL log the exception with request path and method to the application log without request bodies, passwords, tokens or API keys.
7. WHEN a non-Inertia request (JSON or asset request) fails, THE Error_Handler SHALL return the standard Laravel JSON error structure with the same status code.

### Requirement 14: Toasts and flash messages

**User Story:** As a user, I want brief confirmation messages, so that I know my action succeeded or failed.

#### Acceptance Criteria

1. WHEN the server includes a `flash` message of type `success`, `error`, `info` or `warning` in an Inertia response, THE Toast_Host SHALL display one toast with that message and type.
2. THE Toast_Host SHALL dismiss each toast automatically after a duration between 3 and 5 seconds.
3. THE Toast_Host SHALL allow the user to dismiss a toast manually before the timeout.
4. THE Toast_Host SHALL expose each toast to assistive technology through an `aria-live` region.
5. THE Application SHALL expose a typed `useToast` composable for client-side toasts.
6. THE Application SHALL provide a single server-side helper for setting flash messages with the typed structure.
7. WHEN the same flash message is present after a back/forward navigation or page reload without a new server flash, THE Toast_Host SHALL display no repeated toast.

### Requirement 15: Environment configuration

**User Story:** As a developer, I want every environment variable documented, so that I can configure the application without reading code.

#### Acceptance Criteria

1. THE Env_Template SHALL document, with a one-line comment and a non-secret placeholder or default, every environment variable that the Application reads, including `APP_*`, `DB_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `FILESYSTEM_DISK`, `BUSINESS_TIMEZONE`, `AI_BASE_URL`, `AI_TEXT_MODEL`, `AI_TRANSCRIPTION_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE`, `OPENAI_API_KEY`, `DEMO_MODE` and `DEMO_PASSWORD`.
2. THE Env_Template SHALL contain no real secret, API key or password.
3. THE Env_Template SHALL set `DEMO_MODE=false` and leave `DEMO_PASSWORD` empty.
4. THE Application SHALL read environment variables only inside `config/*.php` files and access values elsewhere through `config()`.
5. THE repository SHALL exclude `.env` from version control through `.gitignore`.
6. WHEN a variable is added to code, THE Env_Template SHALL be updated in the same change.
7. THE repository SHALL provide `.env.testing.example` documenting the Test_Harness database settings with no secret.
8. THE Application SHALL never expose `OPENAI_API_KEY` or `DEMO_PASSWORD` to the frontend through Inertia shared props or Vite environment variables.

### Requirement 16: Database queue and cache foundation

**User Story:** As a developer, I want the queue and cache ready, so that later specs can dispatch jobs without extra setup.

#### Acceptance Criteria

1. THE Application SHALL configure the Queue_System with the `database` connection as default.
2. THE Application SHALL configure the Cache_System with the `database` store as default.
3. THE Application SHALL include migrations that create the `jobs`, `job_batches`, `failed_jobs`, `cache` and `cache_locks` tables.
4. WHEN `php artisan migrate` completes, THE Application SHALL have the tables listed in criterion 3 present in PostgreSQL.
5. WHEN a job is dispatched and `php artisan queue:work` is running, THE Queue_System SHALL process the job and remove it from the `jobs` table on success.
6. WHEN a job fails after its allowed attempts, THE Queue_System SHALL record the job in the `failed_jobs` table.
7. THE `config/queue.php` database connection SHALL set `retry_after` to 300 seconds and `after_commit` to true so that jobs dispatch only after the surrounding database transaction commits.
8. THE Application SHALL define two named queues, `default` (imports, evaluation, outcomes, activity, notifications) and `ai` (brief, audio, transcription, extraction), and document both names in the README.
9. THE README SHALL document two Supervisor programs: `bp-default` running `php artisan queue:work --queue=default` and `bp-ai` running `php artisan queue:work --queue=ai`, each with `--tries=3 --timeout=180 --max-time=3600`, `stopwaitsecs=200`, `stopasgroup=true` and `killasgroup=true`.
10. THE README SHALL document the command `php artisan queue:restart` for use after deployment, and a note that `retry_after` must be re-checked whenever a job timeout rises above 150 seconds.
11. THE local start command SHALL run one worker that serves both queues (`--queue=default,ai`).
12. THE Application SHALL include no job class for any later spec.

### Requirement 17: Private storage disk

**User Story:** As a developer, I want a private disk for uploads, so that sensitive files are never publicly accessible.

#### Acceptance Criteria

1. THE Application SHALL configure the default filesystem disk as a local disk rooted at `storage/app/private` with visibility `private`.
2. THE Application SHALL provide no public URL for the Private_Disk and SHALL not create the `public/storage` symlink as part of the documented setup.
3. WHEN a file is written to the Private_Disk, THE Application SHALL store it outside the `public` directory.
4. IF a request path under `/storage` is made, THEN THE Application SHALL return the 404 error page.
5. THE README SHALL state that downloads from the Private_Disk require an authorized streamed response, implemented by later specs.

### Requirement 18: AI configuration skeleton

**User Story:** As a developer, I want AI settings defined in one config file, so that later specs read model identifiers and timeouts from one place.

#### Acceptance Criteria

1. THE AI_Config SHALL define keys `base_url`, `api_key`, `text_model`, `transcription_model`, `speech_model`, `speech_voice`, and a `timeouts` array of HTTP timeouts in seconds with `text` 30, `speech` 60, `transcription` 120 and `extraction` 60.
2. THE AI_Config SHALL read `base_url`, `api_key`, `text_model`, `transcription_model`, `speech_model` and `speech_voice` from `AI_BASE_URL`, `OPENAI_API_KEY`, `AI_TEXT_MODEL`, `AI_TRANSCRIPTION_MODEL`, `AI_SPEECH_MODEL` and `AI_SPEECH_VOICE` respectively.
3. THE AI_Config SHALL contain no hardcoded provider model name; where an environment value is unset, THE AI_Config SHALL return `null` for that key.
4. THE AI_Config SHALL contain no retry or backoff setting, because retries are defined only in the queue jobs of later specs.
5. THE Application SHALL make no network request to any AI provider in S01.
6. THE Application SHALL include no `OpenAiClient`, brief, transcription, extraction or speech class in S01.
7. WHEN `php artisan config:show ai` is run, THE Application SHALL display the configuration and mask the `api_key` value or show it as null.

### Requirement 19: BusinessClock

**User Story:** As a developer, I want one source of the business date and time, so that metrics, imports and demos agree on what "now" is.

#### Acceptance Criteria

1. THE BusinessClock SHALL provide `today()` returning a date-only value and `now()` returning a date-time value, both in the business timezone read from `config/business.php`.
2. WHILE `DEMO_MODE` is false, THE BusinessClock SHALL return the real current date and time and ignore the Demo_Clock_File even when it exists.
3. WHILE `DEMO_MODE` is true and the Demo_Clock_File holds a valid `YYYY-MM-DD HH:MM` value, THE BusinessClock SHALL return that date-time from `now()` and its date from `today()`.
4. WHILE `DEMO_MODE` is true and the Demo_Clock_File does not exist, THE BusinessClock SHALL return the real current date and time.
5. IF `DEMO_MODE` is true and the Demo_Clock_File content is not a valid `YYYY-MM-DD HH:MM` calendar date-time, THEN THE BusinessClock SHALL throw a descriptive exception naming the Demo_Clock_File.
6. WHERE a timezone argument is passed to the BusinessClock, THE BusinessClock SHALL use that timezone in place of the default, enabling S02 to pass the company timezone.
7. IF an invalid timezone identifier is supplied, THEN THE BusinessClock SHALL throw a descriptive exception.
8. THE BusinessClock SHALL be the only code in the Application that determines the business date, and S01 code outside the BusinessClock SHALL not call `now()` or `today()` for business-date purposes.
9. FOR ALL valid dates D and times T written with `demo:set-date` while `DEMO_MODE` is true, `BusinessClock::today()` formatted as `YYYY-MM-DD` SHALL equal D and `BusinessClock::now()` formatted as `HH:MM` SHALL equal T (round-trip property).
10. THE BusinessClock SHALL be resolvable through the Laravel container.

### Requirement 20: Verified versions record

**User Story:** As a team member, I want the verified compatible versions recorded, so that environments can be reproduced.

#### Acceptance Criteria

1. WHEN S01 verification completes, THE Version_Record SHALL replace each `TBD` entry in `docs/architecture.md` section 11 with the installed version of PHP, Laravel, Node, PostgreSQL, Inertia, Vue, Tailwind, shadcn-vue and ECharts.
2. THE Version_Record SHALL cite the source of each version as the output of a version command or the lock file entry.
3. THE Version_Record SHALL record the minimum PHP version required by Laravel 13 as confirmed from the Laravel documentation or `composer.json` of the installed framework.
4. THE README SHALL reference the Version_Record for supported versions.
5. THE Application SHALL commit `composer.lock` and the npm lock file.

### Requirement 21: Local setup README

**User Story:** As a new developer, I want written setup instructions, so that I can run the application in one sitting.

#### Acceptance Criteria

1. THE README SHALL describe, in order: prerequisites, clone, dependency install, `.env` creation, key generation, database creation (including the `businesspulse_testing` test database), migration, frontend build or dev server, queue workers for the `default` and `ai` queues, running `composer test`, and opening the application.
2. THE README SHALL list the Verification_Commands with their exact invocations.
3. THE README SHALL list the repository documentation in `docs/` with a one-line description of each.
4. THE README SHALL state that Docker is not used and that real client data must not be loaded until authorized.
5. WHEN a developer follows the README on a machine with the prerequisites, THE Application SHALL start and render the Today page without additional undocumented steps.

### Requirement 22: Baseline verification commands and test harness

**User Story:** As a developer, I want standard check commands and a small automated test harness, so that every later spec has the same quality baseline.

#### Acceptance Criteria

1. THE Application SHALL provide an npm script `type-check` running the Vue TypeScript compiler check, `lint` running ESLint, `format:check` running Prettier in check mode, and `build` running the Vite production build.
2. THE Application SHALL provide a Composer script that runs the PHP code style check using Laravel Pint in test mode.
3. WHEN each of the scripts in criteria 1, 2 and 6 is run on a clean checkout, THE Verification_Commands SHALL exit with status 0.
4. IF a script finds a violation or a failing test, THEN THE Verification_Commands SHALL exit with a non-zero status and print the file and line or the failing test name.
5. THE Application SHALL provide a single npm script `verify` that runs `type-check`, `lint`, `format:check`, `build` and `composer test` in order and stops at the first failure.
6. THE Application SHALL provide a Composer script `test` that runs the Test_Harness.
7. THE Test_Harness SHALL use the Laravel test framework as shipped by the starter kit (Pest if present, otherwise PHPUnit), connect to the PostgreSQL database `businesspulse_testing` configured in `phpunit.xml` and `.env.testing.example`, and use no SQLite connection.
8. THE Application SHALL include a smoke test set containing a test that the application boots and serves a response, a test that the login page renders, and tests of the BusinessClock demo-mode behavior in Requirement 19 (demo mode off ignores the Demo_Clock_File, demo mode on honors it, an invalid file content throws).
9. THE Test_Harness SHALL use deterministic fixtures, call no external provider, and use no browser automation or property-based testing tool.
10. THE README SHALL state that passing Verification_Commands does not demonstrate production readiness.

### Requirement 23: Security and scope baseline

**User Story:** As the product owner, I want the foundation to be safe by default and limited to Stage 1, so that later work is not compromised.

#### Acceptance Criteria

1. THE Application SHALL enable CSRF protection on all state-changing web routes.
2. THE Application SHALL set `APP_DEBUG=false` in the Env_Template.
3. THE Application SHALL set secure cookie settings so that `SESSION_SECURE_COOKIE` is documented in the Env_Template with guidance to enable it under HTTPS.
4. THE Application SHALL send no emails, notifications or outbound requests in S01.
5. THE Application SHALL contain no authentication logic authored by S01, no user, role or policy definitions, no import code, and no business metric, rule, issue, brief, meeting or task logic.
6. THE Application SHALL include no Stage 2 feature, including extra departments, a configurable rule builder, scheduled briefs, reminders, WhatsApp or external email.

### Requirement 24: Demo date command

**User Story:** As a presenter, I want to set the demo date and time from the command line, so that the demo can run on a chosen business date without changing the server clock.

#### Acceptance Criteria

1. WHEN `php artisan demo:set-date {date}` is run while `DEMO_MODE` is true and `{date}` is a valid `YYYY-MM-DD` calendar date, THE Application SHALL write the date and the time `09:00` to the Demo_Clock_File and exit with status 0.
2. WHERE the `--time=HH:MM` option is supplied with a valid 24-hour time, THE Application SHALL write that time to the Demo_Clock_File in place of `09:00`.
3. IF `DEMO_MODE` is not true, THEN THE Application SHALL refuse to run `demo:set-date`, leave the Demo_Clock_File unchanged and exit with a non-zero status and a message naming `DEMO_MODE`.
4. IF the date or the time is not valid, THEN THE Application SHALL leave the Demo_Clock_File unchanged and exit with a non-zero status and a message naming the invalid value.
5. WHEN `demo:set-date` completes, THE Application SHALL print the stored date-time and the business timezone.
6. THE Application SHALL write the Demo_Clock_File to the Private_Disk path `demo/fixed-datetime`, creating the `demo` directory when absent.
7. THE README SHALL document `demo:set-date` and state that deleting the Demo_Clock_File returns the BusinessClock to real time.
8. THE `demo:set-date` command SHALL be guarded by `DEMO_MODE=true` only, with no other environment condition.
