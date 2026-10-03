# OJT Tracker — NORSU CAS

Internship (OJT) tracking for the College of Arts and Sciences of Negros Oriental State
University: QR-based time in/out at the office kiosk, duty journals with photos, attendance
requests with a coordinator/supervisor review workflow, requirement forms pre-filled per
intern, and an Android app for interns.

One Laravel codebase serves everything — the web app, the front-desk kiosk, and the
Android app (a Capacitor shell that loads this same server, so there is no second backend
and no second database).

## Roles

| Role | What they do |
|---|---|
| System Admin | Interns, staff, offices, logbook review, kiosk, settings, templates, requests |
| OJT Coordinator | Own dashboard of assigned interns, logbook review, placement & completion requests |
| Office Supervisor | Assigned interns' logs, kiosk, reviews for their office |
| Intern | Dashboard, My QR Code, My Time Frame, My Journal (with offline drafts), requests, requirements |

## Stack

- Laravel 13 (PHP 8.4+), Blade + Tailwind CSS v4 (Vite), MySQL 8, Pest tests (316)
- Android: Capacitor 7 wrapper (`com.norsubscojt.tracker`) with an offline layer —
  service-worker page cache (offline QR at the kiosk), IndexedDB journal drafts that
  auto-upload on reconnect
- Production: Docker Compose on the MIS Office Ubuntu server, `https://norsubscojt.online`

## Quick start (development)

```bash
composer install
cp .env.example .env        # configure MySQL, then:
php artisan key:generate && php artisan migrate && php artisan db:seed
npm install && npm run build
composer run dev            # serves the app + Vite + queue worker
```

Interns log in with Student ID or email; default password is their last name.

## Documentation

| Doc | Contents |
|---|---|
| [`docs/deploy-docker.md`](docs/deploy-docker.md) | Production deployment (Docker, HTTPS, backups) — the office's chosen path |
| [`docs/deploy-ubuntu.md`](docs/deploy-ubuntu.md) | Bare-metal fallback deployment |
| [`docs/mobile-app.md`](docs/mobile-app.md) | Building the Android APK, dev loop, release signing, offline behavior |

## Repository layout

```
app/            Laravel application (controllers, models, services, policies)
resources/      Blade views, Tailwind CSS, the shared client bundle (offline layer)
public/sw.js    Service worker: offline page cache
android/        Capacitor Android project (launcher icon, splash, native shell)
docker/         Container stack: nginx vhosts, PHP settings, entrypoint
database/       Migrations, factories, seeders (roster CSVs are git-ignored — real student data)
docs/           Deployment + mobile app guides
```

## Privacy note

`database/data/*.csv` (registrar roster), `resources/screenshot/` (paper attendance
scans), and `public/reference/` (system references) contain real student data and are
intentionally **not** tracked in this repository.
