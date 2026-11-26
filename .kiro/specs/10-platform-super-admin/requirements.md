# S10 Platform Super Admin: Requirements

**Authority.** Explicit user instruction (2026-11-26): BusinessPulse is to be offered to many SMEs, and the platform owner needs a super admin panel to set companies up. This is a scope change to Stage 1 (multi-tenancy was out of scope in `CLAUDE.md` and `docs/stage-1-scope.md`). It is recorded here, in `docs/architecture.md` section 21 and in `docs/risks.md`. Billing, subscriptions, plans, self-service sign-up, impersonation and a separate database per company stay out of scope.

## Glossary
- **Platform_Admin**: a person who runs the platform. Not a company user. Stored in `platform_admins`.
- **Tenant**: a `companies` row with its departments, users and data. Isolation is by `company_id` scoping (existing).
- **Suspended company**: `companies.suspended_at` is not null.

## Requirements
1. THE Application SHALL keep Platform_Admins in their own table and authenticate them with their own guard (`platform`), login page (`/platform/login`) and session key, separate from the Owner, Manager and Staff roles.
2. A Platform_Admin SHALL NOT be able to open any tenant route (`/today`, `/imports`, `/issues`, `/meetings`, `/tasks`, files); a tenant user SHALL NOT be able to open any `/platform` route except the login page. Unauthenticated `/platform` requests redirect to `/platform/login`.
3. THE Platform panel SHALL show tenant metadata only (name, timezone, status, created date, user and department counts, the Owner's name and email). It SHALL NOT show or export sales data, issues, meetings, transcripts, recordings, tasks or briefs of any company.
4. A Platform_Admin SHALL create a company with: name, timezone, trading days, optional branch codes, and the first Owner (name, email, password of at least 12 characters). Creation SHALL, in one transaction, create the company (`is_demo = false`), its Sales department with the branch codes, the three default rules (`SeedDefaultRules`) and the Owner.
5. Owner emails SHALL be unique across the application. A failed creation SHALL leave nothing behind.
6. A Platform_Admin SHALL suspend and reactivate a company. WHILE suspended, no user of that company SHALL be able to sign in, and an existing session SHALL end on its next request with the message "This company's account is suspended."
7. A Platform_Admin SHALL reset the password of a company's Owner (minimum 12 characters). The password SHALL never be shown, logged or stored in the audit log.
8. THE Application SHALL record every platform action (create company, suspend, reactivate, reset Owner password, sign in) in `platform_audit_logs` with the admin id, the action, the company id and non-secret properties.
9. Platform login SHALL be throttled (5 failed attempts per minute per email and IP) and SHALL not reveal whether the email exists.
10. THE first Platform_Admin SHALL be created from the terminal with `php artisan platform:create-admin`. The demo seeder SHALL create one demo Platform_Admin (`admin@businesspulse.demo`) with `DEMO_PASSWORD` in demo mode only.
11. `CurrentCompany` SHALL resolve the signed-in user's own company (not the first company in the database); outside a request with a signed-in user it keeps the oldest-company fallback used by CLI and demo tooling.
12. Tenant isolation SHALL be tested: a user of company A gets 404 for a record of company B on the main modules, with two companies present.
13. Demo commands (`demo:seed`, `demo:reset`) keep refusing to run when any non-demo company exists. Real SMEs therefore belong in a non-demo database.

## Out of scope
Billing, plans, invoices, self-service registration, email invitations, impersonation ("log in as"), per-company AI budgets, a database or schema per company, company deletion, editing a company after creation (the Owner uses Settings).
