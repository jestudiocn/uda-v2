@echo off
setlocal EnableExtensions

set "ROOT=%~dp0"
set "MYSQL_EXE=C:\xampp\mysql\bin\mysql.exe"
set "DB_NAME=uda_v2"
set "SQL_FILE="

if not exist "%MYSQL_EXE%" goto :mysql_missing

if not "%~1"=="" (
    set "SQL_FILE=%~1"
    goto :run_sql
)

echo ===== UDA-V2 SQL Runner =====
echo 1) Seeder 003 (system page permissions)
echo 2) Seeder 004 (granular action permissions)
echo 3) Seeder 005 (calendar permissions)
echo 4) Custom SQL file
echo.
set /p CHOICE=Select (1/2/3/4): 

if "%CHOICE%"=="1" set "SQL_FILE=%ROOT%database\seeders\003_permissions_system_pages_seed.sql"
if "%CHOICE%"=="2" set "SQL_FILE=%ROOT%database\seeders\004_permissions_action_granular_seed.sql"
if "%CHOICE%"=="3" set "SQL_FILE=%ROOT%database\seeders\005_permissions_calendar_seed.sql"

if "%CHOICE%"=="4" (
    echo Enter full SQL file path:
    set /p "SQL_FILE="
)

if not defined SQL_FILE goto :no_selection
set "SQL_FILE=%SQL_FILE:"=%"
for /f "tokens=* delims= " %%A in ("%SQL_FILE%") do set "SQL_FILE=%%A"
if "%SQL_FILE%"=="" goto :no_selection

:run_sql
if not exist "%SQL_FILE%" goto :file_missing

echo.
echo Running:
echo "%MYSQL_EXE%" --default-character-set=utf8mb4 -u root -p %DB_NAME% ^< "%SQL_FILE%"
echo.
"%MYSQL_EXE%" --default-character-set=utf8mb4 -u root -p %DB_NAME% < "%SQL_FILE%"

if errorlevel 1 goto :run_failed

echo.
echo [OK] SQL completed.
pause
exit /b 0

:mysql_missing
echo [ERROR] MySQL not found: %MYSQL_EXE%
pause
exit /b 1

:no_selection
echo [ERROR] No SQL file selected.
pause
exit /b 1

:file_missing
echo [ERROR] SQL file not found:
echo %SQL_FILE%
pause
exit /b 1

:run_failed
echo.
echo [FAILED] SQL execution failed. Check error message above.
pause
exit /b 1
