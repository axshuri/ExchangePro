# setup-runtime.ps1
# -----------------------------------------------------------------------------
# ExchangePro portable runtime installer.
#
# Downloads and configures everything the app needs INSIDE .\runtime so the
# project runs on any Windows machine with no PHP/MySQL/Laragon pre-installed:
#
#   runtime\php\      portable PHP 8.3 (NTS x64) with the required extensions
#   runtime\mysql\    MySQL 8.4 (official CDN) or MariaDB 11.4 fallback
#                     (MariaDB is MySQL-compatible and used automatically when
#                      Oracle's CDN is unreachable / geo-blocked)
#
# Nothing outside the project folder is modified. Delete .\runtime at any time
# to uninstall, or run this script again to repair.
#
# Usage:
#   powershell -NoProfile -ExecutionPolicy Bypass -File setup-runtime.ps1
#   powershell -NoProfile -ExecutionPolicy Bypass -File setup-runtime.ps1 -PhpOnly
#   powershell -NoProfile -ExecutionPolicy Bypass -File setup-runtime.ps1 -Force
# -----------------------------------------------------------------------------
param(
    [switch]$PhpOnly,   # install PHP only (skip the database server)
    [switch]$Force      # reinstall from scratch even if already installed
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$Runtime = Join-Path $Root 'runtime'
$PhpDir = Join-Path $Runtime 'php'
$DbDir  = Join-Path $Runtime 'mysql'
$Downloads = Join-Path $Runtime '.downloads'

# --- pinned, known-good versions -------------------------------------------
$PhpUrl = 'https://windows.php.net/downloads/releases/php-8.3.33-nts-Win32-vs16-x64.zip'

# Tried in order. The official MySQL CDN works in most regions; MariaDB is the
# automatic fallback (MySQL drop-in, hosted by the MariaDB Foundation, reachable
# even where Oracle geo-blocks downloads).
$DbUrls = @(
    'https://cdn.mysql.com/archives/mysql-8.4/mysql-8.4.3-winx64.zip',
    'https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-8.4.3-winx64.zip',
    'https://archive.mariadb.org/mariadb-11.4.10/winx64-packages/mariadb-11.4.10-winx64.zip'
)

# PHP extensions the app needs (see README -> Requirements).
$PhpExtensions = @('bcmath','curl','fileinfo','gd','intl','mbstring','mysqli',
                  'openssl','pdo_mysql','sodium','zip')

function Write-Step($m) { Write-Host "`n==> $m" -ForegroundColor Cyan }
function Write-Ok($m)   { Write-Host "    OK: $m" -ForegroundColor Green }
function Write-Warn($m) { Write-Host "    WARN: $m" -ForegroundColor Yellow }

# ---------------------------------------------------------------------------
function Invoke-Download {
    param([string]$Url, [string]$Out, [int]$MaxSec = 1200)

    if (Test-Path $Out) {
        Write-Ok "already downloaded: $([IO.Path]::GetFileName($Out))"
        return $true
    }

    Write-Host "    downloading $Url ..."
    $curl = Get-Command curl.exe -ErrorAction SilentlyContinue
    if ($curl) {
        & curl.exe -L --fail --retry 3 --connect-timeout 20 --max-time $MaxSec -o $Out $Url
        if ($LASTEXITCODE -ne 0) { Remove-Item $Out -Force -ErrorAction SilentlyContinue; return $false }
    } else {
        try {
            Invoke-WebRequest -Uri $Url -OutFile $Out -UseBasicParsing -TimeoutSec $MaxSec
        } catch {
            Remove-Item $Out -Force -ErrorAction SilentlyContinue
            return $false
        }
    }

    if (-not (Test-Path $Out)) { return $false }

    # Sanity check: a real zip starts with "PK" (error pages do not).
    $fs = [IO.File]::OpenRead($Out)
    try {
        $buf = New-Object byte[] 4
        [void]$fs.Read($buf, 0, 4)
        if (-not ($buf[0] -eq 0x50 -and $buf[1] -eq 0x4B)) {
            Remove-Item $Out -Force -ErrorAction SilentlyContinue
            Write-Warn "$([IO.Path]::GetFileName($Out)) is not a valid zip (server error page?)"
            return $false
        }
    } finally { $fs.Close() }

    Write-Ok "downloaded $([math]::Round((Get-Item $Out).Length / 1MB, 1)) MB"
    return $true
}

# ---------------------------------------------------------------------------
function Expand-Zip {
    param([string]$Zip, [string]$Dest)

    New-Item -ItemType Directory -Force -Path $Dest | Out-Null

    # Prefer Windows' built-in bsdtar (System32) - it reads zips natively.
    # NEVER fall back to a GNU tar from Git/MSYS in PATH: it cannot read zip
    # archives ("Cannot connect to C: resolve failed").
    $bsdtar = Join-Path $env:WINDIR 'System32\tar.exe'
    if (Test-Path $bsdtar) {
        & $bsdtar -xf $Zip -C $Dest
        if ($LASTEXITCODE -eq 0) { return }
    }
    Expand-Archive -Path $Zip -DestinationPath $Dest -Force
}

# ---------------------------------------------------------------------------
# The PHP zip ships with a single top-level folder (php-8.3.33-nts-...).
# Move that folder's contents up into $Dest so php.exe lives directly there
# (matches the Laragon layout start.bat expects: runtime\php\php.exe).
function Flatten-Install {
    param([string]$Dest, [string]$Marker)   # Marker = exe name, e.g. php.exe

    $found = Get-ChildItem -Path $Dest -Recurse -Filter $Marker -File | Select-Object -First 1
    if ($found) {
        $src = $found.DirectoryName
        if ($src -ne $Dest) {
            Get-ChildItem -Path $src | Move-Item -Destination $Dest -Force
            Remove-Item $src -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
    return (Test-Path (Join-Path $Dest $Marker))
}

# ---------------------------------------------------------------------------
# The database zip ships with a single top-level folder too, but we KEEP the
# inner bin\ layout (Laragon convention: <dir>\bin\mysqld.exe) - so we only
# move the wrapper folder's contents one level up.
function Move-Up-OneLevel {
    param([string]$Dest)

    $dirs = Get-ChildItem -Path $Dest -Directory
    if ($dirs.Count -eq 1 -and -not (Test-Path (Join-Path $Dest 'bin'))) {
        Get-ChildItem -Path $dirs[0].FullName | Move-Item -Destination $Dest -Force
        Remove-Item $dirs[0].FullName -Recurse -Force -ErrorAction SilentlyContinue
    }
    return (Test-Path (Join-Path $Dest 'bin\mysqld.exe'))
}

# ---------------------------------------------------------------------------
function Install-Php {
    Write-Step "Installing PHP 8.3.33 into $PhpDir"
    if (Test-Path (Join-Path $PhpDir 'php.exe')) { Write-Ok 'PHP already installed'; return }

    $zip = Join-Path $Downloads 'php-8.3.33-nts-Win32-vs16-x64.zip'
    if (-not (Invoke-Download $PhpUrl $zip 600)) {
        throw 'PHP download failed - check your internet connection.'
    }
    Expand-Zip $zip $PhpDir
    if (-not (Flatten-Install $PhpDir 'php.exe')) {
        throw 'PHP archive does not contain php.exe'
    }

    # Build a working php.ini from the shipped development template.
    $ini = Join-Path $PhpDir 'php.ini'
    $iniDev = Join-Path $PhpDir 'php.ini-development'
    if (Test-Path $iniDev) { Copy-Item $iniDev $ini -Force } else { Set-Content -Path $ini -Value '[PHP]' }

    $extDir = (Join-Path $PhpDir 'ext').Replace('\','/')
    $text = Get-Content $ini -Raw
    $text = [regex]::Replace($text, '(?m)^;?extension_dir\s*=.*$', "extension_dir = `"$extDir`"")
    foreach ($e in $PhpExtensions) {
        $text = [regex]::Replace($text, "(?m)^;extension=$e\s*$", "extension=$e")
    }
    $text = [regex]::Replace($text, '(?m)^;?date\.timezone\s*=.*$', 'date.timezone = UTC')
    $text = [regex]::Replace($text, '(?m)^;?memory_limit\s*=.*$', 'memory_limit = 256M')
    Set-Content -Path $ini -Value $text -Encoding ASCII
    Write-Ok 'php.ini configured (pdo_mysql, bcmath, mbstring, openssl, intl, gd, zip, curl, ...)'

    Remove-Item $zip -Force -ErrorAction SilentlyContinue

    # Quick self-test (fails loudly if the VC++ runtime is missing).
    Write-Host "    verifying: & $PhpDir\php.exe -v"
    try {
        $ver = & (Join-Path $PhpDir 'php.exe') -v 2>&1 | Select-Object -First 1
        Write-Ok "$ver"
    } catch {
        Write-Warn 'php.exe did not run. Install the "Microsoft Visual C++ 2015-2022 Redistributable (x64)" and run this script again.'
    }
    Write-Ok 'PHP installed'
}

# ---------------------------------------------------------------------------
# Initialize an empty data directory. MySQL 8 uses "mysqld --initialize";
# MariaDB has no --initialize and instead ships mysql_install_db.exe.
# Both create root@localhost with an EMPTY password (app default in
# config/config.php). Runs with $ErrorActionPreference='Continue' because both
# tools write noisy warnings to stderr that PowerShell would otherwise treat as
# terminating errors.
function Initialize-Db {
    param([string]$DbDir, [string]$DataDir, [string]$MyIni)

    New-Item -ItemType Directory -Force -Path $DataDir | Out-Null
    $prevEAP = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    $mysqlInstallDb = Join-Path $DbDir 'bin\mysql_install_db.exe'
    if (Test-Path $mysqlInstallDb) {
        # MariaDB: mysql_install_db.exe (no --initialize support). The empty
        # --password= creates root@localhost with an empty password and keeps
        # the tool non-interactive; basedir is auto-detected from its location.
        $output = & $mysqlInstallDb --datadir=$DataDir --password= --port=3306 2>&1
    } else {
        # MySQL 8.x: mysqld --initialize-insecure
        $output = & (Join-Path $DbDir 'bin\mysqld.exe') --defaults-file=$MyIni --initialize-insecure --console 2>&1
    }
    $exit = $LASTEXITCODE
    $ErrorActionPreference = $prevEAP
    if ($output) { $output | ForEach-Object { Write-Host "    $_" } }
    if ($exit -ne 0) { throw "Database initialization failed (exit code $exit)." }
}

# ---------------------------------------------------------------------------
function Install-Db {
    Write-Step "Installing a MySQL-compatible server into $DbDir"

    # Download + extract only when the server binaries are missing; the my.ini
    # and data-directory steps below always run (they are idempotent and also
    # repair an interrupted previous setup).
    if (-not (Test-Path (Join-Path $DbDir 'bin\mysqld.exe'))) {
        $installed = $false
        foreach ($url in $DbUrls) {
            $name = ($url -split '/')[-1]
            $zip = Join-Path $Downloads $name
            if (Invoke-Download $url $zip 1800) {
                try {
                    Expand-Zip $zip $DbDir
                    if (Move-Up-OneLevel $DbDir) { $installed = $true; break }
                } catch {
                    Write-Warn "extraction of $name failed: $($_.Exception.Message)"
                }
                Remove-Item $zip -Force -ErrorAction SilentlyContinue
            }
        }
        if (-not $installed) {
            throw 'Could not download a database server from any source (MySQL CDN may be blocked in your region). Install Laragon instead, or try again later.'
        }
    } else {
        Write-Ok 'Database server already downloaded'
    }

    # my.ini — data dir lives next to the binaries, port 3306 (app default).
    $myIni = Join-Path $DbDir 'my.ini'
    $base = $DbDir.Replace('\','/')
    @"
[mysqld]
basedir=$base
datadir=$base/data
port=3306
bind-address=127.0.0.1
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
default-time-zone='+00:00'
max_connections=100

[client]
port=3306
default-character-set=utf8mb4
"@ | Set-Content -Path $myIni -Encoding ASCII
    Write-Ok "my.ini written"

    # Initialize the data directory: creates root@localhost with an EMPTY
    # password, matching the app defaults in config/config.php. The system
    # tables (mysql + performance_schema) prove initialization completed.
    $dataDir = Join-Path $DbDir 'data'
    $initialized = (Test-Path (Join-Path $dataDir 'mysql')) -and (Test-Path (Join-Path $dataDir 'performance_schema'))
    if (-not $initialized) {
        Write-Step 'Initializing database data directory (first run, can take a minute)...'
        Remove-Item $dataDir -Recurse -Force -ErrorAction SilentlyContinue   # wipe partial attempts
        Initialize-Db $DbDir $dataDir $myIni
        if (-not ((Test-Path (Join-Path $dataDir 'mysql')) -and (Test-Path (Join-Path $dataDir 'performance_schema')))) {
            throw 'Initialization did not create the system tables.'
        }
        Write-Ok 'Data directory initialized (root user, empty password)'
    } else {
        Write-Ok 'Data directory already initialized'
    }
    Write-Ok 'Database server installed'
}

# ---------------------------------------------------------------------------
# main
# ---------------------------------------------------------------------------
Write-Host ''
Write-Host 'ExchangePro portable runtime setup' -ForegroundColor White
Write-Host "Target folder: $Runtime"

if ($Force) {
    Write-Warn '-Force: removing existing runtime folders first'
    Remove-Item $PhpDir -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item $DbDir -Recurse -Force -ErrorAction SilentlyContinue
}
New-Item -ItemType Directory -Force -Path $Runtime, $Downloads | Out-Null

Install-Php
if (-not $PhpOnly) { Install-Db }

Write-Host ''
Write-Step 'Setup complete'
Write-Host "All files live inside .\runtime and can be deleted at any time." -ForegroundColor Gray
Write-Host "Run start.bat to launch the app, or use the CLI installer directly:" -ForegroundColor Gray
Write-Host "  $PhpDir\php.exe database/install.php --with-demo" -ForegroundColor Gray
exit 0
