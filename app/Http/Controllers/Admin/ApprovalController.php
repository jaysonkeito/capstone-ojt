<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

/**
 * Account Approvals — the System Admin's review desk for self-service
 * coordinator and supervisor sign-ups. A pending account is inactive (it
 * can't log in) with no approved_at stamp; approving it activates the
 * account, rejecting it removes the request so the person can re-apply.
 */
class ApprovalController extends Controller
{
    /**
     * List everyone waiting for approval, newest first.
     */
    public function index()
    {
        $pending = User::pendingApproval()
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.approvals.index', ['pending' => $pending]);
    }

    /**
     * Approve a sign-up: the account becomes active and can log in, and
     * moves into the admin's Staff section for normal management.
     */
    public function approve(User $user)
    {
        $this->authorizePending($user);

        $user->update([
            'is_active' => true,
            'approved_at' => now(),
        ]);

        return redirect()->route('admin.approvals.index')
            ->with('status', "{$user->role_label} account approved — {$user->full_name} can now sign in.");
    }

    /**
     * Reject a sign-up. The account never became active and holds no data,
     * so it is removed for good — the email and full name become available
     * again if the applicant wants to re-apply.
     */
    public function reject(User $user)
    {
        $this->authorizePending($user);

        $name = $user->full_name;
        $role = $user->role_label;

        $user->forceDelete();

        return redirect()->route('admin.approvals.index')
            ->with('status', "{$role} request from {$name} rejected and removed.");
    }

    /**
     * These actions only ever apply to accounts actually waiting in the
     * approvals queue — never to an approved, admin-managed staff member.
     */
    protected function authorizePending(User $user): void
    {
        abort_unless($user->isPendingApproval(), 404);
    }
}
