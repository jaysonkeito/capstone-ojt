<?php

use App\Models\College;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Admin-managed Word templates for the intern's printable forms. With a
 * template uploaded, the intern's form generates as a filled .docx; with none,
 * the form is unavailable and the intern is redirected with a notice (the
 * require-upload behavior also covered in ExportsTest and
 * WeeklyProgressReportTest). Also covers the admin manager — download the
 * starter, upload an edited design, remove it — and its access control.
 *
 * Reuses makeIntern(), makeActiveEnrollment(), makeLog(), fullDutyTimes()
 * (InternPhotoUploadTest) and makeStaff() (DailyReportAccessTest) — global Pest
 * helpers shared across this suite.
 */

const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

/**
 * Put a shipped starter onto the faked local disk and mark it the active
 * template for a type — the exact state an admin upload leaves behind. The
 * file copied defaults to the type's own starter; passing another type's
 * starter (or any path under public/documents) reproduces the wrong-design
 * upload that leaves a form unfillable.
 */
function activateTemplate(string $type, ?string $source = null): DocumentTemplate
{
    $path = "document-templates/{$type}.docx";

    $service = app(DocumentTemplateService::class);

    Storage::disk('local')->put(
        $path,
        (string) file_get_contents($source ?? $service->starterPath($type)),
    );

    return DocumentTemplate::create([
        'type' => $type,
        'college_code' => 'cas',
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'Custom '.$type.'.docx',
    ]);
}

/**
 * Pull word/document.xml out of a generated .docx byte string so tests can
 * assert the merged data landed in the document body.
 */
function docxDocumentXml(string $binary): string
{
    $temp = tempnam(sys_get_temp_dir(), 'tpltest_');
    file_put_contents($temp, $binary);

    $zip = new ZipArchive;
    $zip->open($temp);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($temp);

    return $xml;
}

beforeEach(function () {
    // The uploaded templates live on the local disk; isolate every test.
    Storage::fake('local');

    College::firstOrCreate(['code' => 'cas'], ['name' => 'College of Arts and Sciences']);
});

/*
|--------------------------------------------------------------------------
| Intern side — a filled .docx when a template is active
|--------------------------------------------------------------------------
*/

test('the weekly report generates a filled Word file when a template is active', function () {
    if (! class_exists(\PhpOffice\PhpWord\TemplateProcessor::class)) {
        $this->markTestSkipped('phpoffice/phpword is not installed yet — run: composer require phpoffice/phpword');
    }

    Storage::fake('public');

    $intern = makeIntern(['first_name' => 'Juan', 'last_name' => 'Villanueva']);
    $enrollment = makeActiveEnrollment($intern);

    // A completed journal day (photo + message) so the merge fills a day cell
    // and embeds the Documentation photo.
    $photo = UploadedFile::fake()->image('duty.jpg')->store('ojt-photos', 'public');
    makeLog($intern, $enrollment, '2026-08-03', [
        'notes' => 'Onboarding and orientation.',
        'photo_path' => $photo,
        ...fullDutyTimes(),
    ]);

    activateTemplate(DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT);

    $response = $this->actingAs($intern)->get(route('intern.weekly-report'));

    $response->assertOk()->assertHeader('Content-Type', DOCX_MIME);

    expect($response->headers->get('Content-Disposition'))
        ->toStartWith('attachment')   // a Word file always downloads
        ->toContain('.docx');

    $binary = $response->getContent();
    expect($binary)->toStartWith('PK');                       // a real .docx is a zip
    expect(docxDocumentXml($binary))->toContain('Villanueva'); // intern name merged in
});

test('a Documentation photo is sized to fit its row so each week stays on one page', function () {
    if (! class_exists(\PhpOffice\PhpWord\TemplateProcessor::class)) {
        $this->markTestSkipped('phpoffice/phpword is not installed yet — run: composer require phpoffice/phpword');
    }

    Storage::fake('public');

    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    $photo = UploadedFile::fake()->image('duty.jpg')->store('ojt-photos', 'public');
    makeLog($intern, $enrollment, '2026-08-03', [
        'notes' => 'Onboarding and orientation.',
        'photo_path' => $photo,
        ...fullDutyTimes(),
    ]);

    activateTemplate(DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT);

    $xml = docxDocumentXml(
        $this->actingAs($intern)->get(route('intern.weekly-report'))->getContent()
    );

    // The photo must fit the template's Documentation row (≈1.46"/140px tall).
    // At 175px it grew the row and spilled the ${photoN_date} line onto a
    // near-blank overflow page after every week with photos — a 9-week report
    // printed as 15 pages. The box is embedded at a fixed 120×135px.
    expect($xml)->toContain('width:120px;height:135px')
        ->and($xml)->not->toContain('height:175px');
});

test('the timesheet generates a filled Word file when a template is active', function () {
    if (! class_exists(\PhpOffice\PhpWord\TemplateProcessor::class)) {
        $this->markTestSkipped('phpoffice/phpword is not installed yet — run: composer require phpoffice/phpword');
    }

    $intern = makeIntern(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $enrollment = makeActiveEnrollment($intern);
    makeLog($intern, $enrollment, today()->toDateString(), fullDutyTimes());

    activateTemplate(DocumentTemplate::TYPE_TIMESHEET);

    $response = $this->actingAs($intern)->get(route('intern.timesheet'));

    $response->assertOk()->assertHeader('Content-Type', DOCX_MIME);

    expect($response->headers->get('Content-Disposition'))
        ->toStartWith('attachment')
        ->toContain('.docx');

    $binary = $response->getContent();
    expect($binary)->toStartWith('PK');
    expect(docxDocumentXml($binary))->toContain('Santos');
});

test('the timesheet lists duty days oldest first', function () {
    if (! class_exists(\PhpOffice\PhpWord\TemplateProcessor::class)) {
        $this->markTestSkipped('phpoffice/phpword is not installed yet — run: composer require phpoffice/phpword');
    }

    $intern = makeIntern(['first_name' => 'Maria', 'last_name' => 'Santos']);
    $enrollment = makeActiveEnrollment($intern);

    // Log three duty days out of chronological order, so a stable sort — not
    // insertion order — is what puts them in sequence.
    makeLog($intern, $enrollment, '2026-08-05', fullDutyTimes());
    makeLog($intern, $enrollment, '2026-08-03', fullDutyTimes());
    makeLog($intern, $enrollment, '2026-08-04', fullDutyTimes());

    activateTemplate(DocumentTemplate::TYPE_TIMESHEET);

    $xml = docxDocumentXml(
        $this->actingAs($intern)->get(route('intern.timesheet'))->getContent()
    );

    // The rows print top-to-bottom in calendar order: 08-03 (upper), then
    // 08-04, then 08-05 (foot) — the way a Daily Time Record reads.
    $earliest = strpos($xml, '08-03-2026');
    $middle = strpos($xml, '08-04-2026');
    $latest = strpos($xml, '08-05-2026');

    expect($earliest)->not->toBeFalse()
        ->and($earliest)->toBeLessThan($middle)
        ->and($middle)->toBeLessThan($latest);
});

test('journal messages with XML special characters still produce an openable Word file', function () {
    if (! class_exists(\PhpOffice\PhpWord\TemplateProcessor::class)) {
        $this->markTestSkipped('phpoffice/phpword is not installed yet — run: composer require phpoffice/phpword');
    }

    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    // A bare & in the journal text used to reach word/document.xml unescaped,
    // leaving invalid XML that Word refuses to open ("Word experienced an
    // error trying to open the file").
    makeLog($intern, $enrollment, '2026-08-03', [
        'notes' => 'Installed internet in the SPORTS & ATHLETICS office.',
        'photo_path' => UploadedFile::fake()->image('duty.jpg')->store('ojt-photos', 'public'),
        ...fullDutyTimes(),
    ]);

    activateTemplate(DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT);

    $binary = $this->actingAs($intern)->get(route('intern.weekly-report'))->getContent();
    $xml = docxDocumentXml($binary);

    $document = new DOMDocument;
    $loaded = $document->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);

    expect($loaded)->toBeTrue()                                            // well-formed XML — Word can open it
        ->and($xml)->toContain('SPORTS &amp; ATHLETICS');                  // the text made it in, escaped
});

test('a template uploaded into the wrong slot redirects with a fix-it notice instead of erroring', function (string $slotType, string $sourceType, string $downloadRoute, string $label) {
    if (! class_exists(\PhpOffice\PhpWord\TemplateProcessor::class)) {
        $this->markTestSkipped('phpoffice/phpword is not installed yet — run: composer require phpoffice/phpword');
    }

    $intern = makeIntern();
    makeActiveEnrollment($intern);

    // The admin's mixup that broke the Timesheet download: the other report
    // form's file stored as this type's template — a valid .docx, so nothing
    // rejects it at upload time, but its design lacks the repeating
    // placeholder (${date} row / ${week} block) the merge clones.
    activateTemplate($slotType, app(DocumentTemplateService::class)->starterPath($sourceType));

    $response = $this->actingAs($intern)->get(route($downloadRoute));

    $response->assertRedirect()->assertSessionHas('status');

    expect(session('status'))
        ->toContain($label)
        ->toContain('starter');
})->with([
    'weekly design stored as the timesheet' => [
        DocumentTemplate::TYPE_TIMESHEET,
        DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT,
        'intern.timesheet',
        'Timesheet',
    ],
    'timesheet design stored as the weekly report' => [
        DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT,
        DocumentTemplate::TYPE_TIMESHEET,
        'intern.weekly-report',
        'Weekly Progress Report',
    ],
]);

/*
|--------------------------------------------------------------------------
| Admin manager — download starter, upload, remove
|--------------------------------------------------------------------------
*/

test('the templates page renders for an admin', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.document-templates.index'))
        ->assertOk()
        ->assertSee('Document Templates')
        ->assertSee('Weekly Progress Report')
        ->assertSee('Timesheet');
});

test('an admin can download a starter template', function () {
    $admin = makeStaff(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->get(route('admin.document-templates.starter', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]));

    $response->assertOk();
    $response->assertDownload('Timesheet Template.docx');
});

test('an admin can upload an edited template', function () {
    $admin = makeStaff(['role' => 'admin']);

    $file = UploadedFile::fake()->create('my-timesheet.docx', 120, DOCX_MIME);

    $this->actingAs($admin)
        ->post(route('admin.document-templates.store', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]), [
            'template' => $file,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $this->assertDatabaseHas('document_templates', [
        'type' => DocumentTemplate::TYPE_TIMESHEET,
        'disk' => 'local',
        'path' => 'document-templates/timesheet.docx',
        'original_name' => 'my-timesheet.docx',
        'uploaded_by' => $admin->id,
    ]);

    Storage::disk('local')->assertExists('document-templates/timesheet.docx');
});

test('uploading over an existing template replaces it in place', function () {
    $admin = makeStaff(['role' => 'admin']);

    activateTemplate(DocumentTemplate::TYPE_TIMESHEET);

    $this->actingAs($admin)
        ->post(route('admin.document-templates.store', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]), [
            'template' => UploadedFile::fake()->create('replacement.docx', 90, DOCX_MIME),
        ])
        ->assertRedirect();

    // Still exactly one row for the type, now pointing at the new upload.
    expect(DocumentTemplate::where('type', DocumentTemplate::TYPE_TIMESHEET)->count())->toBe(1);
    $this->assertDatabaseHas('document_templates', [
        'type' => DocumentTemplate::TYPE_TIMESHEET,
        'original_name' => 'replacement.docx',
    ]);
});

test('uploading a non-Word file is rejected', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('admin.document-templates.index'))
        ->post(route('admin.document-templates.store', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]), [
            'template' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('template');

    $this->assertDatabaseMissing('document_templates', [
        'type' => DocumentTemplate::TYPE_TIMESHEET,
    ]);
});

test('removing a template leaves the intern form unavailable until a new upload', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    $template = activateTemplate(DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT);
    Storage::disk('local')->assertExists($template->path);

    $this->actingAs($admin)
        ->delete(route('admin.document-templates.destroy', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT]))
        ->assertRedirect()
        ->assertSessionHas('status');

    $this->assertDatabaseMissing('document_templates', [
        'type' => DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT,
    ]);
    Storage::disk('local')->assertMissing($template->path);

    // With the template gone the form can no longer be generated: the intern
    // is redirected back with a notice instead of getting an ad-hoc PDF.
    $this->actingAs($intern)->get(route('intern.weekly-report'))
        ->assertRedirect(route('intern.dashboard'))
        ->assertSessionHas('status');
});

/*
|--------------------------------------------------------------------------
| Access control & routing guards
|--------------------------------------------------------------------------
*/

test('supervisors are closed out of the templates manager', function () {
    $supervisor = makeStaff(['role' => 'supervisor']);

    $this->actingAs($supervisor)->get(route('admin.document-templates.index'))->assertForbidden();
    $this->actingAs($supervisor)->get(route('admin.document-templates.college', 'cas'))->assertForbidden();
    $this->actingAs($supervisor)->post(route('admin.document-templates.store', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]))->assertForbidden();
});

test('a coordinator uploading a template notifies the admins and the other coordinators', function () {
    $coordinator = makeStaff(['role' => 'coordinator']);
    $otherCoordinator = makeStaff(['role' => 'coordinator']);
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($coordinator)
        ->post(route('admin.document-templates.store', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]), [
            'template' => UploadedFile::fake()->create('Timesheet Template.docx'),
        ])->assertRedirect();

    expect($otherCoordinator->fresh()->notifications()->where('type', 'App\Notifications\TemplateChanged')->count())->toBe(1)
        ->and($admin->fresh()->notifications()->where('type', 'App\Notifications\TemplateChanged')->count())->toBe(1)
        ->and($coordinator->fresh()->notifications()->count())->toBe(0); // the actor isn't notified
});

test('a coordinator removing a template notifies as well', function () {
    $coordinator = makeStaff(['role' => 'coordinator']);
    $admin = makeStaff(['role' => 'admin']);
    activateTemplate(DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT);

    $this->actingAs($coordinator)
        ->delete(route('admin.document-templates.destroy', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT]))
        ->assertRedirect();

    expect($admin->fresh()->notifications()->where('type', 'App\Notifications\TemplateChanged')->count())->toBe(1);
});

test('the System Admin changing a template stays silent', function () {
    $admin = makeStaff(['role' => 'admin']);
    $coordinator = makeStaff(['role' => 'coordinator']);

    $this->actingAs($admin)
        ->post(route('admin.document-templates.store', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]), [
            'template' => UploadedFile::fake()->create('Timesheet Template.docx'),
        ])->assertRedirect();

    expect($coordinator->fresh()->notifications()->count())->toBe(0);
});

test('the templates manager is closed to interns', function () {
    $intern = makeIntern();

    $this->actingAs($intern)->get(route('admin.document-templates.index'))->assertForbidden();
    $this->actingAs($intern)->get(route('admin.document-templates.starter', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]))->assertForbidden();
    $this->actingAs($intern)->post(route('admin.document-templates.store', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]))->assertForbidden();
    $this->actingAs($intern)->delete(route('admin.document-templates.destroy', ['college' => 'cas', 'type' => DocumentTemplate::TYPE_TIMESHEET]))->assertForbidden();
});

test('the templates manager requires authentication', function () {
    $this->get(route('admin.document-templates.index'))->assertRedirect(route('login'));
});

test('an unknown template type is a 404', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.document-templates.starter', ['college' => 'cas', 'type' => 'bogus']))
        ->assertNotFound();
});
