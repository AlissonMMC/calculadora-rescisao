@echo off
setlocal

REM Executa o Python sem depender do usuario que instalou o interpretador.
REM Ordem:
REM 1. PYTHON_BIN, se configurado
REM 2. Python Launcher (py)
REM 3. python

if defined PYTHON_BIN (
    "%PYTHON_BIN%" %*
    exit /b %ERRORLEVEL%
)

where py >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    py -3 %*
    exit /b %ERRORLEVEL%
)

where python >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    python %*
    exit /b %ERRORLEVEL%
)

echo ERRO: Python nao foi encontrado.
echo Configure a variavel PYTHON_BIN ou instale o Python.
exit /b 1