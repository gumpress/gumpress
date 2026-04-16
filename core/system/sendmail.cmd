:: GumPress - MIT License

@echo off
@cd %~dp0
setlocal
set MBOX=..\..\root\wordpress\mailer_data
if not exist "%MBOX%" mkdir "%MBOX%"
for /f "tokens=*" %%i in ('powershell -NoProfile -Command "Get-Date -Format 'yyyyMMdd_HHmmss'"') do set TIMESTAMP=%%i
set FILE=%MBOX%\%TIMESTAMP%.eml
more > "%FILE%"
exit /b %ERRORLEVEL%
