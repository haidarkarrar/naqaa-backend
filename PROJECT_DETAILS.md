## Naqaa Backend Overview

- **Tech stack:** Laravel 11 application with multiple database connections (`meditop`, `archive`, default `sqlite/mysql`) defined in `config/database.php`. Composer and Vite tooling support the admin UI in `resources/`.
- **Purpose:** Serves authenticated API endpoints consumed by the Naqaa Doctor Expo app (login, admissions list, detail, digital forms, attachments).

### Public API surface

- `POST /api/doctor/login` → `AuthController@login` validates username/password, issues a hashed `DoctorApiToken` tied to the doctor.
- Protected by `AuthenticateDoctor` middleware that looks up the bearer token, updates `last_used_at`, and resolves the doctor/user.
- `POST /api/doctor/logout` → deletes the token.
- `GET /api/doctor/admissions` → `AdmissionController@index` fetches up to 80 admissions for the logged-in doctor, with optional `start_date`, `end_date`, and `status` filters. Each payload includes patient name, formatted date, and status.
- `GET /api/doctor/admissions/{id}` → `AdmissionController@show` returns the admission record plus patient, history, digital form, and attachments after checking doctor ownership.
- `POST /api/doctor/admissions/{id}/form` → `saveForm` stores or updates `TblDigitalAdmissionForms` with JSON `payload`, optional strokes/metadata, and `status`/`form_version`.
- `POST /api/doctor/admissions/{id}/attachments` → `uploadAttachment` streams the uploaded image to the `public` disk under `admissions/` and records metadata in `TblAdmissionAttachments` (naqaa connection).

### Key models

- `Doctor` (connection `meditop`, table `TblDoctors`) manages doctors, relations to tokens and admissions.
- `AdmissionFile` (`TblAdmFiles`) links to `Patient`, `DigitalAdmissionForm`, and `AdmissionAttachment`.
- `DigitalAdmissionForm` persists form payload + strokes arrays, `status`, and `form_version`.
- `AdmissionAttachment` (connection `naqaa`, table `TblAdmissionAttachments`) stores `path`, `mime`, `label`, and `UploadedAt`.
- `DoctorApiToken` hashes tokens, enforces expiration, and includes helper `findForToken()` used by the auth middleware.

### Validation & requests

- Requests under `App\Http\Requests\Api` guard the payloads:
  - `LoginRequest` requires `username`/`password`.
  - `SaveDigitalFormRequest` enforces an array `payload` and optional `strokes`.
  - `UploadAttachmentRequest` only allows JPG/PNG images with an optional label.

### Operational notes

- Attachments go to the `public` disk; ensure `php artisan storage:link` runs if needed.
- API tokens expire after 12 hours by default; `AuthenticateDoctor` refreshes `last_used_at` each request.
- Admissions history uses the `PatientId` to show recent visits (max 5).
- Controllers always verify `DoctorId` ownership before mutating data.

This file is meant to help Cursor shoppers understand the backend responsibilities and how the API connects to the doctor-facing app.

### php artisan serve --host=0.0.0.0 --port=8000