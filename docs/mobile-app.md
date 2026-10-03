# OJT Tracker — Android app

The intern mobile app is a **Capacitor-wrapped Android build of this same Laravel app**: one
codebase, one database, session auth unchanged. The APK's WebView simply loads the live site
(`https://norsubscojt.online`), so every fix you deploy to the web app is instantly in the app —
no app release needed for content changes. The native layer adds the launcher icon, splash
screen, status-bar styling, real connectivity detection, and the install itself.

Offline support lives on the web side and benefits the browser too:

| Piece | File(s) | What it does |
|---|---|---|
| Service worker | `public/sw.js` | Caches recently visited intern pages (dashboard, **My QR Code**, time frame, journal) network-first; serves the last copy when offline; `public/offline.html` as last resort. |
| Client bundle | `resources/js/app.js` (loaded via `@vite` in `layouts/app.blade.php`) | Registers the SW, shows the offline banner, and owns the journal draft queue. |
| Journal drafts | IndexedDB `ojt-offline` | Submitting a journal while offline (`form[data-offline-queue]` in `documentation.blade.php`) saves it on the device; it replays to the normal `intern.photo.store` endpoint when the connection returns (419 = session expired → retried after the next login; 422 = rejected, draft dropped with the server's message). |

**Offline QR:** My QR Code is fully self-contained (inline base64 PNG), so once an intern has
opened it while online, the kiosk scan works with zero connectivity. The cached copy only goes
stale if the intern's scan token is regenerated — which happens server-side, and refreshes on
the next online visit.

**Privacy note:** cached pages outlive logout on a shared device. The client clears the SW
cache on logout and on every `/login` page load, but an intern who force-closes the app
instead of logging out leaves their cached pages on the device.

---

## Prerequisites (one-time, machine)

- Node 22+, PHP 8.3, Composer — already used by the project.
- Android Studio with the SDK (installed at `%LOCALAPPDATA%\Android\Sdk`, platform 35).
- `JAVA_HOME` must point at **JDK 21** (Android Studio's bundled JBR works), *not* the old
  JDK 8 currently on the machine:

  ```bat
  setx JAVA_HOME "C:\Program Files\Android\Android Studio\jbr"
  setx ANDROID_HOME "%LOCALAPPDATA%\Android\Sdk"
  ```

  (Open a new terminal afterwards. `android/local.properties` also pins `sdk.dir` locally and
  is git-ignored.)

- **Production must run HTTPS** before the app is distributed — the Android WebView refuses
  plain HTTP and service workers need a secure context. On the MIS Office Ubuntu server,
  Certbot handles this automatically (see `docs/deploy-ubuntu.md`); also set
  `SESSION_SECURE_COOKIE=true` in the production `.env`.

## Daily web development

Unchanged: edit Blade/controllers → `npm run build` (or run Vite dev) → refresh. The app
picks up everything on its next load since it renders server-side.

## Building the app

```bash
npm install                     # once per checkout
npx cap sync android            # after changing capacitor.config.ts, the cap-www stub, or plugins
cd android && ./gradlew assembleDebug
```

- Debug APK: `android/app/build/outputs/apk/debug/app-debug.apk` — sideloadable for testing
  ("Install unknown apps" permission on the phone).
- First build downloads Gradle itself; later builds are much faster.

### Pointing a device at your dev machine

```bash
php artisan serve --host=0.0.0.0 --port=8000     # laptop's LAN IP, e.g. 192.168.1.20
CAP_SERVER_URL=http://192.168.1.20:8000 npx cap sync android
cd android && ./gradlew assembleDebug
```

`CAP_SERVER_URL` also enables cleartext traffic (http) — only do this on dev builds. Re-run
`npx cap sync android` without the variable before a production build.

## Release build (Play Store or direct APK)

```bash
keytool -genkey -v -keystore ojt-tracker.keystore -alias ojttracker -keyalg RSA -keysize 2048 -validity 10000
```

In `android/app/build.gradle` add the signing config with that keystore (keep the keystore +
passwords out of the repo), then `./gradlew assembleRelease`. Play Store wants an `.aab`
(`bundleRelease`); for direct distribution to interns the signed APK is enough.

## Distributing the app through the website

The website is the primary access — interns get a link (norsubscojt.online) and use it in
any browser. On **Android phones in a normal browser**, a slim install banner appears
(`partials/app-install-banner.blade.php`, included in both layouts) linking to the public
`/download` page, which streams the APK or — once published — redirects to the Play Store
listing (`APP_APK_URL` in `.env`).

- Requests from inside the app carry the `OJTTrackerApp/1.0` user-agent marker
  (`capacitor.config.ts` → `server.appendUserAgent`), so the banner never shows in the app,
  and never on desktop or iOS (no iOS build yet).
- To post a build on the server: copy `app-release.apk` to
  `/var/www/ojt-tracker/public/downloads/ojt-tracker.apk` (the folder is git-ignored).
  Inside the Docker stack, `public/` is a shared volume seeded from the image — place the
  APK on the host and `docker cp` it into the `app` container at
  `/var/www/public/downloads/`, or rebuild the image with the APK included.

## Store / version updates

- Version name/code: `android/app/build.gradle` (`versionCode`, `versionName`).
- App name: `capacitor.config.ts` (`appName`) — the launcher label.
- Icon/splash sources: `assets/*.png` → `npx @capacitor/assets generate --android`.
- **App id** (`com.norsubscojt.tracker`) is permanent — changing it after publishing is a
  new app for every phone.

## Manual test matrix (run before each distribution)

1. Install APK → splash shows → login with a default password (last name) → banner prompting
   password change appears as on the web.
2. Dashboard renders hours/calendar; open **My QR Code**, then airplane mode → reopen the app
   → QR still displays (service-worker cache).
3. Airplane mode → journal a duty day (photo + notes) → amber chip "saved on this device" →
   back online → toast confirms upload → server shows the journal.
4. Offline draft with an expired session replays after re-login (no data loss).
5. Logout → log in as a different intern → no trace of the previous user's cached pages.

## Current constraints

- Server-rendered pages need connectivity on first view; only cached pages work offline.
- Requests, requirements downloads, and journal editing/archiving are online-only by design.
- Push notifications (FCM) are the natural next addition — the Capacitor shell is ready for it.
- iOS would need a Mac + Apple Developer account; the same web layer works unchanged there.

## Play Store readiness

The signed release build is wired: `android/keystore.properties` (git-ignored — holds the
passwords) points at the keystore outside the repo. **Back that keystore file up** — losing
it means a new app identity for everyone.

- Build: `cd android && ./gradlew assembleRelease` → `app/build/outputs/apk/release/app-release.apk`
  (verify: `apksigner verify --print-certs ...`). For Play upload: `./gradlew bundleRelease` (`.aab`).
- **Listing**: app name "OJT Tracker"; short description ("Time in, journals, and
  requirements for NORSU CAS student interns." — 80 chars max); full description from
  README.md's role table; category *Productivity*; tags: #education #school.
- **Privacy policy URL**: `https://norsubscojt.online/privacy` (public route ships with the
  app; also linked from the login page footer).
- **Data safety form**: collects account info (student ID, name), app activity (attendance,
  journals), and photos; data is encrypted in transit (HTTPS), no data sold or shared with
  third parties; deletion on request via the MIS Office.
- **Screenshots**: phone screenshots of Dashboard, My QR Code, My Journal, Notifications
  (take them from the app once the server is live Tuesday).
- **Content rating**: questionnaire → Everyone; no ads, no user-generated sharing between
  interns beyond journals visible to staff only.
- **Rollout**: start on the *Internal testing* track (email-list testers) until the office
  verifies the flows in `docs/mobile-app.md`'s test matrix, then promote to production.
- Bump `versionCode`/`versionName` in `android/app/build.gradle` for every release.
