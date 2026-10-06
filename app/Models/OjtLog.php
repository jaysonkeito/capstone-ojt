<?php

namespace App\Models;

use App\Support\AttendanceRecorder;
use App\Support\HoursCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class OjtLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ojt_enrollment_id',
        'date',
        'am_time_in',
        'am_time_out',
        'am_time_in_2',
        'am_time_out_2',
        'pm_time_in',
        'pm_time_out',
        'pm_time_in_2',
        'pm_time_out_2',
        'regular_hours',
        'overtime_hours',
        'hours_rendered',
        'notes',
        'photo_path',
        'kiosk_captures',
        'journal_removed_at',
        'logged_by',
        'status',
        'reviewed_by',
        'review_comment',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'regular_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'hours_rendered' => 'decimal:2',
            'kiosk_captures' => 'array',
            'journal_removed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Auto-compute regular/overtime/total hours from the AM/PM times
        // every time a log is saved — no one ever has to type OT by hand.
        // The standard AM/PM window comes from the campus-wide settings
        // (see OjtSetting::current()).
        static::saving(function (OjtLog $log) {
            $result = HoursCalculator::compute(
                $log->am_time_in,
                $log->am_time_out,
                $log->am_time_in_2,
                $log->am_time_out_2,
                $log->pm_time_in,
                $log->pm_time_out,
                $log->pm_time_in_2,
                $log->pm_time_out_2,
            );

            $log->regular_hours = $result['regular_hours'];
            $log->overtime_hours = $result['overtime_hours'];
            $log->hours_rendered = $result['total_hours'];
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ojtEnrollment()
    {
        return $this->belongsTo(OjtEnrollment::class);
    }

    /**
     * Whoever recorded the entry — the admin (manual entry/correction) or
     * the intern themselves (QR scan).
     */
    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    /**
     * The coordinator/supervisor who last reviewed this entry (approved,
     * rejected, or flagged it), or null when never reviewed.
     */
    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Filter by review status (pending/approved/rejected).
     */
    public function scopeStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    /**
     * Entries awaiting a review decision — pending manual entries from
     * supervisors, or entries a coordinator flagged for a second look.
     */
    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Review status badge for the logbook tables — same shape as
     * `attendance_status` so views can render both the same way.
     *
     * @return array{label: string, class: string}
     */
    public function getReviewStatusAttribute(): array
    {
        return match ($this->status) {
            'pending' => ['label' => 'Pending review', 'class' => 'bg-amber-50 text-amber-700'],
            'rejected' => ['label' => 'Rejected', 'class' => 'bg-red-50 text-red-700'],
            default => ['label' => 'Approved', 'class' => 'bg-emerald-50 text-emerald-700'],
        };
    }

    /**
     * Staff-wide scoping: duty logs of the interns this account may see —
     * every intern's logs for admins, or only the logs of a coordinator's
     * assigned interns / a supervisor's office placements.
     */
    public function scopeForStaff($query, User $staff)
    {
        return $query->whereIn(
            'user_id',
            User::query()->where('role', 'intern')->forStaff($staff)->select('users.id'),
        );
    }

    /**
     * Filter by intern name (first/last) or Student ID.
     */
    public function scopeStudentSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->whereHas('user', function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('student_id', 'like', "%{$term}%");
        });
    }

    /**
     * Filter to entries dated within [from, to] (either bound optional).
     */
    public function scopeDateBetween($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        return $query;
    }

    public function getHasOvertimeAttribute(): bool
    {
        return (float) $this->overtime_hours > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors — duty photo
    |--------------------------------------------------------------------------
    */

    /**
     * Public URL for the intern's duty photo, or null if none uploaded.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * Whether the intern has clocked out (AM or PM) on this entry — the
     * point at which the proof photo becomes required.
     */
    public function getClockedOutAttribute(): bool
    {
        return (bool) ($this->am_time_out || $this->pm_time_out);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors — kiosk verification captures
    |--------------------------------------------------------------------------
    */

    /**
     * Public URL of the webcam snapshot the kiosk took at a given slot
     * ('am_time_in', 'pm_time_out', …), or null when that scan wasn't
     * captured (camera off or scan predates the feature).
     */
    public function kioskCaptureUrl(string $slot): ?string
    {
        $path = $this->kiosk_captures[$slot] ?? null;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Whether the day currently has a live journal — a proof photo plus
     * notes that haven't been soft-removed (see `journal_removed_at`).
     * Everything that shows or embeds the journal keys off this one check,
     * so a removed journal stays invisible until restored or replaced.
     */
    public function getHasJournalAttribute(): bool
    {
        return (bool) $this->photo_path && ! $this->journal_removed_at;
    }

    /**
     * Whether the journal has been soft-removed — hidden from the dashboard
     * and documentation, but the photo is kept on disk so the removal can be
     * undone (`journal_removed_at` is set, photo_path/notes untouched).
     */
    public function getJournalRemovedAttribute(): bool
    {
        return (bool) $this->journal_removed_at;
    }

    /**
     * The entry is clocked out but still missing a live journal (either no
     * photo yet, or the journal was soft-removed) — whether today or a past
     * day the intern can still complete from their dashboard. Only future
     * entries (which can't exist yet) are excluded by their date.
     */
    public function getPhotoRequiredAttribute(): bool
    {
        return $this->clocked_out && ! $this->has_journal && ! $this->date?->isFuture();
    }

    /**
     * The most recent time recorded on this entry, by slot order (AM In → AM
     * Out → AM In (2) → AM Out (2) → PM In → PM Out → PM In (2) → PM Out (2)):
     * the label, whether it was a time in or out, the 12-hour clock time, and
     * the absolute moment it happened (`at`, this day at the slot time) — or
     * null when the day has no times yet. Drives the intern dashboard's
     * "successfully timed in/out" confirmation banner, which only shows while
     * `at` is recent.
     *
     * @return array{slot: string, label: string, direction: string, time: string, at: Carbon}|null
     */
    public function getLatestPunchAttribute(): ?array
    {
        $latest = null;

        // Iterate in day order and keep the last filled one — that is the most
        // recent slot even on an afternoon-only day (where the AM slots stay
        // empty and PM In / PM Out carry the times). Slots alternate in/out,
        // so parity decides the direction ("_in" suffix matching would miss
        // the "(2)" gap slots).
        foreach (AttendanceRecorder::slotOrder() as $index => $slot) {
            if ($this->{$slot}) {
                $latest = [
                    'slot' => $slot,
                    'label' => AttendanceRecorder::labelFor($slot),
                    'direction' => $index % 2 === 0 ? 'in' : 'out',
                    'time' => Carbon::parse($this->{$slot})->format('g:i A'),
                    'at' => $this->date->copy()->setTimeFromTimeString($this->{$slot}),
                ];
            }
        }

        return $latest;
    }

    /**
     * Attendance status badge for the Logbook table — Late (past the grace
     * period), Overtime, Ongoing (clocked in but not out yet), or On Time.
     */
    public function getAttendanceStatusAttribute(): array
    {
        $settings = OjtSetting::current();
        $standardAmIn = $settings->am_time_in;

        if ($this->am_time_in) {
            $standardIn = Carbon::parse($standardAmIn);
            $graceEnd = $standardIn->copy()->addMinutes($settings->grace_period_minutes);
            $actualIn = Carbon::parse($this->am_time_in);

            if ($actualIn->gt($graceEnd)) {
                return ['label' => 'Late', 'class' => 'bg-red-50 text-red-700'];
            }
        }

        if ($this->has_overtime) {
            return ['label' => 'Overtime', 'class' => 'bg-indigo-50 text-indigo-700'];
        }

        // A recorded step back in (In (2)) with no matching Out (2) yet means
        // the intern came back mid-session and is on site right now.
        if (($this->am_time_in_2 && ! $this->am_time_out_2)
            || ($this->pm_time_in_2 && ! $this->pm_time_out_2)) {
            return ['label' => 'Ongoing', 'class' => 'bg-brand-50 text-brand-700'];
        }

        if ($this->pm_time_in && ! $this->pm_time_out) {
            return ['label' => 'Ongoing', 'class' => 'bg-brand-50 text-brand-700'];
        }

        return ['label' => 'On Time', 'class' => 'bg-emerald-50 text-emerald-700'];
    }

    /**
     * The line the System Admin's activity log shows for this duty entry.
     */
    public function auditLabel(): string
    {
        return 'Duty log — '.($this->date?->toDateString() ?? $this->date).' ('.($this->user?->full_name ?? 'intern #'.$this->user_id).')';
    }
}
