@echo off
REM ---------------------------------------------------------------------
REM Runs the costing server in its own window.
REM
REM Separate from start.bat so the command that launches it needs only one
REM level of quoting. Passing the PHP path through "start ... cmd /k" meant
REM quotes inside quotes, which cmd parsed into nothing at all: no window
REM opened and no server ran, silently.
REM
REM Called by start.bat, and safe to double-click on its own.
REM ---------------------------------------------------------------------
setlocal
title Imprint Customs Costing Server

cd /d "%~dp0"

if "%PORT%"=="" set PORT=8002
if "%HOST%"=="" set HOST=0.0.0.0

REM PHP from the PATH if it is there, from the usual XAMPP location if not.
set PHPBIN=
for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHPBIN set "PHPBIN=%%P"
if not defined PHPBIN if exist "C:\xampp\php\php.exe" set "PHPBIN=C:\xampp\php\php.exe"

if not defined PHPBIN (
    echo ERROR: PHP was not found.
    pause
    exit /b 1
)

echo Serving on %HOST%:%PORT% - close this window to stop.
echo.
"%PHPBIN%" artisan serve --host=%HOST% --port=%PORT%

REM Reached only if the server stops, so the reason stays on screen rather
REM than the window vanishing.
echo.
echo The server has stopped.
pause
