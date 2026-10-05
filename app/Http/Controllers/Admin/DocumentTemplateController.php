<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\College;
use App\Models\DocumentTemplate;
use App\Models\OjtSetting;
use App\Models\User;
use App\Notifications\TemplateChanged;
use App\Services\DocumentTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Manager for the intern forms' Word templates. Templates are scoped per
 * college (tabs on the manager) — the college whose templates interns
 * actually receive is the one configured under Settings. Coordinators and
 * the System Admin manage them; when a coordinator uploads or removes a
 * design, the System Admins and the other coordinators are notified.
 */
class DocumentTemplateController extends Controller
{
    public function __construct(private DocumentTemplateService $service) {}

    /**
     * List the form types with their current template state for one college,
     * split into the log-driven reports and the school's requirement forms.
     */
    public function index(Request $request)
    {
        return $this->renderManager($this->collegeFor($request->user()));
    }

    /**
     * The same manager for a specific college tab. Coordinators are locked
     * to the college they belong to; the System Admin sees every tab.
     */
    public function show(Request $request, College $college)
    {
        $this->assertCollegeAccess($request->user(), $college);

        return $this->renderManager($college);
    }

    /**
     * Download the shipped starter template for a form type — the blank the
     * admin edits in Word before uploading.
     */
    public function starter(Request $request, College $college, string $type): BinaryFileResponse
    {
        $this->assertCollegeAccess($request->user(), $college);
        $this->assertKnownType($type);

        $downloadName = DocumentTemplate::TYPES[$type]['label'].' Template.docx';

        return response()->download($this->service->starterPath($type, $college->code), $downloadName);
    }

    /**
     * Store an edited .docx as the active template for a form type.
     */
    public function store(Request $request, College $college, string $type): RedirectResponse
    {
        $this->assertCollegeAccess($request->user(), $college);
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

        $this->service->store($type, $request->file('template'), $request->user(), $college->code);

        $this->notifyCoordinatorsWhenCoordinatorActed(
            $request->user(),
            'uploaded',
            DocumentTemplate::TYPES[$type]['label'],
            $college->name,
        );

        return back()->with('status', DocumentTemplate::TYPES[$type]['label'].' template uploaded. Interns now generate this form from your Word design.');
    }

    /**
     * Remove the active template for a form type, leaving it unavailable to
     * interns until a new one is uploaded.
     */
    public function destroy(Request $request, College $college, string $type): RedirectResponse
    {
        $this->assertCollegeAccess($request->user(), $college);
        $this->assertKnownType($type);

        $this->service->delete($type, $college->code);

        $this->notifyCoordinatorsWhenCoordinatorActed(
            $request->user(),
            'removed',
            DocumentTemplate::TYPES[$type]['label'],
            $college->name,
        );

        return back()->with('status', DocumentTemplate::TYPES[$type]['label'].' template removed. Interns cannot generate this form until you upload a new template.');
    }

    /**
     * Guard the {type} route segment against unknown form types.
     */
    private function assertKnownType(string $type): void
    {
        abort_unless(array_key_exists($type, DocumentTemplate::TYPES), 404);
    }

    /**
     * The college a manager belongs to: their staff profile's college, or
     * the installation's default when none is recorded.
     */
    private function collegeFor(User $user): College
    {
        // Coordinators land on their own college; supervisors are locked out
        // of templates by middleware, and the System Admin gets the default.
        $code = $user->isCoordinator()
            ? ($user->collegeCode() ?? $this->defaultCollege())
            : $this->defaultCollege();

        return College::where('code', $code)->first() ?? College::orderBy('id')->firstOr(fn () => new College(['code' => $code, 'name' => 'College']));
    }

    private function defaultCollege(): string
    {
        $code = \App\Models\OjtSetting::current()->college_code;

        return College::where('code', $code)->exists() ? $code : (string) College::orderBy('id')->value('code');
    }

    /**
     * Coordinators may only manage their own college's templates; the System
     * Admin manages every college.
     */
    private function assertCollegeAccess(User $user, College $college): void
    {
        if ($user->isCoordinator()) {
            $own = $user->collegeCode() ?? $this->defaultCollege();

            abort_unless($college->code === $own, 403);
        }
    }

    private function renderManager(?College $college)
    {
        abort_if(! $college, 404);

        $templates = collect(DocumentTemplate::TYPES)
            ->map(fn (array $meta, string $type): array => [
                'type' => $type,
                'label' => $meta['label'],
                'category' => $meta['category'],
                'active' => $this->service->active($type, $college->code),
            ])
            ->groupBy('category');

        return view('admin.document-templates.index', [
            'selectedCollege' => $college,
            'colleges' => College::orderBy('name')->get(),
            'reports' => $templates->get(DocumentTemplate::CATEGORY_REPORT, collect())->values(),
            'requirements' => $templates->get(DocumentTemplate::CATEGORY_REQUIREMENT, collect())->values(),
        ]);
    }

    /**
     * Template changes made by a coordinator inform the System Admins and the
     * other coordinators; changes made by a System Admin are the authority
     * and stay silent.
     */
    private function notifyCoordinatorsWhenCoordinatorActed(User $actor, string $action, string $formLabel, string $collegeName): void
    {
        if (! $actor->isCoordinator()) {
            return;
        }

        $recipients = User::where(function ($query) {
            $query->where('role', 'admin')->orWhere('role', 'coordinator');
        })->where('id', '!=', $actor->id)->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new TemplateChanged($action, $formLabel, $collegeName, $actor));
        }
    }
}
