<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompletionRecommendation;
use App\Models\LogRequest;
use App\Models\PlacementRequest;
use App\Support\RequestDecider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * The System Admin's request queues — what the coordinators have raised:
 * placement proposals (approval assigns the office) and completion
 * recommendations (approval closes the intern's OJT set through
 * OjtEnrollmentService). Rejections carry a reason the coordinator sees.
 */
class RequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');

        $placementRequests = PlacementRequest::with(['intern', 'coordinator', 'office', 'decidedBy'])
            ->status($status)
            ->latest()
            ->paginate(15, ['*'], 'placement_page')
            ->withQueryString();

        $completionRecommendations = CompletionRecommendation::with(['intern', 'coordinator', 'decidedBy'])
            ->status($status)
            ->latest()
            ->paginate(15, ['*'], 'completion_page')
            ->withQueryString();

        $logRequests = LogRequest::with(['intern', 'decidedBy'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15, ['*'], 'attendance_page')
            ->withQueryString();

        $pendingCount = PlacementRequest::pending()->count()
            + CompletionRecommendation::pending()->count()
            + LogRequest::pending()->count();

        return view('admin.requests', [
            'placementRequests' => $placementRequests,
            'completionRecommendations' => $completionRecommendations,
            'logRequests' => $logRequests,
            'status' => in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : null,
            'pendingCount' => $pendingCount,
        ]);
    }

    /**
     * Decide a placement proposal — approval assigns the proposed office
     * to the intern.
     */
    public function decidePlacement(Request $request, PlacementRequest $placementRequest, RequestDecider $decider)
    {
        [$decision, $comment] = $this->validateDecision($request);

        $decider->decidePlacement($placementRequest, $request->user(), $decision, $comment);

        $message = $decision === 'approved'
            ? "Placement approved — {$placementRequest->intern->full_name} is now assigned to {$placementRequest->office->name}."
            : "Placement request rejected — {$placementRequest->coordinator->full_name} has been notified.";

        return redirect()->route('admin.requests.index')->with('status', $message);
    }

    /**
     * Decide a completion recommendation — approval closes the intern's
     * current OJT set.
     */
    public function decideCompletion(Request $request, CompletionRecommendation $completionRecommendation, RequestDecider $decider)
    {
        [$decision, $comment] = $this->validateDecision($request);

        $decider->decideCompletion($completionRecommendation, $request->user(), $decision, $comment);

        $message = $decision === 'approved'
            ? "Completion approved — {$completionRecommendation->intern->full_name}'s OJT set is now completed."
            : "Completion recommendation rejected — {$completionRecommendation->coordinator->full_name} has been notified.";

        return redirect()->route('admin.requests.index')->with('status', $message);
    }

    /**
     * Shared decision validation for the admin queues: approve or reject,
     * with a reason required for a rejection.
     *
     * @return array{string, ?string}
     */
    private function validateDecision(Request $request): array
    {
        $validated = Validator::make($request->all(), [
            'action' => ['required', 'in:approved,rejected'],
            'comment' => [
                'required_if:action,rejected',
                'nullable',
                'string',
                'max:2000',
            ],
        ], [
            'comment.required_if' => 'A reason is required — the coordinator will see why their request was rejected.',
        ])->validate();

        return [$validated['action'], $validated['comment'] ?? null];
    }
}
