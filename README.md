# Mfano Bora Resources Porta

## What this is

Mfano Bora Resources Portal is a small site that stores PDF resources in a SQLite database. Administrators can upload, publish and unpublish PDFs in an admin area, and the public site shows only published resources.

---

## 1 — Install VS Code (quick)

1. Visit [https://code.visualstudio.com](https://code.visualstudio.com) and download the version for your operating system.
2. Install it using the installer.
3. Open VS Code and install the **Git** extension (usually built in) if you want GUI Git support.
   (You can also use Terminal / PowerShell for Git commands.)

---

## 2 — Clone the repository and switch branch

Open a terminal / command prompt and run:

```bash
# clone the repo
git clone https://github.com/Blvck-Emon/mfano-resources.git mfano-resources

# go into the project
cd mfano-resources

# fetch branches and switch to Combined_Resource_portal
git fetch --all
git checkout Combined_Resource_portal
# or: git switch Combined_Resource_portal
```

---

## 3 — Run the whole system (dev)

### On Linux or macOS

```bash
# make the startup script executable (one-time)
chmod +x ./mfano_setup_and_run_improved.sh

# run it
./mfano_setup_and_run_improved.sh
```

This script will check for PHP + sqlite, create the database if needed, generate a config file with an `admin_api_key`, start the PHP dev server and open both the admin UI and the public frontend. (This matches the behaviour described in the repo.) 

### On Windows (PowerShell)

Open PowerShell as a normal user and run:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\mfano_setup_and_run_windows.ps1
```

> If you are using Git Bash / WSL on Windows, you can instead run the `.sh` variant designed for that environment (see the repo).

---

## 4 — Where to open the admin and public site (after the script starts)

* Admin panel:
  `http://127.0.0.1:8000/Backend+AdminPanel/admin/index.html`
* Public frontend (category grid):
  `http://127.0.0.1:8000/frontend/index.php`
  The startup script attempts to open both in your browser automatically. 

---

## 5 — Find the Admin API key (for the admin UI)

After setup, find the generated admin API key with this command in the project root:

```bash
php -r "echo (require 'Backend+AdminPanel/config/config.php')['admin_api_key'], PHP_EOL;"
```

Copy the key and paste it into the **Admin API Key** field at the top of the admin panel. The panel stores the key for that browser tab only. 

---

## 6 — Add (upload) resource documents

1. In the admin panel, click **A · Add New Resource**.
2. Choose Category and Sub-Category.
3. Select the PDF file to upload and submit. The resource is published immediately by default. 

To change publish status later:

* Go to **04 · Library / Existing Resources** and click **Publish / Unpublish** for any item.

To check the public site:

* Reload the public frontend page (`frontend/index.php`) — the new item will appear under the correct category. 

When a visitor clicks **View / Download**, the system streams the PDF and logs the download; those logs appear at **05 · Activity / Download Logs** in the admin panel. 

---

## 7 — Deploying to a live server (summary for the administrator)

**Important:** do not overwrite live data. When updating the deployed site, do **not** replace these on the live server:

* `Backend+AdminPanel/database/mfano_bora.sqlite` (this is the live database)
* `Backend+AdminPanel/config/config.php` (contains your admin key and settings)
* `Backend+AdminPanel/uploads/resources/` (the actual uploaded PDFs)

Use a safe update flow such as `rsync` and exclude the items above. Example (adapt to your host and paths):

```bash
rsync -av --delete \
  --exclude 'Backend+AdminPanel/database/mfano_bora.sqlite' \
  --exclude 'Backend+AdminPanel/database/exports/' \
  --exclude 'Backend+AdminPanel/config/config.php' \
  --exclude 'Backend+AdminPanel/uploads/resources/' \
  ./ user@yourserver:/path/to/public_html/resources/
```

This example is taken from the project README and is a safe starting point. 

### After code sync

1. On the server, apply the schema to ensure any new tables/columns exist (this leaves data intact):

```bash
sqlite3 Backend+AdminPanel/database/mfano_bora.sqlite < Backend+AdminPanel/database/schema.sql
```

2. Back up the live database and uploads **before** deploying or running schema commands:

```bash
cp Backend+AdminPanel/database/mfano_bora.sqlite \
   Backend+AdminPanel/database/backups/mfano_bora_$(date +%Y%m%d%H%M%S).sqlite
# Also copy the uploads folder to a safe backup location.
```

(Backups are simple copies because the database is a single SQLite file.) 

3. Confirm the health endpoint on the live domain:

```
https://yourdomain.example/Backend+AdminPanel/api/health.php
```

It should return JSON including `"success": true` and `"database": "sqlite"`. 

---

## 8 — Quick checklist for administrators (before updating the live site)

* Backup `mfano_bora.sqlite` and `uploads/resources/` first.
* Make sure `Backend+AdminPanel/config/config.php` is not overwritten.
* Keep file permissions so PHP can read/write `database/`, `config/` and `uploads/`.
* Run the idempotent schema command after code sync.
* Check the health endpoint and the public frontend.

---

## 9 — Troubleshooting tips (common issues)

* If the setup script says “Required file not found” — make sure you are running the **current** script from the project root and that `Backend+AdminPanel/` exists. 
* If the health check fails, check `.php-server.log` and confirm `pdo_sqlite` is enabled: `php -m | grep -i sqlite`. 
* If the public frontend shows “No published documents yet”, try publishing a resource in the admin panel and then reload the public page — the frontend now reads the database directly. 

---

