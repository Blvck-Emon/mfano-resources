#!/usr/bin/env bash
#
# Mfano Bora Resources Portal
# Development environment setup + SQLite bootstrap + PHP server launcher
#
# Usage:
#   chmod +x mfano_setup_and_run_improved.sh
#   ./mfano_setup_and_run_improved.sh
#
# What this script does:
#   1. Resolves the project root from the script location.
#   2. Checks required tools/extensions.
#   3. Creates the SQLite database when it does not exist.
#   4. Applies schema.sql safely.
#   5. Interactively prompts to load seed.sql (with INSERT OR IGNORE).
#   6. Optionally creates initial and final DB CSV snapshots.
#   7. Verifies the SQLite database and seeded records.
#   8. Creates config/config.php if missing and generates an admin API key.
#   9. Starts PHP's built-in web server.
#  10. Waits for the health endpoint to respond.
#  11. Opens the admin frontend in the default browser.
#
# Notes:
#   - This script intentionally does NOT require PostgreSQL.
#   - It assumes PHP has PDO SQLite enabled.
#   - It is designed for Linux/macOS development environments.
#

set -Eeuo pipefail

###############################################################################
# Configuration
###############################################################################

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"

PHP_HOST="${PHP_HOST:-127.0.0.1}"
PHP_PORT="${PHP_PORT:-8000}"

DB_PATH="${DB_PATH:-$PROJECT_ROOT/database/mfano_bora.sqlite}"
SCHEMA_FILE="$PROJECT_ROOT/database/schema.sql"
SEED_FILE="$PROJECT_ROOT/database/seed.sql"
CONFIG_FILE="$PROJECT_ROOT/config/config.php"
CONFIG_EXAMPLE="$PROJECT_ROOT/config/config.example.php"

ADMIN_URL="http://${PHP_HOST}:${PHP_PORT}/admin/index.html"
HEALTH_URL="http://${PHP_HOST}:${PHP_PORT}/api/health.php"

SERVER_PID=""

###############################################################################
# Output helpers
###############################################################################

RESET='\033[0m'
BOLD='\033[1m'
CYAN='\033[36m'
GREEN='\033[32m'
YELLOW='\033[33m'
RED='\033[31m'
BLUE='\033[34m'

info() {
    printf "${CYAN}[%s]${RESET} %s\n" "INFO" "$*"
}

success() {
    printf "${GREEN}[%s]${RESET} %s\n" "OK" "$*"
}

warn() {
    printf "${YELLOW}[%s]${RESET} %s\n" "WARN" "$*"
}

error() {
    printf "${RED}[%s]${RESET} %s\n" "ERROR" "$*" >&2
}

section() {
    printf "\n${BLUE}${BOLD}== %s ==${RESET}\n" "$*"
}

die() {
    error "$*"
    exit 1
}

###############################################################################
# Error handling / cleanup
###############################################################################

cleanup() {
    if [[ -n "${SERVER_PID:-}" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
        info "Stopping PHP development server (PID $SERVER_PID)..."
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
}

on_error() {
    local exit_code=$?
    error "Setup failed at line ${BASH_LINENO[0]} with exit code ${exit_code}."
    cleanup
    exit "$exit_code"
}

trap cleanup EXIT
trap on_error ERR

###############################################################################
# Basic platform checks
###############################################################################

section "Mfano Bora Resources Portal"
printf "${BOLD}Project root:${RESET} %s\n" "$PROJECT_ROOT"
printf "${BOLD}Database:${RESET}     %s\n" "$DB_PATH"
printf "${BOLD}PHP server:${RESET}   http://${PHP_HOST}:${PHP_PORT}\n"

if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
    warn "Running as root is not required. A normal user account is recommended."
fi

if [[ ! -d "$PROJECT_ROOT" ]]; then
    die "Project root does not exist: $PROJECT_ROOT"
fi

###############################################################################
# Required project files
###############################################################################

section "Checking project files"

required_files=(
    "$SCHEMA_FILE"
    "$SEED_FILE"
    "$PROJECT_ROOT/admin/index.html"
    "$PROJECT_ROOT/admin/css/admin.css"
    "$PROJECT_ROOT/admin/js/admin.js"
    "$PROJECT_ROOT/api/health.php"
    "$PROJECT_ROOT/config/db.php"
)

for file in "${required_files[@]}"; do
    if [[ ! -f "$file" ]]; then
        die "Required file not found: $file"
    fi
    success "Found $(realpath "$file" 2>/dev/null || echo "$file")"
done

###############################################################################
# Required commands
###############################################################################

section "Checking required tools"

missing_tools=()

check_command() {
    local command_name="$1"

    if command -v "$command_name" >/dev/null 2>&1; then
        success "$command_name: $(command -v "$command_name")"
        return 0
    fi

    warn "$command_name is not installed."
    missing_tools+=("$command_name")
    return 1
}

check_command bash || true
check_command php || true
check_command sqlite3 || true

# Browser opener: at least one supported mechanism must exist.
BROWSER_OPENER=""

if command -v xdg-open >/dev/null 2>&1; then
    BROWSER_OPENER="xdg-open"
    success "Browser launcher: xdg-open"
elif command -v gio >/dev/null 2>&1; then
    BROWSER_OPENER="gio"
    success "Browser launcher: gio"
elif command -v open >/dev/null 2>&1; then
    BROWSER_OPENER="open"
    success "Browser launcher: open"
else
    warn "No supported browser launcher found."
fi

# Python is optional and not required by the application.
if command -v curl >/dev/null 2>&1; then
    HAS_CURL="yes"
    success "curl available for HTTP health checks"
else
    HAS_CURL="no"
    warn "curl not found; health checks will use PHP instead."
fi

# Do not silently install packages. Give a useful command for Debian/Ubuntu.
if (( ${#missing_tools[@]} > 0 )); then
    printf "\n"
    error "Missing required command(s): ${missing_tools[*]}"

    if command -v apt-get >/dev/null 2>&1; then
        printf "${BOLD}Debian/Ubuntu:${RESET} sudo apt-get update && sudo apt-get install -y php php-sqlite3 sqlite3 curl xdg-utils\n"
    elif command -v dnf >/dev/null 2>&1; then
        printf "${BOLD}Fedora/RHEL:${RESET} sudo dnf install -y php php-pdo php-sqlite sqlite curl\n"
    elif command -v pacman >/dev/null 2>&1; then
        printf "${BOLD}Arch:${RESET} sudo pacman -S php sqlite curl xdg-utils\n"
    fi

    die "Install the missing tools and run this script again."
fi

###############################################################################
# PHP / SQLite extension checks
###############################################################################

section "Checking PHP SQLite support"

PHP_VERSION="$(php -r 'echo PHP_VERSION;' 2>/dev/null || true)"
[[ -n "$PHP_VERSION" ]] || die "Unable to determine PHP version."

success "PHP $PHP_VERSION"

if php -m | grep -Eqi '^PDO$'; then
    success "PHP PDO extension is enabled"
else
    die "PHP PDO extension is missing."
fi

if php -m | grep -Eqi '(^|-)pdo_sqlite$'; then
    success "PHP PDO SQLite extension is enabled"
else
    die "PHP PDO SQLite extension is missing. Install php-sqlite3 for your PHP version."
fi

if php -m | grep -Eqi '(^|-)sqlite3$'; then
    success "PHP SQLite3 extension is enabled"
else
    warn "PHP SQLite3 extension is not enabled. PDO SQLite is still available, so the application may work."
fi

###############################################################################
# Prepare directories
###############################################################################

section "Preparing application directories"

mkdir -p "$PROJECT_ROOT/database"
mkdir -p "$PROJECT_ROOT/uploads/resources"
mkdir -p "$PROJECT_ROOT/config"

success "Required directories are ready."

###############################################################################
# SQLite database bootstrap
###############################################################################

section "Creating / verifying SQLite database"

if [[ ! -f "$DB_PATH" ]]; then
    info "SQLite database does not exist. Creating: $DB_PATH"

    # Touch first so sqlite3 can create the file cleanly.
    touch "$DB_PATH"

    # Restrict the local database file by default.
    chmod 600 "$DB_PATH" 2>/dev/null || true

    success "SQLite database file created."
else
    success "SQLite database already exists."
fi

if [[ ! -w "$DB_PATH" ]]; then
    die "SQLite database is not writable: $DB_PATH"
fi

###############################################################################
# Apply schema
###############################################################################

section "Applying SQLite schema"

if [[ ! -s "$SCHEMA_FILE" ]]; then
    die "Schema file is empty: $SCHEMA_FILE"
fi

if ! sqlite3 "$DB_PATH" < "$SCHEMA_FILE"; then
    die "Failed to apply SQLite schema."
fi

success "Schema applied successfully."


###############################################################################
# Seed data (interactive)
###############################################################################

section "Loading seed data"
if [[ ! -s "$SEED_FILE" ]]; then
    die "Seed file is empty: $SEED_FILE"
fi

ask_yes_no() {
    # $1 = prompt, default answer (y/n) in $2 (optional)
    local prompt="${1:-Proceed?}"
    local default="${2:-y}"
    local ans

    # show prompt with default
    if [[ "$default" == "y" ]]; then
        read -r -p "$prompt [Y/n]: " ans
        ans="${ans:-Y}"
    else
        read -r -p "$prompt [y/N]: " ans
        ans="${ans:-N}"
    fi

    case "$ans" in
        [Yy]* ) return 0 ;;
        * ) return 1 ;;
    esac
}

load_seed() {
    if ! sqlite3 "$DB_PATH" < "$SEED_FILE"; then
        die "Failed to apply seed data."
    fi
    success "Seed data applied successfully."
}

# Prompt the user whether to load seed data (default: yes for backward compatibility)
if ask_yes_no "Load seed data into the database?" "y"; then
    load_seed
    SEED_LOADED="yes"
else
    warn "Skipping seed.sql as requested."
    SEED_LOADED="no"
fi

###############################################################################
# Helper: Export DB -> CSV snapshot (creates database/exports dir)
###############################################################################

export_db_to_csv() {
    local export_dir="$PROJECT_ROOT/database/exports"
    mkdir -p "$export_dir"

    # timestamp used in filenames
    local ts
    ts="$(date -u +%Y%m%dT%H%M%SZ)"

    # resources joined with category + subcategory
    local out="$export_dir/resources_export_${ts}.csv"

    # Compose SQL for joined export (includes selected fields)
    local sql="
        SELECT 
            r.id AS resource_id, 
            c.id AS category_id, 
            c.name AS category_name, 
            s.id AS subcategory_id, 
            s.name AS subcategory_name, 
            r.title, 
            r.description, 
            r.file_url, 
            r.storage_type, 
            r.stored_path, 
            r.checksum_sha256, 
            r.file_size_kb, 
            r.download_count, 
            r.is_featured, 
            r.is_published, 
            r.publish_date, 
            r.created_at, 
            r.updated_at
        FROM resources r
        LEFT JOIN sub_categories s ON r.sub_category_id = s.id
        LEFT JOIN categories c ON s.category_id = c.id
        ORDER BY r.id;
    "

    # run sqlite3 in CSV + header mode
    if sqlite3 -header -csv "$DB_PATH" "$sql" > "$out"; then
        success "Exported resources snapshot to: $out"
    else
        warn "Failed to export resources snapshot to CSV."
    fi

    # Also export categories, sub_categories, download_logs for completeness
    sqlite3 -header -csv "$DB_PATH" "SELECT * FROM categories ORDER BY id;" > "$export_dir/categories_${ts}.csv" || warn "categories CSV failed"
    sqlite3 -header -csv "$DB_PATH" "SELECT * FROM sub_categories ORDER BY id;" > "$export_dir/sub_categories_${ts}.csv" || warn "sub_categories CSV failed"
    sqlite3 -header -csv "$DB_PATH" "SELECT * FROM download_logs ORDER BY downloaded_at DESC LIMIT 10000;" > "$export_dir/download_logs_${ts}.csv" || warn "download_logs CSV failed"
}

# If the user chose to load seed, produce a CSV snapshot
if [[ "${SEED_LOADED:-no}" == "yes" ]]; then
    export_db_to_csv
fi


###############################################################################
# Database verification
###############################################################################

section "Verifying database contents"

categories_count="$(
    sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM categories;"
)"

subcategories_count="$(
    sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM sub_categories;"
)"

resources_count="$(
    sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM resources;"
)"

logs_count="$(
    sqlite3 "$DB_PATH" "SELECT COUNT(*) FROM download_logs;"
)"

printf "  Categories:     %s\n" "$categories_count"
printf "  Sub-categories: %s\n" "$subcategories_count"
printf "  Resources:      %s\n" "$resources_count"
printf "  Download logs:  %s\n" "$logs_count"

# Modified to skip hard failures if the user opted NOT to load seed data.
if [[ "${SEED_LOADED:-no}" == "yes" ]]; then
    if [[ "$categories_count" -lt 1 ]]; then
        die "Database verification failed: no categories were seeded."
    fi

    if [[ "$subcategories_count" -lt 1 ]]; then
        die "Database verification failed: no sub-categories were seeded."
    fi

    if [[ "$resources_count" -lt 1 ]]; then
        die "Database verification failed: no resources were seeded."
    fi
fi

success "SQLite database is populated and queryable."

###############################################################################
# Configuration
###############################################################################

section "Checking PHP application configuration"

if [[ ! -f "$CONFIG_FILE" ]]; then
    if [[ ! -f "$CONFIG_EXAMPLE" ]]; then
        die "Neither config/config.php nor config/config.example.php exists."
    fi

    info "Creating config/config.php from config.example.php"
    cp "$CONFIG_EXAMPLE" "$CONFIG_FILE"

    # Generate a new key without exposing it in normal output.
    NEW_KEY="$(php -r 'echo bin2hex(random_bytes(24));')"

    if grep -q "'admin_api_key' =>" "$CONFIG_FILE"; then
        # Perl handles arbitrary replacement text more safely than a simple
        # sed expression when the key contains special characters.
        NEW_KEY="$NEW_KEY" perl -0pi -e \
            "s/'admin_api_key'\s*=>\s*'[^']*'/'admin_api_key' => '\$ENV{NEW_KEY}'/" \
            "$CONFIG_FILE"
    else
        die "config.example.php does not contain the expected admin_api_key setting."
    fi

    chmod 600 "$CONFIG_FILE" 2>/dev/null || true
    success "config/config.php created with a generated admin API key."
else
    success "config/config.php already exists."

    # Avoid overwriting an existing deployment/admin credential.
    if grep -Eq "'admin_api_key'\s*=>\s*'replace-with-a-long-random-string'" "$CONFIG_FILE"; then
        warn "config/config.php still contains the example admin API key placeholder."

        NEW_KEY="$(php -r 'echo bin2hex(random_bytes(24));')"

        NEW_KEY="$NEW_KEY" perl -0pi -e \
            "s/'admin_api_key'\s*=>\s*'replace-with-a-long-random-string'/'admin_api_key' => '\$ENV{NEW_KEY}'/" \
            "$CONFIG_FILE"

        chmod 600 "$CONFIG_FILE" 2>/dev/null || true
        success "Example admin API key placeholder replaced with a generated key."
    fi
fi

###############################################################################
# Verify PHP configuration points at the intended SQLite DB
###############################################################################

section "Verifying SQLite configuration"

configured_db_path="$(
    php -r '
        $config = require $argv[1];
        echo $config["db"]["path"] ?? "";
    ' "$CONFIG_FILE"
)"

if [[ -z "$configured_db_path" ]]; then
    die "Could not read db.path from config/config.php."
fi

configured_db_real="$(
    realpath -m "$configured_db_path" 2>/dev/null || echo "$configured_db_path"
)"
expected_db_real="$(
    realpath -m "$DB_PATH" 2>/dev/null || echo "$DB_PATH"
)"

if [[ "$configured_db_real" != "$expected_db_real" ]]; then
    warn "config/config.php points to:"
    warn "  $configured_db_real"
    warn "while this script initialized:"
    warn "  $expected_db_real"
    warn "The application config should normally point at database/mfano_bora.sqlite."
else
    success "PHP configuration points to the initialized SQLite database."
fi

###############################################################################
# Stop anything already using the requested port
###############################################################################

section "Preparing PHP development server"

find_port_pid() {
    local port="$1"

    if command -v lsof >/dev/null 2>&1; then
        lsof -tiTCP:"$port" -sTCP:LISTEN 2>/dev/null | head -n 1 || true
        return
    fi

    if command -v fuser >/dev/null 2>&1; then
        fuser -n tcp "$port" 2>/dev/null | awk '{print $1}' | head -n 1 || true
        return
    fi

    printf ""
}

PORT_PID="$(find_port_pid "$PHP_PORT")"

if [[ -n "$PORT_PID" ]]; then
    warn "Port $PHP_PORT is already in use by PID $PORT_PID."

    # Only terminate a process when the process is actually a PHP server.
    if ps -p "$PORT_PID" -o comm= 2>/dev/null | grep -qi '^php'; then
        info "Stopping existing PHP process on port $PHP_PORT..."
        kill "$PORT_PID" 2>/dev/null || true
        sleep 1
    else
        die "Port $PHP_PORT is occupied by a non-PHP process. Set PHP_PORT to another port, e.g. PHP_PORT=8080 ./setup_and_run.sh"
    fi
fi

###############################################################################
# Start PHP server
###############################################################################

section "Starting PHP backend + frontend"

SERVER_LOG="$PROJECT_ROOT/.php-server.log"

info "Starting PHP built-in server..."
info "Document root: $PROJECT_ROOT"
info "Listening on:   http://${PHP_HOST}:${PHP_PORT}"

(
    cd "$PROJECT_ROOT"
    exec php -S "${PHP_HOST}:${PHP_PORT}" -t "$PROJECT_ROOT"
) >"$SERVER_LOG" 2>&1 &

SERVER_PID=$!

success "PHP server started with PID $SERVER_PID"

###############################################################################
# Wait for server
###############################################################################

section "Waiting for application"

server_ready="no"

for attempt in $(seq 1 30); do
    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
        error "PHP server exited unexpectedly."
        printf "\n${BOLD}PHP server log:${RESET}\n"
        sed -n '1,120p' "$SERVER_LOG" >&2 || true
        die "PHP server failed to start."
    fi

    if [[ "$HAS_CURL" == "yes" ]]; then
        if curl -fsS --max-time 1 "$HEALTH_URL" >/tmp/mfano_health_response.$$ 2>/dev/null; then
            server_ready="yes"
            break
        fi
    else
        if php -r '
            $url = $argv[1];
            $context = stream_context_create([
                "http" => ["timeout" => 1]
            ]);
            $response = @file_get_contents($url, false, $context);
            exit($response === false ? 1 : 0);
        ' "$HEALTH_URL"; then
            server_ready="yes"
            break
        fi
    fi

    sleep 1
done

if [[ "$server_ready" != "yes" ]]; then
    error "Application did not respond at $HEALTH_URL"
    printf "\n${BOLD}PHP server log:${RESET}\n"
    sed -n '1,160p' "$SERVER_LOG" >&2 || true
    die "Health check failed."
fi

success "PHP backend is responding."

###############################################################################
# Health endpoint verification
###############################################################################

section "Verifying application health"

if [[ "$HAS_CURL" == "yes" && -f /tmp/mfano_health_response.$$ ]]; then
    HEALTH_RESPONSE="$(cat /tmp/mfano_health_response.$$)"
    rm -f /tmp/mfano_health_response.$$
else
    HEALTH_RESPONSE="$(
        php -r '
            $url = $argv[1];
            $context = stream_context_create([
                "http" => ["timeout" => 2]
            ]);
            $response = @file_get_contents($url, false, $context);
            echo $response === false ? "" : $response;
        ' "$HEALTH_URL"
    )"
fi

printf "%s\n" "$HEALTH_RESPONSE"

if printf '%s' "$HEALTH_RESPONSE" | grep -q '"success"[[:space:]]*:[[:space:]]*true'; then
    success "Health endpoint reports success."
else
    die "Health endpoint returned an unexpected response."
fi

if printf '%s' "$HEALTH_RESPONSE" | grep -qi '"database"[[:space:]]*:[[:space:]]*"sqlite"'; then
    success "Backend confirms SQLite."
else
    warn "Health endpoint did not explicitly report database=sqlite."
fi

###############################################################################
# Open browser
###############################################################################

section "Opening admin frontend"

if [[ -n "$BROWSER_OPENER" ]]; then
    case "$BROWSER_OPENER" in
        xdg-open)
            xdg-open "$ADMIN_URL" >/dev/null 2>&1 &
            ;;
        gio)
            gio open "$ADMIN_URL" >/dev/null 2>&1 &
            ;;
        open)
            open "$ADMIN_URL" >/dev/null 2>&1 &
            ;;
    esac

    success "Admin frontend opened: $ADMIN_URL"
else
    warn "Could not automatically open a browser."
    printf "${BOLD}Open this URL manually:${RESET} %s\n" "$ADMIN_URL"
fi

###############################################################################
# Final status
###############################################################################

printf "\n${GREEN}${BOLD}============================================================${RESET}\n"
printf "${GREEN}${BOLD} Mfano Bora Resources Portal is ready                    ${RESET}\n"
printf "${GREEN}${BOLD}============================================================${RESET}\n"
printf "  Admin frontend : %s\n" "$ADMIN_URL"
printf "  Health check   : %s\n" "$HEALTH_URL"
printf "  SQLite DB      : %s\n" "$DB_PATH"
printf "  Resources      : %s\n" "$resources_count"
printf "  Categories     : %s\n" "$categories_count"
printf "  Seed status    : %s\n" "${SEED_LOADED:-no}"
printf "\n"
printf "Server log: %s\n" "$SERVER_LOG"
printf "\n"
printf "${BOLD}Press Ctrl+C to stop the development server.${RESET}\n"


# Final database snapshot for repo/dev convenience
section "Creating final DB CSV snapshot"
export_db_to_csv


###############################################################################
# Keep server attached to terminal
###############################################################################

wait "$SERVER_PID"