@echo off
setlocal
title Imprint Customs Costing Launcher

cd /d "%~dp0"

REM The application always serves on this port. Port 8000 belongs to the
REM production system and 8001 to the sample, so this one is left free for
REM costing and stated once here rather than repeated down the file.
set PORT=8002

REM Serve on every interface, not just this machine. Bound to 127.0.0.1 the
REM app answers only its own PC and the shop floor cannot reach it; 0.0.0.0
REM is what the production system on 8000 already does.
set HOST=0.0.0.0
set APPURL=http://127.0.0.1:%PORT%/login

where php >nul 2>&1
if errorlevel 1 (
    echo.
    echo ERROR: PHP was not found in PATH.
    echo Install PHP 8.2 or newer, then run this file again.
    echo.
    pause
    exit /b 1
)

if not exist "artisan" (
    echo.
    echo ERROR: artisan was not found in:
    echo %CD%
    echo.
    pause
    exit /b 1
)

if not exist "vendor\autoload.php" (
    echo.
    echo ERROR: PHP dependencies are not installed.
    echo Run: composer install
    echo.
    pause
    exit /b 1
)

if not exist ".env" (
    copy ".env.example" ".env" >nul
    php artisan key:generate
)

echo.
echo Starting Imprint Customs Costing System...
echo Login: %APPURL%
echo Close the server window to stop the application.
echo.
echo On this PC:      %APPURL%
echo On the network:  http://<this PC's IP>:%PORT%/login
for /f "tokens=2 delims=:" %%I in ('ipconfig ^| findstr /R /C:"IPv4.*192\.168"') do echo    try: http://%%I:%PORT%/login
echo.

REM A server already on this port is this application, left running from
REM earlier. Starting a second one would fail on the taken port and show an
REM error instead of the app, so the running one is simply reused.
netstat -ano | findstr /R /C:":%PORT% .*LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo Already running on port %PORT%. Opening the browser.
) else (
    start "Imprint Customs Server" /D "%~dp0" cmd /k "php artisan serve --host=%HOST% --port=%PORT%"
    timeout /t 3 /nobreak >nul
)

start "" "%APPURL%"

endlocal
