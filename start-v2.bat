@echo off
setlocal EnableExtensions

set "ROOT=%~dp0"
set "PUBLIC_DIR=%ROOT%public"
set "PHP_EXE=C:\xampp\php\php.exe"
set "HOST=127.0.0.1"
set "PORT=5010"

if not exist "%PHP_EXE%" goto :php_missing
if not exist "%PUBLIC_DIR%\index.php" goto :entry_missing

echo Starting UDA-V2 dev server...
echo URL: http://%HOST%:%PORT%/login
echo Press Ctrl+C to stop.
echo.

cd /d "%PUBLIC_DIR%" || goto :cd_failed
"%PHP_EXE%" -S %HOST%:%PORT% -t . index.php
goto :end

:php_missing
echo [ERROR] PHP not found: %PHP_EXE%
echo Please check XAMPP PHP path in start-v2.bat
pause
exit /b 1

:entry_missing
echo [ERROR] Entry file not found: %PUBLIC_DIR%\index.php
echo Make sure this bat file is in UDA-V2 root.
pause
exit /b 1

:cd_failed
echo [ERROR] Cannot enter folder: %PUBLIC_DIR%
pause
exit /b 1

:end
endlocal
