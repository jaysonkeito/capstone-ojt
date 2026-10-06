<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OjtLog;
use App\Models\User;
use App\Support\AttendanceRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class KioskController extends Controller
{
    /**
     * The office kiosk — a full-screen scanning station left open on the
     * desk computer wired to the QR scanner box. An intern presents their
     * personal QR (phone or printed card), the scanner types the code in,
     * and scan() records the next time of their day. Because the scanner is
     * physically at the office, a photographed code can't be used from home.
     */
    public function index()
    {
        return view('admin.kiosk');
    }

    /**
     * A tiny keep-alive the station pings on a timer while it sits idle. It
     * carries no data and answers 204 — its only job is to slide the admin
     * session forward (and quietly re-authenticate through the remember-me
     * cookie) so a long gap between scans never signs the station out mid-day.
     */
    public function ping(): Response
    {
        return response()->noContent();
    }

    /**
     * Record a scan from the kiosk. The scanner sends the decoded QR string;
     * we resolve the intern behind it, run the same intern / active-set
     * checks the phone flow uses, and advance their day through the shared
     * AttendanceRecorder. Answers JSON so the station can flash a result and
     * be ready for the next person without a page reload.
     */
    public function scan(Request $request): JsonResponse
    {
        // Read the scanned code directly instead of throwing a validation
        // exception on a bad one. This app only auto-renders JSON errors for
        // api/* routes (bootstrap/app.php), but the kiosk always expects a JSON
        // reply — a thrown ValidationException would redirect and break the
        // station. An empty or oversized code simply isn't recognized.
        $code = $request->input('code');

        if (! is_string($code) || trim($code) === '' || strlen($code) > 255) {
            return response()->json([
                'state' => 'unknown_code',
                'message' => "That QR code isn't recognized. Open My QR Code on your phone, or use your printed OJT ID card.",
            ]);
        }

        $intern = User::fromScanPayload($code);

        if (! $intern) {
            return response()->json([
                'state' => 'unknown_code',
                'message' => "That QR code isn't recognized. Open My QR Code on your phone, or use your printed OJT ID card.",
            ]);
        }

        if ($blocked = $this->guardOffice($request, $intern)) {
            return $blocked;
        }

        return response()->json($this->recordFor($request, $intern));
    }

    /**
     * Record a time by hand when the intern doesn't have their QR on them.
     * The desk operator (already signed in on the kiosk) types the intern's
     * Student ID; we resolve them and run the exact same checks and slot
     * advance as a scan, so the outcome is identical either way. The result
     * card still shows the intern's name and photo so the operator can eyeball
     * that the right person is standing there.
     */
    public function manual(Request $request): JsonResponse
    {
        $studentId = $request->input('student_id');

        if (! is_string($studentId) || trim($studentId) === '' || strlen($studentId) > 255) {
            return response()->json([
                'state' => 'unknown_code',
                'message' => "Enter the intern's Student ID to record a time.",
            ]);
        }

        $studentId = trim($studentId);

        $intern = User::where('role', 'intern')
            ->where('student_id', $studentId)
            ->first();

        if (! $intern) {
            return response()->json([
                'state' => 'unknown_code',
                'message' => 'No intern found with Student ID "'.$studentId.'".',
            ]);
        }

        if ($blocked = $this->guardOffice($request, $intern)) {
            return $blocked;
        }

        return response()->json($this->recordFor($request, $intern));
    }

    /**
     * The kiosk lives in one office: a supervisor's station resolves only
     * the interns placed at their office. Admins (the campus-wide station)
     * resolve anyone; non-staff roles never reach this controller.
     */
    protected function guardOffice(Request $request, User $intern): ?JsonResponse
    {
        $staff = $request->user();

        if (! $staff->isAdmin() && $intern->office_id !== $staff->office_id
            && ! $staff->supervisesOffice($intern->office_id)) {
            return response()->json([
                'state' => 'not_assigned',
                'message' => 'This intern is not assigned to this office.',
            ]);
        }

        return null;
    }

    /**
     * Run the shared intern / active-set checks and advance the intern's day
     * through AttendanceRecorder, returning the JSON payload the station
     * renders. Used by both a QR scan and a manual Student-ID entry so the
     * two behave identically once the intern is resolved. When a time is
     * actually recorded, the kiosk's webcam frame captured at the moment of
     * the scan is stored and linked to the day's log for later verification.
     *
     * @return array<string, mixed>
     */
    private function recordFor(Request $request, User $intern): array
    {
        if (! $intern->isIntern()) {
            return [
                'state' => 'not_intern',
                'message' => 'That code isn\'t an intern\'s time-in code.',
            ];
        }

        if (! $intern->is_active) {
            return [
                'state' => 'inactive',
                'message' => $intern->full_name.'\'s account is deactivated — see the admin.',
                'intern' => $this->internPayload($intern),
            ];
        }

        $enrollment = $intern->currentEnrollment;

        if (! $enrollment || $enrollment->status === 'completed') {
            return [
                'state' => 'no_set',
                'message' => $intern->full_name.' has no active OJT set yet.',
                'intern' => $this->internPayload($intern),
            ];
        }

        $outcome = AttendanceRecorder::record($intern, $enrollment, now());
        $slot = $outcome['slot'];

        // A time went on the books — file the face capture that came with
        // the scan (null when the station has no working camera).
        $captureUrl = null;
        if (in_array($outcome['state'], ['recorded', 'done'], true)) {
            $captureUrl = $this->storeCapture($request, $outcome['log'], $slot);
        }

        // A return during the lunch window is recorded as PM Time In even
        // though the afternoon hasn't formally started — spell that out on
        // the result card so the label doesn't look like a mistake. The
        // note (and its window) is shared with the intern dashboard banner.
        $note = $slot === 'pm_time_in' ? AttendanceRecorder::lunchWindowNote(now()) : null;

        $message = match ($outcome['state']) {
            'too_soon' => $intern->full_name.' scanned less than '
                .AttendanceRecorder::COOLDOWN_MINUTES.' minutes ago — try again at '
                .$this->nextAllowedAt($outcome['log'], now())->format('g:i A').'.',
            default => null,
        };

        return [
            'state' => $outcome['state'],
            'message' => $message,
            'note' => $note,
            'action' => $slot ? AttendanceRecorder::labelFor($slot) : null,
            'recordedAt' => $slot ? now()->format('g:i A') : null,
            'captureUrl' => $captureUrl,
            'intern' => $this->internPayload($intern),
            'log' => $this->logPayload($outcome['log']),
            'progress' => [
                'accumulated' => round($intern->accumulated_hours, 2),
                'target' => $intern->target_hours,
                'percent' => $intern->completion_percentage,
            ],
        ];
    }

    /**
     * The scanned intern's identity for the result card.
     *
     * @return array{name: string, studentId: ?string, initials: string, avatarUrl: ?string}
     */
    private function internPayload(User $intern): array
    {
        return [
            'name' => $intern->full_name,
            'studentId' => $intern->student_id,
            'initials' => $intern->initials,
            'avatarUrl' => $intern->avatar_url,
        ];
    }

    /**
     * Store the webcam frame the station grabbed at the moment of the scan
     * and link it to the day's log under the slot it belongs to. Deliberately
     * defensive instead of a ValidationException — the kiosk always expects a
     * JSON reply, and a blocked or broken camera must never fail a scan that
     * would otherwise be valid.
     */
    private function storeCapture(Request $request, OjtLog $log, ?string $slot): ?string
    {
        if (! $slot || ! $request->hasFile('capture')) {
            return null;
        }

        $file = $request->file('capture');

        // Sniff the bytes, not the client's claimed type — the capture is
        // only ever a canvas JPEG, and anything else is ignored.
        $mimeType = $file->getRealPath()
            ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath())
            : '';

        if (! $file->isValid()
            || ! str_starts_with($mimeType, 'image/')
            || ! in_array(strtolower($file->getClientOriginalExtension() ?: ''), ['jpg', 'jpeg', 'png', 'webp'], true)
            || (int) $file->getSize() > 4 * 1024 * 1024) {
            return null;
        }

        try {
            $path = $file->storeAs(
                'kiosk-captures/'.$log->user_id.'/'.$log->date->toDateString(),
                $slot.'-'.now()->format('His').'.jpg',
                'public'
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $captures = $log->kiosk_captures ?? [];
        $captures[$slot] = $path;
        $log->forceFill(['kiosk_captures' => $captures])->save();

        return Storage::disk('public')->url($path);
    }

    /**
     * Today's four slot times (12-hour) plus any stepped-out gap pair per
     * session, and the running day total, for the grid on the result card.
     *
     * @return array{amIn: ?string, amOut: ?string, amIn2: ?string, amOut2: ?string, pmIn: ?string, pmOut: ?string, pmIn2: ?string, pmOut2: ?string, hoursToday: float}
     */
    private function logPayload(OjtLog $log): array
    {
        return [
            'amIn' => $this->clock($log->am_time_in),
            'amOut' => $this->clock($log->am_time_out),
            'amIn2' => $this->clock($log->am_time_in_2),
            'amOut2' => $this->clock($log->am_time_out_2),
            'pmIn' => $this->clock($log->pm_time_in),
            'pmOut' => $this->clock($log->pm_time_out),
            'pmIn2' => $this->clock($log->pm_time_in_2),
            'pmOut2' => $this->clock($log->pm_time_out_2),
            'hoursToday' => (float) $log->hours_rendered,
        ];
    }

    /**
     * The earliest a cooldown-blocked scan may be retried: the day's last
     * recorded punch plus the cooldown. Rendered on the result card so the
     * intern knows exactly when to scan again.
     */
    private function nextAllowedAt(OjtLog $log, Carbon $now): Carbon
    {
        return $now->copy()
            ->setTimeFromTimeString(AttendanceRecorder::latestFilledTime($log))
            ->addMinutes(AttendanceRecorder::COOLDOWN_MINUTES);
    }

    /**
     * A stored 'H:i(:s)' time as a friendly 'g:i A', or null when empty.
     */
    private function clock(?string $time): ?string
    {
        return $time ? Carbon::parse($time)->format('g:i A') : null;
    }
}
