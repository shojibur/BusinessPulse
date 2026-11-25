# Requirements Document

## Introduction

S02 Company, Authentication and Access Control gives BusinessPulse real identity. It replaces the S01 placeholder role with session authentication and adds the single-company profile, the Sales department, user (staff) management, three fixed roles (Owner, Manager, Staff), a policy framework, a reusable department-scoping mechanism, role-appropriate navigation, shared Inertia auth props, Settings pages for Company, Staff and Sales department information, and an idempotent seeder for the fictional Meridian Home Supplies Sdn Bhd demonstration accounts.

Access rules follow `docs/business-workflows.md` D23 and `.kiro/steering/security.md`: Owner is company-wide, Manager is limited to the own department, Staff is limited to own tasks. A wrong role returns 403. A record outside the user's department returns 404.

S02 does not own sales data, imports, rules, issues, briefs, meetings, tasks or outcomes. Their models and policies belong to S03 onward. S02 supplies the framework those specs reuse.

### What S02 introduces

| Kind | Items |
|---|---|
| Tables | `companies`, `departments`, `users` as defined in `docs/data-model.md`, plus Laravel `sessions` |
| Enums | `App\Enums\Role` (`owner`, `manager`, `staff`) |
| Models | `Company`, `Department`, `User` |
| Policies | `CompanyPolicy`, `DepartmentPolicy`, `UserPolicy`, and the policy conventions later specs follow |
| Query scope | `App\Models\Concerns\ScopedToDepartment` trait and `scopeVisibleTo(User $user)` for any model with a `department_id` |
| Services and Actions | `App\Actions\Users\{CreateUser, UpdateUser, DeactivateUser, ReactivateUser, ResetUserPassword}`, `App\Actions\Company\UpdateCompanyProfile`, `App\Services\CurrentCompany` |
| Commands | `identity:create-owner` (first company, Sales department and Owner), `DemoIdentitySeeder` |
| Middleware | Active-user check, department-scoping route binding |
| Shared props | `auth.user`, `auth.role`, `company` (typed in `resources/js/types`) |
| Pages | Login, Settings layout with tabs Company, Staff, Department |
| Changes to S01 | Placeholder role mechanism removed. `BusinessClock` receives the company timezone. |

### Dependencies

- S01: App_Shell, Navigation_Scaffold, design system and components, error pages (403, 404, 419), flash and toast helper, BusinessClock, `SESSION_DRIVER`, `DEMO_PASSWORD`, `DEMO_MODE`.
- S03 to S09 depend on S02.

### Assumptions

- A1: Stage 1 has exactly one company and one department (Sales). Departments are created only by `identity:create-owner` and the demonstration seeder. No UI creates, renames or deletes departments.
- A2: Public registration, email verification and password reset by email are out of scope. The Owner resets another user's password manually. Starter-kit routes and screens for these are removed.
- A3: Users cannot change their own password in Stage 1. The Owner is the only credential administrator. This is a Stage 1 simplification and is flagged for client confirmation.
- A4: Manager has read-only access to the Staff list of the own department. Only Owner creates, edits, deactivates, reactivates and resets passwords. This is the narrowest reading of "restricted by role" and is flagged for client confirmation.
- A5: The Owner has `department_id` null and is not assigned to Sales. The Owner's `manager_id` is null.
- A6: A Manager's `manager_id` is the Owner. A Staff user's `manager_id` is a Manager in the same department.
- A7: Email is stored lowercase and is unique across the application.
- A8: Minimum password length is 12 characters. The demonstration password comes from `DEMO_PASSWORD`, which must meet this length.
- A9: Login throttle is 5 failed attempts per minute for each combination of email and IP address.
- A10: Branch membership of staff (KL, PG, JB) is not modelled. The demonstration seeder records it in `job_title`.
- A11: Recording consent, notifications and activity logging belong to later specs. S02 writes no `activity_logs` rows because that table is introduced later.
- A12: The `company.timezone` value is an IANA identifier. Changing it does not alter stored dates.
- A13: Trading days are ISO weekday integers 1 to 7. Effects of changing trading days on existing sales data are handled by S05.
- A14: Demo reset (`demo:reset`) is owned by S09. S02 provides only the idempotent identity seeder.

## Glossary

- **Application**: The BusinessPulse Laravel 13 and Vue 3 Inertia web application.
- **Company**: The single business tenant, stored in `companies`.
- **Department**: An organizational unit of the Company, stored in `departments`. Stage 1 has the Sales department.
- **User**: A person who can sign in, stored in `users`.
- **Role**: One of `owner`, `manager`, `staff`, stored in `users.role`.
- **Owner**: The User with the `owner` Role and company-wide access.
- **Manager**: The User with the `manager` Role and access limited to the own Department.
- **Staff**: The User with the `staff` Role and access limited to own tasks.
- **Auth_System**: The Laravel session authentication, login, logout and throttling components.
- **Login_Page**: The page where credentials are entered.
- **Access_Policy**: A Laravel Policy class that authorizes actions on a model.
- **Department_Scope**: The `ScopedToDepartment` trait and `visibleTo` query scope that restrict a query to records the User may see.
- **Staff_Manager**: The Settings Staff tab and the actions behind it.
- **Company_Settings**: The Settings Company tab.
- **Department_Settings**: The Settings Department tab.
- **Identity_Seeder**: The idempotent seeder `DemoIdentitySeeder`.
- **Owner_Command**: The Artisan command `identity:create-owner`.
- **Navigation_Provider**: The server-side definition of navigation items and their visibility, introduced in S01.
- **Auth_Props**: The Inertia shared props `auth.user`, `auth.role` and `company`.
- **BusinessClock**: The S01 helper that returns the business date and time.
- **Active_User**: A User with `is_active` true.
- **Sales_Department**: The Department with slug `sales`.

## Requirements

### Requirement 1: Company, department and user data model

**User Story:** As a developer, I want the identity tables defined per the data model, so that later specs reference one consistent company structure.

#### Acceptance Criteria

1. THE Application SHALL provide migrations creating `companies`, `departments`, `users` and `sessions` with the columns, types and constraints in `docs/data-model.md` section 2.
2. THE `companies` table SHALL contain `name`, `timezone` (default `Asia/Kuala_Lumpur`), `currency` (default `MYR`), `trading_days` (JSONB integer array, default `[1,2,3,4,5,6]`), `is_demo` (boolean, default false) and `recording_retention_days` (default 30).
3. THE `departments` table SHALL contain `company_id`, `name` and `slug` with a unique constraint on (`company_id`, `slug`).
4. THE `users` table SHALL contain `company_id`, `department_id` (nullable), `manager_id` (nullable foreign key to `users`), `name`, `email` (unique), `password`, `role`, `job_title`, `is_active` (default true) and the Laravel `remember_token`.
5. THE Application SHALL store `users.role` as a string backed by the `Role` enum and enforce the allowed values with a database check constraint.
6. THE Application SHALL cast `users.password` with the Laravel `hashed` cast and exclude `password` and `remember_token` from model serialization.
7. WHEN `php artisan migrate` is run on a database containing S01 tables, THE Application SHALL complete the S02 migrations without error.
8. THE Application SHALL define Eloquent relationships `Company hasMany Department`, `Company hasMany User`, `Department hasMany User`, `User belongsTo Department`, `User belongsTo manager` and `User hasMany reports`.
9. THE Application SHALL include no table, model or column for Super Admin, custom permissions, multiple companies or tenant switching.

### Requirement 2: Role definition and capability matrix

**User Story:** As the product owner, I want three fixed roles with defined capabilities, so that access is predictable and auditable.

#### Acceptance Criteria

1. THE Application SHALL define exactly three Roles: `owner`, `manager` and `staff`.
2. THE Application SHALL document in a single code location, `App\Enums\Role`, the capability of each Role as stated in `docs/business-workflows.md` D23: Owner is company-wide, Manager is limited to own Department data, Staff is limited to own tasks.
3. THE Application SHALL expose helper methods `isOwner()`, `isManager()` and `isStaff()` on the User model.
4. THE Application SHALL provide no interface or code path to create a custom Role or to assign an individual permission.
5. IF a User is created or updated with a Role value outside the three defined Roles, THEN THE Application SHALL reject the request with a validation error.

### Requirement 3: Login

**User Story:** As a user, I want to sign in with my email and password, so that I can reach my work.

#### Acceptance Criteria

1. WHEN a guest requests any application route other than the Login_Page, THE Auth_System SHALL redirect the guest to the Login_Page.
2. THE Login_Page SHALL render outside the App_Shell, with a form containing email and password fields, a submit button and an inline error area.
3. WHEN an Active_User submits a correct email and password, THE Auth_System SHALL start an authenticated session, regenerate the session identifier and redirect to the Today page.
4. WHEN a guest submits an email that matches a stored User in a different letter case, THE Auth_System SHALL treat the email as case-insensitively equal.
5. IF the email is unknown or the password is wrong, THEN THE Auth_System SHALL display the same generic message "These credentials do not match our records" and reveal neither which field was wrong nor whether the email exists.
6. IF the submitted credentials match a User whose `is_active` is false, THEN THE Auth_System SHALL reject the login with the same generic message as criterion 5 and start no session.
7. IF a guest makes more than 5 failed login attempts within 1 minute for the same email and IP address combination, THEN THE Auth_System SHALL reject further attempts for that combination until the window expires and display the number of seconds remaining.
8. WHEN a successful login occurs, THE Auth_System SHALL clear the failed-attempt counter for that email and IP address combination.
9. WHEN an authenticated user requests the Login_Page, THE Auth_System SHALL redirect to the Today page.
10. THE Login_Page SHALL provide a visible label for each field, a keyboard-operable submit control and an `aria-live` region for errors.
11. THE Auth_System SHALL log failed and throttled login attempts with timestamp and IP address and without the submitted password.
12. THE Application SHALL include no "remember me" option, no social login, no two-factor authentication and no API token authentication.

### Requirement 4: Logout and session lifecycle

**User Story:** As a user, I want to sign out safely, so that nobody else can use my session.

#### Acceptance Criteria

1. WHEN an authenticated user selects Sign out in the user menu, THE Auth_System SHALL invalidate the session, regenerate the CSRF token and redirect to the Login_Page.
2. THE Auth_System SHALL accept logout only through a POST request protected by CSRF.
3. THE Application SHALL store sessions in the `sessions` table using `SESSION_DRIVER=database`.
4. WHEN a session has been idle longer than `SESSION_LIFETIME` minutes, THE Auth_System SHALL end the session and redirect the next request to the Login_Page.
5. WHEN an Inertia request is made with an expired session, THE Auth_System SHALL cause a full-page redirect to the Login_Page.
6. THE Application SHALL set the session cookie attributes `HttpOnly` and `SameSite=Lax`, and `Secure` when `SESSION_SECURE_COOKIE` is true.

### Requirement 5: Removal of public registration and email-based recovery

**User Story:** As the product owner, I want accounts created only by the Owner, so that no outsider can register or recover access by email.

#### Acceptance Criteria

1. THE Application SHALL expose no route for registration, forgot-password, reset-password, email verification, password confirmation or two-factor setup.
2. WHEN a request is made to a removed starter-kit route, THE Application SHALL return the 404 error page.
3. THE Application SHALL send no email from S02 code.
4. THE Login_Page SHALL display the text "Forgot your password? Ask your Owner to reset it."
5. THE README SHALL state that password recovery in Stage 1 is a manual Owner action and that this is an assumption pending client confirmation.

### Requirement 6: Deactivated users

**User Story:** As an Owner, I want a deactivated user to lose access immediately, so that a departing employee cannot continue to use the system.

#### Acceptance Criteria

1. WHEN the Owner deactivates a User, THE Application SHALL set `is_active` to false and delete every row in `sessions` for that User in the same database transaction.
2. WHILE a request carries a session of a User whose `is_active` is false, THE Application SHALL end the session and redirect to the Login_Page with the message "Your account is inactive".
3. THE active-user middleware SHALL run on every authenticated route, including Inertia and JSON requests.
4. WHEN the Owner reactivates a User, THE Application SHALL set `is_active` to true and keep all data of the User unchanged.
5. THE Application SHALL retain deactivated User records and their relationships, and SHALL provide no hard-delete action for Users.
6. IF the Owner attempts to deactivate the own account, THEN THE Application SHALL reject the request with a validation error.
7. IF the Owner attempts to deactivate or change the Role of the last Active_User with the `owner` Role, THEN THE Application SHALL reject the request with a validation error.
8. WHEN a User is deactivated, THE Application SHALL continue to display the name of that User wherever later specs reference the User historically.

### Requirement 7: Staff management (Owner)

**User Story:** As an Owner, I want to create and maintain user accounts, so that the right people have the right access.

#### Acceptance Criteria

1. WHILE the signed-in User is the Owner, THE Staff_Manager SHALL allow creating a User with name, email, job title, Role, Department, manager and an initial password.
2. WHILE the signed-in User is the Owner, THE Staff_Manager SHALL allow editing name, email, job title, Role, Department and manager of any User of the Company.
3. WHEN the Owner submits a create or edit request, THE Staff_Manager SHALL validate the request with a Form Request class before any write.
4. IF the submitted email is not a valid email address or is already used by another User, THEN THE Staff_Manager SHALL reject the request with a field error on `email`.
5. IF the submitted password has fewer than 12 characters, THEN THE Staff_Manager SHALL reject the request with a field error on `password`.
6. IF the Role is `manager` or `staff` and no Department is supplied, THEN THE Staff_Manager SHALL reject the request with a field error on `department_id`.
7. WHEN the Role is `owner`, THE Staff_Manager SHALL store `department_id` and `manager_id` as null.
8. IF the Role is `staff` and the supplied manager is not an Active_User with the `manager` Role in the same Department, THEN THE Staff_Manager SHALL reject the request with a field error on `manager_id`.
9. IF the Role is `manager` and the supplied manager is not the Owner, THEN THE Staff_Manager SHALL reject the request with a field error on `manager_id`.
10. IF an edit would make a User the manager of the same User or would create a reporting cycle, THEN THE Staff_Manager SHALL reject the request with a field error on `manager_id`.
11. IF an edit would change the Role or Department of a Manager who has Active_User reports, THEN THE Staff_Manager SHALL reject the request with an error that lists the number of reports to reassign first.
12. WHILE the signed-in User is the Owner, THE Staff_Manager SHALL allow deactivating and reactivating any User, subject to Requirement 6.
13. WHEN a User is created, edited, deactivated or reactivated, THE Staff_Manager SHALL show a success toast through the S01 flash helper.
14. THE Staff_Manager SHALL accept only the fields listed in criteria 1 and 2, and SHALL ignore `company_id`, `is_active` and `id` supplied in a create or edit request body (mass-assignment protection).
15. THE Staff_Manager SHALL assign every created User to the Company of the Owner.

### Requirement 8: Manual password reset by Owner

**User Story:** As an Owner, I want to set a new password for a staff member, so that a locked-out colleague can sign in again without email.

#### Acceptance Criteria

1. WHILE the signed-in User is the Owner, THE Staff_Manager SHALL allow setting a new password for any other User of the Company.
2. IF the new password has fewer than 12 characters, THEN THE Staff_Manager SHALL reject the request with a field error on `password`.
3. WHEN the Owner sets a new password, THE Application SHALL store the password hashed and delete every session of that User.
4. THE Application SHALL never display, log, flash or return a stored password or a submitted password.
5. THE Staff_Manager SHALL display the password entry field as a masked input and SHALL NOT offer a generated password that is shown back to the Owner.

### Requirement 9: Staff list visibility

**User Story:** As a manager, I want to see the staff of my department, so that I know who can be assigned work.

#### Acceptance Criteria

1. WHILE the signed-in User is the Owner, THE Staff_Manager SHALL list all Users of the Company with name, email, job title, Role, Department, manager and status.
2. WHILE the signed-in User is a Manager, THE Staff_Manager SHALL list only Users of the own Department in read-only form, without create, edit, deactivate or password actions.
3. WHILE the signed-in User is Staff, THE Application SHALL return the 403 error page for the Staff tab.
4. THE Staff_Manager SHALL allow filtering the list by status (active, inactive, all) with active as the default.
5. WHEN the filtered list is empty, THE Staff_Manager SHALL render an EmptyState.
6. THE Staff_Manager SHALL paginate the list at 25 rows per page.
7. IF a Manager requests the detail or edit route of a User outside the own Department, THEN THE Application SHALL return the 404 error page.
8. IF a Manager requests the edit, create, deactivate or reset-password route for a User inside the own Department, THEN THE Application SHALL return the 403 error page.

### Requirement 10: Company profile

**User Story:** As an Owner, I want to maintain the company profile, so that the timezone and trading calendar used across the application are correct.

#### Acceptance Criteria

1. WHILE the signed-in User is the Owner, THE Company_Settings SHALL display and allow editing of `name`, `timezone`, `trading_days` and `recording_retention_days`.
2. THE Company_Settings SHALL display `currency` as `MYR` in read-only form.
3. THE Company_Settings SHALL display `is_demo` as a read-only indicator, and THE Application SHALL provide no UI to change `is_demo`.
4. IF the submitted `name` is empty or longer than 150 characters, THEN THE Company_Settings SHALL reject the request with a field error on `name`.
5. IF the submitted `timezone` is not a valid IANA timezone identifier, THEN THE Company_Settings SHALL reject the request with a field error on `timezone`.
6. IF the submitted `trading_days` is empty, contains a value outside 1 to 7, or contains duplicates, THEN THE Company_Settings SHALL reject the request with a field error on `trading_days`.
7. IF the submitted `recording_retention_days` is not an integer between 1 and 365, THEN THE Company_Settings SHALL reject the request with a field error on `recording_retention_days`.
8. WHEN a valid update is saved, THE Company_Settings SHALL store the values, normalize `trading_days` to a sorted array and display a success toast.
9. WHILE the signed-in User is a Manager, THE Company_Settings SHALL display the company name, timezone and trading days in read-only form.
10. WHILE the signed-in User is Staff, THE Application SHALL return the 403 error page for the Company tab.
11. THE Company_Settings SHALL expose the Company to the frontend only through Auth_Props fields `name`, `timezone`, `currency` and `trading_days`.
12. FOR ALL valid `trading_days` arrays, saving then reading the Company SHALL return the same set of weekdays in ascending order (round-trip property).

### Requirement 11: Department structure and information

**User Story:** As an Owner or Manager, I want to see the Sales department details, so that I know its members and reporting lines.

#### Acceptance Criteria

1. THE `identity:create-owner` Owner_Command and the Identity_Seeder SHALL create the Sales_Department with `name` "Sales" and `slug` "sales" for the Company.
2. WHILE the signed-in User is the Owner or a Manager, THE Department_Settings SHALL display the department name, the Manager name or names, the count of Active_User Staff, and the list of Staff with job title.
3. WHILE the signed-in User is a Manager, THE Department_Settings SHALL display only the own Department.
4. WHILE the signed-in User is Staff, THE Application SHALL return the 403 error page for the Department tab.
5. THE Department_Settings SHALL be read-only.
6. THE Application SHALL provide no UI or route to create, rename, delete or merge Departments.
7. IF the signed-in Manager or Staff has a null `department_id`, THEN THE Application SHALL treat the User as having no accessible Department and SHALL return no department-scoped records.

### Requirement 12: Policy framework

**User Story:** As a developer, I want one policy convention, so that later specs authorize every model action the same way.

#### Acceptance Criteria

1. THE Application SHALL provide `CompanyPolicy`, `DepartmentPolicy` and `UserPolicy` registered with the Laravel Gate.
2. THE `CompanyPolicy` SHALL permit `view` to Owner and Manager, and `update` to Owner only.
3. THE `DepartmentPolicy` SHALL permit `view` to the Owner for any Department and to a Manager for the own Department only.
4. THE `UserPolicy` SHALL permit `viewAny` to Owner and Manager, `view` to Owner for any User and to a Manager for Users of the own Department, and `create`, `update`, `deactivate`, `reactivate` and `resetPassword` to Owner only.
5. THE Application SHALL authorize every S02 controller action by calling the Gate or a Policy and SHALL not rely on the navigation visibility for authorization.
6. THE Application SHALL deny access by default WHEN a Policy method does not explicitly permit the action.
7. THE Application SHALL deny every Policy action to an inactive User.
8. THE Application SHALL document in `docs/architecture.md` the policy conventions for later specs: one Policy per model, `before()` hook not granting blanket Owner access outside department-scoped checks, Form Request validation and department scoping by `ScopedToDepartment`.
9. THE Application SHALL include a policy base class or trait that exposes `sameDepartment(User $user, int $departmentId)` for reuse by later Policies.
10. IF an authenticated User fails a Policy check on a record that is within the User's Department scope, THEN THE Application SHALL return the 403 error page.

### Requirement 13: Department scoping

**User Story:** As a developer, I want a shared way to restrict queries by department, so that no later spec leaks records across departments.

#### Acceptance Criteria

1. THE Department_Scope SHALL provide the query scope `visibleTo(User $user)` for any model that has a `department_id` column.
2. WHEN `visibleTo` is applied for an Owner of the Company, THE Department_Scope SHALL return records of every Department of the Company.
3. WHEN `visibleTo` is applied for a Manager, THE Department_Scope SHALL return only records whose `department_id` equals the `department_id` of the Manager.
4. WHEN `visibleTo` is applied for Staff, THE Department_Scope SHALL return only records whose `department_id` equals the `department_id` of the Staff user, and later specs SHALL further restrict Staff to own assignments.
5. WHEN `visibleTo` is applied for a User with null `department_id` and a Role other than Owner, THE Department_Scope SHALL return no records.
6. WHEN `visibleTo` is applied for an inactive User, THE Department_Scope SHALL return no records.
7. THE Application SHALL provide route model binding that resolves department-scoped models through `visibleTo` for the authenticated User, so that an out-of-scope record causes the 404 error page.
8. IF an authenticated User requests a department-scoped record that exists in another Department, THEN THE Application SHALL return the 404 error page and SHALL not reveal that the record exists.
9. IF an authenticated User requests a department-scoped record that is in scope but the Role is not permitted for the action, THEN THE Application SHALL return the 403 error page.
10. THE Department_Scope SHALL apply the same rule to the `User` model for the Staff list so that a Manager never retrieves Users of another Department.
11. FOR ALL Users U and department-scoped records R, `R` SHALL be returned by `visibleTo(U)` if and only if U is an Active_User and U is the Owner of the Company of R or the `department_id` of U equals the `department_id` of R (invariant).
12. THE Application SHALL document the scope and binding usage in `docs/architecture.md` with one example for later specs.

### Requirement 14: Authenticated shared props and typing

**User Story:** As a frontend developer, I want typed auth props from the server, so that components can show role-aware UI safely.

#### Acceptance Criteria

1. THE Application SHALL share `auth.user` with fields `id`, `name`, `email`, `role`, `job_title` and `department` (object with `id` and `name`, or null) as Inertia shared props for authenticated requests.
2. THE Application SHALL share `auth.role` equal to `auth.user.role` for authenticated requests, preserving the S01 prop name.
3. THE Application SHALL share `auth.user` as null on the Login_Page for guests.
4. THE Application SHALL share the `company` prop with fields `name`, `timezone`, `currency` and `trading_days` for authenticated requests.
5. THE Application SHALL define TypeScript types `AuthUser`, `CompanyProfile` and `Role` in `resources/js/types` and use them in all S02 components.
6. THE Application SHALL exclude `password`, `remember_token`, `manager_id`, `is_active` of other users and any session data from shared props.
7. THE Application SHALL share only the data of the signed-in User and the Company, and SHALL share no list of other Users in shared props.

### Requirement 15: Role-aware navigation replaces the placeholder

**User Story:** As a user, I want navigation that reflects my real role, so that I only see sections I may use.

#### Acceptance Criteria

1. THE Navigation_Provider SHALL derive navigation visibility from `auth.user.role` of the authenticated User.
2. WHILE the User is the Owner, THE App_Shell SHALL show all eight navigation items.
3. WHILE the User is a Manager, THE App_Shell SHALL show all eight navigation items.
4. WHILE the User is Staff, THE App_Shell SHALL show only Today and Tasks.
5. THE Application SHALL remove the `FOUNDATION_PLACEHOLDER_ROLE` variable, its reads and its documentation from `config`, `.env.example` and the README.
6. WHEN a User opens the route of a navigation section that the Role does not see, THE Application SHALL return the 403 error page.
7. THE App_Shell user menu SHALL display the name, Role and, for Manager and Staff, the Department name of the signed-in User, and an enabled Sign out action.
8. THE App_Shell SHALL render the Settings navigation item for Owner and Manager and link to the first Settings tab permitted for the Role.
9. THE Application SHALL document in code comments that navigation visibility is display only and that Policies enforce access.
10. THE Settings page SHALL show the tabs Company, Staff and Department, and SHALL reserve no tab for S03 or S04 content other than a documented extension point where those specs add the Import template and Rules tabs.

### Requirement 16: Business clock timezone integration

**User Story:** As a developer, I want the business date to follow the company timezone, so that "today" is correct for the business.

#### Acceptance Criteria

1. WHEN `BusinessClock::today()` or `now()` is called without a timezone argument and a Company exists, THE BusinessClock SHALL use `companies.timezone` of the Company.
2. WHEN `BusinessClock` is called and no Company exists, THE BusinessClock SHALL use `config('business.timezone')`.
3. WHERE a timezone argument is passed, THE BusinessClock SHALL use the argument in place of the Company timezone.
4. WHEN the Owner changes the Company timezone, THE BusinessClock SHALL use the new timezone on the next call, without requiring an application restart.
5. THE Application SHALL provide `App\Services\CurrentCompany` returning the single Company, resolved through the container and cached for the duration of one request.

### Requirement 17: Initial company and owner setup

**User Story:** As an operator, I want a command that creates the first company and Owner, so that a new installation can be used without a registration page or seeder.

#### Acceptance Criteria

1. WHEN `php artisan identity:create-owner` is run with the options `--company`, `--name`, `--email` and `--password` or with equivalent interactive prompts, THE Owner_Command SHALL create the Company, the Sales_Department and the Owner in one database transaction.
2. IF a Company already exists, THEN THE Owner_Command SHALL abort with a message and create no record.
3. IF the email is invalid or the password has fewer than 12 characters, THEN THE Owner_Command SHALL abort with a message and create no record.
4. THE Owner_Command SHALL prompt for the password with hidden input when the `--password` option is not supplied.
5. THE Owner_Command SHALL never print or log the password.
6. THE README SHALL document the Owner_Command as the production setup step.

### Requirement 18: Demonstration account seeding

**User Story:** As a presenter, I want the fictional demonstration company and accounts created repeatably, so that the demonstration environment can be rebuilt.

#### Acceptance Criteria

1. WHEN the Identity_Seeder runs and `DEMO_MODE` is true, THE Identity_Seeder SHALL create or update the Company "Meridian Home Supplies Sdn Bhd" with timezone `Asia/Kuala_Lumpur`, currency `MYR`, trading days 1 to 6 and `is_demo` true.
2. THE Identity_Seeder SHALL create or update the Sales_Department and the six Users in `docs/demo-scenario.md` section 1: owner Lim Wei Han, manager Nurul Aisyah, and staff Daniel Tan, Daniel Lee, Priya Nair and Farid Ismail, with the listed emails.
3. THE Identity_Seeder SHALL set the manager of Nurul Aisyah to Lim Wei Han, and the manager of every demonstration Staff user to Nurul Aisyah.
4. THE Identity_Seeder SHALL set `job_title` for each Staff user to a title containing the branch code (KL, PG or JB) from `docs/demo-scenario.md`.
5. THE Identity_Seeder SHALL identify existing rows by email and Company name so that running the seeder twice produces the same rows and no duplicates (idempotence property).
6. THE Identity_Seeder SHALL hash the password from `DEMO_PASSWORD` for every demonstration User.
7. IF `DEMO_MODE` is false, THEN THE Identity_Seeder SHALL abort without writing any record and print a message naming `DEMO_MODE`.
8. IF `DEMO_PASSWORD` is empty or shorter than 12 characters, THEN THE Identity_Seeder SHALL abort without writing any record and print a message naming `DEMO_PASSWORD`.
9. IF a Company that is not `is_demo` already exists, THEN THE Identity_Seeder SHALL abort without writing any record.
10. THE Identity_Seeder SHALL print no password and SHALL write no password to any file or log.
11. THE Application SHALL commit no demonstration password, and the `.env.example` entry for `DEMO_PASSWORD` SHALL remain empty.
12. THE Identity_Seeder SHALL use only fictional names and the `meridian.demo` email domain.

### Requirement 19: Error behavior and response codes

**User Story:** As a user, I want clear and consistent responses to unauthorized requests, so that I understand whether I lack permission or the page does not exist for me.

#### Acceptance Criteria

1. WHEN an authenticated User requests an S02 route whose Role requirement is not met, THE Application SHALL return the 403 error page.
2. WHEN an authenticated User requests an S02 record outside the own Department, THE Application SHALL return the 404 error page.
3. WHEN a guest requests a protected route through a non-Inertia JSON request, THE Application SHALL return a 401 JSON response.
4. IF a CSRF token is missing or expired on a state-changing request, THEN THE Application SHALL return the 419 error page.
5. IF a validation error occurs on a form, THEN THE Application SHALL redisplay the form with field errors and preserve the non-secret submitted values.
6. THE Application SHALL never redisplay the submitted password after a validation error.
7. THE Application SHALL redact passwords, session identifiers and tokens from logs and error messages.

### Requirement 20: Security and scope baseline

**User Story:** As the product owner, I want the access layer secure by default and limited to Stage 1, so that later work can trust it.

#### Acceptance Criteria

1. THE Application SHALL hash passwords with the Laravel default hasher.
2. THE Application SHALL protect every state-changing S02 route with CSRF verification and the `auth` and active-user middleware, with the exception of the Login_Page POST which is protected by CSRF and throttling.
3. THE Application SHALL use Eloquent or the query builder with bound parameters for every S02 query.
4. THE Application SHALL validate every S02 request body with a Form Request class.
5. THE Application SHALL include no Super Admin, no custom permission, no public registration, no company switching, no SaaS onboarding, no two-factor authentication and no invitation email in S02.
6. THE Application SHALL include no import, sales record, rule, issue, brief, meeting, task or outcome code in S02.
7. THE Application SHALL include no Stage 2 feature in S02.
8. THE Application SHALL contain no S02-authored use of the `any` type in TypeScript, except where a third-party declaration requires it and a comment states the reason.
9. WHEN S02 verification runs, THE Verification_Commands defined in S01 SHALL exit with status 0.
