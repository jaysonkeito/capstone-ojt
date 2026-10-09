<?php

namespace App\Http\Controllers\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecideAttendanceRequest;
use App\Http\Requests\StoreCompletionRequest;
use App\Http\Requests\StorePlacementRequest;
use App\Models\LogRequest;
use App\Models\PlacementRequest;
use App\Models\User;
use App\Support\CoordinatorRequests;
use App\Support\RequestDecider;
use Illuminate\Http\Request;

/**
 * The coordinator/supervisor Requests page — the decision desk for the
 * interns' attendance requests within their scope, and the status view
 * of what they've raised to the System Admin (placement proposals and
 * completion recommendations).
 */
class RequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Attendance requests awaiting a decision, scoped exactly like the
        // dashboards: a coordinator's own interns plus their office's when
        // they supervise one, a supervisor's office.
        $pendingLogRequests = LogRequest::query()
            ->pending()
            ->with(['intern', 'ojtLog'])
            ->whereHas('intern', fn ($q) => $user->isCoordinator()
                ? $q->where(function ($qq) use ($user) {
                    $qq->where('coordinator_id', $user->id);

                    if ($user->office_id !== null) {
                        $qq->orWhere('office_id', $user->office_id);
                    }
                })
                : $q->where('office_id', $user->office_id))
            ->orderBy('created_at')
            ->get();

        $placementRequests = PlacementRequest::where('coordinator_id', $user->id)
            ->with(['intern', 'office', 'decidedBy'])
            ->latest()
            ->take(20)
            ->get();

        $completionRecommendations = \App\Models\CompletionRecommendation::where('coordinator_id', $user->id)
            ->with(['intern', 'decidedBy'])
            ->latest()
            ->take(20)
            ->get();

        // Coordinators raise placement/completion requests; supervisors only
        // decide attendance requests.
        $myInterns = $user->isCoordinator()
            ? User::where('role', 'intern')->where('coordinator_id', $user->id)->where('is_active', true)->orderBy('last_name')->get()
            : collect();

        return view('monitor.requests', [
            'pendingLogRequests' => $pendingLogRequests,
            'placementRequests' => $placementRequests,
            'completionRecommendations' => $completionRecommendations,
            'myInterns' => $myInterns,
            'offices' => $user->isCoordinator() ? \App\Models\Office::orderBy('name')->get() : collect(),
        ]);
    }

    /**
     * Decide an intern's attendance request — approve (a correction is
     * applied to the entry) or reject (with a reason the intern sees).
     */
    public function decide(DecideAttendanceRequest $request, LogRequest $logRequest, RequestDecider $decider)
    {
        $decision = $request->validated('action');

        $decider->decideLogRequest($logRequest, $request->user(), $decision, $request->validated('comment'));

        $message = $decision === 'approved'
            ? "Approved {$logRequest->intern->full_name}'s {$logRequest->type_label} for {$logRequest->date->format('M d, Y')}"
                .($logRequest->type === LogRequest::TYPE_CORRECTION ? ' — the entry has been updated.' : '.')
            : "Rejected {$logRequest->intern->full_name}'s {$logRequest->type_label} for {$logRequest->date->format('M d, Y')} — the intern has been notified.";

        // Admins decide from their own tools; monitors land back on the
        // request desk.
        if ($request->user()->isAdmin()) {
            return redirect()->route('admin.logs.show', ['intern' => $logRequest->intern_id])->with('status', $message);
        }

        return redirect()->route('monitor.requests.index')->with('status', $message);
    }

    /**
     * Raise a placement proposal for one of the coordinator's interns.
     */
    public function storePlacement(StorePlacementRequest $request, CoordinatorRequests $requests)
    {
        $validated = $request->validated();
        $intern = User::findOrFail($validated['intern_id']);
        $office = \App\Models\Office::findOrFail($validated['office_id']);

        $requests->requestPlacement($request->user(), $intern, $office->id, $validated['note'] ?? null);

        return redirect()->route('monitor.requests.index')
            ->with('status', "Placement request submitted — {$intern->full_name} at {$office->name} is awaiting the System Admin's approval.");
    }

    /**
     * Raise a completion recommendation for one of the coordinator's
     * interns (who has reached the target hours).
     */
    public function storeCompletion(StoreCompletionRequest $request, CoordinatorRequests $requests)
    {
        $validated = $request->validated();
        $intern = User::findOrFail($validated['intern_id']);

        $requests->recommendCompletion($request->user(), $intern, $validated['note'] ?? null);

        return redirect()->route('monitor.requests.index')
            ->with('status', "Completion recommendation submitted for {$intern->full_name} — awaiting the System Admin's approval.");
    }
}
