# Change Guide

This guide is meant to shorten the time between "I need to change this feature" and "I know exactly where to inspect first".

## Add or Change an API Endpoint

Inspect in this order:

1. `routes/api.php`
2. the target controller under `app/Http/Controllers/Api`
3. any request validator under `app/Http/Requests/Api`
4. `app/api/client.ts` in `naqaa-doctor`
5. the screen that consumes the endpoint

When making the change:

- keep the route under the correct namespace (`auth`, `admissions`, `admin`, or legacy `doctor`)
- update request/response types in the doctor app immediately
- if the endpoint is authenticated, preserve the existing bearer-token + refresh retry flow
- if the endpoint changes permissions, update both backend enforcement and frontend gating

## Change Admission Permissions

Inspect in this order:

1. `App\Support\PermissionCatalog`
2. `AdmissionController::resolveScopeForPermissions()`
3. `AdmissionController::buildAdmissionActions()`
4. doctor app uses of:
   - auth context permission helpers
   - returned admission action flags

Typical side effects of permission changes:

- admissions disappear from the list because scope changed
- buttons still show but backend rejects writes
- admin entry visibility changes
- batch tools appear or disappear unexpectedly

If you add a new permission:

- add it to `PermissionCatalog`
- decide whether it belongs in role defaults
- enforce it in the backend
- expose or consume it correctly in the doctor app

## Modify Auth or Session Behavior

Inspect in this order:

1. `app/Http/Controllers/Api/AuthController.php`
2. `app/Http/Middleware/AuthenticateApiUser.php`
3. `app/Models/UserApiToken.php`
4. `app/Models/UserRefreshToken.php`
5. `naqaa-doctor/app/providers/auth-provider.tsx`
6. `naqaa-doctor/app/api/client.ts`

Be careful with these existing contracts:

- access token expiry is short-lived
- refresh tokens are rotated, not reused
- refresh is device-bound through `deviceId`
- logout may revoke both access token and current-device refresh token
- native backgrounding and Tauri/web unload can trigger sign-out

After auth changes, verify:

- login
- app reload hydration
- one failed request followed by automatic refresh
- manual logout
- deactivated or password-reset users losing access

## Change Form Payload Structure

Inspect in this order:

1. `SaveDigitalFormRequest`
2. `AdmissionController::saveForm()`
3. `DigitalAdmissionForm`
4. `naqaa-doctor/app/admissions/[id].tsx`
5. `naqaa-doctor/components/drawing-*`

Current model:

- form data is sent in `Payload`
- drawing data is sent in `Strokes`
- client hydrates backend payload into local form state

When changing the form shape:

- keep backward compatibility in mind for older saved records
- update request validation if a field becomes required or changes type
- update local hydration logic so old payloads do not break screen rendering
- confirm read-only admissions still block saves correctly

## Update Attachment Behavior

Inspect in this order:

1. `UploadAttachmentRequest`
2. `AdmissionController::uploadAttachment()`
3. `AdmissionController::deleteAttachment()`
4. filesystem config and `Storage::url()` assumptions
5. `naqaa-doctor/app/admissions/[id].tsx`

Things to preserve unless intentionally changing them:

- only image uploads are allowed
- attachments are blocked for closed admissions
- attachments are blocked when legacy documents exist
- metadata and file deletion should stay in sync
- frontend URL normalization should still work if backend returns relative paths

If files appear uploaded but not viewable, check:

- backend disk/storage configuration
- public URL generation
- doctor app `normalizeAttachmentUrl()`

## Change Admin User or Role Flows

Inspect user flows in this order:

1. `Api\Admin\UserController`
2. `Api\Admin\MetadataController`
3. `naqaa-doctor/app/admin/users.tsx`

Inspect role flows in this order:

1. `Api\Admin\RoleController`
2. `Api\Admin\MetadataController`
3. `naqaa-doctor/app/admin/roles.tsx`

Important invariants:

- `doctor` role requires a linked `doctor_id`
- linked doctor must exist in `TblDoctors`
- `doctor_id` is unique across users
- password reset revokes tokens
- deactivation revokes tokens
- `admin` role is protected from deletion
- roles assigned to users cannot be deleted

When changing admin payloads, keep `app/api/client.ts` in sync with backend serializers.

## Debug Backend/Frontend Mismatch

When behavior differs between the backend and doctor app, compare these layers:

1. backend route and controller response
2. backend validation and permission logic
3. doctor app API type definitions
4. auth-provider permission state
5. screen-specific state mapping

Common mismatch patterns:

- backend returns a new field but the client type does not include it
- client sends lowercase keys but backend expects legacy PascalCase keys
- permission exists in auth context but the admission action flags still forbid the write
- UTC boundary filters differ from local date-picker expectations
- backend returns a valid attachment URL but the app rewrites it incorrectly for the current host

## Fast Reference

If you need to change:

- API contract: start with `routes/api.php` and `app/api/client.ts`
- permissions: start with `PermissionCatalog` and `AdmissionController::buildAdmissionActions()`
- login/refresh/logout: start with `AuthController` and `auth-provider.tsx`
- admission detail behavior: start with `AdmissionController` and `app/admissions/[id].tsx`
- list filters or batch tools: start with `app/admissions/index.tsx` and the matching admissions endpoints
- admin accounts and roles: start with admin controllers and `app/admin/*`
