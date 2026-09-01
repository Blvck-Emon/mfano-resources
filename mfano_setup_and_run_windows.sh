#!/usr/bin/env bash
# mfano_setup_and_run_windows.sh
# Git Bash / WSL-compatible setup script for Windows.
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"
DB_PATH="$PROJECT_ROOT/database/mfano_bora.sqlite"

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

mkdir -p "$PROJECT_ROOT/database" "$PROJECT_ROOT/uploads/resources" "$PROJECT_ROOT/config"

# Apply schema
echo "Applying schema to $DB_PATH"
sqlite3 "$DB_PATH" < "$PROJECT_ROOT/database/schema.sql"

# If config exists already, we consider it's ok. Otherwise copy example.
if [[ ! -f "$PROJECT_ROOT/config/config.php" ]]; then
  cp "$PROJECT_ROOT/config/config.example.php" "$PROJECT_ROOT/config/config.php"
fi

# Generate key if no key in DB
has=$(sqlite3 "$DB_PATH" "SELECT value FROM settings WHERE name='admin_api_key' LIMIT 1;" 2>/dev/null || echo "")
if [[ -z "$has" ]]; then
  NEW_KEY="$(php -r 'echo bin2hex(random_bytes(24));')"
  sqlite3 "$DB_PATH" "INSERT OR REPLACE INTO settings (name,value,created_at,updated_at) VALUES ('admin_api_key','${NEW_KEY}', datetime('now'), datetime('now'));"
  echo "Stored generated admin API key in DB (settings.admin_api_key)."
fi

echo "Setup complete. You may now run the PHP dev server (php -S 127.0.0.1:8000 -t .) or use the main setup script."