#!/usr/bin/env bash
# mfano_setup_and_run_windows.sh
# Git Bash / WSL-compatible setup script for Windows.
#
# FIXED:
#   1. Paths now point at Backend+AdminPanel/ (where database/, config/,
#      etc. actually live) instead of the project root.
#   2. Removed the dead code that stored the generated admin key into a
#      SQLite `settings` table — that table doesn't exist in schema.sql
#      and includes/auth.php never reads from it. The key is now written
#      only to Backend+AdminPanel/config/config.php, which is what the
#      application actually checks.
#
# This script only bootstraps the database + config. To also start the
# server, run mfano_setup_and_run_improved.sh afterwards (or just use
# that script directly — it does everything this one does, plus starts
# PHP and opens both the admin panel and frontend).

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"
BACKEND_DIR="$PROJECT_ROOT/Backend+AdminPanel"
DB_PATH="$BACKEND_DIR/database/mfano_bora.sqlite"

check() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing: $1"; return 1; }
  return 0
}

echo "Checking required commands..."
missing=0
for cmd in bash php sqlite3 openssl; do
  if ! check "$cmd"; then missing=1; fi
done
if [[ $missing -ne 0 ]]; then
  echo "Please install missing tools (php, sqlite3, openssl) for Windows (MSYS/WSL/Git-Bash)."
  exit 1
fi

if [[ ! -d "$BACKEND_DIR" ]]; then
  echo "Backend+AdminPanel folder not found at: $BACKEND_DIR"
  exit 1
fi

mkdir -p "$BACKEND_DIR/database" "$BACKEND_DIR/uploads/resources" "$BACKEND_DIR/config"

# Apply schema (idempotent — safe to re-run on an existing DB)
echo "Applying schema to $DB_PATH"
sqlite3 "$DB_PATH" < "$BACKEND_DIR/database/schema.sql"

echo "Checking SQLite integrity..."
integrity="$(sqlite3 "$DB_PATH" "PRAGMA integrity_check;")"
if [[ "$integrity" != "ok" ]]; then
  echo "SQLite integrity_check FAILED: $integrity"
  exit 1
fi
echo "SQLite integrity_check: ok"

# If config exists already, we consider it's ok. Otherwise copy example.
if [[ ! -f "$BACKEND_DIR/config/config.php" ]]; then
  cp "$BACKEND_DIR/config/config.example.php" "$BACKEND_DIR/config/config.php"
fi

# Generate a key and write it into config.php (the ONLY place
# includes/auth.php reads admin_api_key from) if still on the placeholder.
if grep -Eq "'admin_api_key'\s*=>\s*'replace-with-a-long-random-string'" "$BACKEND_DIR/config/config.php"; then
  NEW_KEY="$(php -r 'echo bin2hex(random_bytes(24));')"
  NEW_KEY="$NEW_KEY" perl -0pi -e \
    "s/'admin_api_key'\s*=>\s*'replace-with-a-long-random-string'/'admin_api_key' => '\$ENV{NEW_KEY}'/" \
    "$BACKEND_DIR/config/config.php"
  echo "Stored generated admin API key in config/config.php."
fi

echo "Setup complete."
echo "Run ./mfano_setup_and_run_improved.sh to start the PHP server and open the admin panel + frontend."