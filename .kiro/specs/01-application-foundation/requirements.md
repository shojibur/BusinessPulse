# Requirements Document

## Introduction

S01 Application Foundation and Design System creates the technical base of BusinessPulse, a Stage 1 modular monolith built on Laravel 13, PostgreSQL, Vue 3, Inertia.js and TypeScript. S01 delivers the project skeleton, native local development setup (no Docker), the application shell with role-aware navigation scaffold, the design system tokens and reusable UI components, global error handling and toasts, environment configuration, database queue and cache, private storage, an AI configuration skeleton, the BusinessClock helper, recorded verified versions, a setup README and baseline verification commands.

S01 does not own authentication, users, roles or policies (S02), imports (S03), or any business logic (S04 onward). Because authentication does not exist in S01, the shell is rendered with a placeholder role supplied through a typed Inertia prop so that navigation visibility can be demonstrated and later replaced by S02 without changing the shell.

### What S01 introduces

| Kind | Items |
|---|---|
| Services | `App\Support\BusinessClock` (`today()`, `now()`), single source of the business date |
| Contracts (typed) | Inertia shared props `auth.role` placeholder, `flash` toast payload, `navigation` items; TypeScript types in `resources/js/types` for `Severity`, `StatusTone`, `NavItem`, `FlashMessage`, `TimelineEvent`, `Role` |
| Configuration | `config/ai.php` skeleton, `config/demo.php` (demo mode and fixed date), `config/business.php` (default timezone), queue/cache/filesystem settings, `.env.example` |
| UI components | `AppShell`, `PageHeader`, `KpiCard`, `SeverityBadge`, `StatusBadge`, `EmptyState`, `ErrorState`, `InsufficientDataNotice`, `EChart`, `FileDropzone`, `Timeline`, toast host (Sonner), shadcn-vue base set |
| Pages | Placeholder pages for Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes, Settings; error pages 403, 404, 419, 500, 503 |
| Documentation | README local setup, `docs/architecture.md` section 11 filled with verified versions |
| Tooling | Scripts for type-check, lint, format check and build |

### Dependencies

S01 has no dependencies on other specs. S02 to S09 depend on S01.

### Assumptions

- A1: The official Laravel Vue starter kit is used as the scaffold. Its authentication screens and user model are retained only if removal would break the build. S02 owns their final shape, and S01 does not extend them.
- A2: The business timezone default is `Asia/Kuala_Lumpur` (demo default, not a confirmed client requirement). In S01 the timezone comes from `config/business.php`. S02 supplies the company timezone to BusinessClock once the company exists.
- A3: Placeholder navigation pages contain only a PageHeader and an EmptyState. They contain no business data.
- A4: The demo mode and fixed date are set by `DEMO_MODE` (boolean) and `DEMO_FIXED_DATE` (format `YYYY-MM-DD`).
- A5: Dark mode is not required (per design system).
- A6: Unit and end-to-end test suites are out of Stage 1 scope. Verification uses the baseline commands and manual checks.

## Glossary

- **Application**: The BusinessPulse Laravel 13 and Vue 3 Inertia web application.
- **App_Shell**: The Vue layout containing sidebar, top bar and content area.
- **Navigation_Scaffold**: The list of primary navigation items: Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes, Settings.
- **Placeholder_Role**: A typed role value (`owner`, `manager`, `staff`) supplied to the shell by a shared Inertia prop in S01, replaced by authenticated user data in S02.
- **Design_System**: The CSS variable tokens, Tailwind mapping and reusable Vue components defined in `docs/ui-ux-design-system.md`.
- **Component_Library**: The custom components KpiCard, SeverityBadge, StatusBadge, EmptyState, ErrorState, InsufficientDataNotice, EChart, FileDropzone, PageHeader, Timeline.
- **Error_Handler**: The Laravel exception handling configuration and Inertia error pages.
- **Toast_Host**: The Sonner-based component that renders transient notifications.
- **Env_Template**: The `.env.example` file.
- **AI_Config**: The `config/ai.php` file.
- **BusinessClock**: The PHP helper returning the business date and time.
- **Private_Disk**: The Laravel filesystem disk rooted at `storage/app/private`, not web accessible.
- **Queue_System**: The Laravel database queue.
- **Cache_System**: The Laravel database cache store.
- **Verification_Commands**: The npm and Composer scripts for type-check, lint, format check and build.
- **Version_Record**: Section 11 of `docs/architecture.md`.
- **README**: The root `README.md` with local setup instructions.
- **Severity**: One of `high`, `medium`, `low`.

## Requirements

### Requirement 1: Application scaffold

**User Story:** As a developer, I want a Laravel 13 application scaffolded from the official Vue starter kit, so that all later specs build on a conventional base.

#### Acceptance Criteria

1. THE Application SHALL be a Laravel 13 project using Vue 3 with the Composition API, Inertia.js, Vite and Tailwind CSS.
2. WHEN `php artisan about` is run, THE Application SHALL report the Laravel version as 13.x and the environment as configured in `.env`.
3. THE Application SHALL organize backend code in the folders `app/Actions`, `app/Services`, `app/Services/AI`, `app/Services/Imports`, `app/Services/Intelligence`, `app/Jobs`, `app/Policies`, `app/Enums`, `app/Support` and frontend code in `resources/js/{components,layouts,composables,types,pages}` as defined in `docs/architecture.md` section 4.
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
2. THE Application SHALL define shared Inertia page props types in `resources/js/types`, covering `auth.role`, `flash`, `navigation` and the Component_Library prop types.
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

### Requirement 7: Role-aware navigation scaffold

**User Story:** As a user, I want to see only the sections that my role may use, so that the interface is not cluttered.

#### Acceptance Criteria

1. THE Navigation_Scaffold SHALL list, in order, Today, Issues, Data Imports, Briefs, Meetings, Tasks, Outcomes and Settings, each with a Lucide icon and a route.
2. WHILE the Placeholder_Role is `owner`, THE App_Shell SHALL show all eight navigation items.
3. WHILE the Placeholder_Role is `manager`, THE App_Shell SHALL show all eight navigation items.
4. WHILE the Placeholder_Role is `staff`, THE App_Shell SHALL show only Today and Tasks.
5. WHEN the current route matches a navigation item, THE App_Shell SHALL mark that item as active visually and with `aria-current="page"`.
6. THE Application SHALL define navigation visibility in one server-side definition shared with the frontend through the Inertia `navigation` prop, so that the visible items are not duplicated in Vue components.
7. THE Application SHALL document in the README and in code comments that navigation visibility is a display convenience and that authorization is enforced by S02 policies.
8. WHERE the `APP_ENV` is `local`, THE Application SHALL read the Placeholder_Role from the `FOUNDATION_PLACEHOLDER_ROLE` environment variable with a default of `owner`.
9. IF the `APP_ENV` is not `local`, THEN THE Application SHALL ignore `FOUNDATION_PLACEHOLDER_ROLE` and use `staff`.

### Requirement 8: Placeholder pages

**User Story:** As a developer, I want a routable page per navigation item, so that later specs fill in screens without changing the shell.

#### Acceptance Criteria

1. WHEN a user opens the route of any Navigation_Scaffold item permitted for the Placeholder_Role, THE Application SHALL render the page inside the App_Shell with a PageHeader showing the section name.
2. THE Application SHALL render each placeholder page with an EmptyState that names the section and states that the section is not yet available.
3. WHEN a user opens the route of a Navigation_Scaffold item that the Placeholder_Role does not see, THE Application SHALL return the 403 error page.
4. WHEN a user opens the application root path, THE Application SHALL redirect to the Today page.

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
8. THE InsufficientDataNotice SHALL render in the `--info` neutral style, never in a red error style, and list the missing data items supplied to it.
9. THE Timeline SHALL render an ordered list of events, each with a timestamp, a title and an optional description, in the order supplied.
10. WHEN the Timeline receives an empty list, THE Timeline SHALL render an EmptyState.
11. THE Component_Library SHALL define a typed props interface for every component in `resources/js/types` or in the component file.

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

### Requirement 11: FileDropzone

**User Story:** As a developer, I want a file selection component, so that import and meeting upload screens can reuse it.

#### Acceptance Criteria

1. THE FileDropzone SHALL accept files by drag-and-drop and by a keyboard-operable file picker button.
2. THE FileDropzone SHALL accept configuration of allowed extensions, allowed MIME types and maximum size in megabytes through props.
3. WHEN a file is selected that satisfies the configuration, THE FileDropzone SHALL emit a `selected` event with the file and display the file name and size.
4. IF a selected file has a disallowed extension or MIME type, THEN THE FileDropzone SHALL reject the file, emit no `selected` event, and display a message naming the allowed types.
5. IF a selected file exceeds the maximum size, THEN THE FileDropzone SHALL reject the file, emit no `selected` event, and display a message stating the maximum size.
6. WHEN the user removes the selected file, THE FileDropzone SHALL clear the selection and emit a `cleared` event.
7. WHILE a file is dragged over the drop area, THE FileDropzone SHALL show a distinct highlighted state.
8. THE FileDropzone SHALL treat client-side checks as a convenience and document in code comments that server-side validation is authoritative (owned by S03 and S06).

### Requirement 12: Loading, empty, error and insufficient-data states

**User Story:** As a user, I want clear feedback while data loads or is unavailable, so that I know what is happening.

#### Acceptance Criteria

1. WHILE an Inertia visit is in progress, THE Application SHALL display a page progress indicator.
2. THE Application SHALL provide a layout-matching Skeleton for KpiCard and for table rows.
3. THE Application SHALL provide a composable that polls a supplied reload callback every 3 seconds while a supplied `active` flag is true and stops polling when the flag becomes false or the component unmounts.
4. THE Application SHALL announce job status text changes and toast messages through an `aria-live` region.
5. THE Application SHALL render a text alternative or equivalent for each state component that conveys meaning through an icon.

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
8. IF a frontend render error occurs inside a page component, THEN THE Application SHALL display an ErrorState with a retry action in place of the page content and leave the App_Shell usable.

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

1. THE Env_Template SHALL document, with a one-line comment and a non-secret placeholder or default, every environment variable that the Application reads, including `APP_*`, `DB_*`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `FILESYSTEM_DISK`, `BUSINESS_TIMEZONE`, `FOUNDATION_PLACEHOLDER_ROLE`, `AI_BASE_URL`, `AI_TEXT_MODEL`, `AI_TRANSCRIPTION_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE`, `OPENAI_API_KEY`, `DEMO_MODE`, `DEMO_FIXED_DATE` and `DEMO_PASSWORD`.
2. THE Env_Template SHALL contain no real secret, API key or password.
3. THE Env_Template SHALL set `DEMO_MODE=false` and leave `DEMO_FIXED_DATE` and `DEMO_PASSWORD` empty.
4. THE Application SHALL read environment variables only inside `config/*.php` files and access values elsewhere through `config()`.
5. THE repository SHALL exclude `.env` from version control through `.gitignore`.
6. WHEN a variable is added to code, THE Env_Template SHALL be updated in the same change.
7. THE Application SHALL never expose `OPENAI_API_KEY` or `DEMO_PASSWORD` to the frontend through Inertia shared props or Vite environment variables.

### Requirement 16: Database queue and cache foundation

**User Story:** As a developer, I want the queue and cache ready, so that later specs can dispatch jobs without extra setup.

#### Acceptance Criteria

1. THE Application SHALL configure the Queue_System with the `database` connection as default.
2. THE Application SHALL configure the Cache_System with the `database` store as default.
3. THE Application SHALL include migrations that create the `jobs`, `job_batches`, `failed_jobs`, `cache` and `cache_locks` tables.
4. WHEN `php artisan migrate` completes, THE Application SHALL have the tables listed in criterion 3 present in PostgreSQL.
5. WHEN a job is dispatched and `php artisan queue:work` is running, THE Queue_System SHALL process the job and remove it from the `jobs` table on success.
6. WHEN a job fails after its allowed attempts, THE Queue_System SHALL record the job in the `failed_jobs` table.
7. THE README SHALL document the worker command `php artisan queue:work --tries=3 --timeout=180` and the command `php artisan queue:restart` for use after deployment.
8. THE Application SHALL include no job class for any later spec.

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

1. THE AI_Config SHALL define keys `base_url`, `api_key`, `text_model`, `transcription_model`, `speech_model`, `speech_voice`, and a `timeouts` array with `text` 30, `transcription` 120 and `speech` 60 seconds, and a `retry` array with `tries` 3 and `backoff` of 10, 30 and 60 seconds.
2. THE AI_Config SHALL read `base_url`, `api_key`, `text_model`, `transcription_model`, `speech_model` and `speech_voice` from `AI_BASE_URL`, `OPENAI_API_KEY`, `AI_TEXT_MODEL`, `AI_TRANSCRIPTION_MODEL`, `AI_SPEECH_MODEL` and `AI_SPEECH_VOICE` respectively.
3. THE AI_Config SHALL contain no hardcoded provider model name; where an environment value is unset, THE AI_Config SHALL return `null` for that key.
4. THE Application SHALL make no network request to any AI provider in S01.
5. THE Application SHALL include no `OpenAiClient`, brief, transcription, extraction or speech class in S01.
6. WHEN `php artisan config:show ai` is run, THE Application SHALL display the configuration and mask the `api_key` value or show it as null.

### Requirement 19: BusinessClock

**User Story:** As a developer, I want one source of the business date, so that metrics, imports and demos agree on what "today" is.

#### Acceptance Criteria

1. THE BusinessClock SHALL provide `today()` returning a date-only value and `now()` returning a date-time value, both in the business timezone.
2. WHEN `today()` is called and `DEMO_MODE` is false, THE BusinessClock SHALL return the current date in the business timezone.
3. WHEN `today()` is called and `DEMO_MODE` is true and `DEMO_FIXED_DATE` is a valid `YYYY-MM-DD` date, THE BusinessClock SHALL return `DEMO_FIXED_DATE`.
4. WHEN `now()` is called and `DEMO_MODE` is true and `DEMO_FIXED_DATE` is valid, THE BusinessClock SHALL return `DEMO_FIXED_DATE` at the current local time of day in the business timezone.
5. WHILE `DEMO_MODE` is false, THE BusinessClock SHALL ignore `DEMO_FIXED_DATE` even when set.
6. IF `DEMO_MODE` is true and `DEMO_FIXED_DATE` is empty or not a valid calendar date, THEN THE BusinessClock SHALL throw a descriptive exception naming the variable.
7. WHERE a timezone argument is passed to the BusinessClock, THE BusinessClock SHALL use that timezone in place of the default, enabling S02 to pass the company timezone.
8. IF an invalid timezone identifier is supplied, THEN THE BusinessClock SHALL throw a descriptive exception.
9. THE BusinessClock SHALL be the only code in the Application that determines the business date, and S01 code outside the BusinessClock SHALL not call `now()` or `today()` for business-date purposes.
10. FOR ALL valid dates D with `DEMO_MODE` true and `DEMO_FIXED_DATE` equal to D, `BusinessClock::today()` formatted as `YYYY-MM-DD` SHALL equal D (round-trip property).
11. THE BusinessClock SHALL be resolvable through the Laravel container.

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

1. THE README SHALL describe, in order: prerequisites, clone, dependency install, `.env` creation, key generation, database creation, migration, frontend build or dev server, queue worker, and opening the application.
2. THE README SHALL list the Verification_Commands with their exact invocations.
3. THE README SHALL list the repository documentation in `docs/` with a one-line description of each.
4. THE README SHALL state that Docker is not used and that real client data must not be loaded until authorized.
5. WHEN a developer follows the README on a machine with the prerequisites, THE Application SHALL start and render the Today page without additional undocumented steps.

### Requirement 22: Baseline verification commands

**User Story:** As a developer, I want standard check commands, so that every later spec has the same quality baseline.

#### Acceptance Criteria

1. THE Application SHALL provide an npm script `type-check` running the Vue TypeScript compiler check, `lint` running ESLint, `format:check` running Prettier in check mode, and `build` running the Vite production build.
2. THE Application SHALL provide a Composer script that runs the PHP code style check using Laravel Pint in test mode.
3. WHEN each of the scripts in criteria 1 and 2 is run on a clean checkout, THE Verification_Commands SHALL exit with status 0.
4. IF a script finds a violation, THEN THE Verification_Commands SHALL exit with a non-zero status and print the file and line.
5. THE Application SHALL provide a single npm script `verify` that runs `type-check`, `lint`, `format:check` and `build` in order and stops at the first failure.
6. THE Application SHALL include no unit test or end-to-end test suite authored by S01.
7. THE README SHALL state that passing Verification_Commands does not demonstrate production readiness.

### Requirement 23: Security and scope baseline

**User Story:** As the product owner, I want the foundation to be safe by default and limited to Stage 1, so that later work is not compromised.

#### Acceptance Criteria

1. THE Application SHALL enable CSRF protection on all state-changing web routes.
2. THE Application SHALL set `APP_DEBUG=false` in the Env_Template.
3. THE Application SHALL set secure cookie settings so that `SESSION_SECURE_COOKIE` is documented in the Env_Template with guidance to enable it under HTTPS.
4. THE Application SHALL send no emails, notifications or outbound requests in S01.
5. THE Application SHALL contain no authentication logic authored by S01, no user, role or policy definitions, no import code, and no business metric, rule, issue, brief, meeting or task logic.
6. THE Application SHALL include no Stage 2 feature, including extra departments, a configurable rule builder, scheduled briefs, reminders, WhatsApp or external email.
