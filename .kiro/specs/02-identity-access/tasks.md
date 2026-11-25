# Implementation Plan: Identity and Access (S02)

## Overview

Implementation uses PHP (Laravel 13) and Vue 3 with TypeScript (Inertia), as in the design. The plan builds the data layer first, then access control (scope, policies), then authentication, shared props and navigation, read-only Settings pages, and finally the PoV Polish write forms and docs. Per `docs/stage-1-refinements.md` R5, a small set of focused feature tests runs on the S01 harness (`composer test`, PostgreSQL `businesspulse_testing`, no browser automation, no property-based tool). Correctness Properties 1 to 5 in the design map to those tests. Verification commands are `composer test`, `npm run verify` and `composer pint-test`.

### Tier legend (R1)

- **[PoV Core]**: required for the 22-step demonstration. Build and finish these first.
- **[PoV Polish]**: Stage 1 scope built after the Core slice works (staff CRUD UI, Manager staff list, password reset UI, company profile edit UI, safeguards beyond database constraints).

## Tasks

- [x] 1. Data layer for identity
  - [x] 1.1 [PoV Core] Create the `Role` enum and the identity migrations
    - Add `app/Enums/Role.php` (`owner`, `manager`, `staff`) with `values()`, and PHPDoc stating each role's capability per `docs/business-workflows.md` D23
    - Add migrations for `companies` (including `reporting_cutoff_time` time default `12:00` and `reporting_cutoff_lag_days` smallint default 1), `departments` (including `settings` JSONB not null default `{}`, created here and not by S03), `users` (replace the starter-kit users migration in place) with defaults, unique (`company_id`, `slug`), unique `email`, indexes on `department_id` and `manager_id`, and a raw-SQL CHECK constraint on `users.role`
    - Confirm the `sessions` table from S01 exists and is used with `SESSION_DRIVER=database`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.7, 1.9, 2.1, 2.2, 2.4, 4.3_

  - [x] 1.2 [PoV Core] Create `Company`, `Department`, and `User` models
    - Define relationships (departments, users, department, manager, reports, company)
    - `Company`: cast `trading_days` to array, sorted and de-duplicated on set; cast `reporting_cutoff_time` (`HH:MM`) and `reporting_cutoff_lag_days` (integer)
    - `Department`: cast `settings` to array; `expectedBranches()` accessor returns `settings.expected_branches` or `[]`
    - `User`: `hashed` password cast, `role` enum cast, `$hidden` for `password` and `remember_token`, `isOwner()`, `isManager()`, `isStaff()`, lowercase email on set
    - Restrict fillable fields so `company_id`, `is_active`, and `id` are never mass-assigned from requests
    - _Requirements: 1.6, 1.8, 1.10, 1.11, 2.3, 7.14, 10.8, 10.12_

- [x] 2. Access control framework
  - [x] 2.1 [PoV Core] Implement `ScopedToDepartment` and the scoped route-binding helper
    - Add `app/Models/Concerns/ScopedToDepartment.php` with `scopeVisibleTo(User $user)` (inactive: none; Owner: own company; null department: none; otherwise same `department_id`)
    - Apply it to `User` for the Staff list
    - Add a reusable route-binding helper that resolves via `visibleTo()` and returns 404 when out of scope
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.10, 13.11_

  - [x] 2.2 [PoV Core] Implement policies and the shared policy trait
    - Add `app/Policies/Concerns/AuthorizesByDepartment.php` exposing `sameDepartment()` (a trait, no interface)
    - Add `CompanyPolicy`, `DepartmentPolicy`, `UserPolicy` per the design policy table, default deny, deny for inactive users, no blanket Owner `before()` hook
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.6, 12.7, 12.9, 12.10, 13.9_

- [x] 3. Company resolution and business clock
  - [x] 3.1 [PoV Core] Add `CurrentCompany` and update `BusinessClock`
    - Add `app/Services/CurrentCompany.php` bound as a request-scoped singleton
    - Update `BusinessClock` to use the company timezone when no timezone argument is passed, falling back to `config('business.timezone')` when no company exists; explicit argument still wins
    - Leave the S01 demo clock file and `demo:set-date` as the only demo date source; add no other date override
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 10.11_

- [x] 4. Setup command and demo seeder
  - [x] 4.1 [PoV Core] Implement `identity:create-owner`
    - Options `--company`, `--name`, `--email`, `--password` with interactive prompts and hidden password input
    - Single transaction creating Company, Sales department (`sales`), and Owner with null `department_id` and `manager_id`
    - Abort with no writes if a Company exists or email or password (min 12) is invalid; never print or log the password
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 11.1_

  - [x] 4.2 [PoV Core] Implement `DemoIdentitySeeder`
    - Guards: refuse to run unless `DEMO_MODE=true`; abort without writes if `DEMO_PASSWORD` is empty or under 12 chars, or a non-demo Company exists
    - Upsert Meridian Home Supplies Sdn Bhd (`is_demo=true`, trading days Monday to Saturday, `reporting_cutoff_time` 12:00, `reporting_cutoff_lag_days` 1), the Sales department with `settings.expected_branches` explicitly `["KL","PG","JB"]`, and the six users from `docs/demo-scenario.md` section 1 (emails on `meridian.demo`), with manager chain and branch-code job titles, keyed by email and company name for idempotence
    - Hash the password from `DEMO_PASSWORD`; never print or log it; keep `DEMO_PASSWORD` empty in `.env.example`
    - Register the seeder with `DatabaseSeeder`
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.7, 18.8, 18.9, 18.10, 18.11, 18.12, 11.1_

- [x] 5. Authentication
  - [x] 5.1 [PoV Core] Add `EnsureUserIsActive` middleware and session configuration
    - Log out, invalidate the session, and redirect with "Your account is inactive" (401 JSON for non-Inertia requests); apply after `auth` on all authenticated routes
    - Set session config: database driver, `HttpOnly`, `SameSite=Lax`, `Secure` via `SESSION_SECURE_COOKIE`
    - _Requirements: 6.2, 6.3, 4.4, 4.5, 4.6, 19.3_

  - [x] 5.2 [PoV Core] Implement login and logout, and remove starter-kit auth routes
    - `LoginRequest`, `AuthenticatedSessionController` (create, store, destroy)
    - Case-insensitive email lookup, 5 attempts per minute throttle keyed by email and IP with seconds remaining, clear on success, regenerate session, redirect to Today
    - Generic failure message for unknown email, wrong password, and inactive user; log failed and throttled attempts without the password
    - Logout is POST-only with CSRF; invalidate session and regenerate token
    - Redirect authenticated users away from Login; delete registration, forgot/reset password, email verification, password confirmation, and two-factor routes so they 404; ensure no code sends email
    - _Requirements: 3.1, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.11, 3.12, 4.1, 4.2, 5.1, 5.2, 5.3, 19.4, 19.7, 20.1, 20.2, 20.4_

  - [x] 5.3 [PoV Core] Build the Login page
    - Vue page outside `AppShell` with labelled email and password fields, keyboard-operable submit, `aria-live` error region
    - Show "Forgot your password? Ask your Owner to reset it."; no remember-me or social login
    - Never redisplay the submitted password after errors
    - _Requirements: 3.2, 3.10, 3.12, 5.4, 19.5, 19.6_

  - [x] 5.4 [PoV Core] Write focused feature tests for identity core
    - Use the S01 harness with `RefreshDatabase` on PostgreSQL and fictional fixtures; no browser automation or property-based tool
    - Department scope (Property 1): `visibleTo` for Owner, Manager, Staff, inactive user, and null-department non-Owner against rows in two departments
    - Deactivated user (Property 3): correct credentials for an inactive user return the generic message and start no session; an open session is rejected on the next request
    - Seeder (Property 4): run twice with `DEMO_MODE=true` gives one Company, one Sales department with `["KL","PG","JB"]`, six Users, no duplicates; refuses with no writes when `DEMO_MODE` is not `true`
    - _Requirements: 21.1, 21.2, 21.5, 21.6, 21.8, 13.2, 13.3, 13.4, 13.5, 13.6, 3.6, 6.2, 18.5, 18.7_

- [x] 6. Checkpoint - Verify identity core
  - Run `php artisan migrate:fresh`, `composer test`, `npm run verify`, and `composer pint-test`. Ensure all pass, ask the user if questions arise.

- [x] 7. Shared props and role-aware navigation
  - [x] 7.1 [PoV Core] Extend `HandleInertiaRequests` and add frontend types
    - Share `auth.user` (`id`, `name`, `email`, `role`, `job_title`, `department`), `auth.role`, and `company` (`name`, `timezone`, `currency`, `trading_days`); `auth.user` is null for guests
    - Exclude `password`, `remember_token`, `manager_id`, other users' `is_active`, session data, and any user list
    - Add `AuthUser`, `CompanyProfile`, `Role` to `resources/js/types` with no untyped `any`
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7, 10.11, 20.8_

  - [x] 7.2 [PoV Core] Add role filtering to the S01 navigation list
    - Filter the existing S01 `navigation` list by `auth.user.role`: Owner and Manager see all eight items, Staff sees Today and Tasks; change no item definition, route, or label
    - Return 403 at route level for sections the role cannot see
    - User menu shows name, role, department (Manager and Staff), and an enabled Sign out
    - Settings link goes to the first permitted tab; add a code comment that navigation is display-only
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.7, 15.8, 15.9_

- [ ] 8. Settings: Company, Staff, Department
  - [x] 8.1 [PoV Core] Build the Settings shell with tabs
    - Settings layout with tabs Company, Staff, Department, each gated by policy
    - Document an extension point for the S03 Import template tab and S04 Rules tab without rendering placeholders
    - _Requirements: 15.10, 10.10, 9.3, 11.4_

  - [x] 8.2 [PoV Core] Implement the read-only Company tab
    - `CompanyController@edit` (display only) with `CompanyPolicy`; show `name`, `timezone`, `trading_days`, `reporting_cutoff_time`, `reporting_cutoff_lag_days`, `recording_retention_days`, read-only `currency` and `is_demo`
    - Owner and Manager see the page read-only; Staff gets 403
    - _Requirements: 10.1, 10.2, 10.3, 10.9, 10.10, 10.11, 12.2, 12.5_

  - [x] 8.3 [PoV Core] Implement the read-only Department tab
    - `DepartmentController@show` with route binding via `visibleTo()` and `DepartmentPolicy`
    - Show department name, expected branches, manager names, active staff count, and staff list with job titles; Manager sees own department only; Staff 403; no create, rename, delete, or merge routes
    - Treat null `department_id` as no accessible department
    - _Requirements: 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 12.3, 13.7_

  - [x] 8.4 [PoV Core] Implement the read-only Owner Staff list
    - `UserController@index` for the Owner (all company users: name, email, job title, role, department, manager, status) and its Vue page, authorized through `UserPolicy::viewAny`; Staff gets 403
    - Resolve users through `User::visibleTo()` so a Manager can never retrieve another department
    - _Requirements: 9.1, 9.3, 12.4, 12.5, 13.10, 20.3_

  - [ ] 8.5 [PoV Polish] Implement user management actions
    - Add `CreateUser`, `UpdateUser`, `DeactivateUser`, `ReactivateUser`, `ResetUserPassword` under `app/Actions/Users`, each with a single `handle()`
    - `CreateUser` assigns the Owner's company; `UpdateUser` blocks changing a Manager's role or department when active reports exist, naming the count
    - `DeactivateUser` sets `is_active` false and deletes the user's sessions in one transaction; rejects self-deactivation and removing the last active Owner (count check inside the transaction)
    - `ResetUserPassword` hashes the new password and deletes the user's sessions in one transaction; the password is never returned, logged, or flashed
    - Keep deactivated records with no hard delete
    - _Requirements: 6.1, 6.4, 6.5, 6.6, 6.7, 6.8, 7.7, 7.11, 7.15, 8.1, 8.3, 8.4_

  - [ ] 8.6 [PoV Polish] Implement `UserController` write actions and Form Requests
    - `StoreUserRequest`, `UpdateUserRequest`, `ResetUserPasswordRequest` accepting only the listed fields, with email, password (min 12), role enum, department-required, manager-role and department rules, and reporting-cycle detection
    - Actions: store, update, deactivate, reactivate, resetPassword; status filter (default active) and 25 per page on the list; Manager read-only list of the own department
    - Resolve users via `visibleTo()` binding first (404), then policy (403)
    - Flash success toasts through the S01 helper
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.8, 7.9, 7.10, 7.12, 7.13, 7.14, 8.1, 8.2, 8.5, 9.2, 9.4, 9.6, 9.7, 9.8, 12.4, 19.1, 19.2, 20.2, 20.4_

  - [ ] 8.7 [PoV Polish] Build the Staff tab forms
    - Create and edit forms; deactivate, reactivate, and reset-password controls shown only when the policy allows (Owner); status filter, pagination, and `EmptyState`
    - Masked password input with no generated password shown back; preserve non-secret input on validation errors
    - _Requirements: 7.1, 7.2, 8.5, 9.4, 9.5, 9.6, 19.5, 19.6_

  - [ ] 8.8 [PoV Polish] Implement the Company edit form
    - `UpdateCompanyProfileRequest` (name 1-150, valid IANA timezone, non-empty unique `trading_days` in 1-7, valid `reporting_cutoff_time`, `reporting_cutoff_lag_days` 0-7, retention 1-365), `UpdateCompanyProfile` action normalizing `trading_days` sorted, `CompanyController@update` (Owner only), success toast
    - `currency` and `is_demo` have no write path
    - Add a feature test for the `trading_days` round trip with `[5,1,3]`, `[7]`, `[1,2,3,4,5,6,7]` (Property 5)
    - _Requirements: 21.7, 10.1, 10.4, 10.5, 10.6, 10.7, 10.8, 10.12, 10.13, 12.2, 20.4_

  - [x] 8.9 [PoV Core] Write focused feature tests for authorization
    - Use the S01 harness with `RefreshDatabase` on PostgreSQL and fictional fixtures
    - Unauthorized department access (Property 2): a Manager requesting another department's record gets 404; Staff cannot read another department's records; Staff gets 403 on the Staff, Company, and Department tabs; a Manager gets 403 on user create
    - Role navigation: Staff receives only Today and Tasks in the shared `navigation` prop; Owner and Manager receive all eight
    - A second test-only department fixture is created in the test, not in the seeder
    - _Requirements: 21.1, 21.3, 21.4, 21.8, 9.3, 9.7, 9.8, 13.7, 13.8, 13.9, 15.2, 15.3, 15.4, 19.1, 19.2_

- [x] 9. Documentation
  - [x] 9.1 [PoV Core] Update `docs/architecture.md` and the README
    - `docs/architecture.md`: policy conventions for later specs (one policy per model, no blanket Owner `before()`, Form Request validation, `ScopedToDepartment`) and a `visibleTo` scope plus route-binding example
    - README: `identity:create-owner` as the production setup step, how to run `composer test`, manual Owner password reset and its pending-client-confirmation assumption, demo seeding with `DEMO_MODE` and `DEMO_PASSWORD`
    - _Requirements: 12.8, 13.12, 17.6, 5.5_

- [x] 10. Final checkpoint - Verify S02
  - Run `composer test`, `npm run verify` and `composer pint-test`. Run the manual checks from the design Testing Strategy (migrate:fresh, owner bootstrap, login walkthrough, removed routes, shared props). Ensure all pass, ask the user if questions arise.
  - _Requirements: 20.9_

## Notes

- Implementation notes (2026-11-26): the starter kit's Fortify package, registration, email verification, password reset, password confirmation, two-factor and passkey code were removed entirely (composer and npm dependencies included), and the `users` migration was replaced in place by one `create_identity_tables` migration (companies, departments, users, sessions). The Manager's read-only Staff list (Requirement 9.2, tier Polish) falls out of the Core `UserController@index` and is included. Task 8.9: no user write route exists until Polish tasks 8.5 to 8.7, so "Manager gets 403 on user create" is verified at the policy level (`Gate`) and by asserting no write routes exist; add the HTTP assertion with task 8.6. Polish tasks 8.5 to 8.8 are not implemented and stay unchecked, so parent task 8 stays unchecked.

- Focused feature tests (tasks 5.4 and 8.9) replace the earlier manual-only verification; Correctness Properties 1 to 4 are covered by them, Property 5 (trading-days round trip) is a PoV Polish example test added with task 8.8.
- Each task references granular requirement numbers from `requirements.md`.
- S02 adds no import, sales, rule, issue, brief, meeting, task, or outcome code (Requirements 20.6, 20.7).
- S02 adds no interface beyond the `ScopedToDepartment` trait and the policy trait.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["2.1", "2.2", "3.1", "4.1", "4.2", "5.1"] },
    { "id": 3, "tasks": ["5.2"] },
    { "id": 4, "tasks": ["5.3", "5.4"] },
    { "id": 5, "tasks": ["6"] },
    { "id": 6, "tasks": ["7.1", "8.1"] },
    { "id": 7, "tasks": ["7.2", "8.2", "8.3", "8.4", "8.5"] },
    { "id": 8, "tasks": ["8.6", "8.8", "8.9", "9.1"] },
    { "id": 9, "tasks": ["8.7"] },
    { "id": 10, "tasks": ["10"] }
  ]
}
```
