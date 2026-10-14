<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    /**
     * The monitoring accounts — coordinators, supervisors, deans, and
     * Program Chairs, including multi-role ones (matched by their primary
     * role OR any extra one). Pending self-service sign-ups wait under
     * Account Approvals first; only after they are approved do they join
     * this managed list.
     */
    public function index(Request $request)
    {
        $roleFilter = $request->get('role');

        $staff = User::where(function ($q) {
                // Every account that holds ANY staff role, primary or extra.
                $q->whereIn('role', User::STAFF_ROLES)
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('role', User::STAFF_ROLES));
            })
            // Pending self-service sign-ups live in Approvals, not here.
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNotNull('approved_at');
            })
            ->with(['office', 'roles'])
            ->withCount('coordinatedInterns')
            ->when($roleFilter, function ($q) use ($roleFilter) {
                $q->where(function ($qq) use ($roleFilter) {
                    $qq->where('role', $roleFilter)
                        ->orWhereHas('roles', fn ($r) => $r->where('role', $roleFilter));
                });
            })
            ->orderBy('last_name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.staff.index', [
            'colleges' => College::orderBy('name')->get(),
            'staff' => $staff,
            'offices' => Office::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.staff.form', [
            'colleges' => College::orderBy('name')->get(),
            'staff' => new User(['role' => 'coordinator']),
            'offices' => Office::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedStaffData($request);

        $user = DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'role' => $validated['primary_role'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'title' => $validated['title'] ?? null,
                'position' => $validated['position'] ?? null,
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_changed_at' => now(),
                'office_id' => $validated['office_id'] ?? null,
                'student_id' => null,
                'target_hours' => 0,
                // Admin-provisioned staff skip the self-service approval gate and
                // the first-login profile completion wall — the admin already
                // filled their record.
                'is_active' => $request->boolean('is_active', true),
                'approved_at' => now(),
                'profile_completed_at' => now(),
            ]);

            $this->syncRoles($user, $validated['roles'], $validated['primary_role']);

            // Save middle name + college to the staff profile
            $user->staffProfile()->create([
                'middle_name' => $validated['middle_name'] ?? null,
                'college_code' => $validated['college_code'] ?? null,
            ]);

            return $user;
        });

        $displayName = trim("{$validated['first_name']} {$validated['last_name']}");

        return redirect()->route('admin.staff.index')->with('status', "{$user->role_label} account created for {$displayName}.");
    }

    public function edit(User $staff)
    {
        abort_unless($this->isManagedStaff($staff), 404);

        return view('admin.staff.form', [
            'staff' => $staff,
            'offices' => Office::orderBy('name')->get(),
            'colleges' => College::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $staff)
    {
        abort_unless($this->isManagedStaff($staff), 404);

        $validated = $this->validatedStaffData($request, $staff->id);

        DB::transaction(function () use ($validated, $request, $staff) {
            $staff->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'title' => $validated['title'] ?? null,
                'position' => $validated['position'] ?? null,
                'email' => $validated['email'],
                'office_id' => $validated['office_id'] ?? null,
                'is_active' => $request->boolean('is_active', false),
                'approved_at' => now(),
            ]);

            $this->syncRoles($staff, $validated['roles'], $validated['primary_role']);

            // Update middle name + college in the staff profile
            $profile = $staff->staffProfile ?? $staff->staffProfile()->create([]);
            $profile->middle_name = $validated['middle_name'] ?? null;
            $profile->college_code = $validated['college_code'] ?? null;
            $profile->save();
        });

        return redirect()->route('admin.staff.index')->with('status', "{$staff->refresh()->role_label} {$staff->full_name} updated.");
    }

    /**
     * Shared validation for create and update. Roles arrive as a checkbox
     * set (roles[]) plus the priority role (primary_role) — the primary
     * must be among the checked roles, and the office/college requirements
     * follow the selected roles.
     */
    private function validatedStaffData(Request $request, ?int $uniqueIgnore = null): array
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(User::STAFF_ROLES)],
            'primary_role' => ['required', Rule::in(User::STAFF_ROLES)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($uniqueIgnore)],
            'password' => $uniqueIgnore === null ? ['required', 'string', 'min:8'] : ['sometimes'],
            // A supervisor represents exactly one office — the desk station
            // and the intern list resolve to it. A coordinator, dean, or
            // Program Chair may also hold an office they supervise, or stay
            // college-wide with none.
            'office_id' => ['nullable', 'exists:offices,id'],
            // Coordinators, deans, and Program Chairs belong to a college; a
            // supervisor's college is optional — external offices may host
            // interns from any college, so their sign-ups fall to the System
            // Admin.
            'college_code' => ['nullable', 'string', 'max:20', 'exists:colleges,code'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $roles = array_values($validated['roles']);

        if (! in_array($validated['primary_role'], $roles, true)) {
            throw ValidationException::withMessages([
                'primary_role' => 'The priority role must be one of the selected roles.',
            ]);
        }

        // required_if across an array field needs the manual check — a
        // supervisor always needs an office; college roles always need a
        // college.
        if (in_array('supervisor', $roles, true) && empty($validated['office_id'])) {
            throw ValidationException::withMessages([
                'office_id' => 'An office is required for supervisors.',
            ]);
        }

        if (array_intersect(['coordinator', 'dean', 'chair'], $roles) && empty($validated['college_code'])) {
            throw ValidationException::withMessages([
                'college_code' => 'A college is required for coordinators, deans, and Program Chairs.',
            ]);
        }

        $validated['roles'] = $roles;

        return $validated;
    }

    /**
     * Write the full role set: users.role carries the priority role, the
     * user_roles pivot mirrors every held role (primary included) so the
     * account's capabilities are one query away.
     */
    private function syncRoles(User $staff, array $roles, string $primary): void
    {
        $staff->forceFill(['role' => $primary])->save();

        $staff->roles()->delete();
        $staff->roles()->createMany(
            array_map(fn (string $role) => ['role' => $role], $roles)
        );

        $staff->refresh();
    }

    /**
     * Soft-delete a staff account: hidden everywhere and can't log in,
     * but their assignments survive (a supervisor's office, a
     * coordinator's interns) so restoring brings everything back.
     */
    public function destroy(User $staff)
    {
        abort_unless($this->isManagedStaff($staff), 404);

        $staff->update(['is_active' => false]); // logs out any live session
        $staff->delete();

        return redirect()->route('admin.staff.index')->with('status', "{$staff->role_label} {$staff->full_name} deleted — restore anytime from the Deleted section below.");
    }

    public function restore($staffId)
    {
        $staff = User::onlyTrashed()->where(function ($q) {
            $q->whereIn('role', User::STAFF_ROLES)
                ->orWhereHas('roles', fn ($r) => $r->whereIn('role', User::STAFF_ROLES));
        })->findOrFail($staffId);

        $staff->restore();
        $staff->update(['is_active' => true]);

        return redirect()->route('admin.staff.index', ['role' => $staff->role])->with('status', "{$staff->role_label} {$staff->full_name} restored and reactivated.");
    }

    /**
     * Permanently remove an archived (soft-deleted) staff account — no undo.
     * Only accounts already sitting in the Deleted section qualify, mirroring
     * restore()'s trashed-only lookup. Interns they coordinated stay enrolled
     * (their coordinator/office links null out), but this record, its login,
     * and its profile details are gone for good.
     */
    public function forceDelete($staffId)
    {
        $staff = User::onlyTrashed()->where(function ($q) {
            $q->whereIn('role', User::STAFF_ROLES)
                ->orWhereHas('roles', fn ($r) => $r->whereIn('role', User::STAFF_ROLES));
        })->findOrFail($staffId);

        $label = $staff->role_label;
        $name = $staff->full_name;

        $staff->forceDelete();

        return redirect()->route('admin.staff.index')->with('status', "{$label} {$name} permanently deleted.");
    }

    /**
     * A staff account the admin manages here: any account holding a staff
     * role (primary or extra) that is either active or admin-provisioned.
     * Pending self-service sign-ups are handled exclusively from the
     * Approvals section.
     */
    protected function isManagedStaff(User $staff): bool
    {
        return (in_array($staff->role, User::STAFF_ROLES, true) || $staff->roles->isNotEmpty())
            && ($staff->is_active || $staff->approved_at !== null);
    }
}
