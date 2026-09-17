@echo off
setlocal enabledelayedexpansion
title Imprint Customs Costing Launcher

cd /d "%~dp0"

REM ---------------------------------------------------------------------
REM The port this application always serves on.
REM
REM 8000 is the production system and 8001 is the sample, so costing takes
REM 8002. Stated once here rather than repeated down the file: the launcher
REM once had 8001 left in it and happily "found" the sample already running,
REM skipped starting costing at all, and opened the browser on the wrong app.
REM ---------------------------------------------------------------------
set PORT=8002

REM Serve on every interface, not just this machine. Bound to 127.0.0.1 the
REM app answers only its own PC and the shop floor cannot reach it. Reaching
REM it from another PC also needs a firewall rule for this port.
set HOST=0.0.0.0
set APPURL=http://127.0.0.1:%PORT%/login

REM PHP, from the PATH if it is there and from the usual XAMPP location if it
REM is not. A console without PHP on its PATH is the commonest reason this
REM file used to stop at the first step.
set PHPBIN=
for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHPBIN set "PHPBIN=%%P"
if not defined PHPBIN if exist "C:\xampp\php\php.exe" set "PHPBIN=C:\xampp\php\php.exe"

if not defined PHPBIN (
    echo.
    echo ERROR: PHP was not found.
    echo Install PHP 8.2 or newer and put it on the PATH, then run this file again.
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
    "%PHPBIN%" artisan key:generate
)

echo.
echo Starting Imprint Customs Costing System...
echo.
echo   On this PC:     %APPURL%

REM Each IPv4 address this PC has, so whoever runs it can tell the floor what
REM to type. ipconfig prints "IPv4 Address. . . : 192.168.1.5", so the address
REM arrives with a leading space that has to come off before it is part of a
REM URL. The angle brackets that used to sit in this message were read by cmd
REM as redirection and stopped the script here.
for /f "tokens=2 delims=:" %%I in ('ipconfig ^| findstr /C:"IPv4 Address"') do (
    set "IPADDR=%%I"
    set "IPADDR=!IPADDR: =!"
    echo   On the network: http://!IPADDR!:%PORT%/login
)

echo.
echo Close the server window to stop the application.
echo.

REM A server already on this port is this application, left running from
REM earlier. Starting a second one would fail on the taken port and show an
REM error instead of the app, so the running one is simply reused. Matched on
REM the whole address so a port ending in these digits cannot be mistaken for
REM this one.
set RUNNING=
netstat -ano | findstr /R /C:"TCP.*[:.]%PORT% .*LISTENING" >nul 2>&1
if not errorlevel 1 set RUNNING=1

if defined RUNNING (
    echo Already running on port %PORT%. Opening the browser.
) else (
    start "Imprint Customs Server" /D "%~dp0" cmd /k "%~dp0serve.bat"
    "%SystemRoot%\System32\timeout.exe" /t 3 /nobreak >nul 2>&1
)

start "" "%APPURL%"

endlocal
