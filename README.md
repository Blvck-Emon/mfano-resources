# README — Mfano Bora Resources Portal 
## What is this

A lightweight PHP backend + admin UI to manage and serve digital resources (PDFs, guides, templates) grouped in categories and sub-categories. The app exposes public REST endpoints and protected admin endpoints guarded by a shared admin API key.

Files and structure are in the `mfano-resources/` folder; a compact project index is included in the compiled resources. 

---

## Quick architecture summary

* Data: SQLite (development) — tables: `categories`, `sub_categories`, `resources`, `download_logs`, etc. (schema in `database/schema.sql`).
* Backend: native PHP scripts exposing REST endpoints in `/api/`.
* Admin UI: static frontend at `/admin/index.html` that calls the admin API endpoints using an `X-Api-Key` header (see `config/config.example.php`). 

---

## Prerequisites

* PHP (>= 8 recommended) with PDO + SQLite (or pdo_pgsql if you deploy with PostgreSQL).
* `sqlite3` (for development), `curl` (optional), a browser.
* (Optional for production) Apache/Nginx and PHP-FPM.

---

## 1) Clone the repo

```bash
git clone <your-repo-url> mfano-resources
cd mfano-resources
```

---

## 2) Setup — automatic (recommended for dev)

There are helper scripts included:

### Linux / macOS (one-step)

1. Make the script executable and run it:

```bash
chmod +x ./mfano_setup_and_run_improved.sh
./mfano_setup_and_run_improved.sh
```

What it does (high level): checks required tools, creates `database/mfano_bora.sqlite` if missing, applies `database/schema.sql`, offers to load `database/seed.sql`, creates `config/config.php` from the example, generates an admin API key if needed, exports CSV snapshots and starts the built-in PHP server. The script also launches the admin frontend in your browser. (See `mfano_setup_and_run_improved.sh`.) 

### Windows (Git Bash / WSL) — convenience script

Run:

```bash
chmod +x ./mfano_setup_and_run_windows.sh
./mfano_setup_and_run_windows.sh
# or, use the PowerShell script:
./mfano_setup_and_run_windows.ps1
```

The Windows scripts create folders, apply schema, copy `config.example` to `config.php` as needed, and insert a generated admin key into the DB if missing. Example: the Windows script inserts an `admin_api_key` into the `settings` table. 

---

## 3) Manual setup (if you prefer to do steps yourself)

### Apply the schema

```bash
# for sqlite (dev)
sqlite3 database/mfano_bora.sqlite < database/schema.sql
```

(If you deploy with Postgres, run `psql -U <user> -d <db> -f database/schema.sql` and adjust `config/config.php`.) 

### Seed data (optional)

```bash
sqlite3 database/mfano_bora.sqlite < database/seed.sql
```

The seed file contains the default 10 categories, sub-categories and example resources. 

### Create config

Copy the example and update DB settings / domain / allowed origin:

```bash
cp config/config.example.php config/config.php
# edit config/config.php: set DB path/credentials & admin_api_key & allowed_origin
```

The example shows the `admin_api_key` entry in the example config. 

---

## 4) Generate & copy the Admin API key

You have two places where an admin key may live:

1. **In `config/config.php`** — when the example contained a placeholder you should replace it with a generated key. The example shows where to place `admin_api_key`. 

2. **In the `settings` table inside the SQLite DB** — the setup scripts may insert the key into the DB under `settings.name = 'admin_api_key'`. Example insertion logic is present in the helper scripts. 

### Generate a secure key (48 hex chars)

```bash
php -r "echo bin2hex(random_bytes(24));"
```

Use the printed value as your `admin_api_key`. 

### Copy from SQLite (if the setup inserted it there)

```bash
sqlite3 database/mfano_bora.sqlite "SELECT value FROM settings WHERE name='admin_api_key' LIMIT 1;"
```

This will print the key inserted by the setup scripts (if present). 

### Or: place it in `config/config.php`

Open `config/config.php` and set:

```php
'admin_api_key' => 'paste-your-generated-key-here',
```

Make sure `config/config.php` is protected (it’s typically gitignored).

---

## 5) Run the application (dev)

From the project root:

```bash
# Start PHP built-in server
php -S 127.0.0.1:8000 -t .
# Admin UI: http://127.0.0.1:8000/admin/index.html
# Public API health: http://127.0.0.1:8000/api/health.php
```

The improved setup script will automatically start the same built-in server and wait for the health endpoint before opening the admin. 

---

## 6) Add a document / resource

There are two common ways to add resources:

### A) Admin Dashboard (recommended)

Open the admin UI in your browser:

```
http://127.0.0.1:8000/admin/index.html
```

Sign/authorize with the admin key (the admin UI sends the `X-Api-Key` header). Use the UI forms to create a category / sub-category or upload a resource. The UI calls the admin endpoints under `/api/admin/`. 

### B) Direct API (curl) — POST a new resource

The `resources` table expects fields such as `sub_category_id`, `title`, `description`, `file_url` (or use local upload). The seed SQL shows example inserts using `(sub_category_id, title, description, file_url, is_featured)` which reflects the API’s expected fields when creating a resource. Use the admin endpoint and include `X-Api-Key`. 

Example (external file URL):

```bash
curl -X POST "http://127.0.0.1:8000/api/admin/resources.php" \
  -H "X-Api-Key: your_admin_key_here" \
  -F "sub_category_id=1" \
  -F "title=My Document Title" \
  -F "description=Short description" \
  -F "file_url=https://example.com/path/to/doc.pdf" \
  -F "is_featured=0"
```

If you want to upload a file to be stored locally, use the admin UI (it handles file multipart upload) or check `includes/upload.php` and `api/admin/resources.php` to see how the backend expects the multipart file and fields (the code supports `storage_type = 'local'` and `stored_path` for local uploads). The `resources` schema includes `storage_type`, `stored_path`, `checksum_sha256` for local uploads. 

---

## 7) Useful queries (inspection & troubleshooting)

* Show categories count (sqlite):

```bash
sqlite3 database/mfano_bora.sqlite "SELECT COUNT(*) FROM categories;"
```

* Read current `admin_api_key` from DB:

```bash
sqlite3 database/mfano_bora.sqlite "SELECT value FROM settings WHERE name='admin_api_key' LIMIT 1;"
```

(Setup scripts insert or replace this entry when creating the DB/config.) 

* Health endpoint:

```
http://127.0.0.1:8000/api/health.php
```

---

## Tips & security

* Never commit `config/config.php` with a real `admin_api_key` to source control. Use environment-specific config and keep secrets out of the repo. The example config shows the placeholder field to replace. 
* For production, run behind Nginx/Apache and enforce HTTPS. Consider moving to PostgreSQL for larger deployments (the original design supported Postgres). 

---

## Where in the repo to look for details

* `mfano_setup_and_run_improved.sh` — end-to-end dev setup + server entrypoint. 
* `database/schema.sql` — DB structure and triggers. 
* `database/seed.sql` — example categories/subcategories/resources. 
* `config/config.example.php` — example runtime settings incl. `admin_api_key`. 
* `admin/index.html`, `admin/js/admin.js` — admin frontend and API integration. 

---
