<?php

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\DocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

/*
 * The intern's OJT requirement documents. Interns get a dedicated section
 * listing the school's required forms; each downloads with the intern's own
 * information merged into the admin's uploaded Word design. With no design
 * uploaded, a form ships as its blank official copy — except the Internship
 * Application Letter and Cover Page, whose built-in designs are merged per
 * intern either way. Admins keep managing the uploads through the existing
 * template manager.
 */

/**
 * Build a throwaway .docx carrying the given text (usually ${placeholders}) as
 * a single run, so TemplateProcessor sees the tags intact. Returns its path.
 */
function requirementDocxFixture(string $text): string
{
    $phpWord = new PhpWord;
    $phpWord->addSection()->addText($text);

    $path = tempnam(sys_get_temp_dir(), 'reqtpl_').'.docx';
    $phpWord->save($path, 'Word2007');

    return $path;
}

// docxDocumentXml() — pulls word/document.xml out of a .docx byte string — is a
// global Pest helper defined in DocumentTemplateTest.php and reused here.

test('the requirements page lists the school forms for an intern', function () {
    $intern = makeIntern();

    $this->actingAs($intern)
        ->get(route('intern.requirements.index'))
        ->assertOk()
        ->assertSee('OJT Requirements')
        ->assertSee('Cover Page')
        ->assertSee('Endorsement Letter')
        ->assertSee("Student Intern's Performance Appraisal");
});

test('the Endorsement Letter is generated per intern even without an uploaded template', function () {
    $intern = makeIntern(['first_name' => 'Juan', 'last_name' => 'Cruz']);
    $coordinator = makeCoordinator();
    $intern->update(['coordinator_id' => $coordinator->id]);

    // The Endorsement Letter is macroized: the request letter to the host
    // company is generated per intern — their coordinator signs it.
    $response = $this->actingAs($intern->fresh())
        ->get(route('intern.requirements.download', DocumentTemplate::TYPE_ENDORSEMENT_LETTER));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('Endorsement_Letter_Cruz_Juan.docx');

    $xml = docxDocumentXml($response->getContent());

    expect($xml)
        ->toContain($coordinator->display_name_with_middle_initial) // the signing coordinator
        ->toContain('College of Arts and Sciences') // from Settings
        // …and no raw placeholder macros reach the letter.
        ->not->toContain('${');
});

test('an intern downloads a requirement form as the blank official copy when no template is uploaded', function () {
    $intern = makeIntern(['first_name' => 'Juan', 'last_name' => 'Cruz']);

    // The Acceptance Form has no built-in filled design, so it still ships
    // as the school's blank official copy.
    $response = $this->actingAs($intern)
        ->get(route('intern.requirements.download', DocumentTemplate::TYPE_ACCEPTANCE_FORM));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('Acceptance_Form_Cruz_Juan.docx');

    // A real .docx came back (the shipped starter), not an empty body.
    expect(strlen($response->getContent()))->toBeGreaterThan(0);
});

test('the Cover Page and Application Letter are listed as filled even without an uploaded design', function () {
    $intern = makeIntern();

    // The Cover Page, the Application Letter, and the Endorsement Letter are
    // generated per intern out of the box, so they show the filled badge;
    // forms without a macroized starter are still listed as blank official
    // copies.
    $this->actingAs($intern)
        ->get(route('intern.requirements.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'Cover Page',
            'Filled with your info',
            'Internship Application Letter',
            'Filled with your info',
            'Endorsement Letter',
            'Filled with your info',
        ]);
});

test("the Personal Information sheet is filled with the intern's own details even without an uploaded template", function () {
    $coordinator = makeCoordinator();
    $coordinator->staffProfile()->create(['mobile_number' => '0917-555-1234']);

    $intern = makeIntern([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'student_id' => '202311111',
        'department' => 'BSIT',
        'year_level' => 4,
        'coordinator_id' => $coordinator->id,
    ]);
    $intern->personalInfo()->create([
        'middle_name' => 'Santos',
        'birthdate' => '2004-10-05',
        'sex' => 'Female',
        'civil_status' => 'Single',
        'citizenship' => 'Filipino',
        'father_name' => 'Pedro Dela Cruz',
        'father_occupation' => 'Farmer',
        'mother_name' => 'Maria Dela Cruz',
        'mother_occupation' => 'Teacher',
        'emergency_name' => 'Carmen Santos',
        'emergency_relationship' => 'Aunt',
        'emergency_contact' => '09170000000',
    ]);

    $response = $this->actingAs($intern)
        ->get(route('intern.requirements.download', DocumentTemplate::TYPE_PERSONAL_INFORMATION));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $xml = docxDocumentXml($response->getContent());

    // The official sheet comes back carrying this intern's own data — roster
    // identity, the details they saved, their coordinator — never the shipped
    // sample intern's values.
    expect($xml)->toContain('Student Personal Information')
        ->and($xml)->toContain('Juan')
        ->and($xml)->toContain('Dela Cruz')
        ->and($xml)->toContain('Santos') // middle name
        ->and($xml)->toContain('October 5, 2004') // birthdate
        ->and($xml)->toContain('Age: '.\Illuminate\Support\Carbon::parse('2004-10-05')->age)
        ->and($xml)->toContain('Female')
        ->and($xml)->toContain('Filipino')
        ->and($xml)->toContain('Bachelor of Science in Information Technology')
        ->and($xml)->toContain('Fourth')
        ->and($xml)->toContain('0917-555-1234') // coordinator contact from their staff record
        // The sheet's emergency block prints the intern's own emergency entry.
        ->and($xml)->toContain('Carmen Santos')
        ->and($xml)->toContain('Aunt')
        ->and($xml)->toContain('09170000000')
        // Fields the intern left blank print as N/A on this data sheet.
        ->and($xml)->toContain('N/A')
        // …and none of the shipped sample intern's baked-in data survives…
        ->and($xml)->not->toContain('Pob. Sta. Catalina')
        ->and($xml)->not->toContain('Caloocan City')
        ->and($xml)->not->toContain('JOVANY B. CASTILLO')
        ->and($xml)->not->toContain('09279792711')
        // …nor does any raw placeholder macro.
        ->and($xml)->not->toContain('${');
});

test('an intern with no saved details downloads the Personal Information sheet marked N/A', function () {
    $intern = makeIntern(['first_name' => 'Ana', 'last_name' => 'Lopez']);

    $xml = docxDocumentXml(
        $this->actingAs($intern)
            ->get(route('intern.requirements.download', DocumentTemplate::TYPE_PERSONAL_INFORMATION))
            ->assertOk()
            ->getContent()
    );

    // Roster identity always prints; every personal detail not yet on file
    // reads N/A so the sheet is obviously unfinished rather than silently blank.
    expect($xml)->toContain('Ana')
        ->and($xml)->toContain('Lopez')
        ->and($xml)->toContain('Sex: N/A')
        ->and($xml)->toContain('Birth Place: N/A')
        ->and($xml)->not->toContain('${sex}')
        ->and($xml)->not->toContain('${birth_place}');
});

test('a requirement form is filled with the intern data when a template is uploaded', function () {
    Storage::fake('local');

    $intern = makeIntern([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'student_id' => '202311111',
    ]);

    // A template touching a known field, a second known field, a field the
    // intern has no data for (no office), and one the app never provides.
    $fixture = requirementDocxFixture('Name: ${intern_name} | ID: ${student_id} | Company: ${company_name} | ${leftover_field}');
    $upload = new UploadedFile($fixture, 'cover.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    app(DocumentTemplateService::class)->store(DocumentTemplate::TYPE_COVER_PAGE, $upload, $intern);

    $response = $this->actingAs($intern)
        ->get(route('intern.requirements.download', DocumentTemplate::TYPE_COVER_PAGE));

    $response->assertOk();

    $xml = docxDocumentXml($response->getContent());

    expect($xml)->toContain('Juan Dela Cruz')
        ->and($xml)->toContain('202311111')
        // Every placeholder is resolved — knowns filled, the rest blanked —
        // so no raw ${…} macro ever reaches the page.
        ->and($xml)->not->toContain('${intern_name}')
        ->and($xml)->not->toContain('${student_id}')
        ->and($xml)->not->toContain('${company_name}')
        ->and($xml)->not->toContain('${leftover_field}');
});

test('fillProfile merges the known placeholders and blanks the rest', function () {
    $fixture = requirementDocxFixture('${intern_name} — ${student_id} — ${unknown_token}');

    $binary = app(DocumentTemplateService::class)->fillProfile($fixture, [
        'intern_name' => 'Maria Santos',
        'student_id' => '202322222',
    ]);

    $xml = docxDocumentXml($binary);

    expect($xml)->toContain('Maria Santos')
        ->and($xml)->toContain('202322222')
        ->and($xml)->not->toContain('${intern_name}')
        ->and($xml)->not->toContain('${unknown_token}');
});

test('fillProfile substitutes the given empty value for missing and unknown placeholders', function () {
    $fixture = requirementDocxFixture('${intern_name} — ${student_id} — ${unknown_token}');

    // Current signature: per-field empty markers come first (none here),
    // then the global marker for every other blank or unknown token.
    $binary = app(DocumentTemplateService::class)->fillProfile($fixture, [
        'intern_name' => 'Maria Santos',
        'student_id' => '',
    ], [], 'N/A');

    $xml = docxDocumentXml($binary);

    // The real value wins; the empty known field and the token the app never
    // provides both resolve to the marker — never a raw ${…} tag.
    expect($xml)->toContain('Maria Santos')
        ->and(substr_count($xml, 'N/A'))->toBe(2)
        ->and($xml)->not->toContain('${student_id}')
        ->and($xml)->not->toContain('${unknown_token}');
});

test('the Personal Information form marks fields the intern has no data for as N/A', function () {
    Storage::fake('local');

    $intern = makeIntern([
        'first_name' => 'Ana',
        'last_name' => 'Lopez',
        'student_id' => '202344444',
    ]);

    // Two fields the intern has, plus a placement field they don't yet (no office).
    $fixture = requirementDocxFixture('Name: ${intern_name} | ID: ${student_id} | Company: ${company_name}');
    $upload = new UploadedFile($fixture, 'pi.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    app(DocumentTemplateService::class)->store(DocumentTemplate::TYPE_PERSONAL_INFORMATION, $upload, $intern);

    $response = $this->actingAs($intern)
        ->get(route('intern.requirements.download', DocumentTemplate::TYPE_PERSONAL_INFORMATION));

    $response->assertOk();

    $xml = docxDocumentXml($response->getContent());

    expect($xml)->toContain('Ana Lopez')
        ->and($xml)->toContain('202344444')
        // The empty placement field reads N/A on this data sheet, not a blank.
        ->and($xml)->toContain('N/A')
        ->and($xml)->not->toContain('${company_name}');
});

test('a requirement form without an N/A default leaves missing fields blank', function () {
    Storage::fake('local');

    $intern = makeIntern(['first_name' => 'Ben', 'last_name' => 'Reyes']);

    // company_name is empty (no office); the Cover Page opts out of the N/A
    // marker, so its missing fields stay blank to complete by hand.
    $fixture = requirementDocxFixture('Company: ${company_name}');
    $upload = new UploadedFile($fixture, 'cover.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    app(DocumentTemplateService::class)->store(DocumentTemplate::TYPE_COVER_PAGE, $upload, $intern);

    $xml = docxDocumentXml(
        $this->actingAs($intern)
            ->get(route('intern.requirements.download', DocumentTemplate::TYPE_COVER_PAGE))
            ->getContent()
    );

    expect($xml)->not->toContain('N/A')
        ->and($xml)->not->toContain('${company_name}');
});

test('the Internship Application Letter is generated per intern even without an uploaded template', function () {
    $office = makeOffice(['name' => 'NORSU Data Center', 'address' => 'Dumaguete City']);
    $supervisor = makeSupervisor($office, [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'title' => 'IT Manager',
        'position' => 'MIS, Campus Director',
    ]);
    $supervisor->staffProfile()->create(['gender' => 'Male']);

    $intern = makeIntern([
        'office_id' => $office->id,
        'first_name' => 'Ana',
        'last_name' => 'Lopez',
        'student_id' => '202355555',
    ]);
    makeActiveEnrollment($intern)->update([
        'started_at' => '2026-08-01',
        'completed_at' => '2026-10-30',
    ]);
    $intern->personalInfo()->create(['phone_number' => '0917-123-4567']);

    // No template uploaded — the letter still comes back addressed to Ana's own
    // supervisor at her office, never a fixed copy of another student's letter.
    $xml = docxDocumentXml(
        $this->actingAs($intern)
            ->get(route('intern.requirements.download', DocumentTemplate::TYPE_APPLICATION_LETTER))
            ->assertOk()
            ->getContent()
    );

    expect($xml)->toContain('Mr. Juan Dela Cruz')
        // The supervisor's position prints below the name; the title line is
        // no longer part of the letter's header.
        ->and($xml)->toContain('MIS, Campus Director')
        ->and($xml)->not->toContain('IT Manager')
        ->and($xml)->toContain('NORSU Data Center')
        ->and($xml)->toContain('Ana Lopez')
        ->and($xml)->toContain(now()->format('d F Y'))
        // The shipped letter commits to training until the intern's own target
        // hours are done — this intern's enrollment is set to 500 hours.
        ->and($xml)->toContain('August 2026 until I complete my required 500 training hours')
        ->and($xml)->not->toContain('to December 2026')
        ->and($xml)->not->toContain('${target_hours}')
        ->and($xml)->toContain('0917-123-4567')
        // No leftover placeholder macros and none of the shipped sample's data.
        ->and($xml)->not->toContain('${letter_date}')
        ->and($xml)->not->toContain('${ojt_period_start}')
        ->and($xml)->not->toContain('${intern_name}')
        ->and($xml)->not->toContain('JAYSON P. FRANCISCO')
        ->and($xml)->not->toContain('FRANCO ABEQUIBEL');
});

test('two interns download different application letters when no template is uploaded', function () {
    $office = makeOffice(['name' => 'NORSU Data Center', 'address' => 'Dumaguete City']);
    $supervisor = makeSupervisor($office, [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'title' => 'IT Manager',
    ]);

    $first = makeIntern(['office_id' => $office->id, 'first_name' => 'Ana', 'last_name' => 'Lopez', 'student_id' => 'T-2001']);
    $second = makeIntern(['office_id' => $office->id, 'first_name' => 'Ben', 'last_name' => 'Reyes', 'student_id' => 'T-2002']);

    $xmlOf = fn (User $intern): string => docxDocumentXml(
        $this->actingAs($intern)
            ->get(route('intern.requirements.download', DocumentTemplate::TYPE_APPLICATION_LETTER))
            ->assertOk()
            ->getContent()
    );

    expect($xmlOf($first))->toContain('Ana Lopez')
        ->and($xmlOf($second))->toContain('Ben Reyes')
        ->and($xmlOf($first))->not->toContain('Ben Reyes')
        ->and($xmlOf($second))->not->toContain('Ana Lopez');
});

test("the Cover Page prints the intern's own name on both file pages even without an uploaded template", function () {
    $intern = makeIntern(['first_name' => 'Ana', 'last_name' => 'Lopez']);

    // No template uploaded — the binder cover still comes back carrying this
    // intern's name on the Student Trainee line of both the IFS and ISS file
    // pages, never a fixed copy with the shipped sample student's name.
    $xml = docxDocumentXml(
        $this->actingAs($intern)
            ->get(route('intern.requirements.download', DocumentTemplate::TYPE_COVER_PAGE))
            ->assertOk()
            ->getContent()
    );

    // The official cover design is intact (both file pages' title and the
    // school identity block)…
    expect($xml)->toContain('COMPILATION')
        ->and($xml)->toContain('NEGROS ORIENTAL STATE UNIVERSITY')
        ->and($xml)->toContain('Cooperating Agency')
        // …carrying this intern's own name, twice (once per file page), with
        // no raw macro and none of the shipped sample student's baked-in
        // name surviving. (The current shipped design stores the Student
        // Trainee line in plain styling — no small-caps run property.)
        ->and($xml)->toContain('Ana Lopez')
        ->and(substr_count($xml, 'Ana Lopez'))->toBe(2)
        ->and($xml)->not->toContain('${intern_name}')
        ->and($xml)->not->toContain('${')
        ->and($xml)->not->toContain('JOVANY B. CASTILLO')
        ->and($xml)->not->toContain('JOVANY');
});

test('two interns download different cover pages when no template is uploaded', function () {
    $first = makeIntern(['first_name' => 'Ana', 'last_name' => 'Lopez', 'student_id' => 'T-2001']);
    $second = makeIntern(['first_name' => 'Ben', 'last_name' => 'Reyes', 'student_id' => 'T-2002']);

    $xmlOf = fn (User $intern): string => docxDocumentXml(
        $this->actingAs($intern)
            ->get(route('intern.requirements.download', DocumentTemplate::TYPE_COVER_PAGE))
            ->assertOk()
            ->getContent()
    );

    expect($xmlOf($first))->toContain('Ana Lopez')
        ->and($xmlOf($second))->toContain('Ben Reyes')
        ->and($xmlOf($first))->not->toContain('Ben Reyes')
        ->and($xmlOf($second))->not->toContain('Ana Lopez');
});

test('an uploaded Internship Application Letter fills the letter placeholders from the intern placement', function () {
    Storage::fake('local');

    $office = makeOffice(['name' => 'NORSU Data Center', 'address' => 'Dumaguete City']);
    $supervisor = makeSupervisor($office, [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'title' => 'IT Manager',
        'position' => 'MIS, Campus Director',
    ]);
    $supervisor->staffProfile()->create(['gender' => 'Male']);

    $intern = makeIntern([
        'office_id' => $office->id,
        'first_name' => 'Ana',
        'last_name' => 'Lopez',
        'student_id' => '202355555',
        'department' => 'BSIT',  // the accessor expands this to the full degree name
    ]);
    makeActiveEnrollment($intern)->update([
        'started_at' => '2026-08-01',
        'completed_at' => '2026-10-30',
    ]);
    $intern->personalInfo()->create(['phone_number' => '0917-123-4567']);

    $fixture = requirementDocxFixture(
        '${addressee_name}|${addressee_position}|${addressee_title}|${company_name}|${company_address}|'
        .'${salutation}|${course_name}|${training_period}|${contact_number}|${letter_date}|${intern_name}'
    );
    $upload = new UploadedFile($fixture, 'letter.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    app(DocumentTemplateService::class)->store(DocumentTemplate::TYPE_APPLICATION_LETTER, $upload, $intern);

    $xml = docxDocumentXml(
        $this->actingAs($intern)
            ->get(route('intern.requirements.download', DocumentTemplate::TYPE_APPLICATION_LETTER))
            ->assertOk()
            ->getContent()
    );

    // The letter is addressed to the assigned supervisor (Mr. + Sir for a male
    // record) at their office, and fills the intern's course, training window,
    // contact number, today's letter date, and signature name.
    expect($xml)->toContain('Mr. Juan Dela Cruz')
        ->and($xml)->toContain('IT Manager')
        // The supervisor's position fills its placeholder when one is on file.
        ->and($xml)->toContain('MIS, Campus Director')
        ->and($xml)->toContain('NORSU Data Center')
        ->and($xml)->toContain('Dumaguete City')
        // The honourific is not repeated (no "Sir Sir Juan"): the macro value
        // carries it and the template supplies none.
        ->and($xml)->toContain('|Sir Juan|')
        ->and($xml)->not->toContain('Sir Sir')
        ->and($xml)->toContain('Bachelor of Science in Information Technology')
        ->and($xml)->toContain('August until October 2026')
        ->and($xml)->toContain('0917-123-4567')
        ->and($xml)->toContain(now()->format('d F Y'))
        ->and($xml)->toContain('Ana Lopez')
        // Every macro resolved — no raw tag survives.
        ->and($xml)->not->toContain('${addressee_name}')
        ->and($xml)->not->toContain('${training_period}');
});

test('a report type cannot be downloaded through the requirements route', function () {
    $intern = makeIntern();

    $this->actingAs($intern)
        ->get(route('intern.requirements.download', DocumentTemplate::TYPE_TIMESHEET))
        ->assertNotFound();
});

test('an unknown requirement type is not found', function () {
    $intern = makeIntern();

    $this->actingAs($intern)
        ->get(route('intern.requirements.download', 'not_a_real_form'))
        ->assertNotFound();
});

test('a guest is redirected from the requirements page', function () {
    $this->get(route('intern.requirements.index'))
        ->assertRedirect(route('login'));
});

test('a coordinator cannot reach the intern requirements', function () {
    $coordinator = makeCoordinator();

    $this->actingAs($coordinator)
        ->get(route('intern.requirements.index'))
        ->assertForbidden();
});
