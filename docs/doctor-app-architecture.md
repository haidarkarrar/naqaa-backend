# Doctor App Architecture

## Platform and Runtime

`naqaa-doctor` is an Expo Router application built with React Native and packaged for multiple targets.

Primary stack:

- Expo 54
- React 19 / React Native 0.81
- Expo Router file-based navigation
- web support through Expo web
- optional Tauri desktop packaging around the web build
- image and drawing support through Expo Image APIs and Skia-based drawing components

Key platform settings from `app.json` and `src-tauri/tauri.conf.json`:

- default app orientation: landscape
- web output: static export
- Tauri desktop shell points at the exported web frontend in production
- Tauri dev mode uses Expo web on `http://localhost:19006`

## Route and Screen Map

The app uses Expo Router under `app/`.

### Auth and shell

- `app/_layout.tsx`
  - wraps the app in theme and auth providers
  - defines stack screens
  - exposes header actions like `Admin` and `Logout`
- `app/login.tsx`
  - sign-in screen

### Admission workflow

- `app/admissions/index.tsx`
  - admissions list, filtering, previewing images, and batch status tools
- `app/admissions/[id].tsx`
  - admission detail, patient editing, history sidebar, digital form editing, drawing overlay, attachments, and status actions
- `app/admissions/date-time.ts`
  - formatting helpers used by admissions screens

### Admin workflow

- `app/admin/index.tsx`
  - redirects into users or roles depending on permissions
- `app/admin/users.tsx`
  - user list, form, doctor linking, activation toggling, password resets, direct permission assignment
- `app/admin/roles.tsx`
  - role list, create/update/delete, role permission assignment

## Auth Provider and Session Storage

Auth state is managed in `app/providers/auth-provider.tsx`.

### Stored values

- access token
- access token expiry
- refresh token
- device ID
- serialized `user`
- serialized linked `doctor`

### Storage strategy

- web
  - localStorage fallback for both secure and profile data
- native
  - `expo-secure-store` for token-like secrets
  - `AsyncStorage` for user/doctor profile state

### Auth behavior

- On startup, the provider hydrates stored state.
- If the access token is missing or close to expiry, it attempts refresh automatically.
- A background interval keeps testing refresh eligibility.
- The provider exposes:
  - `login`
  - `logout`
  - `autoSignOut`
  - `refreshAccessToken`
  - `getAccessToken`
  - permission helpers like `hasPermission` and `hasAnyPermission`

### Session lifecycle details

- `login()` uses `authApi.login()` and persists profile plus tokens.
- `logout()` clears local session and notifies backend logout.
- On native, app backgrounding signs the user out unless the app is in a temporary external flow.
- On web/Tauri, page unload hooks call `autoSignOut()` when a session exists.

This means auth changes must be tested across:

- browser
- mobile native
- Tauri desktop

## API Client Structure

The shared API contract lives in `app/api/client.ts`.

### Base request layer

- `API_URL` points to `/api`
- `request()` handles:
  - bearer token injection
  - JSON vs multipart requests
  - query param serialization
  - network error wrapping through `ApiError`
  - one retry after `401` when `reauthenticate` is supplied

### API groups

- `authApi`
  - `login`
  - `logout`
  - `refresh`
  - `me`
- `admissionsApi`
  - list/detail
  - patient update
  - form save
  - attachment upload/delete
  - single status update
  - batch preview/update
- `adminApi`
  - permissions list
  - roles list/create/update/delete
  - users list/show/create/update/password/activation
  - doctor search

### Compatibility layer

- `doctorApi` is still exported as a wrapper around the newer auth/admissions APIs for older frontend call sites.

## Admissions List Screen

`app/admissions/index.tsx` is the main list screen.

Responsibilities:

- redirect unauthenticated users back to login
- fetch paginated admissions through `admissionsApi.fetchAdmissions()`
- build UTC date boundaries from local date pickers
- filter by:
  - status
  - patient text
  - start/end date
- display backend-provided action availability indirectly through row behavior
- show preview modals for legacy thumbnails
- provide batch status tooling when the user has `admissions.status.update.batch`

Important maintenance details:

- The screen keeps a support/debug card when loading fails.
- It computes API request paths for support output.
- Batch operations use a preview-first workflow and handle `409` conflicts by refetching preview counts.

## Admission Detail Screen

`app/admissions/[id].tsx` is the most complex screen in the app.

Responsibilities:

- load one admission through `admissionsApi.fetchAdmission()`
- keep a history sidebar that can switch between admission records and legacy-only history entries
- render patient metadata and checklist selections
- allow patient updates when `canEditPatientInfo` is true
- render and save digital form payload fields
- restore and persist drawing strokes for pen/eraser overlay
- upload and delete attachments
- preview current attachments and legacy documents
- open/close admission status when `canChangeStatus` is true

Important constraints mirrored from the backend:

- legacy documents make the admission read-only for form and attachment edits
- closed admissions make the admission read-only for form and attachment edits
- lack of permissions should block writes even if fields are visible

### Form and drawing behavior

- Form payload is stored as a generic record and merged into UI field state.
- Strokes are hydrated from backend JSON into local `Stroke[]` objects.
- Drawing tools support pen, eraser, undo, and redo.
- Attachment URLs are normalized against the backend base URL because the backend may return host-relative or differently-hosted URLs.

## Admin Screens

### Admin entry

`app/admin/index.tsx` decides where to route the user:

- users screen if they can manage users
- roles screen if they can manage roles
- admissions screen otherwise

### Users screen

`app/admin/users.tsx` combines several admin responsibilities:

- paginated user search
- create vs edit flow in one form
- role assignment
- direct permission assignment
- doctor search and linking
- activation toggling
- password reset flow

The UI mirrors backend invariants:

- doctor role requires a linked doctor
- linked doctor implies doctor role
- role/permission sections only appear when the current user may assign them

### Roles screen

`app/admin/roles.tsx` supports:

- paginated role list
- create/update role
- assign permissions to roles
- delete role when allowed

The screen also mirrors backend restrictions:

- protected roles cannot be deleted
- permission assignment UI is hidden if the current user cannot assign permissions

## Permission-Gated UI Model

The app uses two sources of gating:

- current authenticated user permissions from auth context
- admission-specific action flags returned by the backend

Examples:

- admin navigation appears when the user is an `admin` or has `users.view` / `roles.view`
- batch status tools appear when the user has `admissions.status.update.batch`
- detail-screen write actions depend on per-admission flags such as:
  - `canEditForm`
  - `canEditPatientInfo`
  - `canManageAttachments`
  - `canChangeStatus`
  - `canViewHistory`

When changing permission behavior, update both:

- backend permission enforcement and action-flag generation
- frontend visibility and disabled-state logic

## Web, Mobile, and Tauri Differences

The app is not identical across platforms.

### Web

- uses browser storage fallback
- shows inline notice toasts for some messages
- relies on browser clipboard APIs for support copying

### Native mobile

- uses `SecureStore` and `AsyncStorage`
- signs out on background unless a temporary external flow is active
- uses native pickers and gesture handling

### Tauri desktop

- runs the web app inside a desktop shell
- page unload hooks are treated like an app-close boundary
- mixed-content and host/origin differences matter when calling the backend

If a bug happens only on one platform, inspect the auth-provider and the request debug/mixed-content handling in `app/api/client.ts` first.
