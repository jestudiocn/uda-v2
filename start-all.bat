@echo off
setlocal EnableExtensions

set "ROOT=%~dp0"
set "PUBLIC_DIR=%ROOT%public"
set "WEB_START_BAT=%ROOT%start-v2.bat"
set "PHP_EXE=C:\xampp\php\php.exe"
set "MYSQLD_EXE=C:\xampp\mysql\bin\mysqld.exe"
set "HOST=127.0.0.1"
set "PORT=5010"

if not exist "%PHP_EXE%" goto :php_missing
if not exist "%MYSQLD_EXE%" goto :mysqld_missing
if not exist "%PUBLIC_DIR%\index.php" goto :entry_missing
if not exist "%WEB_START_BAT%" goto :web_start_missing

echo Starting UDA-V2 all services...
echo.

tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul
if %ERRORLEVEL%==0 (
    echo [OK] MySQL already running.
) else (
    echo [RUN] Starting MySQL...
    start "UDA-V2-MySQL" cmd /k ""%MYSQLD_EXE%" --console"
    timeout /t 2 /nobreak >nul
)

echo [RUN] Starting PHP dev server...
start "UDA-V2-Web" "%WEB_START_BAT%"

echo.
echo [DONE] Services started.
echo Open: http://%HOST%:%PORT%/login
pause
exit /b 0

:php_missing
echo [ERROR] PHP not found: %PHP_EXE%
pause
exit /b 1

:mysqld_missing
echo [ERROR] mysqld not found: %MYSQLD_EXE%
pause
exit /b 1

:entry_missing
echo [ERROR] Entry file not found: %PUBLIC_DIR%\index.php
pause
exit /b 1

:web_start_missing
echo [ERROR] start-v2.bat not found: %WEB_START_BAT%
pause
exit /b 1

