# Mfano Bora Resources Portal — Data Pipeline Audit & Recommendations

**Scope:** review whether the SQLite backend, admin panel, and public
frontend actually form a single source of truth (SSOT), as intended by
the "publish a resource → it appears on the site → downloads get
logged back to the admin panel" workflow.

## 1. Critical finding: the frontend was not wired to the database

`frontend/index.php` and `frontend/documents.php` contained a hardcoded
PHP array of categories and ~100 documents pointing at static file paths
(e.g. `resources/category-1/attachment-application-form.pdf`) that mostly
did not exist on disk (`Backend+AdminPanel/uploads/resources/` only had 2
real uploaded files, with random hashed names). Neither file `require`d
`config/db.php`, called any `/api/*` endpoint, or referenced `is_published`,
`sub_category_id`, or any other database concept.

**Consequence:** the admin panel's "04 · Library / Existing Resources"
Publish/Unpublish button, and the "Add New Resource" form, updated the
`resources` table correctly — but the public site never read that table,
so none of it reached visitors. The system looked integrated (matching
categories/sub-categories existed on both sides) but the two halves were
not actually connected.

**Fix applied:** `frontend/inc/bootstrap.php` now `require`s the same
`Backend+AdminPanel/config/db.php` connection the admin API uses.
`index.php` renders live category document counts from the database;
`documents.php` renders only `is_published = 1` resources grouped by the
admin-assigned sub-category. Both files are heavily commented explaining
the before/after. Downloads route through `api/download.php`, which both
streams the file and inserts a `download_logs` row — so the admin's
"05 · Activity / Download Logs" module now genuinely reflects frontend
activity, not just direct API testing.

## 2. Critical finding: the setup scripts pointed at the wrong directory

Both `mfano_setup_and_run_improved.sh` and the Windows scripts referenced
`$PROJECT_ROOT/admin`, `$PROJECT_ROOT/api`, `$PROJECT_ROOT/database`,
`$PROJECT_ROOT/config` — but those files actually live one level down,
under `Backend+AdminPanel/`. The Linux script would `die` immediately at
its "Checking project files" step; the PowerShell/Bash Windows scripts
would silently apply `schema.sql` to a database file at the wrong path
and never touch the real one.

**Fix applied:** all three scripts now resolve paths under
`Backend+AdminPanel/`, and `mfano_setup_and_run_improved.sh` /
`mfano_setup_and_run_windows.ps1` now also: run `PRAGMA integrity_check`
against the SQLite file, start the PHP server from the repo root (so both
`Backend+AdminPanel/` and `frontend/` are served), health-check
`/api/health.php`, verify the frontend responds, and open **both** the
admin panel and the public frontend in the browser.

## 3. Secondary finding: Windows scripts stored the admin key in a
   non-existent, unread database table

Both Windows variants generated an API key and ran
`INSERT ... INTO settings (name, value, ...) VALUES ('admin_api_key', ...)`
— but no `settings` table exists anywhere in `schema.sql`, and
`includes/auth.php` only ever reads the key from
`Backend+AdminPanel/config/config.php`. This code path either silently
failed (Bash, due to `2>/dev/null || echo ""`) or wrote to a location the
application never checks (PowerShell). **Fix applied:** both scripts now
write the generated key into `config.php`, matching the Linux script and
what `includes/auth.php` actually reads.

## 4. Security recommendations

| Issue | Status | Notes |
|---|---|---|
| Real `admin_api_key` committed in plaintext in `config/config.php` | **Action needed** | Rotate immediately — see README "Security notes". Not something code can fix retroactively. |
| No `.htaccess` protecting `database/`, `config/`, `includes/` from direct HTTP access under Apache | **Fixed** | Added deny-all `.htaccess` files; `uploads/` gets a narrower rule that still allows PDF downloads but blocks script execution and directory listing. |
| Unlimited admin API key guess attempts | **Fixed** | `includes/auth.php` now rate-limits failed attempts per IP via the new `admin_auth_attempts` table (config-tunable). |
| Kill switch (`api/admin/kill_switch.php`) reachable with just the same shared key, no environment gate | **Fixed** | Now requires `security.kill_switch_enabled = true` in `config.php`, off by default, plus an `error_log` audit line recording IP + timestamp on every run. Also switched to the shared, rate-limited `requireAdminKey()` instead of its own weaker inline check. |
| `export_csv.php` re-implemented its own auth check (no rate limit, no CORS headers) instead of reusing `includes/auth.php` | **Fixed** | Now uses `applyCors()` + `requireAdminKey()` like every other admin endpoint. |
| Admin key held in browser `sessionStorage`, sent on every admin request | **Documented, not changed** | Reasonable for a small trusted admin team; recommend moving to per-user login (hashed password + session token) if the team grows — noted in README as a future improvement, out of scope for this pass to avoid a breaking auth-model change without your sign-off. |
| `download_logs` retains IP + user agent indefinitely | **Documented, not changed** | Add a retention/anonymization job if required by your privacy policy; flagged in README. |
| Uploaded file validation | **Reviewed, already solid** | `includes/upload.php` already checks real MIME type via `finfo` (not just the client-supplied name), enforces a 25MB cap, and stores under a random 32-hex filename — good practice, left as-is. |

## 5. UI/UX recommendations

- **Fixed:** frontend document counts per category are now real
  (computed from published resources), not hardcoded numbers that could
  drift from reality.
- **Fixed:** an empty state ("No published documents yet") now renders
  per-category on the frontend instead of a confusing empty page or,
  previously, static content unrelated to what was actually published.
- Recommended (not yet implemented, to scope this change conservatively):
  - Pagination on the admin "Existing Resources" and "Download Logs"
    tables — both currently load every row in one response, which will
    get slow as the library grows past a few hundred resources/logs.
  - A category/sub-category/status filter above the admin resource
    table, and a date-range filter above the download log table.
  - Show `file_size_kb` and `updated_at` on the public resource cards
    (the data already exists in the query — surfaced in this update's
    `documents.php`).
  - Toast-style transient notifications in the admin panel instead of
    the current persistent inline message divs, for a lighter feel on
    repeated actions.

## 6. Data routing / API recommendations

- **Reviewed:** there are two independent ways a "download" can be
  logged — `POST /api/resources.php?id=N&action=download` (log only) and
  `GET /api/download.php?id=N` (stream + log). The rewritten frontend
  uses **only** `download.php` as the canonical path, so a single click
  never double-logs. `resources.php`'s `action=download` is left in place
  for any other integration that only needs to record a "view" event
  without streaming the file, but is not used by the current frontend.
- Recommended (not implemented in this pass): add `LIMIT`/`OFFSET`
  pagination to `GET /api/resources.php` and `GET /api/admin/logs.php`
  for the same scaling reason as the UI pagination point above.
- Recommended: add `Cache-Control`/`ETag` headers to the public,
  unauthenticated `GET /api/categories.php` and `GET /api/resources.php`
  responses — they change relatively rarely and are safe to cache
  briefly at the edge/browser.

## 7. What was intentionally left unchanged

- The overall schema design (categories → sub_categories → resources →
  download_logs, with FTS5 search and the `download_count` trigger) is
  sound and was not restructured — only additively extended with the new
  `admin_auth_attempts` table, which is safe to apply to an existing
  database (`CREATE TABLE IF NOT EXISTS`).
- The admin panel's client-side JS (`admin.js`) already correctly talks
  to the real API and needed no changes — the disconnect was entirely on
  the public-frontend side.
