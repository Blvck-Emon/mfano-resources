# mfano_setup_and_run_windows.ps1
# PowerShell setup script for Windows (works with native sqlite3 and php on PATH)
Set-StrictMode -Version Latest

$here = Split-Path -Parent $MyInvocation.MyCommand.Definition
Push-Location $here

function Check-Command($n) {
  $c = Get-Command $n -ErrorAction SilentlyContinue
  if (-not $c) { Write-Host "Missing: $n"; return $false } else { Write-Host "$n: OK"; return $true }
}

$ok = $true
foreach ($cmd in @('php','sqlite3','openssl')) {
  if (-not (Check-Command $cmd)) { $ok = $false }
}
if (-not $ok) { throw 'Install missing commands and re-run script.' }

# Ensure folders
New-Item -ItemType Directory -Force -Path "$here\database","$here\uploads\resources","$here\config" | Out-Null

$schema = Join-Path $here 'database\schema.sql'
$db = Join-Path $here 'database\mfano_bora.sqlite'

if (-not (Test-Path $db)) { New-Item -ItemType File -Path $db | Out-Null }

# Apply schema
& sqlite3 $db < $schema

# Ensure config
$configExample = Join-Path $here 'config\config.example.php'
$config = Join-Path $here 'config\config.php'
if (-not (Test-Path $config)) { Copy-Item -Path $configExample -Destination $config }

# Insert admin key if missing
$has = & sqlite3 $db "SELECT value FROM settings WHERE name='admin_api_key' LIMIT 1;" 2>$null
if (-not $has) {
    # generate 24 random bytes and hex them (PowerShell)
    $rng = New-Object System.Security.Cryptography.RNGCryptoServiceProvider
    $bytes = New-Object byte[] 24
    $rng.GetBytes($bytes)
    $hex = -join ($bytes | ForEach-Object { $_.ToString("x2") })
    & sqlite3 $db "INSERT OR REPLACE INTO settings (name,value,created_at,updated_at) VALUES ('admin_api_key','$hex', datetime('now'), datetime('now'));"
    Write-Host "Stored generated admin API key in DB (settings.admin_api_key)."
}

Write-Host "Windows setup complete. Run: php -S 127.0.0.1:8000 -t . to start the dev server."

Pop-Location