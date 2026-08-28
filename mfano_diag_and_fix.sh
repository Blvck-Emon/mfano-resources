#!/usr/bin/env bash
# mfano_diag_and_fix.sh
# Diagnostic helper: verifies DB, config, API connectivity and shows categories/sub-categories.

set -euo pipefail

ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
BACKEND="$ROOT/Backend+AdminPanel"
CONFIG="$BACKEND/config/config.php"
DB_EXPECT="$BACKEND/database/mfano_bora.sqlite"
HEALTH_URL="${PHP_HOST:-127.0.0.1}:${PHP_PORT:-8000}"
HEALTH_URL="http://$HEALTH_URL/Backend+AdminPanel/api/health.php"
ADMIN_CATS_URL="http://$HEALTH_URL/Backend+AdminPanel/api/admin/categories.php"
ADMIN_SUBS_URL="http://$HEALTH_URL/Backend+AdminPanel/api/admin/subcategories.php"

echo "Repo root: $ROOT"
echo "Backend dir: $BACKEND"
echo ""

# 1) Does config.php exist and what db path does it point to?
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

# Resolve to absolute for direct compare:
real_conf="$(realpath -m "$PHP_READ_DB_PATH" 2>/dev/null || echo "$PHP_READ_DB_PATH")"
real_expected="$(realpath -m "$DB_EXPECT" 2>/dev/null || echo "$DB_EXPECT")"
echo "Resolved config db: $real_conf"
echo "Expected db file:   $real_expected"

if [[ "$real_conf" != "$real_expected" ]]; then
  echo "WARNING: config points to a different sqlite file than expected."
  echo "This often explains why seed data doesn't appear in the UI."
fi
echo ""

# 2) Check raw SQLite contents (counts + sample rows)
if [[ ! -f "$real_expected" ]]; then
  echo "ERROR: expected sqlite DB missing: $real_expected"
  exit 3
fi

echo "== SQLite content quick-check =="
echo "Total rows (categories / sub_categories) :"
sqlite3 "$real_expected" "SELECT 'categories:' || COUNT(*) FROM categories;" || true
sqlite3 "$real_expected" "SELECT 'sub_categories:' || COUNT(*) FROM sub_categories;" || true

echo ""
echo "Top 10 categories:"
sqlite3 -header -column "$real_expected" "SELECT id,name,slug FROM categories ORDER BY id LIMIT 10;" || true

echo ""
echo "Top 10 sub_categories:"
sqlite3 -header -column "$real_expected" "SELECT sc.id, sc.name, sc.slug, c.name AS parent_category FROM sub_categories sc LEFT JOIN categories c ON sc.category_id = c.id ORDER BY sc.id LIMIT 20;" || true

echo ""

# 3) Check PHP built-in server availability + health endpoint
echo "== API health check =="
if command -v curl >/dev/null 2>&1; then
  echo "curl -> GET $HEALTH_URL"
  set +e
  curl -fsS --max-time 3 "$HEALTH_URL" -i || echo "curl failed or returned non-2xx"
  set -e
else
  echo "curl not available; please install curl for full diagnostics."
fi

echo ""
echo "== Admin endpoints test (no API key) =="
if command -v curl >/dev/null 2>&1; then
  echo "GET $ADMIN_CATS_URL (no key)"
  curl -fsS --max-time 3 "$ADMIN_CATS_URL" -i || echo "non-2xx or failed"
  echo ""
  echo "GET $ADMIN_SUBS_URL (no key)"
  curl -fsS --max-time 3 "$ADMIN_SUBS_URL" -i || echo "non-2xx or failed"
else
  echo "curl missing - skipping API endpoint checks"
fi

# 4) If admin key present in config, try same requests with the key
echo ""
echo "== Admin endpoints (with X-Admin-Api-Key from config) =="
ADMIN_KEY="$(php -r '$c = require $argv[1]; echo $c[\"admin_api_key\"] ?? \"\";' "$CONFIG" 2>/dev/null || echo "")"
if [[ -z "$ADMIN_KEY" ]]; then
  echo "No admin_api_key found in config or unable to read it. Admin endpoints may require a key."
else
  if command -v curl >/dev/null 2>&1; then
    echo "Using admin key from config (length ${#ADMIN_KEY}) to call endpoints..."
    echo "GET $ADMIN_CATS_URL (with header)"
    curl -fsS --max-time 4 -H "X-Admin-Api-Key: $ADMIN_KEY" -i "$ADMIN_CATS_URL" || echo "non-2xx or failed"
    echo ""
    echo "GET $ADMIN_SUBS_URL (with header)"
    curl -fsS --max-time 4 -H "X-Admin-Api-Key: $ADMIN_KEY" -i "$ADMIN_SUBS_URL" || echo "non-2xx or failed"
  else
    echo "curl missing - cannot test endpoints with admin key."
  fi
fi

echo ""
echo "== Tail PHP server log (last 200 lines) =="
if [[ -f "$ROOT/.php-server.log" ]]; then
  tail -n 200 "$ROOT/.php-server.log" || true
else
  echo "No .php-server.log present at repo root."
fi

echo ""
echo "DIAGNOSTIC COMPLETE."

# Exit normally (the script prints warnings but you can examine the output)
exit 0
