<#
.SYNOPSIS
  Mfano Bora Resources Portal - Windows setup + run script (PowerShell)
  Enhanced to support automated "on-click" running via generated run_mfano.bat
  and an optional interactive mode.

USAGE
  Double-click run_mfano.bat (auto) OR:
    powershell -NoProfile -ExecutionPolicy Bypass -File .\mfano_setup_and_run_windows.ps1
  To run interactively (prompt for seed):
    .\mfano_setup_and_run_windows.ps1 -Interactive

NOTES
  This script requires 'php' and 'sqlite3' on PATH.
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Param(
    [switch]$Interactive  # Use -Interactive to enable prompts; default behaviour is non-interactive (auto-run)
)

# Ensure process-level bypass so a double-click via the generated .bat doesn't get blocked.
try {
    Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -ErrorAction SilentlyContinue
} catch {
    # Non-fatal; proceed anyway
}

function Info    ($m) { Write-Host "[INFO]  $m" -ForegroundColor Cyan }
function Ok      ($m) { Write-Host "[OK]    $m" -ForegroundColor Green }
function Warn    ($m) { Write-Host "[WARN]  $m" -ForegroundColor Yellow }
function Fail    ($m) { Write-Host "[ERROR] $m" -ForegroundColor Red; Pop-Location; exit 1 }
function Section ($m) { Write-Host ""; Write-Host "== $m ==" -ForegroundColor Blue }

function Check-Command($n) {
    $c = Get-Command $n -ErrorAction SilentlyContinue
    if (-not $c) { Warn "Missing: $n"; return $false } else { Ok "$n found: $($c.Source)"; return $true }
}

# Resolve script directory
$here = Split-Path -Parent $MyInvocation.MyCommand.Definition
Push-Location $here

Section "Mfano Bora Resources Portal (Windows)"

$backendDir  = Join-Path $here 'Backend+AdminPanel'
$frontendDir = Join-Path $here 'frontend'

Write-Host "Project root:  $here"
Write-Host "Backend dir:   $backendDir"
Write-Host "Frontend dir:  $frontendDir"

if (-not (Test-Path $backendDir))  { Fail "Backend+AdminPanel folder not found at: $backendDir" }
if (-not (Test-Path $frontendDir)) { Fail "frontend folder not found at: $frontendDir" }

Section "Checking required tools"

$ok = $true
foreach ($cmd in @('php','sqlite3')) {
    if (-not (Check-Command $cmd)) { $ok = $false }
}
if (-not $ok) {
    Fail "Install the missing tools (PHP with pdo_sqlite enabled, sqlite3 CLI) and re-run this script."
}

Section "Preparing application directories"

$dbDir      = Join-Path $backendDir 'database'
$uploadsDir = Join-Path $backendDir 'uploads\resources'
$configDir  = Join-Path $backendDir 'config'

New-Item -ItemType Directory -Force -Path $dbDir, $uploadsDir, $configDir | Out-Null
Ok "Required directories are ready."

$schema = Join-Path $dbDir 'schema.sql'
$seed   = Join-Path $dbDir 'seed.sql'
$db     = Join-Path $dbDir 'mfano_bora.sqlite'

if (-not (Test-Path $schema)) { Fail "Schema file not found: $schema" }
if (-not (Test-Path $seed))   { Fail "Seed file not found: $seed" }

Section "Creating / verifying SQLite database"

# Detect if DB exists already (so we can auto-load seed on first run when non-interactive)
$dbNew = -not (Test-Path $db)

if ($dbNew) {
    New-Item -ItemType File -Path $db | Out-Null
    Ok "SQLite database file created: $db"
} else {
    Ok "SQLite database already exists: $db"
}

Section "Applying SQLite schema (idempotent, safe to re-run)"

# Use sqlite3 to apply schema; use -Raw to avoid line-break issues
Get-Content $schema -Raw | & sqlite3 $db
if ($LASTEXITCODE -ne 0) { Fail "Failed to apply SQLite schema." }
Ok "Schema applied successfully (existing data preserved)."

Section "Checking SQLite database health"

$integrity = (& sqlite3 $db "PRAGMA integrity_check;") -join "`n"
if ($integrity.Trim() -eq 'ok') {
    Ok "SQLite integrity_check: ok"
} else {
    Write-Host $integrity -ForegroundColor Red
    Fail "SQLite integrity_check reported problems. Restore from a backup before continuing."
}

Section "Loading seed data"

# Decide whether to auto-load seed:
# - If running non-interactively and the DB was newly created, load seed automatically.
# - If interactive, ask the user (default: yes).
$seedLoaded = $false
if (-not $Interactive) {
    if ($dbNew) {
        Info "Non-interactive mode and a new DB was created — loading seed.sql automatically."
        Get-Content $seed -Raw | & sqlite3 $db
        if ($LASTEXITCODE -ne 0) { Fail "Failed to apply seed data." }
        Ok "Seed data applied successfully."
        $seedLoaded = $true
    } else {
        Warn "Non-interactive mode and existing DB - skipping seed.sql (to avoid overwriting data)."
    }
} else {
    $loadSeed = Read-Host "Load seed data into the database? [Y/n]"
    if ([string]::IsNullOrWhiteSpace($loadSeed) -or $loadSeed -match '^[Yy]') {
        Get-Content $seed -Raw | & sqlite3 $db
        if ($LASTEXITCODE -ne 0) { Fail "Failed to apply seed data." }
        Ok "Seed data applied successfully."
        $seedLoaded = $true
    } else {
        Warn "Skipping seed.sql as requested."
    }
}

Section "Verifying database contents"

$categoriesCount    = (& sqlite3 $db "SELECT COUNT(*) FROM categories;").Trim()
$subcategoriesCount = (& sqlite3 $db "SELECT COUNT(*) FROM sub_categories;").Trim()
$resourcesCount     = (& sqlite3 $db "SELECT COUNT(*) FROM resources;").Trim()
$logsCount          = (& sqlite3 $db "SELECT COUNT(*) FROM download_logs;").Trim()

Write-Host "  Categories:     $categoriesCount"
Write-Host "  Sub-categories: $subcategoriesCount"
Write-Host "  Resources:      $resourcesCount"
Write-Host "  Download logs:  $logsCount"

Section "Checking PHP application configuration"

$configExample = Join-Path $configDir 'config.example.php'
$config        = Join-Path $configDir 'config.php'

if (-not (Test-Path $configExample)) { Fail "config.example.php not found: $configExample" }

function New-AdminKey {
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $bytes = New-Object byte[] 24
    $rng.GetBytes($bytes)
    -join ($bytes | ForEach-Object { $_.ToString('x2') })
}

if (-not (Test-Path $config)) {
    Copy-Item -Path $configExample -Destination $config
    $newKey = New-AdminKey
    (Get-Content $config -Raw) -replace "'admin_api_key'\s*=>\s*'[^']*'", "'admin_api_key' => '$newKey'" |
        Set-Content -Path $config -NoNewline
    Ok "config/config.php created with a generated admin API key."
    Warn "Admin API key generated. To view it later, run:"
    Warn "  php -r `"echo (require '$config')['admin_api_key'], PHP_EOL;`""
} else {
    Ok "config/config.php already exists."
    $existing = Get-Content $config -Raw
    if ($existing -match "'admin_api_key'\s*=>\s*'replace-with-a-long-random-string'") {
        Warn "config/config.php still contains the placeholder admin API key."
        $newKey = New-AdminKey
        ($existing -replace "'admin_api_key'\s*=>\s*'replace-with-a-long-random-string'", "'admin_api_key' => '$newKey'") |
            Set-Content -Path $config -NoNewline
        Ok "Placeholder admin API key replaced with a generated key."
    }
}

# Create a helper .bat file for easy "double-click to run" behaviour
try {
    $batPath = Join-Path $here 'run_mfano.bat'
    $psScriptName = Split-Path -Leaf $MyInvocation.MyCommand.Definition
    $batContent = "@echo off
REM Helper created by mfano_setup_and_run_windows.ps1
REM Double-click this file to launch the Mfano dev server.
powershell -NoProfile -ExecutionPolicy Bypass -File `"%~dp0$psScriptName`""
    Set-Content -Path $batPath -Value $batContent -Force -Encoding ASCII
    Ok "Created helper launcher: $batPath (double-click to run the system)"
} catch {
    Warn "Failed to create helper run_mfano.bat: $($_.Exception.Message)"
}

Section "Starting PHP backend + admin panel + frontend"

$phpHost = if ($env:PHP_HOST) { $env:PHP_HOST } else { '127.0.0.1' }
$phpPort = if ($env:PHP_PORT) { $env:PHP_PORT } else { '8000' }

$adminUrl    = "http://${phpHost}:${phpPort}/Backend+AdminPanel/admin/index.html"
$healthUrl   = "http://${phpHost}:${phpPort}/Backend+AdminPanel/api/health.php"
$frontendUrl = "http://${phpHost}:${phpPort}/frontend/index.php"

Write-Host "Document root: $here"
Write-Host "Listening on:  http://${phpHost}:${phpPort}"

$serverLog = Join-Path $here '.php-server.log'
$serverErr = Join-Path $here '.php-server.log.err'

# Start PHP built-in server (hidden window). Use -PassThru so we can monitor process.
$phpProcess = Start-Process -FilePath "php" `
    -ArgumentList @("-S", "${phpHost}:${phpPort}", "-t", $here) `
    -WorkingDirectory $here `
    -RedirectStandardOutput $serverLog `
    -RedirectStandardError $serverErr `
    -PassThru -WindowStyle Hidden

Ok "PHP server started (PID $($phpProcess.Id))"

Section "Waiting for application health endpoint"

$ready = $false
for ($i = 0; $i -lt 30; $i++) {
    Start-Sleep -Seconds 1
    if ($phpProcess.HasExited) {
        Write-Host (Get-Content $serverLog -Raw -ErrorAction SilentlyContinue) -ForegroundColor Red
        Fail "PHP server exited unexpectedly."
    }
    try {
        $resp = Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 2
        if ($resp.StatusCode -eq 200) { $ready = $true; break }
    } catch {
        # not ready yet
    }
}

if (-not $ready) {
    Write-Host (Get-Content $serverLog -Raw -ErrorAction SilentlyContinue) -ForegroundColor Red
    Fail "Application did not respond at $healthUrl"
}

Ok "PHP backend is responding."

$healthBody = (Invoke-WebRequest -Uri $healthUrl -UseBasicParsing -TimeoutSec 5).Content
Write-Host $healthBody

if ($healthBody -match '"success"\s*:\s*true') {
    Ok "Health endpoint reports success."
} else {
    Fail "Health endpoint returned an unexpected response."
}

if ($healthBody -match '"database"\s*:\s*"sqlite"') {
    Ok "Backend confirms SQLite."
} else {
    Warn "Health endpoint did not explicitly report database=sqlite."
}

Section "Verifying public frontend"
try {
    $feResp = Invoke-WebRequest -Uri $frontendUrl -UseBasicParsing -TimeoutSec 5
    if ($feResp.StatusCode -eq 200) {
        Ok "Frontend responded 200 OK at $frontendUrl"
    } else {
        Warn "Frontend responded with status $($feResp.StatusCode)"
    }
} catch {
    Warn "Could not confirm the frontend responded: $($_.Exception.Message)"
}

Section "Opening admin panel and public frontend"

# Use Start-Process which opens the default browser for these URLs
try {
    Start-Process $adminUrl
    Start-Sleep -Seconds 1
    Start-Process $frontendUrl
    Ok "Opened admin panel ($adminUrl) and frontend ($frontendUrl) in default browser."
} catch {
    Warn "Could not automatically open the browser: $($_.Exception.Message)"
    Write-Host "Open these manually:"
    Write-Host "  Admin : $adminUrl"
    Write-Host "  Front : $frontendUrl"
}

Write-Host ""
Write-Host "============================================================" -ForegroundColor Green
Write-Host " Mfano Bora Resources Portal is ready" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host "  Admin panel    : $adminUrl"
Write-Host "  Public frontend: $frontendUrl"
Write-Host "  Health check   : $healthUrl"
Write-Host "  SQLite DB      : $db"
Write-Host "  Resources      : $resourcesCount"
Write-Host "  Categories     : $categoriesCount"
Write-Host "  Seed loaded    : $seedLoaded"
Write-Host ""
Write-Host "Server log: $serverLog"
Write-Host "PHP server PID: $($phpProcess.Id)  (Stop-Process -Id $($phpProcess.Id) to stop it)"

Pop-Location