<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The System Admin's activity trail: every create/update/delete on the
 * tracked records plus sign-ins, with the acting user on each line. The page
 * polls for new entries every few seconds so the admin watches changes land
 * in near-real time.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $actions = ['created', 'updated', 'deleted', 'restored', 'logged-in', 'logged-out'];

        $logs = $this->fetchEntries($request, 300);

        $users = AuditLog::query()
            ->select('user_id', 'user_name')
            ->whereNotNull('user_id')
            ->distinct()
            ->orderBy('user_name')
            ->get();

        return view('admin.audit-log.index', [
            'logs' => $logs,
            'users' => $users,
            'actions' => $actions,
            'filters' => [
                'user_id' => (int) $request->query('user_id'),
                'action' => (string) $request->query('action'),
            ],
        ]);
    }

    /**
     * The rows newer than ?after= for the live polling — rendered as the
     * same row markup so the page can prepend them directly.
     */
    public function entries(Request $request): View
    {
        $logs = $this->fetchEntries($request, 50, (int) $request->query('after', 0));

        return view('admin.audit-log.rows', ['logs' => $logs]);
    }

    private function fetchEntries(Request $request, int $limit, int $after = 0)
    {
        return AuditLog::query()
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($after, fn ($q) => $q->where('id', '>', $after))
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
