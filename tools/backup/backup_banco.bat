@echo off
setlocal

rem Uso:
rem   backup_banco.bat
rem   backup_banco.bat calculadora_rescisao
rem   backup_banco.bat calculadora_rescisao_teste

set "XAMPP=%XAMPP_DIR%"
if "%XAMPP%"=="" set "XAMPP=C:\xampp"

set "DB=%~1"
if "%DB%"=="" set "DB=calculadora_rescisao"

set "BACKUP_DIR=%~2"
if "%BACKUP_DIR%"=="" set "BACKUP_DIR=%~dp0backup"

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

for /f %%I in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "STAMP=%%I"

set "ARQ=%BACKUP_DIR%\%DB%_%STAMP%.sql"

if not exist "%XAMPP%\mysql\bin\mysqldump.exe" (
  echo mysqldump.exe nao encontrado em "%XAMPP%\mysql\bin".
  exit /b 1
)

"%XAMPP%\mysql\bin\mysqldump.exe" --host=127.0.0.1 --user=root --single-transaction --routines --events --triggers "%DB%" > "%ARQ%"

if errorlevel 1 (
  echo Falha ao criar backup do banco "%DB%".
  del /q "%ARQ%" >nul 2>&1
  exit /b 1
)

forfiles /p "%BACKUP_DIR%" /m "%DB%_*.sql" /d -30 /c "cmd /c del /q @path" >nul 2>&1

echo Backup criado:
echo "%ARQ%"

endlocal
