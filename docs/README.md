# Naqaa Workspace Technical Docs

This documentation set describes the two applications that make up the current Naqaa doctor workflow:

- `naqaa-backend`: Laravel backend, API layer, permissions system, and admin surface.
- `naqaa-doctor`: Expo Router client used by doctors and admins on web, mobile, and Tauri desktop.

These apps are developed as separate codebases but behave as one system. The doctor app depends on backend auth, admission APIs, and permission flags to decide what the UI can show and mutate.

## Start Here

If you are new to the project, read the docs in this order:

1. [Backend Architecture](./backend-architecture.md)
2. [Doctor App Architecture](./doctor-app-architecture.md)
3. [Change Guide](./change-guide.md)

## Workspace Map

### `naqaa-backend`

- Primary system of record for:
  - user authentication and refresh-token rotation
  - role and permission management
  - admission list/detail APIs
  - patient updates, digital forms, attachments, and status changes
  - admin endpoints for users, roles, and doctor lookup
- Uses multiple database connections, especially:
  - `naqaa` for app-owned auth/admin data
  - `meditop` for admissions, doctors, patients, and legacy clinical records
  - `archive` as an additional SQL Server connection defined in config

### `naqaa-doctor`

- Client application built with Expo Router and React Native.
- Supports web, mobile, and a Tauri-packaged desktop shell.
- Provides:
  - login/logout and token refresh handling
  - admissions list and filters
  - admission detail, history, patient editing, digital form editing, and attachments
  - admin screens for users and roles when permissions allow

## High-Level Runtime Flow

1. A user signs in through `POST /api/auth/login`.
2. The backend returns:
   - a short-lived access token
   - a long-lived refresh token
   - a device ID
   - serialized user and linked doctor data
3. The doctor app stores auth state locally and uses the access token for API requests.
4. If a request returns `401`, the doctor app tries `POST /api/auth/refresh` once, then retries the original request.
5. The backend returns permission-driven action flags on admissions so the client can disable or hide unsupported actions.

## Source of Truth Rules

- Prefer live code over old notes in `PROJECT_DETAILS.md` or starter `README.md` files.
- Backend API shape is defined by:
  - `routes/api.php`
  - controllers under `app/Http/Controllers/Api`
  - request validation classes under `app/Http/Requests/Api`
- Doctor app behavior is defined by:
  - `app/providers/auth-provider.tsx`
  - `app/api/client.ts`
  - screens under `app/admissions` and `app/admin`

## What These Docs Optimize For

- onboarding a developer into both apps quickly
- showing where to inspect before changing behavior
- documenting cross-app contracts, not just isolated files
- reducing regressions caused by auth, permissions, or payload mismatches
