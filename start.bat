@echo off
REM ExchangePro — start with Laragon's PHP built-in server
REM Also auto-starts Laragon's MySQL if it isn't already running.
cd /d "%~dp0"

set PHP=C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
set MYSQLDIR=C:\laragon\bin\mysql\mysql-8.4.3-winx64
set MYSQL=%MYSQLDIR%\bin\mysqld.exe
set MYINI=%MYSQLDIR%\my.ini

if not exist "%PHP%" (
    echo PHP not found at %PHP%. Update the PHP path in start.bat or run:
    echo   php -S 127.0.0.1:8000 -t public
    pause
    exit /b 1
)

if not exist "storage\backups" mkdir "storage\backups"
if not exist "storage\logs" mkdir "storage\logs"
if not exist "storage\uploads" mkdir "storage\uploads"

REM --- Start MySQL if nothing is listening on port 3306 ---
REM (":3306 " with trailing space avoids matching the X-Protocol port 33060)
netstat -an | findstr ":3306 " | findstr "LISTENING" >nul
if errorlevel 1 (
    if not exist "%MYSQL%" (
        echo WARNING: MySQL not found at %MYSQL%.
        echo          Open Laragon and click "Start All", then run this script again.
    ) else (
        echo MySQL is not running - starting it...
        start "ExchangePro MySQL" /min "%MYSQL%" --defaults-file="%MYINI%"
        ping -n 8 127.0.0.1 >nul
        netstat -an | findstr ":3306 " | findstr "LISTENING" >nul
        if errorlevel 1 (
            echo.
            echo ERROR: MySQL is still not listening on port 3306 after starting it.
            echo        Open Laragon and click "Start All", then run this script again.
            echo        Continuing anyway - the app will show a database error if MySQL is down.
        ) else (
            echo MySQL started successfully.
        )
    )
) else (
    echo MySQL is already running.
)

echo Starting ExchangePro at http://127.0.0.1:8000
if not exist "config\installed.lock" (
    echo First run? Open http://127.0.0.1:8000/install to run the web installer.
)
echo (Press Ctrl+C to stop)
"%PHP%" -S 127.0.0.1:8000 -t public
