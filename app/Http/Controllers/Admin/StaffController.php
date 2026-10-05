<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    /**
     * OJT Coordinators and office Supervisors — the two monitoring
     * accounts, provisioned here by the admin. Self-service staff sign-ups
     * wait under Account Approvals first; only after they are approved do
     * they become part of this managed list.
     */
    public function index(Request $request)
    {
        $staff = User::whereIn('role', ['coordinator', 'supervisor'])
            // Pending self-service sign-ups live in Approvals, not here.
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNotNull('approved_at');
            })
            ->with('office')
            ->withCount('coordinatedInterns')
            ->when($request->get('role'), fn ($q, $role) => $q->where('role', $role))
            ->orderBy('last_name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.staff.index', [
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
        $validated = $request->validate([
            'role' => ['required', Rule::in(['coordinator', 'supervisor'])],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            // A supervisor represents exactly one office; coordinators
            // don't belong to one.
            'office_id' => ['required_if:role,supervisor', 'nullable', 'exists:offices,id'],
            // Coordinators and deans belong to a college; a supervisor's
            // college is optional — external offices may host interns from
            // any college, so their sign-ups fall to the System Admin.
            'college_code' => [
                'nullable', 'string', 'max:20', 'exists:colleges,code',
                'required_if:role,coordinator',
                'required_if:role,dean',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'role' => $validated['role'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'title' => $validated['title'] ?? null,
            'position' => $validated['position'] ?? null,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'password_changed_at' => now(),
            'office_id' => $validated['role'] === 'supervisor' ? ($validated['office_id'] ?? null) : null,
            'student_id' => null,
            'target_hours' => 0,
            // Admin-provisioned staff skip the self-service approval gate and
            // the first-login profile completion wall — the admin already
            // filled their record.
            'is_active' => $request->boolean('is_active', true),
            'approved_at' => now(),
            'profile_completed_at' => now(),
        ]);

        // Save middle name + college to the staff profile
        $user->staffProfile()->create([
            'middle_name' => $validated['middle_name'] ?? null,
            'college_code' => $validated['college_code'],
        ]);

        $label = $validated['role'] === 'supervisor' ? 'Supervisor' : 'OJT Coordinator';

        return redirect()->route('admin.staff.index')->with('status', "{$label} account created for {$validated['first_name']} {$validated['last_name']}.");
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

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff->id)],
            'office_id' => ['required_if:role,supervisor', 'nullable', 'exists:offices,id'],
            // Role isn't editable here — the account's current role decides
            // whether the college is required.
            'college_code' => [
                'nullable', 'string', 'max:20', 'exists:colleges,code',
                $staff->isCoordinator() || $staff->isDean() ? 'required' : 'nullable',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $staff->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'title' => $validated['title'] ?? null,
            'position' => $validated['position'] ?? null,
            'email' => $validated['email'],
            'office_id' => $staff->role === 'supervisor' ? ($validated['office_id'] ?? null) : null,
            'is_active' => $request->boolean('is_active', false),
            'approved_at' => now(),
        ]);

        // Update middle name + college in the staff profile
        $profile = $staff->staffProfile ?? $staff->staffProfile()->create([]);
        $profile->middle_name = $validated['middle_name'] ?? null;
        $profile->college_code = $validated['college_code'];
        $profile->save();

        return redirect()->route('admin.staff.index')->with('status', "{$staff->role_label} {$staff->full_name} updated.");
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
        $staff = User::onlyTrashed()->whereIn('role', ['coordinator', 'supervisor'])->findOrFail($staffId);

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
        $staff = User::onlyTrashed()->whereIn('role', ['coordinator', 'supervisor'])->findOrFail($staffId);

        $label = $staff->role_label;
        $name = $staff->full_name;

        $staff->forceDelete();

        return redirect()->route('admin.staff.index')->with('status', "{$label} {$name} permanently deleted.");
    }

    /**
     * A staff account the admin manages here: a coordinator/supervisor who is
     * either active or admin-provisioned. Pending self-service sign-ups are
     * handled exclusively from the Approvals section.
     */
    protected function isManagedStaff(User $staff): bool
    {
        return in_array($staff->role, ['coordinator', 'supervisor'])
            && ($staff->is_active || $staff->approved_at !== null);
    }
}
