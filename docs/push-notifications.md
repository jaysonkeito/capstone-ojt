# Push notifications (FCM) — setup guide

The server side is fully wired: every review/request notification the app already
delivers to the in-app inbox also fans out to the recipient's registered Android devices
through Firebase Cloud Messaging. Everything is **fail-safe** — with no Firebase
credentials configured the push channel silently no-ops and only the database
notification is created, which is exactly how the app runs today.

## How it works

```
review decided ──→ Laravel notification ──→ database (in-app inbox, always)
                                        └──> fcm channel (App\Notifications\Channels\FcmChannel)
                                                  └──> Firebase ──> intern's phone
```

- Device registration: the app registers its FCM token to `POST /devices` after login
  (`resources/js/app.js`); logout calls `DELETE /devices`. Tokens live in the
  `device_tokens` table; a phone changing hands follows the newest login.
- The channel prunes tokens FCM reports as no longer registered (app uninstalled).
- Tapping a notification opens the app and navigates to the linked page
  (default `/notifications`).

## Enabling it (one-time, ~15 minutes)

1. **Firebase project** — console.firebase.google.com → *Add project* (Google account).
2. **Register the Android app** — project settings → *Add app* → Android → package name
   **`com.norsubscojt.tracker`** → register.
3. **`google-services.json`** — download it and place it at `android/app/google-services.json`.
   The Gradle build picks it up automatically (the google-services plugin is already wired
   conditionally in `android/app/build.gradle`).
4. **Install the plugin** and rebuild:
   ```bash
   npm install @capacitor/push-notifications
   npx cap sync android
   cd android && ./gradlew assembleDebug
   ```
   The app then asks for notification permission on first launch and registers its token.
5. **Service account (server side)** — Firebase console → project settings → *Service
   accounts* → *Generate new private key* → save the JSON **outside git** (e.g.
   `storage/firebase-service-account.json` — `storage/` is git-ignored) and point the
   server `.env` at it:
   ```ini
   FIREBASE_CREDENTIALS=storage/firebase-service-account.json
   ```
6. `php artisan config:clear` (or restart the Docker stack). Pushes now go out with every
   notification.

## Sending a test push

```bash
php artisan tinker
```
```php
$user = App\Models\User::where('email', 'an.intern@norsubscojt.online')->first();
$user->notify(new App\Notifications\LogReviewed(
    $user->logs()->first(), 'approved', null, auth()->user() ?? $user
));
```
The phone shows "Duty entry reviewed — Your duty entry for <date> was approved."

## Notification coverage

| Event | Recipient | Push copy |
|---|---|---|
| Duty entry reviewed | Intern | "Duty entry reviewed — approved / rejected: comment" |
| Duty entry flagged | Intern | "Duty entry flagged" |
| Manual entry submitted | Admin/reviewers | "Manual time entry — {intern} needs your review" |
| Attendance request submitted / decided | Reviewer / Intern | "Attendance request …" |
| Placement/completion request submitted / decided | Admin / Coordinator | "Request submitted / decided" |
| Timesheet certified | Intern | "Timesheet certified" |

New notification classes only need `kind` + readable fields in `toArray()` (already the
codebase convention) and `'fcm'` in `via()`; `FcmChannel::KIND_TITLES` and `bodyFor()` map
them to phone copy — add a line there for a custom title/body.
