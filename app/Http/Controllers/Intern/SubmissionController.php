<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\SubmittedDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The intern's side of requirement submissions: the Requirements page's
 * downloads now have a "submit back" half — the signed/completed form is
 * uploaded here and lands on their coordinator's review queue.
 */
class SubmissionController extends Controller
{
    public function store(Request $request, string $type)
    {
        abort_unless(DocumentTemplate::isRequirementType($type), 404);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ], [
            'file.max' => 'The file must be 10 MB or smaller.',
            'file.mimes' => 'Upload the document as PDF, DOC/DOCX, or an image (JPG/PNG).',
        ]);

        $intern = $request->user();
        $file = $validated['file'];

        $path = $file->storeAs(
            "submissions/{$intern->id}",
            now()->format('Ymd-His').'-'.Str::random(6).'.'.$file->getClientOriginalExtension(),
            'public',
        );

        $document = SubmittedDocument::create([
            'user_id' => $intern->id,
            'type' => $type,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        $coordinator = $intern->coordinator;
        if ($coordinator) {
            $coordinator->notify(new \App\Notifications\DocumentSubmitted($document, $intern->full_name));
        }

        return redirect()->route('intern.requirements.index')
            ->with('status', $document->typeLabel().' submitted — your coordinator has been notified and will review it.');
    }
}
