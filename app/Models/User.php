<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * OJT track => fixed target hours.
     */
    public const TRACK_HOURS = [
        'internship' => 500,
    ];

    /**
     * Prefix embedded in an intern's personal time-in/out QR. Lets the office
     * kiosk tell a real attendance scan apart from stray keyboard input or the
     * wall poster's URL, both of which it simply ignores.
     */
    public const SCAN_QR_PREFIX = 'OJTID:';

    protected $fillable = [
        'role',
        'college_code',
        'ojt_track',
        'ojt_status',
        'batch',
        'department',
        'year_level',
        'student_id',
        'first_name',
        'last_name',
        'title',
        'position',
        'username',
        'email',
        'password',
        'password_changed_at',
        'avatar_path',
        'office_id',
        'coordinator_id',
        'target_hours',
        'is_active',
        'approved_at',
        'profile_completed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'scan_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'target_hours' => 'integer',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
            'profile_completed_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function ojtLogs()
    {
        return $this->hasMany(OjtLog::class);
    }

    /**
     * The requirement documents this intern has submitted for review.
     */
    public function submittedDocuments()
    {
        return $this->hasMany(SubmittedDocument::class);
    }

    /**
     * The requests this intern has filed with their coordinator
     * (office transfers, consultations).
     */
    public function coordinatorRequests()
    {
        return $this->hasMany(InternRequest::class, 'intern_id');
    }

    /**
     * The office this user belongs to — where an intern is placed, or the
     * office a supervisor represents.
     */
    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * The OJT coordinator this intern reports to (interns only).
     */
    public function coordinator()
    {
        return $this->belongsTo(self::class, 'coordinator_id');
    }

    /**
     * The interns assigned to this coordinator (coordinators only).
     */
    public function coordinatedInterns()
    {
        return $this->hasMany(self::class, 'coordinator_id')->where('role', 'intern');
    }

    /**
     * Every OJT set this intern has gone through, most recent first.
     */
    public function enrollments()
    {
        return $this->hasMany(OjtEnrollment::class)->orderByDesc('started_at')->orderByDesc('id');
    }

    /**
     * The intern's current (or most recent) OJT set — the one their
     * `ojt_track` / `target_hours` / `ojt_status` mirror columns describe
     * right now. Hours, progress, and the daily logbook all read through
     * this, so a new set always starts its hour count at zero regardless
     * of earlier sets.
     */
    public function currentEnrollment()
    {
        return $this->hasOne(OjtEnrollment::class)->latestOfMany();
    }

    /**
     * The intern's self-entered personal details (birth details, addresses,
     * contacts, family background) behind the Student Intern's Personal
     * Information requirement form. Interns only; at most one row.
     */
    public function personalInfo(): HasOne
    {
        return $this->hasOne(InternPersonalInfo::class);
    }

    /**
     * The coordinator/supervisor's instructor details (employee record,
     * employment dates, resume) behind their profile completion form.
     * Staff only; at most one row.
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Role helpers
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isIntern(): bool
    {
        return $this->role === 'intern';
    }

    public function isCoordinator(): bool
    {
        return $this->role === 'coordinator';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isDean(): bool
    {
        return $this->role === 'dean';
    }

    /**
     * Program Chair of a college — a college can have one per program
     * (CAS has two). They monitor their college's interns (via those
     * interns' coordinators) without supervising any office.
     */
    public function isChair(): bool
    {
        return $this->role === 'chair';
    }

    /**
     * Scanner-only account for the office's kiosk PC — it can run the
     * station and nothing else.
     */
    public function isOffice(): bool
    {
        return $this->role === 'office';
    }

    /**
     * The college this staff member belongs to: the account's own code when
     * recorded (roster import, registration, admin provisioning), else the
     * staff profile's, else the installation default.
     */
    public function collegeCode(): ?string
    {
        return $this->college_code
            ?? $this->staffProfile?->college_code;
    }

    /**
     * The college code with the installation default — for scoping template
     * folders and other places that need a definite college. Supervisors of
     * external offices may have none.
     */
    public function collegeCodeOr(string $default = 'cas'): string
    {
        return $this->collegeCode() ?? $default;
    }

    /**
     * The college a staff member belongs to as a model (for dropdowns).
     */
    public function college()
    {
        return $this->belongsTo(College::class, 'college_code', 'code');
    }

    /**
     * Whether this staff member may approve/reject a pending sign-up:
     * the System Admin everything; a dean coordinator sign-ups plus
     * supervisor sign-ups of their college; a coordinator supervisor
     * sign-ups of their college.
     */
    public function mayApprove(User $pending): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! in_array($pending->role, ['coordinator', 'supervisor'], true)) {
            return false;
        }

        if (! in_array($this->role, ['dean', 'coordinator'], true)) {
            return false;
        }

        // A supervisor sign-up without a college (external office) has no
        // dean or coordinator queue — only the System Admin decides it.
        if ($this->collegeCode() === null || $pending->collegeCode() === null) {
            return false;
        }

        if ($this->collegeCode() !== $pending->collegeCode()) {
            return false;
        }

        // Deans may approve both queues; coordinators only supervisors.
        return $this->isDean() || $pending->role === 'supervisor';
    }

    /**
     * Coordinator or supervisor — the read-only monitoring roles. A dean
     * whose office accepts interns is that office's supervisor and monitors
     * interns the same way.
     */
    public function isMonitor(): bool
    {
        return $this->isCoordinator() || $this->isSupervisor() || $this->isDean() || $this->isChair();
    }

    /**
     * Whether this staff member supervises a specific office: a supervisor
     * with that office assigned, or a dean whose office accepts interns
     * (dean's offices commonly host OJT interns, making the dean their
     * de-facto supervisor). Deans without an office supervise nothing.
     */
    public function supervisesOffice(?int $officeId): bool
    {
        if ($officeId === null || $this->isIntern()) {
            return false;
        }

        // Supervisors, deans who run their office's desk, and coordinators
        // who take over an office's supervision — the office_id on the
        // account is the supervisor link in all three cases.
        if (! in_array($this->role, ['supervisor', 'dean', 'coordinator'], true)) {
            return false;
        }

        return $this->office_id !== null && $this->office_id === $officeId;
    }

    /**
     * Whether this monitor-role account may see and act on an intern: a
     * coordinator's assigned interns, a supervisor's office placements —
     * and a coordinator who also supervises an office (coordinator-
     * supervisor) gets both sets. The single gate behind the monitoring
     * dashboards and every act-on-an-intern policy.
     */
    public function monitors(User $intern): bool
    {
        if (! $intern->isIntern()) {
            return false;
        }

        if ($this->isCoordinator() && $intern->coordinator_id === $this->id) {
            return true;
        }

        // A Program Chair oversees their college's interns — those whose
        // coordinators belong to the chair's college.
        if ($this->isChair() && $intern->coordinator
            && $intern->coordinator->collegeCode() === $this->collegeCode()) {
            return true;
        }

        return $this->supervisesOffice($intern->office_id);
    }

    /**
     * A self-service staff sign-up still waiting for the System Admin's
     * approval: a coordinator/supervisor who registered themselves but was
     * never activated nor stamped approved. Admin-provisioned staff and
     * interns never have this state.
     */
    public function isPendingApproval(): bool
    {
        return $this->isMonitor() && ! $this->is_active && $this->approved_at === null;
    }

    /**
     * Whether this account must complete its profile before reaching a
     * dashboard: any non-admin whose profile has never been completed.
     * Self-service sign-ups start in this state; admin-provisioned accounts
     * and everyone who existed before the gate are stamped completed.
     */
    public function needsProfileCompletion(): bool
    {
        return ! $this->isAdmin() && $this->profile_completed_at === null;
    }

    /**
     * Accounts waiting in the admin Approvals section.
     */
    public function scopePendingApproval($query)
    {
        return $query->whereIn('role', ['coordinator', 'supervisor', 'dean'])
            ->whereNull('approved_at')
            ->where('is_active', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Personal scan code — the intern's own QR for the office desk scanner
    |--------------------------------------------------------------------------
    */

    /**
     * The intern's personal token — the secret encoded in their time-in/out
     * QR. Generated on first read and saved, so existing interns get one the
     * moment they open their code (no backfill migration). Kept out of
     * $fillable and listed in $hidden so it can't be mass-assigned or leak
     * through JSON.
     */
    public function scanToken(): string
    {
        if (! $this->scan_token) {
            $this->forceFill(['scan_token' => Str::random(40)])->save();
        }

        return $this->scan_token;
    }

    /**
     * The full string the intern's QR encodes — the prefix plus their token.
     * The kiosk decodes this and only acts on strings carrying the prefix.
     */
    public function scanQrPayload(): string
    {
        return self::SCAN_QR_PREFIX.$this->scanToken();
    }

    /**
     * Resolve the intern behind a scanned payload. Accepts the prefixed QR
     * string or a bare token, tolerates the trailing newline a scanner appends,
     * and returns null when the code is empty or matches no one.
     */
    public static function fromScanPayload(?string $payload): ?self
    {
        $payload = trim((string) $payload);

        if (str_starts_with($payload, self::SCAN_QR_PREFIX)) {
            $payload = substr($payload, strlen(self::SCAN_QR_PREFIX));
        }

        $token = trim($payload);

        if ($token === '') {
            return null;
        }

        return static::query()->where('scan_token', $token)->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors — hours & progress
    |--------------------------------------------------------------------------
    */

    /**
     * Display format: "Last Name, First Name Middle Initial" — e.g.
     * "Lamat, Lady Pearl T". Interns imported from the Registrar roster
     * already have the middle initial as the trailing token in
     * `first_name` (that's how the source CSV stored it), so this just
     * needs to flip the order — no schema change required.
     */
    /**
     * Public URL for the user's profile picture, or null if none uploaded.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /**
     * Two-letter initials for the avatar fallback ("Last, First").
     */
    public function getInitialsAttribute(): string
    {
        return strtoupper(
            substr((string) $this->first_name, 0, 1).
            substr((string) $this->last_name, 0, 1)
        ) ?: 'U';
    }

    public function getFullNameAttribute(): string
    {
        if (! $this->last_name) {
            return trim((string) $this->first_name);
        }

        if (! $this->first_name) {
            return trim((string) $this->last_name);
        }

        return "{$this->last_name}, {$this->first_name}";
    }

    /**
     * Display-first format: "Firstname Lastname" — used in the sidebar
     * and other places where a natural reading order is preferred over
     * the formal "Lastname, Firstname" sorting format.
     */
    public function getDisplayNameAttribute(): string
    {
        if (! $this->last_name) {
            return trim((string) $this->first_name);
        }

        if (! $this->first_name) {
            return trim((string) $this->last_name);
        }

        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * The middle name from whichever record carries it — staff profiles for
     * employees, the Personal Information sheet for interns.
     */
    public function getMiddleNameAttribute(): ?string
    {
        return $this->staffProfile?->middle_name ?: $this->personalInfo?->middle_name;
    }

    /**
     * The middle name reduced to its initial with a trailing period ("P."),
     * or null when no middle name is on file.
     */
    public function getMiddleInitialAttribute(): ?string
    {
        if (! $this->middle_name) {
            return null;
        }

        return strtoupper(substr(trim($this->middle_name), 0, 1)).'.';
    }

    /**
     * Full name with middle initial — "Firstname M. Lastname" — used in
     * documents where a middle initial is preferred over the full middle
     * name. Falls back to display_name when no middle name is on file.
     */
    public function getDisplayNameWithMiddleInitialAttribute(): string
    {
        $middleInitial = $this->middle_initial;

        if (! $middleInitial) {
            return $this->display_name;
        }

        if (! $this->last_name) {
            return "{$this->first_name} {$middleInitial}";
        }

        if (! $this->first_name) {
            return "{$middleInitial} {$this->last_name}";
        }

        return "{$this->first_name} {$middleInitial} {$this->last_name}";
    }

    /**
     * Formal sorting format with the middle initial — "Lastname, Firstname M."
     * — used on profile pages and rosters.
     */
    public function getFullNameWithMiddleInitialAttribute(): string
    {
        $middleInitial = $this->middle_initial;

        if (! $middleInitial) {
            return $this->full_name;
        }

        if (! $this->last_name || ! $this->first_name) {
            return $this->full_name;
        }

        return "{$this->last_name}, {$this->first_name} {$middleInitial}";
    }

    public function getOjtTrackLabelAttribute(): string
    {
        if ($label = $this->currentEnrollment?->label) {
            return $label;
        }

        return match ($this->ojt_track) {
            'internship' => 'Internship OJT (500h)',
            'custom' => 'Custom OJT',
            default => ucfirst((string) $this->ojt_track),
        };
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'System Admin',
            'intern' => 'Intern',
            'coordinator' => 'OJT Coordinator',
            'supervisor' => 'Supervisor',
            'dean' => 'College Dean',
            'office' => 'Office Scanner',
            'chair' => 'Program Chair',
            default => ucfirst($this->role),
        };
    }

    public function getOjtStatusLabelAttribute(): string
    {
        return match ($this->ojt_status) {
            'pending' => 'Pending',
            'active' => 'Active',
            'completed' => 'Completed',
            default => ucfirst((string) $this->ojt_status),
        };
    }

    /**
     * Total accumulated OJT hours — scoped to the intern's CURRENT set
     * only. Older, completed sets keep their own hour count in their own
     * `OjtEnrollment::accumulated_hours`; they never bleed into this one.
     */
    public function getAccumulatedHoursAttribute(): float
    {
        $enrollmentId = $this->currentEnrollment?->id;

        if (! $enrollmentId) {
            // Pre-enrollment-system fallback — shouldn't happen after the
            // backfill migration, but keeps this safe either way.
            return (float) $this->ojtLogs()->sum('hours_rendered');
        }

        return (float) $this->ojtLogs()->where('ojt_enrollment_id', $enrollmentId)->sum('hours_rendered');
    }

    /**
     * Remaining hours needed to hit the target. Never negative.
     */
    public function getHoursRemainingAttribute(): float
    {
        $remaining = $this->target_hours - $this->accumulated_hours;

        return $remaining > 0 ? round($remaining, 2) : 0.0;
    }

    /**
     * Completion percentage, capped at 100.
     */
    public function getCompletionPercentageAttribute(): float
    {
        if ($this->target_hours <= 0) {
            return 0.0;
        }

        $percentage = ($this->accumulated_hours / $this->target_hours) * 100;

        return (float) min(round($percentage, 1), 100);
    }

    public function getIsCompleteAttribute(): bool
    {
        return $this->accumulated_hours >= $this->target_hours;
    }

    /**
     * Staff-wide scoping: the interns this account may see and manage —
     * every intern for admins, those assigned to a coordinator, or those
     * placed at a supervisor's office. Non-staff roles match nothing.
     */
    public function scopeForStaff($query, self $staff)
    {
        if ($staff->isAdmin()) {
            return $query;
        }

        if ($staff->isCoordinator() || ($staff->isDean() && $staff->office_id)) {
            // A dean supervising their office's interns coordinates them.
            return $query->where('coordinator_id', $staff->id);
        }

        if ($staff->isSupervisor() || $staff->isDean()) {
            return $query->where('office_id', $staff->office_id);
        }

        if ($staff->isChair()) {
            // The college's interns, reached through the coordinators that
            // belong to the chair's college (college code on the account or
            // its staff profile).
            $college = $staff->collegeCode();

            return $college === null
                ? $query->whereRaw('0 = 1')
                : $query->whereHas('coordinator', function ($q) use ($college) {
                    $q->where('college_code', $college)
                        ->orWhereHas('staffProfile', fn ($sq) => $sq->where('college_code', $college));
                });
        }

        return $query->whereRaw('0 = 1');
    }

    /**
     * The interns visible to this account, as a query.
     */
    public function visibleInterns()
    {
        return self::query()->where('role', 'intern')->forStaff($this);
    }

    /**
     * Whether this account may view and manage the given intern.
     */
    public function mayAccess(User $intern): bool
    {
        return $this->isAdmin() || $this->visibleInterns()->whereKey($intern->id)->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes — cross-cutting intern filters (search, status, track,
    | department, batch)
    |--------------------------------------------------------------------------
    */

    /**
     * Real-time search by name, email, or student ID.
     */
    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('student_id', 'like', "%{$term}%");
        });
    }

    public function scopeOjtStatus($query, ?string $status)
    {
        return $status ? $query->where('ojt_status', $status) : $query;
    }

    public function scopeTrack($query, ?string $track)
    {
        return $track ? $query->where('ojt_track', $track) : $query;
    }

    public function scopeDepartment($query, ?string $department)
    {
        return $department ? $query->where('department', $department) : $query;
    }

    /**
     * Roman numeral display for the year level — used in timesheets.
     */
    public function getYearLevelRomanAttribute(): ?string
    {
        return match ($this->year_level) {
            1 => 'I',
            2 => 'II',
            3 => 'III',
            4 => 'IV',
            default => $this->year_level ? (string) $this->year_level : null,
        };
    }

    /**
     * Full degree label — e.g. "Bachelor of Science in Information Technology".
     */
    public function getCourseNameAttribute(): string
    {
        return match ($this->department) {
            'BSINT', 'BSIT' => 'Bachelor of Science in Information Technology',
            'BSCS' => 'Bachelor of Science in Computer Science',
            default => $this->department ?? '—',
        };
    }

    public function scopeBatch($query, ?string $batch)
    {
        return $batch ? $query->where('batch', $batch) : $query;
    }

    /**
     * The phones this user has signed into the Android app from. Every push
     * notification fans out to all of them (a user may hold two devices).
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Firebase channel route — the registration tokens to deliver this
     * notification to. An empty list simply means nothing to push to.
     *
     * @return list<string>
     */
    public function routeNotificationForFcm(Notification $notification): array
    {
        return $this->deviceTokens()->pluck('token')->all();
    }

    /**
     * The line the System Admin's activity log shows for this account.
     */
    public function auditLabel(): string
    {
        return "({$this->role_label}) {$this->full_name}";
    }
}
