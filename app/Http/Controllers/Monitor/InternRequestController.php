<?php

namespace App\Http\Controllers\Monitor;

use App\Http\Controllers\Controller;
use App\Models\InternRequest;
use App\Models\User;
use App\Notifications\InternRequestDecided;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The decision side of intern requests: the intern's coordinator (or the
 * named consultation recipient) approves or rejects a transfer or
 * consultation with remarks. An approved office transfer moves the
 * intern's placement on the spot.
 */
class InternRequestController extends Controller
{
    public function decide(Request $request, InternRequest $internRequest)
    {
        $staff = $request->user();
        $intern = $internRequest->intern;

        $mayDecide = $staff->isAdmin()
            || $internRequest->recipient_id === $staff->id
            || $staff->monitors($intern);

        abort_unless($mayDecide, 403, 'Only the intern\'s coordinator (or the named recipient) can decide this request.');
        abort_unless($internRequest->status === 'pending', 422, 'This request was already decided.');

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'remarks' => ['required', 'string', 'max:1000'],
        ], [
            'remarks.required' => 'Remarks are required either way — the intern reads them.',
        ]);

        DB::transaction(function () use ($internRequest, $validated, $staff, $intern) {
            $internRequest->update([
                'status' => $validated['decision'],
                'decision_remarks' => $validated['remarks'],
                'decided_by' => $staff->id,
                'decided_at' => now(),
            ]);

            // An approved transfer moves the placement immediately — the
            // same placement change a manual edit would make, audited the
            // same way (the User observer logs the intern update).
            if ($internRequest->type === 'office_transfer' && $validated['decision'] === 'approved') {
                $intern->update(['office_id' => $internRequest->office_id]);
            }
        });

        $intern->notify(new InternRequestDecided(
            $internRequest->fresh(),
            $validated['decision'],
            $staff->full_name,
            $validated['remarks'],
        ));

        $label = $internRequest->typeLabel();

        return back()->with('status', "{$label} request {$validated['decision']} — {$intern->first_name} has been notified.");
    }
}
