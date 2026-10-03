<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin manager for the intern forms' Word templates. For each form type the
 * admin can download the starter .docx, upload an edited version (which the
 * system then mail-merges for every intern), or remove it — after which interns
 * can't generate that form until a new template is uploaded.
 */
class DocumentTemplateController extends Controller
{
    public function __construct(private DocumentTemplateService $service) {}

    /**
     * List the form types with their current template state, split into the
     * log-driven reports and the school's requirement forms.
     */
    public function index()
    {
        $templates = collect(DocumentTemplate::TYPES)
            ->map(fn (array $meta, string $type): array => [
                'type' => $type,
                'label' => $meta['label'],
                'category' => $meta['category'],
                'active' => $this->service->active($type),
            ])
            ->groupBy('category');

        return view('admin.document-templates.index', [
            'reports' => $templates->get(DocumentTemplate::CATEGORY_REPORT, collect())->values(),
            'requirements' => $templates->get(DocumentTemplate::CATEGORY_REQUIREMENT, collect())->values(),
        ]);
    }

    /**
     * Download the shipped starter template for a form type — the blank the
     * admin edits in Word before uploading.
     */
    public function starter(string $type): BinaryFileResponse
    {
        $this->assertKnownType($type);

        $downloadName = DocumentTemplate::TYPES[$type]['label'].' Template.docx';

        return response()->download($this->service->starterPath($type), $downloadName);
    }

    /**
     * Store an admin-edited .docx as the active template for a form type.
     */
    public function store(Request $request, string $type): RedirectResponse
    {
        $this->assertKnownType($type);

        $request->validate([
            'template' => [
                'required',
                'file',
                'max:10240',
                'extensions:docx',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip,application/octet-stream',
            ],
        ], [
            'template.extensions' => 'The template must be a Word .docx file.',
            'template.mimetypes' => 'The template must be a Word .docx file.',
        ]);

        $this->service->store($type, $request->file('template'), $request->user());

        return back()->with('status', DocumentTemplate::TYPES[$type]['label'].' template uploaded. Interns now generate this form from your Word design.');
    }

    /**
     * Remove the active template for a form type, leaving it unavailable to
     * interns until a new one is uploaded.
     */
    public function destroy(string $type): RedirectResponse
    {
        $this->assertKnownType($type);

        $this->service->delete($type);

        return back()->with('status', DocumentTemplate::TYPES[$type]['label'].' template removed. Interns cannot generate this form until you upload a new template.');
    }

    /**
     * Guard the {type} route segment against unknown form types.
     */
    private function assertKnownType(string $type): void
    {
        abort_unless(array_key_exists($type, DocumentTemplate::TYPES), 404);
    }
}
