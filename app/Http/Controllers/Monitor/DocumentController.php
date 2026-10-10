<?php

namespace App\Http\Controllers\Monitor;

use App\Http\Controllers\Controller;
use App\Models\SubmittedDocument;
use App\Models\User;
use App\Notifications\DocumentReviewed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The review side of requirement submissions: coordinators (and the
 * System Admin) open the file, then approve it — which clears the
 * requirement — or reject it with remarks that tell the intern what to
 * fix. Anyone who may monitor the intern may read the file; only the
 * intern's coordinator and the admin decide.
 */
class DocumentController extends Controller
{
    public function download(Request $request, SubmittedDocument $document)
    {
        $user = $request->user();

        // The intern who submitted it, or anyone who may monitor them.
        abort_unless($document->user_id === $user->id || $user->monitors($document->intern), 403);

        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download($document->file_path, $document->original_name);
    }

    public function review(Request $request, SubmittedDocument $document)
    {
        $staff = $request->user();
        $intern = $document->intern;

        $mayDecide = $staff->isAdmin()
            || (in_array($staff->role, ['coordinator', 'chair'], true) && $staff->monitors($intern));

        abort_unless($mayDecide, 403, 'Only the intern\'s coordinator or the System Admin can review documents.');

        abort_if($document->status !== 'pending', 422, 'This document was already reviewed.');

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'remarks' => ['required', 'string', 'max:1000'],
        ], [
            'remarks.required' => 'Remarks are required either way — the intern reads them.',
        ]);

        $document->update([
            'status' => $validated['decision'],
            'remarks' => $validated['remarks'],
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
        ]);

        $intern->notify(new DocumentReviewed(
            $document,
            $validated['decision'],
            $staff->full_name,
            $validated['remarks'],
        ));

        $label = $document->typeLabel();

        return $validated['decision'] === 'approved'
            ? redirect()->back()->with('status', "{$label} approved — the requirement is cleared for {$intern->first_name}.")
            : redirect()->back()->with('status', "{$label} rejected — {$intern->first_name} has been notified with your remarks.");
    }
}
