@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0\.."

if not exist ".env.docker" (
  echo ERROR: .env.docker was not found in the project root.
  exit /b 1
)

set "BASE_URL=%~1"
set "APP_PORT=8080"

for /f "usebackq tokens=1,* delims==" %%A in (`findstr /b /c:"APP_PORT=" ".env.docker"`) do (
  if not "%%B"=="" set "APP_PORT=%%B"
)

if defined BASE_URL goto validate_url

echo Detecting the active LAN IPv4 address...
set "LAN_IP="
for /f "usebackq delims=" %%I in (`powershell.exe -NoProfile -Command "$c=Get-NetIPConfiguration ^| Where-Object {$_.IPv4DefaultGateway -and $_.IPv4Address} ^| Select-Object -First 1; if($c){$c.IPv4Address.IPAddress}"`) do (
  if not defined LAN_IP set "LAN_IP=%%I"
)

if not defined LAN_IP (
  echo ERROR: No usable LAN IPv4 address was detected.
  echo.
  echo Run this command with the shared URL explicitly, for example:
  echo   tools\configure-verification-url.cmd http://192.168.1.50:8080
  echo.
  echo For actual deployment from any network, use the approved HTTPS URL, for example:
  echo   tools\configure-verification-url.cmd https://spmu.example.edu.ph
  exit /b 1
)

set "BASE_URL=http://%LAN_IP%:%APP_PORT%"

:validate_url
if /i "%BASE_URL:~0,7%"=="http://" goto reject_loopback
if /i "%BASE_URL:~0,8%"=="https://" goto reject_loopback

echo ERROR: The URL must start with http:// or https://
exit /b 1

:reject_loopback
echo %BASE_URL% | findstr /i /r "^http[s]*://localhost[:/] ^http[s]*://127\.0\.0\.1[:/]" >nul
if not errorlevel 1 (
  echo ERROR: localhost and 127.0.0.1 cannot be used for cross-device verification.
  exit /b 1
)

:write_env
set "TEMP_ENV=.env.docker.verification.tmp"
findstr /v /b /c:"EMAIL_VERIFICATION_BASE_URL=" ".env.docker" > "%TEMP_ENV%"
if errorlevel 1 (
  rem findstr returns 1 when no lines are selected; keep going if temp file exists.
  if not exist "%TEMP_ENV%" (
    echo ERROR: Could not prepare the temporary environment file.
    exit /b 1
  )
)

>>"%TEMP_ENV%" echo EMAIL_VERIFICATION_BASE_URL=%BASE_URL%
move /y "%TEMP_ENV%" ".env.docker" >nul
if errorlevel 1 (
  echo ERROR: Could not update .env.docker.
  exit /b 1
)

echo.
echo Verification email URL set to: %BASE_URL%
echo.
echo Recreate the Laravel containers so the new URL is loaded:
echo   docker compose up -d --build --force-recreate app scheduler queue
echo.
echo Then send a NEW verification email. Old emails containing localhost or 127.0.0.1 cannot be repaired.
echo.
echo NOTE: A private LAN address works only while the recipient device can reach this computer.
echo For verification from any network, configure the institution-approved public HTTPS SPMU-ACPMP URL instead.

endlocal
