@echo off
setlocal
set "XAMPP=C:\xampp"
set "DB=calculadora_rescisao"
set "BACKUP_DIR=%~dp0backup"
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
for /f %%I in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set "STAMP=%%I"
set "ARQ=%BACKUP_DIR%\%DB%_%STAMP%.sql"
"%XAMPP%\mysql\bin\mysqldump.exe" --host=127.0.0.1 --user=root --single-transaction --routines --events --triggers "%DB%" > "%ARQ%"
if errorlevel 1 (
  echo Falha ao criar backup do banco.
  del /q "%ARQ%" >nul 2>&1
  exit /b 1
)
forfiles /p "%BACKUP_DIR%" /m "*.sql" /d -30 /c "cmd /c del /q @path" >nul 2>&1
 echo Backup criado: "%ARQ%"
endlocal
