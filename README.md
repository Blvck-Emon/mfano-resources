# Mfano Bora Resources Portal

A SQLite-backed resource library: an admin panel for uploading/publishing
PDF resources into categories & sub-categories, a public frontend where
visitors browse and download them, and a JSON API that ties the two
together as a single source of truth.

```
mfano-resources/
├── mfano_setup_and_run_improved.sh    # Linux/macOS: setup + run everything
├── mfano_setup_and_run_windows.ps1    # Windows: setup + run everything
├── mfano_setup_and_run_windows.sh     # Windows (Git Bash/WSL): setup only
├── Backend+AdminPanel/
│   ├── database/        # schema.sql, seed.sql, mfano_bora.sqlite, exports/
│   ├── config/          # config.php (secret, gitignored), config.example.php
│   ├── admin/           # Admin panel (index.html, css/, js/)
│   ├── api/             # Public + /api/admin/* JSON endpoints
│   ├── includes/        # Shared PHP (auth, uploads, helpers)
│   └── uploads/resources/  # Uploaded PDFs (random filenames)
└── frontend/
    ├── inc/bootstrap.php  # Connects the public site to the SAME database
    ├── index.php           # Category grid (live counts from the DB)
    ├── documents.php        # Published resources for one category
    ├── css/, js/
```

## How the pieces actually connect (single source of truth)

```
 Admin uploads PDF, picks Category > Sub-Category
             │
             ▼
 POST /Backend+AdminPanel/api/admin/resources.php   (X-Api-Key required)
             │  validates PDF (mime+ext), stores under uploads/resources/<random>.pdf
             ▼
        resources table  (is_published = 1 by default on creation)
             │
             │   Admin can Publish / Unpublish any time from
             │   "04 · Library / Existing Resources" — this flips
             │   resources.is_published via PUT /api/admin/resources.php
             ▼
 frontend/index.php + documents.php read the SAME SQLite database
 directly (frontend/inc/bootstrap.php → Backend+AdminPanel/config/db.php)
 and only ever show rows where is_published = 1, under the exact
 category/sub-category the admin assigned.
             │
             ▼
 Visitor clicks "View / Download" → GET /Backend+AdminPanel/api/download.php?id=N
             │  streams the PDF AND inserts one row into download_logs
             ▼
 Admin panel "05 · Activity / Download Logs" reads download_logs
 via GET /api/admin/logs.php — the download shows up immediately.
```

**This is the part that was fixed in this update.** Previously,
`frontend/index.php` and `frontend/documents.php` contained a hardcoded
PHP array of ~100 documents with no relationship to the database at all —
publishing or unpublishing a resource in the admin panel had **no effect**
on the public site, because the public site never queried the database.
See `frontend/inc/bootstrap.php` for the new connection layer, and the
comments at the top of `index.php` / `documents.php` for details.

## Prerequisites

- PHP 8.x with `pdo_sqlite` enabled (`php -m | grep pdo_sqlite`)
- `sqlite3` CLI
- Bash (Linux/macOS/WSL/Git Bash) or PowerShell (Windows)

## 1. Clone the repo

```bash
git clone <your-repo-url> mfano-resources
cd mfano-resources
```

## 2. Run the backend, admin panel, and frontend together

**Linux / macOS:**

```bash
chmod +x mfano_setup_and_run_improved.sh
./mfano_setup_and_run_improved.sh
```

**Windows (PowerShell):**

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\mfano_setup_and_run_windows.ps1
```

Either script will:

1. Check for PHP + `pdo_sqlite` + `sqlite3`.
2. Create `Backend+AdminPanel/database/mfano_bora.sqlite` if it doesn't
   exist yet, and (re-)apply `schema.sql` — this is idempotent, so
   re-running it on an existing database only adds anything new (tables,
   indexes) without touching existing data.
3. Run `PRAGMA integrity_check` against the SQLite file and stop if the
   database is corrupted.
4. Optionally load `seed.sql` (10 categories / 102 sub-categories).
5. Create `Backend+AdminPanel/config/config.php` from
   `config.example.php` and generate a random `admin_api_key` if one
   isn't already set.
6. Start PHP's built-in server from the repo root (so both
   `Backend+AdminPanel/` and `frontend/` are reachable in one process).
7. Wait for `/Backend+AdminPanel/api/health.php` to report success.
8. Open **both** the admin panel and the public frontend in your browser:
   - Admin panel: `http://127.0.0.1:8000/Backend+AdminPanel/admin/index.html`
   - Frontend: `http://127.0.0.1:8000/frontend/index.php`

To find your admin API key after setup:

```bash
php -r "echo (require 'Backend+AdminPanel/config/config.php')['admin_api_key'], PHP_EOL;"
```

Paste it into the "Admin API Key" field at the top of the admin panel —
it's kept in `sessionStorage` for that browser tab only.

## 3. Publishing a resource end-to-end

1. In the admin panel, open **"A · Add New Resource"**, pick a Category
   & Sub-Category, upload a PDF, and submit — it's published immediately.
2. To publish/unpublish something already in the library, use the
   **Publish / Unpublish** button in **"04 · Library / Existing
   Resources"**.
3. Reload the public frontend — the resource now appears (or disappears)
   under that exact category/sub-category, with no redeploy needed.
4. Click "View / Download" on the frontend — the download is logged and
   shows up in **"05 · Activity / Download Logs"** in the admin panel.

## Deploying to a live server (shared hosting / Apache)

1. Upload the whole repo (excluding `.git`) to your hosting account,
   e.g. as a subfolder of your existing site:
   `public_html/resources/` containing `Backend+AdminPanel/` and
   `frontend/` as siblings, matching the local layout.
2. Ensure PHP has `pdo_sqlite` enabled on the host (most shared hosts do
   by default; check with your host's PHP extension panel if not).
3. Copy `Backend+AdminPanel/config/config.example.php` to
   `Backend+AdminPanel/config/config.php` and set:
   - `admin_api_key` — a fresh value from
     `php -r "echo bin2hex(random_bytes(24));"` (**never** reuse a key
     that has ever been committed to git or shared — see Security below).
   - `allowed_origin` — your real site origin, e.g.
     `https://www.mfanoboraafrica.com` (not `*` in production).
   - `security.kill_switch_enabled` — leave `false` unless this is a
     disposable demo environment.
4. Make sure `Backend+AdminPanel/database/`, `Backend+AdminPanel/config/`,
   and `Backend+AdminPanel/uploads/` are writable by the PHP process
   (typically `chmod 750` + correct ownership, not `777`).
5. Run `sqlite3 Backend+AdminPanel/database/mfano_bora.sqlite < Backend+AdminPanel/database/schema.sql`
   once to create the database, then load `seed.sql` if you want the
   default taxonomy.
6. Visit `/Backend+AdminPanel/api/health.php` on the live domain and
   confirm `"success": true` and `"database": "sqlite"`.
7. Visit `/frontend/index.php` and confirm the category grid loads.

### Updating the existing live Mfano Bora root website folder

If the resources portal is deployed as a subfolder of the main
`mfanoboraafrica.com` site (the common setup — the admin/frontend footer
links already point back at `https://www.mfanoboraafrica.com/...`):

1. **Never overwrite these two paths on the live server** when deploying
   an update — doing so wipes real data:
   - `Backend+AdminPanel/database/mfano_bora.sqlite`
   - `Backend+AdminPanel/config/config.php`
   - `Backend+AdminPanel/uploads/resources/*` (the actual uploaded PDFs)
2. Recommended update flow (rsync example — adapt for your host's
   deploy method, e.g. cPanel File Manager / FTP / CI pipeline):

   ```bash
   rsync -av --delete \
     --exclude 'Backend+AdminPanel/database/mfano_bora.sqlite' \
     --exclude 'Backend+AdminPanel/database/exports/' \
     --exclude 'Backend+AdminPanel/config/config.php' \
     --exclude 'Backend+AdminPanel/uploads/resources/' \
     ./ user@yourserver:/path/to/public_html/resources/
   ```
3. After syncing code, re-run the idempotent schema apply on the server
   so any new tables/columns are added without touching existing rows:

   ```bash
   sqlite3 Backend+AdminPanel/database/mfano_bora.sqlite < Backend+AdminPanel/database/schema.sql
   ```
4. Take a backup copy of `mfano_bora.sqlite` (and the `uploads/resources/`
   folder) before every deploy — it's a single file, so this is just a
   copy:

   ```bash
   cp Backend+AdminPanel/database/mfano_bora.sqlite \
      Backend+AdminPanel/database/backups/mfano_bora_$(date +%Y%m%d%H%M%S).sqlite
   ```
5. If the resources portal needs to be linked from the main site's
   navigation/menu, add the link to wherever the main Mfano Bora Africa
   WordPress/static site menu is managed — that's outside this repo.

## Security notes (read before deploying)

- **Rotate the admin API key immediately if this repo (or the compiled
  system export used to review it) was ever shared outside your team.**
  A real, working key was present in `Backend+AdminPanel/config/config.php`
  in the version reviewed for this update — treat it as compromised and
  regenerate with `php -r "echo bin2hex(random_bytes(24));"`.
- `config/config.php` must stay out of git (confirm it's listed in
  `.gitignore`) — only `config.example.php` (no real secret) should ever
  be committed.
- New in this update: failed `X-Api-Key` attempts against `/api/admin/*`
  are now rate-limited per IP (`security.max_failed_attempts` /
  `lockout_minutes` in `config.php`) — see `includes/auth.php`.
- New in this update: the "Delete everything" kill switch
  (`api/admin/kill_switch.php`) now refuses to run unless
  `security.kill_switch_enabled = true` is explicitly set in
  `config.php`. Keep it `false` in production.
- New in this update: `.htaccess` files were added under
  `Backend+AdminPanel/database/`, `config/`, `includes/`, and
  `uploads/` to prevent the SQLite file, `config.php`, and PHP includes
  from ever being served as raw static files if this is deployed under
  Apache, while still allowing the actual PDFs to be opened directly.
  (These have no effect under PHP's built-in dev server — Apache only.)
- The admin key is stored in the browser tab's `sessionStorage`, not
  `localStorage` — it's cleared when the tab closes, but it is still a
  single shared secret with full CRUD + delete-everything power. For a
  larger team, consider replacing the shared static key with per-admin
  login (hashed passwords + short-lived session tokens) as a future
  improvement.
- `download_logs` stores IP address + user agent per download
  indefinitely. If this matters for your privacy policy, add a periodic
  job to anonymize/purge rows older than N days.

## Troubleshooting

- **"Required file not found" when running the setup script** — you're
  probably running an old copy of the script from before this fix; the
  correct paths are under `Backend+AdminPanel/`, not the project root.
- **Health check fails** — check `.php-server.log` in the project root
  for the PHP error, and confirm `pdo_sqlite` is enabled with
  `php -m | grep -i sqlite`.
- **Frontend shows "No published documents yet"** — this is expected
  until you publish resources into that category from the admin panel;
  it no longer means the frontend is broken.
- **Downloads not appearing in "05 · Activity / Download Logs"** —
  confirm the frontend's download links point at
  `/Backend+AdminPanel/api/download.php?id=N` (see
  `frontend/inc/bootstrap.php::mfano_download_url()`), not directly at a
  static file path.