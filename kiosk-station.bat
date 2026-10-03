@echo off
setlocal
REM ============================================================================
REM  NORSU OJT - Time In / Out Station launcher
REM ----------------------------------------------------------------------------
REM  Opens the kiosk scan page FULL-SCREEN on this desk PC and keeps it there,
REM  so nobody has to open a browser, log in, or click anything each day.
REM
REM  ONE-TIME SETUP
REM    1) Make this start by itself when the PC turns on:
REM         - Press  Win + R , type   shell:startup   and press Enter.
REM         - Drop a SHORTCUT to this file into the folder that opens
REM           (right-drag this file in -> "Create shortcuts here").
REM    2) First launch only: the page opens on the sign-in screen. Sign in as
REM       the admin and TICK "Remember me". After that the station stays signed
REM       in on its own - even across restarts - so scans just work.
REM
REM  DAILY USE
REM    - Turn the PC on. The station comes up full-screen and shows "Ready to
REM      scan". Interns present their personal OJT QR to the scanner box.
REM    - To leave / close the station: press  Alt + F4 .
REM ============================================================================

REM --- The address of the kiosk page. Change this ONLY if you open the app at
REM     a different address in your browser (e.g. http://localhost:8000/... on a
REM     local dev machine). It must end in /admin/kiosk .
set "KIOSK_URL=http://127.0.0.1/admin/kiosk"
REM "KIOSK_URL=https://norsubscojt.online/admin/kiosk"

REM --- A private browser profile used only by the station. This is what keeps
REM     it signed in between restarts - don't point it at your normal profile.
set "PROFILE=%LOCALAPPDATA%\OjtKiosk"

REM --- Find a browser. Chrome is preferred; Edge (built into Windows) is the
REM     fallback.
set "CHROME="
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set "CHROME=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" set "CHROME=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if exist "%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe" set "CHROME=%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"

set "EDGE="
if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" set "EDGE=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" set "EDGE=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"

if defined CHROME (
    start "" "%CHROME%" --kiosk "%KIOSK_URL%" --user-data-dir="%PROFILE%" --no-first-run --no-default-browser-check --noerrdialogs --disable-session-crashed-bubble --hide-crash-restore-bubble --disable-features=TranslateUI --overscroll-history-navigation=0
) else if defined EDGE (
    start "" "%EDGE%" --kiosk "%KIOSK_URL%" --edge-kiosk-type=fullscreen --kiosk-idle-timeout-minutes=0 --no-first-run --no-default-browser-check --user-data-dir="%PROFILE%"
) else (
    echo.
    echo   Could not find Google Chrome or Microsoft Edge on this PC.
    echo   Install Google Chrome, then run this file again.
    echo.
    pause
)

endlocal
