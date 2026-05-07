@echo off
setlocal
set ROOT_DIR=%~dp0..\..
call "%ROOT_DIR%\node.exe" "%ROOT_DIR%\out\server-cli.js" "codium" "1.112.01907" "e2b23bbd5646cc28aaa6f26ee53b257c290b6be1" "codium.cmd" %*
endlocal
