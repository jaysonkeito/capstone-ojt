<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\LogRequest;
use App\Support\RequestDecider;
use Illuminate\Http\Request;

/**
 * The intern's attendance requests — the corrections and absence reports
 * they've raised about their own duty record, and the form to raise new
 * ones. Decisions come back as notifications; nothing on the record
 * changes until a supervisor or coordinator approves.
 */
class RequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = LogRequest::where('intern_id', $request->user()->id)
            ->with('decidedBy')
            ->latest()
            ->take(30)
            ->get();

        return view('intern.requests', ['requests' => $requests]);
    }

    /**
     * File a correction or an absence report. Validation (existing log
     * for corrections, no log for absences, no duplicate pending request)
     * lives in StoreAttendanceRequest; up to two proof photos travel
     * alongside the reason.
     */
    public function store(StoreAttendanceRequest $request, RequestDecider $decider)
    {
        $validated = $request->validated();

        $proofPaths = [];
        foreach ($request->file('proofs', []) as $proof) {
            $proofPaths[] = $proof->storeAs(
                "request-proofs/{$request->user()->id}",
                now()->format('Ymd-His').'-'.\Illuminate\Support\Str::random(6).'.'.$proof->getClientOriginalExtension(),
                'public',
            );
        }

        if ($proofPaths !== []) {
            $validated['proof_paths'] = $proofPaths;
        }

        $logRequest = $decider->submitAttendanceRequest($request->user(), $validated);

        return redirect()->route('intern.requests.index')
            ->with('status', "Your {$logRequest->type_label} for {$logRequest->date->format('M d, Y')} was submitted — your supervisor and coordinator have been notified.");
    }

    /**
     * Withdraw a request that hasn't been decided yet. It's the intern's own
     * filing and only while it still waits on a decision — the can:delete
     * policy middleware refuses decided or foreign requests outright.
     */
    public function destroy(Request $request, LogRequest $logRequest)
    {
        $label = "{$logRequest->type_label} for {$logRequest->date->format('M d, Y')}";

        $logRequest->delete();

        return redirect()->route('intern.requests.index')
            ->with('status', "Your {$label} was withdrawn — file a new one anytime.");
    }
}
