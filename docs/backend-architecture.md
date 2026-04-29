# Backend Architecture

## Stack and Runtime

`naqaa-backend` is a Laravel 12 application with:

- PHP 8.2+
- Inertia + React admin web UI in `resources/js`
- SQL Server-oriented multi-database configuration
- Spatie permissions for roles and fine-grained access control
- Vite for frontend bundling

The backend serves two different surfaces:

- internal/admin web pages through Laravel + Inertia
- JSON API endpoints under `routes/api.php` consumed by `naqaa-doctor`

## Database Connection Model

Database connections are defined in `config/database.php`.

- `naqaa`
  - App-owned data.
  - Stores users, roles, permissions, access tokens, refresh tokens, digital forms, attachments, and status audits that belong to the Naqaa system.
- `meditop`
  - External or legacy clinical data source.
  - Stores doctors, admissions, patients, checklist definitions, and legacy documents.
- `archive`
  - Additional SQL Server connection defined for archived data access.
- default local connection
  - Used for standard Laravel local/dev defaults such as `sqlite` or `mysql`, depending on environment.

Operationally, most cross-system work reads admissions and patient data from `meditop` while storing app-specific auth and workflow metadata in `naqaa`.

## Core Domains and Models

### Auth and Access Control

- `App\Models\User`
  - Stored on `naqaa.users`.
  - Main authenticated actor for current API routes.
  - Can link to a `Doctor` through `doctor_id`.
- `App\Models\UserApiToken`
  - Stored on `naqaa.user_api_tokens`.
  - Access tokens are stored hashed, looked up by hashed bearer token, and expire after 5 minutes.
- `App\Models\UserRefreshToken`
  - Stored on `naqaa.user_refresh_tokens`.
  - Refresh tokens are device-bound, rotated on refresh, and revoked on logout, password change, or deactivation.
- `App\Models\Role` and `App\Models\Permission`
  - Managed through Spatie permission tables on the `naqaa` connection.

### Clinical Workflow

- `App\Models\Doctor`
  - Stored on `meditop.TblDoctors`.
  - Represents the legacy doctor entity and may link back to one `User`.
- `App\Models\AdmissionFile`
  - Stored on `meditop.TblAdmFiles`.
  - Main admission record used by list/detail/status workflows.
  - Includes scopes for "assigned to doctor via tblWorks" and "has any non-null doctor assignment".
- `App\Models\Patient`
  - Patient demographic and medical flags are updated from the admission detail screen.
- `App\Models\TblDocument`
  - Legacy image/document source.
  - Presence of legacy documents makes the admission read-only for form and attachment edits.
- `App\Models\DigitalAdmissionForm`
  - App-managed digital form record tied to an admission.
  - Stores `Payload`, `Strokes`, `FormVersion`, `Status`, and updater metadata.
- `App\Models\AdmissionAttachment`
  - App-managed uploaded image attachments.
  - Files are stored on the `public` disk and metadata is stored in the DB.
- `App\Models\AdmissionStatusAudit`
  - Audit trail for single and batch status changes.
- `App\Models\CheckList`, `CheckListItem`, `PatientCheckedItem`
  - Used to expose checklist groups and save patient checklist selections.

## API Route Groups and Responsibilities

The API surface lives in `routes/api.php`.

### Public auth routes

- `POST /api/auth/login`
- `POST /api/auth/refresh`

These are the current routes used by `naqaa-doctor`.

### Legacy compatibility auth routes

- `POST /api/doctor/login`
- `POST /api/doctor/refresh`

These still exist for compatibility and are restricted to doctor-linked users with the doctor role.

### Authenticated user routes

Protected by `App\Http\Middleware\AuthenticateApiUser`.

- `POST /api/auth/logout`
- `GET /api/auth/me`
- admissions list/detail/update routes
- admin metadata, role, and user routes

`AuthenticateApiUser`:

- requires a bearer token
- hashes and resolves the token through `UserApiToken::findForToken()`
- rejects missing, expired, invalid, or inactive-user sessions with `401`
- updates `LastUsedAt`
- injects the resolved `User` into the request

### Legacy doctor compatibility routes

Protected by both:

- `AuthenticateApiUser`
- `EnsureDoctorLinkedUser`

These mirror some admissions endpoints under `/api/doctor/*` and exist to preserve older client compatibility.

## Permission Model

Permissions are cataloged in `App\Support\PermissionCatalog`.

Important permissions:

- user management
  - `users.view`
  - `users.create`
  - `users.update`
  - `users.activate`
  - `users.assign_roles`
  - `users.assign_permissions`
- role management
  - `roles.view`
  - `roles.create`
  - `roles.update`
  - `roles.delete`
  - `roles.assign_permissions`
- admissions
  - `admissions.list.assigned`
  - `admissions.list.all`
  - `admissions.view.detail.assigned`
  - `admissions.view.detail.all`
  - `admissions.history.view`
  - `admissions.form.edit.assigned`
  - `admissions.form.edit.all`
  - `admissions.patient.edit`
  - `admissions.attachments.manage.assigned`
  - `admissions.attachments.manage.all`
  - `admissions.status.update`
  - `admissions.status.update.batch`

Default role bundles:

- `doctor`
  - assigned list/detail/history/form/attachment permissions for doctor-owned admissions
- `nurse`
  - all-admission list/detail/history/form/patient/attachment permissions plus status change permissions
- `admin`
  - protected role name used throughout admin logic; it cannot be deleted

The backend does not rely only on client-side gating. Controllers compute scope and enforce permissions server-side on every protected action.

## Auth Token Lifecycle

Auth is implemented in `App\Http\Controllers\Api\AuthController`.

### Login

`POST /api/auth/login`

- Looks up `User` by `username`.
- Verifies password with Laravel hashing.
- Rejects:
  - inactive accounts
  - doctor-role accounts that are not linked to a doctor
- Issues:
  - access token in `user_api_tokens`
  - refresh token in `user_refresh_tokens`
  - new `deviceId`
- Returns:
  - `Token`
  - `refreshToken`
  - `deviceId`
  - serialized `user`
  - serialized linked `doctor` or `null`

### Access token behavior

- Plain token is returned once to the client.
- Stored hashed in DB.
- Default expiry: 5 minutes.
- Middleware updates `LastUsedAt` on successful use.

### Refresh token behavior

- Refresh tokens are hashed before persistence.
- Bound to a device ID.
- Default expiry: 90 days.
- Refresh uses DB transaction + row lock.
- On refresh:
  - old refresh token is revoked
  - a new access token is issued
  - a new refresh token is issued
  - the same device ID is retained

### Logout and forced revocation

`POST /api/auth/logout`

- Deletes the current access token.
- If a device ID is provided, revokes active refresh tokens for that device.

Refresh and access tokens are also revoked or invalidated when:

- a user is deactivated
- a user password is changed
- an expired/revoked refresh token is used for refresh rotation cleanup

## Admissions Lifecycle

Admissions behavior is concentrated in `App\Http\Controllers\Api\AdmissionController`.

### List admissions

`GET /api/admissions`

- Permission scope is resolved as either:
  - all accessible admissions
  - only admissions assigned to the linked doctor through `tblWorks`
- Supports filtering by:
  - `status`
  - `start_date` and `end_date` legacy-style inputs
  - `start_at` and `end_before` UTC boundary inputs
  - `patient`
- Paginates with bounded `per_page`
- Returns:
  - normalized admissions
  - pagination metadata
  - action flags:
    - `canView`
    - `canEditForm`
    - `canEditPatientInfo`
    - `canManageAttachments`
    - `canChangeStatus`
    - `canViewHistory`

### Admission detail

`GET /api/admissions/{id}`

- Resolves the admission through permission-aware scope.
- Returns:
  - `Admission`
  - `Patient`
  - `Checklists`
  - `History`
  - `DigitalForm`
  - `LegacyDocuments`
  - `Attachments`
  - action flags

History may include:

- prior admission records
- legacy document-only entries for the same patient

### Update patient

`PATCH /api/admissions/{id}/patient`

- Requires `admissions.patient.edit`.
- Validates fields through `UpdateAdmissionPatientRequest`.
- Updates selected patient fields and checklist selections in a transaction.
- Checklist item IDs must exist in `meditop.TblCheckListItems`.

### Save digital form

`POST /api/admissions/{id}/form`

- Requires form edit permission at the correct scope.
- Validates payload through `SaveDigitalFormRequest`.
- Blocks write if:
  - admission is closed
  - legacy documents already exist
- Creates or updates `DigitalAdmissionForm`.

### Upload and delete attachments

- `POST /api/admissions/{id}/attachments`
- `DELETE /api/admissions/{id}/attachments/{attachmentId}`

Rules:

- require attachment-manage permission
- blocked for closed admissions
- blocked when legacy documents exist
- upload accepts only image files (`jpg`, `jpeg`, `png`)
- files are stored on the `public` disk under `admissions/`

Response payloads include both stored path and public URL.

### Update status

`PATCH /api/admissions/{id}/status`

- Requires `admissions.status.update`.
- Updates the `Closed` flag on the admission if needed.
- Always creates an `AdmissionStatusAudit` row recording old/new status, user, and optional notes.

### Batch status update

- `POST /api/admissions/status/batch/preview`
- `PATCH /api/admissions/status/batch`

Rules:

- require both:
  - `admissions.status.update`
  - `admissions.status.update.batch`
- support two modes:
  - `selected`
  - `scope`
- scope mode supports:
  - `all`
  - `date_range`

The preview endpoint computes counts first. The write endpoint requires `expected_will_change_count` so the backend can reject stale client expectations and avoid blind bulk updates.

## Admin Lifecycle

### Metadata

`App\Http\Controllers\Api\Admin\MetadataController`

- `GET /api/admin/permissions`
  - used to browse available permission names
- `GET /api/admin/doctors/search`
  - used to link a `User` to a legacy `Doctor`

### Roles

`App\Http\Controllers\Api\Admin\RoleController`

- list, create, update, delete roles
- can sync permission names onto roles
- rejects deletion of:
  - the protected `admin` role
  - roles currently assigned to users

### Users

`App\Http\Controllers\Api\Admin\UserController`

- list, create, show, update users
- update passwords
- activate/deactivate accounts
- link/unlink doctors
- sync direct roles and direct permissions

Important invariants:

- a user with role `doctor` must have a linked `doctor_id`
- a `doctor_id` must exist in `TblDoctors`
- `doctor_id` is unique across users
- deactivation revokes tokens
- password reset revokes tokens

## Important Constraints and Maintenance Notes

- Legacy documents make an admission read-only for form editing and attachment management.
- Closed admissions cannot be edited for forms or attachments.
- Patient editing is permission-based and independent from form editing.
- Permission checks are enforced server-side even if the client hides buttons.
- `auth/*` is the primary API namespace; `doctor/*` routes are compatibility paths and should be changed carefully.
- The doctor app depends on backend-provided action flags. If permission or scope logic changes, verify both controller enforcement and UI gating together.
