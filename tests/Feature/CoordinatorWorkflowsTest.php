<?php

use App\Models\ClassMessage;
use App\Models\DocumentTemplate;
use App\Models\InternRequest;
use App\Models\Office;
use App\Models\SubmittedDocument;
use App\Models\User;
use App\Notifications\DocumentReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The coordinator-facing workflows: interns submit their requirement
 * documents back for review (the other half of the Requirements page),
 * request office transfers and consultations, and talk on the class
 * board. The Program Chair role sees the college's interns read-only.
 */

function makeCoordinatorIntern(User $coordinator, array $attributes = []): User
{
    return makeIntern([
        'student_id' => fake()->unique()->numerify('T-####'),
        'coordinator_id' => $coordinator->id,
        ...$attributes,
    ]);
}

// ---------- Document submission + review ----------

test('an intern submits a requirement document and the coordinator is notified', function () {
    Storage::fake('public');
    $coordinator = makeCoordinator();
    $intern = makeCoordinatorIntern($coordinator);

    $this->actingAs($intern)
        ->post(route('intern.requirements.submit', 'clearance'), [
            'file' => UploadedFile::fake()->create('clearance-signed.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect(route('intern.requirements.index'))
        ->assertSessionHas('status');

    $document = SubmittedDocument::firstOrFail();
    expect($document->type)->toBe('clearance')
        ->and($document->status)->toBe('pending')
        ->and($coordinator->notifications()->where('type', \App\Notifications\DocumentSubmitted::class)->exists())->toBeTrue();

    // The intern's own Requirements page lists it as pending.
    $this->actingAs($intern)->get(route('intern.requirements.index'))
        ->assertOk()
        ->assertSee('Pending review')
        ->assertSee('clearance-signed.pdf');
});

test('an invalid file type is refused', function () {
    $intern = makeIntern();

    $this->actingAs($intern)
        ->post(route('intern.requirements.submit', 'clearance'), [
            'file' => UploadedFile::fake()->create('evil.exe', 10),
        ])
        ->assertSessionHasErrors('file');

    expect(SubmittedDocument::count())->toBe(0);
});

test('the coordinator approves a document and it clears the requirement', function () {
    Storage::fake('public');
    $coordinator = makeCoordinator();
    $intern = makeCoordinatorIntern($coordinator);

    $intern->submittedDocuments()->create([
        'type' => 'clearance',
        'file_path' => 'submissions/x/file.pdf',
        'original_name' => 'file.pdf',
        'status' => 'pending',
    ]);
    Storage::disk('public')->put('submissions/x/file.pdf', 'pdf');

    $document = SubmittedDocument::firstOrFail();

    $this->actingAs($coordinator)
        ->post(route('monitor.documents.review', $document), [
            'decision' => 'approved',
            'remarks' => 'Complete and signed.',
        ])
        ->assertRedirect();

    expect($document->fresh()->status)->toBe('approved')
        ->and($document->fresh()->reviewed_by)->toBe($coordinator->id)
        ->and($intern->notifications()->where('type', DocumentReviewed::class)->exists())->toBeTrue();

    // The intern's page shows the approved state — no longer lacking.
    $this->actingAs($intern)->get(route('intern.requirements.index'))
        ->assertOk()
        ->assertSee('✓ Approved')
        ->assertSee('Complete and signed.');
});

test('a rejected document stays listed with its remarks', function () {
    Storage::fake('public');
    $coordinator = makeCoordinator();
    $intern = makeCoordinatorIntern($coordinator);

    $intern->submittedDocuments()->create([
        'type' => 'clearance',
        'file_path' => 'submissions/x/file.pdf',
        'original_name' => 'file.pdf',
        'status' => 'pending',
    ]);
    $document = SubmittedDocument::firstOrFail();

    $this->actingAs($coordinator)
        ->post(route('monitor.documents.review', $document), [
            'decision' => 'rejected',
            'remarks' => 'Unsigned page 2 — resubmit.',
        ])
        ->assertRedirect();

    $this->actingAs($intern)->get(route('intern.requirements.index'))
        ->assertOk()
        ->assertSee('✕ Rejected')
        ->assertSee('Unsigned page 2 — resubmit.');

    // And it still counts as lacking.
    $this->actingAs($intern)->get(route('intern.requirements.index'))
        ->assertOk()
        ->assertSee('still lacking');
});

test('only the intern\'s coordinator or an admin can review a document', function () {
    $otherCoordinator = makeCoordinator();
    $coordinator = makeCoordinator();
    $intern = makeCoordinatorIntern($coordinator);

    $intern->submittedDocuments()->create([
        'type' => 'clearance',
        'file_path' => 'submissions/x/file.pdf',
        'original_name' => 'file.pdf',
        'status' => 'pending',
    ]);
    $document = SubmittedDocument::firstOrFail();

    $this->actingAs($otherCoordinator)
        ->post(route('monitor.documents.review', $document), ['decision' => 'approved', 'remarks' => 'ok'])
        ->assertForbidden();

    expect($document->fresh()->status)->toBe('pending');
});

// ---------- Transfer + consultation requests ----------

test('an intern requests an office transfer and the coordinator approves it', function () {
    $coordinator = makeCoordinator();
    $intern = makeCoordinatorIntern($coordinator);
    $office = Office::create(['name' => 'New Office', 'type' => 'internal']);

    $this->actingAs($intern)
        ->post(route('intern.coordinator-requests.store'), [
            'type' => 'office_transfer',
            'office_id' => $office->id,
            'message' => 'Closer to home, same hours.',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $request = InternRequest::firstOrFail();
    expect($request->type)->toBe('office_transfer')
        ->and($coordinator->notifications()->where('type', \App\Notifications\InternRequestSubmitted::class)->exists())->toBeTrue();

    // The decision moves the placement on the spot.
    $this->actingAs($coordinator)
        ->post(route('monitor.intern-requests.decide', $request), [
            'decision' => 'approved',
            'remarks' => 'Approved — report Monday.',
        ])
        ->assertRedirect();

    expect($intern->fresh()->office_id)->toBe($office->id)
        ->and($request->fresh()->status)->toBe('approved')
        ->and($intern->notifications()->where('type', \App\Notifications\InternRequestDecided::class)->exists())->toBeTrue();
});

test('an intern files a consultation request with a mode', function () {
    $coordinator = makeCoordinator();
    $intern = makeCoordinatorIntern($coordinator);

    $this->actingAs($intern)
        ->post(route('intern.coordinator-requests.store'), [
            'type' => 'consultation',
            'mode' => 'online',
            'message' => 'Can we talk about my timesheet this week?',
        ])
        ->assertRedirect();

    $request = InternRequest::firstOrFail();
    expect($request->type)->toBe('consultation')
        ->and($request->mode)->toBe('online')
        ->and($request->office_id)->toBeNull();
});

test('a transfer to the current office is refused', function () {
    $office = Office::create(['name' => 'Current Office', 'type' => 'internal']);
    $intern = makeIntern(['office_id' => $office->id]);

    $this->actingAs($intern)
        ->post(route('intern.coordinator-requests.store'), [
            'type' => 'office_transfer',
            'office_id' => $office->id,
            'message' => 'Same place?',
        ])
        ->assertSessionHasErrors('office_id');

    expect(InternRequest::count())->toBe(0);
});

// ---------- Class board ----------

test('the coordinator and their interns share one class board', function () {
    $coordinator = makeCoordinator();
    $intern = makeCoordinatorIntern($coordinator);

    ClassMessage::create(['coordinator_id' => $coordinator->id, 'user_id' => $coordinator->id, 'body' => 'Welcome to OJT, class!']);
    ClassMessage::create(['coordinator_id' => $coordinator->id, 'user_id' => $intern->id, 'body' => 'Good morning, ma\'am!']);

    $this->actingAs($intern)->get(route('class-board.index'))
        ->assertOk()
        ->assertSee('Welcome to OJT, class!')
        ->assertSee("Good morning, ma'am!");

    $this->actingAs($coordinator)->get(route('class-board.index'))
        ->assertOk()
        ->assertSee("Good morning, ma'am!");
});

test('an intern with no coordinator has no class board', function () {
    $intern = makeIntern();

    $this->actingAs($intern)->get(route('class-board.index'))->assertNotFound();
});

test('an intern posts to their own coordinator\'s board', function () {
    $coordinator = makeCoordinator();
    $intern = makeCoordinatorIntern($coordinator);
    $otherCoordinator = makeCoordinator();

    $this->actingAs($intern)
        ->post(route('class-board.store'), ['body' => 'Question about tomorrow.'])
        ->assertRedirect();

    expect(ClassMessage::where('coordinator_id', $coordinator->id)->where('body', 'Question about tomorrow.')->exists())->toBeTrue()
        ->and(ClassMessage::where('coordinator_id', $otherCoordinator->id)->exists())->toBeFalse();
});

// ---------- Program Chair ----------

test('a program chair sees the college\'s interns through their coordinators', function () {
    $coordinator = makeCoordinator();
    $coordinator->staffProfile()->create(['college_code' => 'cas']);
    $intern = makeCoordinatorIntern($coordinator, ['first_name' => 'College', 'last_name' => 'Insider']);

    $otherCoordinator = makeCoordinator();
    $otherCoordinator->staffProfile()->create(['college_code' => 'cba']);
    $outsider = makeCoordinatorIntern($otherCoordinator, ['first_name' => 'Other', 'last_name' => 'Outsider']);

    $chair = makeStaff(['role' => 'chair', 'profile_completed_at' => now()]);
    $chair->staffProfile()->create(['college_code' => 'cas']);

    expect($chair->isChair())->toBeTrue()
        ->and($chair->monitors($intern))->toBeTrue()
        ->and($chair->monitors($outsider))->toBeFalse();

    $this->actingAs($chair)->get(route('monitor.dashboard'))
        ->assertOk()
        ->assertSee('Insider, College')
        ->assertDontSee('Outsider');
});

test('a program chair cannot record manual entries or certify hours', function () {
    $coordinator = makeCoordinator();
    $coordinator->staffProfile()->create(['college_code' => 'cas']);
    $intern = makeCoordinatorIntern($coordinator, ['student_id' => 'T-7700']);

    $chair = makeStaff(['role' => 'chair', 'profile_completed_at' => now()]);
    $chair->staffProfile()->create(['college_code' => 'cas']);

    expect($chair->can('createLog', $intern))->toBeFalse()
        ->and($chair->can('certify', $intern))->toBeFalse()
        ->and($chair->can('monitor', $intern))->toBeTrue();
});

// ---------- One person, several hats ----------

test('one person can be Program Chair and office supervisor at once', function () {
    $office = Office::create(['name' => 'CSIT Faculty Office', 'type' => 'internal']);
    $chair = makeStaff(['role' => 'chair', 'office_id' => $office->id, 'profile_completed_at' => now()]);
    $chair->staffProfile()->create(['college_code' => 'cas']);

    // An intern placed at their office AND assigned to them as coordinator.
    $intern = makeIntern(['student_id' => 'T-7800', 'office_id' => $office->id, 'coordinator_id' => $chair->id]);
    makeActiveEnrollment($intern);

    expect($chair->monitors($intern))->toBeTrue()
        ->and($chair->supervisesOffice($office->id))->toBeTrue()
        ->and($chair->can('createLog', $intern))->toBeTrue()
        ->and($chair->can('certify', $intern))->toBeTrue();

    // Runs the desk station: scans their office's interns.
    $this->travelTo(now()->setTime(8, 3));
    $this->actingAs($chair)
        ->post(route('admin.kiosk.scan'), ['code' => $intern->scanQrPayload()])
        ->assertOk()
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time In']);

    // Decides their intern's transfer request — approval moves the placement.
    $target = Office::create(['name' => 'Other Office', 'type' => 'internal']);
    $request = InternRequest::create([
        'intern_id' => $intern->id,
        'type' => 'office_transfer',
        'office_id' => $target->id,
        'message' => 'Moving closer to home.',
        'status' => 'pending',
    ]);

    $this->post(route('monitor.intern-requests.decide', $request), [
        'decision' => 'approved',
        'remarks' => 'Approved — report Monday.',
    ])->assertRedirect();

    expect($intern->fresh()->office_id)->toBe($target->id)
        ->and($request->fresh()->status)->toBe('approved');
});

test('a staff announcement reaches the right interns only', function () {
    $coordinator = makeCoordinator();
    $mine = makeCoordinatorIntern($coordinator, ['student_id' => 'T-7901']);
    $otherIntern = makeIntern(['student_id' => 'T-7902']);

    $this->actingAs($coordinator)
        ->post(route('admin.announcements.store'), [
            'title' => 'No duty Monday',
            'body' => 'Campus holiday — stay home.',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $announcement = \App\Models\Announcement::firstOrFail();
    expect($announcement->audience)->toBe('class')
        ->and($announcement->coordinator_id)->toBe($coordinator->id);

    // The class's dashboard banners it; other interns never see it.
    $this->actingAs($mine)->get(route('intern.dashboard'))
        ->assertOk()
        ->assertSee('No duty Monday');
    $this->actingAs($otherIntern)->get(route('intern.dashboard'))
        ->assertOk()
        ->assertDontSee('No duty Monday');

    // And the inbox notification arrived.
    expect($mine->notifications()->where('type', \App\Notifications\AnnouncementPublished::class)->exists())->toBeTrue()
        ->and($otherIntern->notifications()->count())->toBe(0);
});
