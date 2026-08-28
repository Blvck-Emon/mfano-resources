#!/usr/bin/env bash
# mfano_diag_and_fix.sh
# Diagnostic helper: verifies DB, config, API connectivity and shows categories/sub-categories.

set -euo pipefail

ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
BACKEND="$ROOT/Backend+AdminPanel"
CONFIG="$BACKEND/config/config.php"
DB_EXPECT="$BACKEND/database/mfano_bora.sqlite"

# ------------------------------------------------------------------------------
# Correct Base URL Construction
# ------------------------------------------------------------------------------
HOST="${PHP_HOST:-127.0.0.1}"
PORT="${PHP_PORT:-8000}"
API_BASE="http://${HOST}:${PORT}/Backend+AdminPanel/api"

HEALTH_URL="${API_BASE}/health.php"
ADMIN_CATS_URL="${API_BASE}/admin/categories.php"
ADMIN_SUBS_URL="${API_BASE}/admin/subcategories.php"

echo "Repo root:   $ROOT"
echo "Backend dir: $BACKEND"
echo "API Base:    $API_BASE"
echo ""

# ------------------------------------------------------------------------------
# 1) Config File & Database Path Verification
# ------------------------------------------------------------------------------
echo "== config.php check =="
if [[ ! -f "$CONFIG" ]]; then
  echo "ERROR: config/config.php not found at: $CONFIG"
  exit 2
fi

echo "config: $CONFIG"
PHP_READ_DB_PATH="$(php -r '
    $c = require $argv[1];
    if (!isset($c["db"]["path"])) { echo "__NOT_SET__"; exit; }
    echo $c["db"]["path"];
' "$CONFIG" 2>/dev/null || echo "__PHP_FAIL__")"

echo "db.path from config: $PHP_READ_DB_PATH"
if [[ "$PHP_READ_DB_PATH" == "__NOT_SET__" ]]; then
  echo "ERROR: config.php does not define db.path"
  exit 2
fi

# Resolve to absolute paths for direct comparison
real_conf="$(realpath -m "$PHP_READ_DB_PATH" 2>/dev/null || echo "$PHP_READ_DB_PATH")"
real_expected="$(realpath -m "$DB_EXPECT" 2>/dev/null || echo "$DB_EXPECT")"
echo "Resolved config db: $real_conf"
echo "Expected db file:   $real_expected"

if [[ "$real_conf" != "$real_expected" ]]; then
  echo "WARNING: config points to a different SQLite file than expected."
  echo "This often explains why seed data doesn't appear in the UI."
fi
echo ""

# ------------------------------------------------------------------------------
# 2) SQLite Contents Check
# ------------------------------------------------------------------------------
if [[ ! -f "$real_expected" ]]; then
  echo "ERROR: expected sqlite DB missing: $real_expected"
  exit 3
fi

echo "== SQLite content quick-check =="
echo "Total rows (categories / sub_categories) :"
sqlite3 "$real_expected" "SELECT 'categories: ' || COUNT(*) FROM categories;" || true
sqlite3 "$real_expected" "SELECT 'sub_categories: ' || COUNT(*) FROM sub_categories;" || true

echo ""
echo "Top 10 categories:"
sqlite3 -header -column "$real_expected" "SELECT id, name, slug FROM categories ORDER BY id LIMIT 10;" || true

echo ""
echo "Top 10 sub_categories:"
sqlite3 -header -column "$real_expected" "SELECT sc.id, sc.name, sc.slug, c.name AS parent_category FROM sub_categories sc LEFT JOIN categories c ON sc.category_id = c.id ORDER BY sc.id LIMIT 20;" || true

echo ""

# ------------------------------------------------------------------------------
# 3) Health Endpoint Test
# ------------------------------------------------------------------------------
echo "== API health check =="
if command -v curl >/dev/null 2>&1; then
  echo "GET $HEALTH_URL"
  set +e
  curl -i -sS --max-time 3 "$HEALTH_URL" || echo "curl failed or server unreachable"
  set -e
else
  echo "curl not available; skipping network checks."
fi

echo ""

# ------------------------------------------------------------------------------
# 4) Admin Endpoints Test (Unauthenticated)
# ------------------------------------------------------------------------------
echo "== Admin endpoints test (no API key) =="
if command -v curl >/dev/null 2>&1; then
  echo "GET $ADMIN_CATS_URL (no key)"
  curl -i -sS --max-time 3 "$ADMIN_CATS_URL" || echo "failed"
  echo ""
  echo "GET $ADMIN_SUBS_URL (no key)"
  curl -i -sS --max-time 3 "$ADMIN_SUBS_URL" || echo "failed"
fi

echo ""

# ------------------------------------------------------------------------------
# 5) Admin Endpoints Test (Authenticated with X-Api-Key & X-Admin-Api-Key)
# ------------------------------------------------------------------------------
echo "== Admin endpoints (with API Key from config) =="
ADMIN_KEY="$(php -r '$c = require $argv[1]; echo $c["admin_api_key"] ?? $c["api_key"] ?? "";' "$CONFIG" 2>/dev/null || echo "")"

if [[ -z "$ADMIN_KEY" ]]; then
  echo "No admin_api_key found in config or unable to read it."
else
  if command -v curl >/dev/null 2>&1; then
    echo "Using key from config (length ${#ADMIN_KEY}) to call endpoints..."
    echo "GET $ADMIN_CATS_URL"
    curl -i -sS --max-time 4 \
      -H "X-Api-Key: $ADMIN_KEY" \
      -H "X-Admin-Api-Key: $ADMIN_KEY" \
      "$ADMIN_CATS_URL" || echo "failed"
    
    echo ""
    echo "GET $ADMIN_SUBS_URL"
    curl -i -sS --max-time 4 \
      -H "X-Api-Key: $ADMIN_KEY" \
      -H "X-Admin-Api-Key: $ADMIN_KEY" \
      "$ADMIN_SUBS_URL" || echo "failed"
  fi
fi

echo ""

# ------------------------------------------------------------------------------
# 6) PHP Server Logs
# ------------------------------------------------------------------------------
echo "== Tail PHP server log (last 200 lines) =="
if [[ -f "$ROOT/.php-server.log" ]]; then
  tail -n 200 "$ROOT/.php-server.log" || true
else
  echo "No .php-server.log present at repo root."
fi

echo ""
echo "DIAGNOSTIC COMPLETE."
exit 0