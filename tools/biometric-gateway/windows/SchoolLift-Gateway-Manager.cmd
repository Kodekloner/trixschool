@echo off
setlocal
set "SCHOOLLIFT_MANAGER=%~dp0gateway-manager.ps1"

if not exist "%SCHOOLLIFT_MANAGER%" (
    echo SchoolLift Gateway Manager is incomplete. Missing gateway-manager.ps1.
    pause
    exit /b 1
)

start "" "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoLogo -NoProfile -ExecutionPolicy Bypass -STA -WindowStyle Hidden -File "%SCHOOLLIFT_MANAGER%"
exit /b 0
