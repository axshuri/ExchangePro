@echo off
setlocal EnableExtensions
title ExchangePro Launcher
cd /d "%~dp0"

set "ROOT=%~dp0"
set "RUNTIME=%ROOT%runtime"

echo ============================================================
echo   ExchangePro - Currency Exchange Management System
echo ============================================================
echo.

REM ============================================================
REM  1. Find PHP - priority: Laragon (any version) -^> bundled
REM     runtime\php -^> system PATH. If none is found we offer to
REM     download a portable PHP + MySQL/MariaDB into .\runtime.
REM ============================================================
set "PHP="

if not defined PHP (
    for /d %%D in ("C:\laragon\bin\php\php-*") do (
        if exist "%%D\php.exe" set "PHP=%%D\php.exe"
    )
)
if not defined PHP if exist "%RUNTIME%\php\php.exe" set "PHP=%RUNTIME%\php\php.exe"
if not defined PHP where php >nul 2>nul && set "PHP=php"

REM ============================================================
REM  2. Find MySQL - Laragon (any version) -^> bundled runtime\mysql
REM ============================================================
set "MYSQLDIR="

if not defined MYSQLDIR (
    for /d %%D in ("C:\laragon\bin\mysql\mysql-*") do (
        if exist "%%D\bin\mysqld.exe" set "MYSQLDIR=%%D"
    )
)
if not defined MYSQLDIR (
    for /d %%D in ("C:\laragon\bin\mariadb\mariadb-*") do (
        if exist "%%D\bin\mysqld.exe" set "MYSQLDIR=%%D"
    )
)
if not defined MYSQLDIR if exist "%RUNTIME%\mysql\bin\mysqld.exe" set "MYSQLDIR=%RUNTIME%\mysql"

REM ============================================================
REM  3. Auto-setup: if PHP or MySQL is missing, download a
REM     portable copy into .\runtime and use it.
REM ============================================================
set "NEED_SETUP="
if not defined PHP set "NEED_SETUP=1"
if not defined MYSQLDIR set "NEED_SETUP=1"

if defined NEED_SETUP (
    call :auto_setup
    if errorlevel 1 exit /b 1
)

if not defined PHP (
    echo.
    echo ERROR: PHP is not available and could not be installed automatically.
    echo        Install Laragon ^(https://laragon.org^) and run this script again,
    echo        or start PHP manually with:
    echo          php -S 127.0.0.1:8000 -t public
    pause
    exit /b 1
)

if defined PHP echo [PHP]   using: %PHP%
if defined MYSQLDIR echo [MySQL] using: %MYSQLDIR%

REM ============================================================
REM  4. Make sure storage folders exist
REM ============================================================
if not exist "storage\backups" mkdir "storage\backups"
if not exist "storage\logs" mkdir "storage\logs"
if not exist "storage\uploads" mkdir "storage\uploads"

REM ============================================================
REM  5. Start MySQL if nothing is listening on port 3306
REM     (":3306 " with trailing space avoids the X-Protocol port 33060)
REM ============================================================
set "PORT_OPEN="
netstat -an | findstr ":3306 " | findstr "LISTENING" >nul && set "PORT_OPEN=1"

if defined PORT_OPEN (
    echo [MySQL] already running on port 3306.
) else (
    if not defined MYSQLDIR (
        echo [MySQL] WARNING: no MySQL/MariaDB server found and none is running.
        echo          The app will show a database error. Open Laragon and click
        echo          "Start All", or run this script again to auto-setup.
    ) else (
        echo [MySQL] starting %MYSQLDIR%\bin\mysqld.exe ...
        if exist "%MYSQLDIR%\my.ini" (
            start "ExchangePro MySQL" /min "%MYSQLDIR%\bin\mysqld.exe" --defaults-file="%MYSQLDIR%\my.ini"
        ) else (
            start "ExchangePro MySQL" /min "%MYSQLDIR%\bin\mysqld.exe"
        )
        ping -n 8 127.0.0.1 >nul
        netstat -an | findstr ":3306 " | findstr "LISTENING" >nul
        if errorlevel 1 (
            echo [MySQL] WARNING: still not listening after start - the app may
            echo          show a database error. Open Laragon and try again.
        ) else (
            echo [MySQL] started successfully.
        )
    )
)

REM ============================================================
REM  6. Serve the app with the PHP built-in server
REM ============================================================
echo.
echo Starting ExchangePro at http://127.0.0.1:8000
if not exist "config\installed.lock" (
    echo First run? Open http://127.0.0.1:8000/install to run the web installer.
)
echo (Press Ctrl+C to stop)
if /i "%PHP%"=="%RUNTIME%\php\php.exe" set "PHPRC=%RUNTIME%\php\php.ini"
"%PHP%" -S 127.0.0.1:8000 -t public
exit /b 0

REM ============================================================
REM  auto_setup - download and configure the portable runtime
REM ============================================================
:auto_setup
echo.
echo One or more required components are missing:
if not defined PHP echo   - PHP            ^(not found on this PC^)
if not defined MYSQLDIR echo   - MySQL server   ^(no Laragon MySQL/MariaDB either^)
echo.
echo ExchangePro can download and set up its OWN portable copies inside
echo   "%RUNTIME%"
echo PHP 8.3 + MySQL/MariaDB -^> about 120-300 MB, first run takes a few minutes.
echo Nothing outside this project folder is changed.
echo.
choice /C YN /M "Download and set them up now"
if errorlevel 2 (
    echo.
    echo Skipped. You can install Laragon ^(https://laragon.org^) and run this
    echo script again, or start PHP manually with:
    echo   php -S 127.0.0.1:8000 -t public
    pause
    exit /b 1
)
echo.
echo Running the setup - this downloads and configures everything...
powershell -NoProfile -ExecutionPolicy Bypass -File "%ROOT%scripts\setup-runtime.ps1"
if errorlevel 1 (
    echo.
    echo SETUP FAILED. Check your internet connection and try again,
    echo or install Laragon manually.
    pause
    exit /b 1
)

REM Re-detect after setup
if not defined PHP if exist "%RUNTIME%\php\php.exe" set "PHP=%RUNTIME%\php\php.exe"
if not defined MYSQLDIR if exist "%RUNTIME%\mysql\bin\mysqld.exe" set "MYSQLDIR=%RUNTIME%\mysql"
echo.
echo Setup finished.
exit /b 0
