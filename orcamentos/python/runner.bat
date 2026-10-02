@echo off
setlocal
set "PYTHON=C:\Users\imobj\AppData\Local\Programs\Python\Python314\python.exe"
if exist "%PYTHON%" (
  "%PYTHON%" %*
  exit /b %ERRORLEVEL%
)
py -3 %*
exit /b %ERRORLEVEL%
