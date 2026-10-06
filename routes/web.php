<?php

use App\Http\Controllers\Admin\ApprovalController as AdminApprovalController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DocumentTemplateController;
use App\Http\Controllers\Admin\InternController;
use App\Http\Controllers\Admin\KioskController;
use App\Http\Controllers\Admin\NoClassDayController;
use App\Http\Controllers\Admin\OfficeController;
use App\Http\Controllers\Admin\OjtLogController;
use App\Http\Controllers\Admin\RequestController as AdminRequestController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Intern\DashboardController as InternDashboardController;
use App\Http\Controllers\Intern\PersonalInformationController as InternPersonalInformationController;
use App\Http\Controllers\Intern\RequestController as InternRequestController;
use App\Http\Controllers\Intern\RequirementController as InternRequirementController;
use App\Http\Controllers\AppDownloadController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\Monitor\RequestController as MonitorRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileCompletionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Root redirect — each role's home screen
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    $user = auth()->user();

    if (! $user) {
        return redirect()->route('login');
    }

    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    // A fresh self-service account completes its profile before reaching
    // any dashboard — the first sign-in lands here.
    if ($user->needsProfileCompletion()) {
        return redirect()->route('profile-completion.edit');
    }

    if ($user->isMonitor()) {
        return redirect()->route('monitor.dashboard');
    }

    return redirect()->route('intern.dashboard');
});

/*
|--------------------------------------------------------------------------
| Guest / Authentication routes
|--------------------------------------------------------------------------
*/

// Privacy policy — public by design (the Play Store listing links to it).
Route::view('/privacy', 'privacy')->name('privacy');

// Android app download — the target of the mobile-browser install banner.
Route::get('/download', [AppDownloadController::class, 'show'])->name('app.download');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Self-service registration — interns, coordinators, and supervisors.
    // Accounts are active immediately; the admin fills in OJT placement
    // details afterwards. Throttled to blunt automated sign-up abuse.
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');

    // Live "already taken?" check for the sign-up form (Student ID, email,
    // full name). Throttled to blunt account-enumeration scraping.
    Route::post('/register/availability', [RegisterController::class, 'availability'])
        ->middleware('throttle:30,1')
        ->name('register.availability');
});

Route::middleware('auth')->post('/logout', [LoginController::class, 'destroy'])->name('logout');

/*
|--------------------------------------------------------------------------
| Profile — every role gets one: photo, contact info, password. Replaces
| the old forced first-login change-password wall (a banner nudges
| default-password accounts instead).
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'profile-completed'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'create'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::put('/change-password', [PasswordController::class, 'update'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Profile completion — the first-login form for self-service accounts
|--------------------------------------------------------------------------
| Fresh sign-ups (interns immediately; coordinators and supervisors once
| the admin approves them) land here on their first sign-in and stay away
| from the dashboards until the form has been saved at least once. Admins
| never complete profiles — accounts they provision are stamped completed
| at creation.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:intern,coordinator,supervisor'])->group(function () {
    Route::get('/profile-completion', [ProfileCompletionController::class, 'edit'])->name('profile-completion.edit');
    Route::put('/profile-completion', [ProfileCompletionController::class, 'update'])->name('profile-completion.update');
});

/*
|--------------------------------------------------------------------------
| System Admin routes — full oversight of interns, logs, and settings
|--------------------------------------------------------------------------
*/ Route::middleware(['auth', 'role:admin,coordinator,supervisor'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Account Approvals — self-service coordinator/supervisor sign-ups
        // waiting for the System Admin's go-ahead. Approving activates
        // the account; rejecting removes it so the person can re-apply.
        // Stays with the System Admin: monitors can't approve their own
        // accounts.
        Route::get('/interns', [InternController::class, 'index'])->name('interns.index');
        Route::get('/interns/create', [InternController::class, 'create'])->name('interns.create');
        Route::post('/interns', [InternController::class, 'store'])->name('interns.store');
        Route::get('/interns/{intern}', [InternController::class, 'show'])->name('interns.show');
        Route::get('/interns/{intern}/edit', [InternController::class, 'edit'])->name('interns.edit');
        Route::put('/interns/{intern}', [InternController::class, 'update'])->name('interns.update');
        Route::post('/interns/{intern}/start-new-set', [InternController::class, 'startNewSet'])->name('interns.start-new-set');
        Route::post('/interns/{intern}/reset-password', [InternController::class, 'resetPassword'])->name('interns.reset-password');
        Route::delete('/interns/{intern}', [InternController::class, 'destroy'])->name('interns.destroy');
        Route::post('/interns/{internId}/restore', [InternController::class, 'restore'])->name('interns.restore');
        Route::delete('/interns/{internId}/force-delete', [InternController::class, 'forceDelete'])->name('interns.force-delete');

        // Offices & staff — internal/external offices, coordinators, and
        // office supervisors (monitoring accounts). Coordinator + System
        // Admin only; supervisors don't manage other offices' accounts.
        Route::middleware('role:admin,coordinator')->group(function () {
        Route::get('/offices', [OfficeController::class, 'index'])->name('offices.index');
        Route::get('/offices/create', [OfficeController::class, 'create'])->name('offices.create');
        Route::post('/offices', [OfficeController::class, 'store'])->name('offices.store');
        Route::get('/offices/{office}/edit', [OfficeController::class, 'edit'])->name('offices.edit');
        Route::put('/offices/{office}', [OfficeController::class, 'update'])->name('offices.update');
        Route::delete('/offices/{office}', [OfficeController::class, 'destroy'])->name('offices.destroy');

        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
        Route::post('/staff/{staffId}/restore', [StaffController::class, 'restore'])->name('staff.restore');
        Route::delete('/staff/{staffId}/force-delete', [StaffController::class, 'forceDelete'])->name('staff.force-delete');
        });

        // Logbook — review QR-scanned entries, fix or fill in times by hand
        Route::get('/logs', [OjtLogController::class, 'index'])->name('logs.index');
        Route::get('/logs/export', [OjtLogController::class, 'export'])->name('logs.export');
        Route::post('/logs', [OjtLogController::class, 'store'])->name('logs.store');
        Route::get('/interns/{intern}/logs', [OjtLogController::class, 'show'])->name('logs.show');
        Route::get('/interns/{intern}/summary', [InternController::class, 'summary'])->name('interns.summary');
        Route::get('/interns/{intern}/timesheet', [InternController::class, 'timesheet'])->name('interns.timesheet');
        Route::get('/interns/{intern}/journals/export', [InternController::class, 'exportJournals'])->name('interns.journals.export');
        Route::put('/logs/{log}', [OjtLogController::class, 'update'])->name('logs.update');
        Route::delete('/logs/{log}', [OjtLogController::class, 'destroy'])->name('logs.destroy');

        // Activity log — System Admin only: the full trail of who changed
        // what, across accounts, duty records, requests, and templates.
        Route::middleware('role:admin')->group(function () {
            Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
            Route::get('/audit-log/entries', [AuditLogController::class, 'entries'])->name('audit-log.entries');
        });

        // Settings — standard working hours, working days, no-class calendar
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/no-class-days', [NoClassDayController::class, 'store'])->name('no-class-days.store');

        // Coordinator requests — placement proposals and completion
        // recommendations awaiting the admin's decision.
        Route::get('/requests', [AdminRequestController::class, 'index'])->name('requests.index');
        Route::post('/requests/placement/{placementRequest}/decide', [AdminRequestController::class, 'decidePlacement'])->name('requests.placement.decide');
        Route::post('/requests/completion/{completionRecommendation}/decide', [AdminRequestController::class, 'decideCompletion'])->name('requests.completion.decide');
        // Attendance-request cleanup — pending only; a decided request is
        // part of the record. System Admin only.
        Route::delete('/requests/attendance/{logRequest}', [AdminRequestController::class, 'destroyAttendance'])
            ->name('requests.attendance.destroy')
            ->middleware('role:admin');

        // Document templates — download the starter, upload an edited Word
        // design, or remove it (the form is then unavailable until a new
        // template is uploaded). Scoped per college (tabs on the manager);
        // Coordinator + System Admin only — supervisors don't manage them.
        Route::middleware('role:admin,coordinator')->group(function () {
            Route::get('/document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
            Route::get('/document-templates/{college}', [DocumentTemplateController::class, 'show'])->name('document-templates.college');
            Route::get('/document-templates/{college}/{type}/starter', [DocumentTemplateController::class, 'starter'])->name('document-templates.starter');
            Route::post('/document-templates/{college}/{type}', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
            Route::delete('/document-templates/{college}/{type}', [DocumentTemplateController::class, 'destroy'])->name('document-templates.destroy');
        });
    });

/*
|--------------------------------------------------------------------------
| Account Approvals — pending staff sign-ups. The System Admin sees
| everything; a College Dean may approve coordinators and supervisors of
| their college; a coordinator may approve supervisor sign-ups of their
| college (the controller enforces the per-row permissions). Lives outside
| the admin group because the dean role must reach it.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin,dean,coordinator', 'profile-completed'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/approvals', [AdminApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{user}/approve', [AdminApprovalController::class, 'approve'])->name('approvals.approve');
        Route::delete('/approvals/{user}/reject', [AdminApprovalController::class, 'reject'])->name('approvals.reject');
    });

/*
|--------------------------------------------------------------------------
| Kiosk & scan captures — the office front-desk station plus the webcam
| verification gallery. Lives outside the System Admin group so every
| reviewing role reaches it: the office scanner account (kiosk PC,
| station-only — nested role middleware does not widen the group's own
| restriction, the same reason the dean's approvals live out here),
| supervisors and coordinators for their scope, admins campus-wide.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'profile-completed'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // The station itself: the office scanner account runs it, and its
        // guardOffice resolves only that office's interns.
        Route::middleware('role:admin,supervisor,dean,office')->group(function () {
            Route::get('/kiosk', [KioskController::class, 'index'])->name('kiosk.index');
            Route::get('/kiosk/ping', [KioskController::class, 'ping'])->name('kiosk.ping');
            Route::post('/kiosk/scan', [KioskController::class, 'scan'])->name('kiosk.scan');
            Route::post('/kiosk/manual', [KioskController::class, 'manual'])->name('kiosk.manual');
        });

        Route::middleware('role:admin,supervisor,coordinator,dean')->group(function () {
            Route::get('/kiosk-captures', [KioskController::class, 'captures'])->name('kiosk-captures.index');
        });

        Route::middleware('role:admin')->group(function () {
            Route::delete('/kiosk-captures/{log}/{slot}', [KioskController::class, 'deleteCapture'])->name('kiosk-captures.delete');
        });
    });

/*
|--------------------------------------------------------------------------
| Daily Report access — preview (inline PDF, used in the history-page
| modal) or download an intern's submitted Daily Report. Admins may open
| any; coordinators and supervisors only for interns in their scope
| (enforced by OjtLogPolicy::viewDailyReport).
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin,coordinator,supervisor'])
    ->get('/reports/{log}', [ReportController::class, 'show'])
    ->name('reports.show');

/*
|--------------------------------------------------------------------------
| Notifications — every role's inbox. Review decisions, pending manual
| entries, and flagged entries land here.
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Android app push registration — the FCM token the device expects
    // notifications on. Same session auth as everything else.
    Route::post('/devices', [DeviceTokenController::class, 'store'])->name('devices.store');
    Route::delete('/devices', [DeviceTokenController::class, 'destroy'])->name('devices.destroy');
});

/*
|--------------------------------------------------------------------------
| Attendance request decision — supervisors and coordinators decide their
| interns' attendance requests; the admin is the fallback adjudicator
| (LogRequestPolicy::decide). Defined outside the monitor group because
| its group-level role middleware would otherwise lock the admin out.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin,coordinator,supervisor,dean', 'profile-completed'])
    ->post('/monitor/requests/log/{logRequest}/decide', [MonitorRequestController::class, 'decide'])
    ->name('monitor.requests.log.decide');

/*
|--------------------------------------------------------------------------
| Monitor routes — the two oversight roles. Coordinators see the interns
| assigned to them; supervisors see the interns placed at their office.
| Both review within their scope: supervisors approve or reject duty
| entries and record missed scans manually (as pending entries awaiting
| the admin's confirmation); coordinators flag entries for a second look.
| Both export their interns' logbooks and open their reports. QR scans
| and admin corrections remain the authoritative ways times change.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:coordinator,supervisor,dean', 'profile-completed'])
    ->prefix('monitor')
    ->name('monitor.')
    ->group(function () {
        Route::get('/', [MonitorController::class, 'index'])->name('dashboard');
        Route::get('/export', [MonitorController::class, 'export'])->name('export');

        // Supervisors record a scan an intern missed — saved as pending
        // for the admin to confirm (see LogReview::submitManualEntry).
        Route::middleware('role:supervisor')->group(function () {
            Route::get('/logs/create', [MonitorController::class, 'createLog'])->name('logs.create');
            Route::post('/logs', [MonitorController::class, 'storeLog'])->name('logs.store');
        });

        // Supervisors approve/reject; coordinators flag for a second look
        // (the action decides which policy ability is required).
        Route::post('/logs/{log}/review', [MonitorController::class, 'review'])->name('logs.review');

        Route::get('/interns/{intern}', [MonitorController::class, 'show'])->name('intern');
        Route::get('/interns/{intern}/timesheet', [MonitorController::class, 'timesheet'])->name('interns.timesheet');
        Route::get('/interns/{intern}/weekly-report', [MonitorController::class, 'weeklyReport'])->name('interns.weekly-report');

        // Supervisors certify an intern's duty hours for a timesheet period
        // (the formal per-month sign-off on the Word timesheet).
        Route::middleware('role:supervisor')->group(function () {
            Route::post('/interns/{intern}/certify', [MonitorController::class, 'certify'])->name('interns.certify');
        });

        // The request desk: interns' attendance requests awaiting a decision,
        // plus the coordinator's own placement/completion requests to the admin.
        Route::get('/requests', [MonitorRequestController::class, 'index'])->name('requests.index');

        Route::middleware('role:coordinator')->group(function () {
            Route::post('/requests/placement', [MonitorRequestController::class, 'storePlacement'])->name('requests.placement.store');
            Route::post('/requests/completion', [MonitorRequestController::class, 'storeCompletion'])->name('requests.completion.store');
        });
    });

/*
|--------------------------------------------------------------------------
| Intern routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:intern', 'profile-completed'])
    ->prefix('intern')
    ->name('intern.')
    ->group(function () {
        Route::get('/dashboard', [InternDashboardController::class, 'index'])->name('dashboard');

        // The intern's personal time-in/out QR — shown on their phone and as
        // a print-ready ID card. This is what the office desk scanner reads;
        // it carries the intern's own token, so it can't be used from home
        // the way a photo of the wall poster could.
        Route::get('/my-qr', [InternDashboardController::class, 'myQr'])->name('my-qr');

        // The intern's documentation archive — every duty photo they've
        // uploaded, by date.
        Route::get('/documentation', [InternDashboardController::class, 'documentation'])->name('documentation');

        // Intern's daily duty photo + notes — required once a clock-out
        // (AM OUT / PM OUT) has been recorded on the day's entry. The log
        // is targeted explicitly so past days missing their photo can be
        // completed too. Removing soft-deletes the journal (the photo stays
        // on disk) so it can be undone via the restore route; the day then
        // flips back to the "awaiting journal" state.
        Route::post('/dashboard/photo/{log}', [InternDashboardController::class, 'storePhoto'])->name('photo.store');
        Route::delete('/dashboard/photo/{log}', [InternDashboardController::class, 'destroyJournal'])->name('photo.destroy');
        Route::post('/dashboard/photo/{log}/restore', [InternDashboardController::class, 'restoreJournal'])->name('photo.restore');
        Route::delete('/dashboard/photo/{log}/force', [InternDashboardController::class, 'forceDestroyJournal'])->name('photo.force-destroy');

        // Print-ready Daily Report for a single duty day — the intern's
        // documentation (photo, notes, times) to print or save as PDF
        // from the browser.
        Route::get('/dashboard/report/{log}', [InternDashboardController::class, 'showReport'])->name('report.show');

        // The intern's Time Frame export — every duty day of their current OJT
        // set with hour totals, as the admin's Word template filled with their
        // data (downloadable .docx), behind the Export button on My Time Frame.
        Route::get('/dashboard/timesheet', [InternDashboardController::class, 'timesheet'])->name('timesheet');

        // The My Time Frame page — the same duty days and hour totals the
        // export fills in, readable on screen without downloading anything.
        Route::get('/time-frame', [InternDashboardController::class, 'timeFrame'])->name('time-frame');

        // The intern's Weekly Progress Report — daily journal entries (message
        // + photo) compiled into the school's weekly form, one page per week.
        Route::get('/dashboard/weekly-report', [InternDashboardController::class, 'weeklyReport'])->name('weekly-report');

        // The intern's personal information — the birth details, addresses,
        // contacts, and family background behind the Student Intern's Personal
        // Information requirement form. Filled in once here, then merged into
        // that form per-intern at download time.
        Route::get('/personal-information', [InternPersonalInformationController::class, 'edit'])->name('personal-information.edit');
        Route::put('/personal-information', [InternPersonalInformationController::class, 'update'])->name('personal-information.update');

        // OJT requirement documents — the school's required forms, each
        // downloadable with the intern's information merged into the admin's
        // uploaded design, or as the blank official copy when none is uploaded.
        Route::get('/requirements', [InternRequirementController::class, 'index'])->name('requirements.index');
        Route::get('/requirements/{type}', [InternRequirementController::class, 'download'])->name('requirements.download');

        // The My Journal page's Export Journal — the intern's daily journal
        // entries (message + photo) compiled into the school's Word Weekly
        // Progress Report, filled from the admin's uploaded template.
        Route::get('/journals/export', [InternDashboardController::class, 'exportJournals'])->name('journals.export');

        // The intern's attendance requests — corrections to recorded scan
        // times and absence reports, decided by their supervisor or
        // coordinator. A pending one can be withdrawn by its own intern.
        Route::get('/requests', [InternRequestController::class, 'index'])->name('requests.index');
        Route::post('/requests', [InternRequestController::class, 'store'])->name('requests.store');
        Route::delete('/requests/{logRequest}', [InternRequestController::class, 'destroy'])
            ->name('requests.destroy')
            ->middleware('can:delete,logRequest');
    });
