@echo off
setlocal
set "SCRIPT=%~dp0backup_banco.bat"
schtasks /Create /TN "Folha de Calculo - Backup MySQL" /TR "\"%SCRIPT%\"" /SC DAILY /ST 23:00 /RU SYSTEM /RL HIGHEST /F
if errorlevel 1 (
  echo Nao foi possivel criar a tarefa agendada.
  echo Execute este arquivo como Administrador.
  exit /b 1
)
echo Backup diario configurado para 23:00.
endlocal
