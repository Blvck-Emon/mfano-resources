#!/usr/bin/env bash
# scripts/fix_resources_paths.sh
# Make resource rows point to actual files under Backend+AdminPanel/uploads/resources
set -euo pipefail
ROOT="$(cd "$(dirname "$0")"/.. && pwd)"
BACKEND="$ROOT/Backend+AdminPanel"
DB="$BACKEND/database/mfano_bora.sqlite"
UPLOADS_DIR="$BACKEND/uploads/resources"

if [[ ! -f "$DB" ]]; then
  echo "DB not found: $DB"
  exit 1
fi
if [[ ! -d "$UPLOADS_DIR" ]]; then
  echo "No uploads/resources directory: $UPLOADS_DIR"
  exit 0
fi

echo "Scanning uploads folder and updating resources table where filename matches file_url..."
for f in "$UPLOADS_DIR"/*; do
  [[ -f "$f" ]] || continue
  name=$(basename "$f")
  rel="uploads/resources/$name"
  # Update rows where file_url contains basename and where stored_path is empty or differs
  echo "Processing $name ..."
  sqlite3 "$DB" <<SQL
BEGIN;
UPDATE resources
  SET storage_type = 'local',
      stored_path = '$rel'
  WHERE (file_url LIKE '%$name%' OR stored_path LIKE '%$name%')
    AND NOT (stored_path = '$rel' AND storage_type = 'local');
COMMIT;
SQL
done

echo "Done. You may inspect DB with:"
echo "sqlite3 $DB \"SELECT id, title, file_url, storage_type, stored_path FROM resources WHERE stored_path IS NOT NULL ORDER BY id LIMIT 20;\""
