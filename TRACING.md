# Tracing API requests on Windows IIS

Use this to find out why **GET single admission** (or other API calls) are failing on the server.

## 1. Laravel logs (first place to check)

- **Path:** `storage/logs/laravel.log` (relative to the app root, e.g. `D:\path\to\naqaa-backend\storage\logs\laravel.log`).
- **Ensure logging is on:** In `.env` set `LOG_CHANNEL=stack` and `LOG_LEVEL=debug` (or at least `info`).
- **What you’ll see:**
  - **"API get single admission request"** – request reached the controller (admission id, doctor id, URL).
  - **"API get single admission failed"** – an exception was thrown (message, file, line, stack trace).

If you see **no log line at all** for the request, the failure is likely before Laravel (IIS, URL rewrite, or auth).

## 2. IIS Failed Request Tracing (see why IIS returned an error)

1. In **IIS Manager** select your site (or the server).
2. Double‑click **Failed Request Tracing…**.
3. **Enable** tracing and choose a folder (e.g. `C:\inetpub\logs\FailedReqLogFiles`).
4. **Add** a tracing rule:
   - **All content (\*).**
   - Status codes: e.g. **400-599** (or **500** if you only care about server errors).
5. Reproduce the failing request from the app.
6. Open the latest **.xml** (or **.html**) under the log folder; it shows which module failed and the request/response at that stage.

## 3. IIS request logs (see if the request hit IIS)

- **Path:** Usually `C:\inetpub\logs\LogFiles\W3SVC<site-id>\` (or the path set in the site’s **Logging**).
- Check the **time** and **URL** of the failing call (e.g. `GET /api/doctor/admissions/123`).
- **Status code** (e.g. 401, 404, 500) tells you whether it’s auth, not found, or server error.

## 4. Quick checklist

| If you see… | Then… |
|-------------|--------|
| No "API get single admission request" in `laravel.log` | Request never reached Laravel: check IIS rewrite, URL, or auth (401). |
| "API get single admission request" then "API get single admission failed" | Check the exception message and stack trace in `laravel.log`. |
| 404 in IIS log, URL looks correct | Check URL rewrite rule and that `index.php` gets the path (e.g. `api/doctor/admissions/123`). |
| 401 in IIS log | Token missing/invalid/expired; check `Authorization: Bearer <token>` from the app. |
| 500 in IIS log | Use Failed Request Tracing and Laravel’s exception log to see the exact error. |

## 5. Test the endpoint from the server

From PowerShell on the server (replace URL and token):

```powershell
$token = "YOUR_DOCTOR_API_TOKEN"
$admissionId = 1
Invoke-WebRequest -Uri "http://localhost/api/doctor/admissions/$admissionId" `
  -Headers @{ Authorization = "Bearer $token" } `
  -UseBasicParsing | Select-Object StatusCode, Content
```

Then check `storage/logs/laravel.log` for the "API get single admission request" / "API get single admission failed" lines.
