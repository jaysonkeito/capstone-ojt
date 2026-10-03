# OJT Tracker — User's Manual

**On-the-Job Training Management System for NORSU CAS**
Version 1.0 · October 2026 · Prepared for the capstone documentation

> 📷 *[Cover screenshot — intern dashboard]*

---

## 1. Introduction

OJT Tracker is the web-and-mobile system used by the College of Arts and Sciences (CAS) of
Negros Oriental State University to manage student internships. It replaces paper attendance
sheets and scattered spreadsheets with:

- QR-code time in/out recorded at each office's kiosk scanner,
- daily duty journals with photos, reviewed by office supervisors,
- a supervisor review workflow (approve / reject / flag duty entries),
- attendance requests (missed scans, corrections) with a decision trail,
- monthly timesheets certified by supervisors,
- the school's OJT requirement forms (Personal Information sheet, Weekly Progress Report,
  Time Frame, endorsements) pre-filled with each intern's own data,
- an Android app for interns that keeps working without internet (offline QR and journal
  drafts that sync automatically).

One system serves four roles: **System Admin**, **OJT Coordinator**, **Office Supervisor**,
and **Intern**.

| | Web | Android app |
|---|---|---|
| Intern | ✔ | ✔ (primary) |
| Supervisor | ✔ | ✔ |
| Coordinator | ✔ | ✔ |
| System Admin | ✔ | ✔ |

- Production: **https://norsubscojt.online**
- App id: **com.norsubscojt.tracker** ("OJT Tracker")

> 📷 *[Figure 1.1 — system architecture diagram]*

---

## 2. Getting started

### 2.1 Accounts

Intern accounts are bulk-created by the admin from the registrar's official list. Staff
accounts (coordinators, supervisors) are created by the admin under **Staff**. Self-service
registration is also available for interns, coordinators, and supervisors.

### 2.2 Signing in

1. Open https://norsubscojt.online (or launch the OJT Tracker app).
2. Enter your **Student ID or email** and your password.
3. Your **default password is your last name** — capitalization does not matter
   (e.g., a student named "Teope" logs in with `Teope`, `teope`, or `TEOPE`).

> 📷 *[Figure 2.1 — login page]*

### 2.3 First password change and profile completion

- A banner reminds you to change your password until you do (Profile → password fields).
- New interns land on the **profile completion** form before anything else. This one form
  supplies the data printed on the school's Personal Information sheet and requirement forms.

---

## 3. Intern guide

Interns live in the mobile app; every screen below is also on the web.

### 3.1 My Dashboard

Shows today's punches (AM In → AM Out → PM In → PM Out), hours rendered against the 500-hour
target, the duty-day calendar for the month, and the current OJT set (enrollment period).
Past OJT sets stay readable as archives.

> 📷 *[Figure 3.1 — dashboard with punches and hours]*

### 3.2 My QR Code

Your personal QR encodes your identity for the kiosk scanner. At the office, open this page
and hold the code to the scanner — each scan records the next event of your day:
**AM In → AM Out → PM In → PM Out** (four scans per full day). Download the PNG badge for a
wallet-card version.

**Offline:** once you've opened this page while online, the QR keeps working with no
internet — perfect for offices without data coverage.

> 📷 *[Figure 3.2 — QR badge page]*

### 3.3 My Time Frame

The monthly duty calendar the school requires ("On-the-Job Training Time Frame") — your
logged days per month, exportable for submission.

### 3.4 My Journal

After a day with a recorded clock-out, upload that day's journal:

1. Find the day card and tap **Add Journal**.
2. Attach the **duty photo** (required for the first entry of a day) and write the
   **journal message** (what you did that day).
3. Save. A chip shows the journal status; you can edit it, or delete it with two choices —
   **Archive** (restorable, kept under "Archived journals") or **Delete forever** (permanent).

**Offline:** with no connection, saving a journal stores it **on your device** ("saved on
this device") and it uploads automatically the moment you're back online. Drafts survive
re-login.

> 📷 *[Figure 3.3 — journal upload form]* · 📷 *[Figure 3.4 — archived journals section]*

### 3.5 My Requests

Submit an **attendance request** when a scan was missed or wrong (lost scan, office
errands, sickness). Choose the date, type, and reason. Your supervisor or coordinator
decides; the decision (with any comment) lands in your notifications.

### 3.6 Personal Info

Your personal data sheet — birth details, addresses, contacts, parents/guardian. Used to
pre-fill the school's forms.

### 3.7 Requirements

Download the OJT requirement forms **already filled with your data**: Student Intern's
Personal Information (blank fields print N/A), Internship Application Letter (addressed to
your supervisor), Cover Page, Training Agreement, and more. Forms the admin has customized
use the customized design.

### 3.8 Notifications

The inbox for review decisions, request decisions, and flagged entries. The Android app
also shows these as **push notifications** (once enabled by the office) — tapping one opens
the related page.

---

## 4. Office Supervisor guide

Supervisors see **only their office's interns**.

- **Dashboard** — assigned interns, on-duty-now card, pending reviews.
- **Logbook** — every duty entry for your interns. Open a day to **approve**, **reject**
  (with a required comment), or **flag** it. Manual corrections your interns request appear
  as **pending manual entries** for your review.
- **Kiosk** — the desk scanner page (same as admin; see 6.4) and a manual lookup by
  Student ID when a QR won't scan.
- **Requests** — attendance requests from your interns: approve or reject with a comment.
- **Timesheet certification** — at month's end, review and certify each intern's timesheet
  for the period; certification locks the period.
- **Templates & Settings** — same as the admin's (see 6.5, 6.6), scoped to your office's use.

> 📷 *[Figure 4.1 — logbook review panel]* · 📷 *[Figure 4.2 — attendance request decision]*

## 5. OJT Coordinator guide

Coordinators supervise their assigned interns across offices and carry the academic-side
workflow:

- Everything supervisors have, **plus**:
- **Placement proposals** — endorse an intern's office placement to the System Admin for
  approval.
- **Completion recommendations** — certify that an intern has completed their hours,
  triggering the admin's final sign-off.
- **Staff and Offices directories** — view staff and office records (admin manages them).

> 📷 *[Figure 5.1 — coordinator dashboard]*

## 6. System Admin guide

### 6.1 Dashboard

University-wide counts: interns on duty now, pending reviews, pending requests, per-office
distribution.

### 6.2 Interns

Create, edit, archive (soft-delete), restore, or permanently delete intern accounts. Key
actions per intern:

- **Profile** — full roster + personal information view.
- **Fix Student ID** — edit the ID without recreating the account (uniqueness is enforced).
- **Start new set** — open the intern's next OJT enrollment (previous sets become read-only
  archives).
- **Reset password** — back to the default (their last name).

> 📷 *[Figure 6.1 — intern profile, tabbed view]*

### 6.3 Logbook

All duty entries across offices, with the same review workflow as supervisors, plus manual
entry creation and CSV export.

### 6.4 Kiosk

The front-desk scanner station. The office PC opens this page full-screen (Chrome kiosk
mode via `kiosk-station.bat`); each QR scan records the intern's next time-of-day event.
Manual entry by Student ID for unreadable codes.

### 6.5 Templates

For each school form: **download the starter** (macroized Word file), edit it in Word, then
**upload** the customized design. Every intern's download then merges their own data into
that design. Deleting a template reverts the form to the built-in default.

### 6.6 Settings

Standard working hours, working days, and no-class days (holidays, suspensions) — the
calendar that drives hour computations and the duty calendar.

### 6.7 Staff, Offices, Requests

Manage staff accounts and offices; decide coordinator proposals (placement, completion).

---

## 7. The Android app

- Install the APK (or via Play Store once published). Sign in as on the web.
- **Offline behavior:** pages you've visited stay readable without internet; the QR always
  works; journal entries save on-device and sync when reconnected; an amber banner tells
  you when you're offline.
- **Push notifications:** review/request decisions arrive as phone notifications (after
  the office enables Firebase — see `docs/push-notifications.md`).
- Notifications, My Journal, My QR Code, My Time Frame mirror the web exactly.

> 📷 *[Figure 7.1 — app home screen]* · 📷 *[Figure 7.2 — offline banner + queued journal]*

## 8. Troubleshooting

| Problem | Fix |
|---|---|
| "These credentials do not match" | Student ID or email typo; password is your last name (any capitalization). Five wrong attempts lock the account for one minute. |
| QR won't scan at the kiosk | The desk staff can look you up by Student ID and record your time manually. |
| Journal upload rejected | A photo is required on a day's first entry, files max 5 MB (jpeg/png/webp), and the day must have a recorded clock-out. |
| Offline journal didn't sync | It retries on the next online page load; if your session expired, it syncs right after you log in again. |
| Form prints N/A | Those fields aren't in your Personal Info yet — complete the form under Personal Info. |
| Forgot password | Ask your OJT coordinator or the admin to reset it (resets to your last name). |
