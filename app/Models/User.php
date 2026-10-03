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

    /**
     * Coordinator or supervisor — the two read-only monitoring roles.
     */
    public function isMonitor(): bool
    {
        return $this->isCoordinator() || $this->isSupervisor();
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
        return $query->whereIn('role', ['coordinator', 'supervisor'])
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
     * Full name with middle initial — "Firstname M. Lastname" — used in
     * documents where a middle initial is preferred over the full middle
     * name. Falls back to display_name when no middle name is on file.
     */
    public function getDisplayNameWithMiddleInitialAttribute(): string
    {
        $middleName = $this->staffProfile?->middle_name;

        if (! $middleName) {
            return $this->display_name;
        }

        $middleInitial = strtoupper(substr(trim($middleName), 0, 1));

        if (! $this->last_name) {
            return "{$this->first_name} {$middleInitial}";
        }

        if (! $this->first_name) {
            return "{$middleInitial} {$this->last_name}";
        }

        return "{$this->first_name} {$middleInitial}. {$this->last_name}";
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

        if ($staff->isCoordinator()) {
            return $query->where('coordinator_id', $staff->id);
        }

        if ($staff->isSupervisor()) {
            return $query->where('office_id', $staff->office_id);
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
}
