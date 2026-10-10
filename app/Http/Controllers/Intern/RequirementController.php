<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Services\RequirementDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The intern's OJT requirement documents — the school's required forms, each
 * downloadable with the intern's information already merged in (when the admin
 * has uploaded a fillable design) or as the blank official copy otherwise.
 */
class RequirementController extends Controller
{
    public function __construct(private RequirementDocumentService $service) {}

    /**
     * List every requirement form with whether it comes pre-filled, plus
     * the intern's submissions of each one — the return half: download,
     * complete, submit back, and follow the coordinator's review.
     */
    public function index(Request $request)
    {
        $requirements = collect(DocumentTemplate::requirementTypes())
            ->map(fn (array $meta, string $type): array => [
                'type' => $type,
                'label' => $meta['label'],
                // Filled either by an admin-uploaded design or the form's own
                // macroized starter (the Internship Application Letter is
                // always generated per intern).
                'autofilled' => $this->service->isFilledForIntern($type),
            ])
            ->values();

        // Every submission this intern has made, grouped by requirement type;
        // the newest one per type is the deciding status.
        $submissions = \App\Models\SubmittedDocument::where('user_id', $request->user()->id)
            ->with('reviewer:id,first_name,last_name')
            ->latest()
            ->get()
            ->groupBy('type');

        return view('intern.requirements', [
            'intern' => $request->user(),
            'requirements' => $requirements,
            'submissions' => $submissions,
        ]);
    }

    /**
     * Download one requirement form, filled with the intern's data.
     */
    public function download(Request $request, string $type): Response
    {
        abort_unless(DocumentTemplate::isRequirementType($type), 404);

        $document = $this->service->render($request->user(), $type);

        return $this->streamDocument($document, true);
    }
}
