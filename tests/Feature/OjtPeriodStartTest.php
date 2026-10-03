<?php

use App\Models\DocumentTemplate;
use App\Models\OjtSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The application letter's "from … until I complete my required hours" line.
 * The start month is per-intern (their enrollment's start date), falling back
 * to the campus-wide OJT period start configured under Settings.
 */

test('the OJT period start is configurable from Settings', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('admin.settings.edit'))
        ->put(route('admin.settings.update'), [
            'am_time_in' => '08:00',
            'am_time_out' => '12:00',
            'pm_time_in' => '13:00',
            'pm_time_out' => '17:00',
            'grace_period_minutes' => 15,
            'working_days' => [1, 2, 3, 4, 5],
            'training_starts_on' => '2026-08-01',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(OjtSetting::current()->training_starts_on?->toDateString())->toBe('2026-08-01');
});

test('creating an intern records their training start date', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.interns.store'), [
            'student_id' => 'T-3001',
            'first_name' => 'Sample',
            'last_name' => 'Intern',
            'email' => '',
            'ojt_track' => 'internship',
            'target_hours' => 500,
            'ojt_status' => 'active',
            'department' => '',
            'year_level' => '',
            'batch' => '',
            'office_id' => '',
            'coordinator_id' => '',
            'password' => '',
            'training_starts_on' => '2026-09-01',
        ])
        ->assertRedirect(route('admin.interns.index'))
        ->assertSessionHasNoErrors();

    $intern = User::where('student_id', 'T-3001')->first();

    expect($intern)->not->toBeNull()
        ->and($intern->currentEnrollment->started_at?->toDateString())->toBe('2026-09-01');
});

test('the application letter start month follows the intern enrollment start', function () {
    $intern = makeIntern(['first_name' => 'Ana', 'last_name' => 'Lopez', 'student_id' => '202377777']);
    makeActiveEnrollment($intern)->update(['started_at' => '2027-01-15']);

    $xml = docxDocumentXml(
        $this->actingAs($intern)
            ->get(route('intern.requirements.download', DocumentTemplate::TYPE_APPLICATION_LETTER))
            ->assertOk()
            ->getContent()
    );

    // An off-cycle start prints its own month — never the campus-wide default.
    expect($xml)->toContain('January 2027 until I complete my required 500 training hours')
        ->and($xml)->not->toContain('August 2026 until')
        ->and($xml)->not->toContain('${ojt_period_start}');
});

test('the letter falls back to the configured period start when no enrollment start exists', function () {
    OjtSetting::current()->update(['training_starts_on' => '2026-08-01']);

    // makeIntern does not create an enrollment — no start date on file.
    $intern = makeIntern(['first_name' => 'Ben', 'last_name' => 'Reyes', 'student_id' => '202388888']);

    $xml = docxDocumentXml(
        $this->actingAs($intern)
            ->get(route('intern.requirements.download', DocumentTemplate::TYPE_APPLICATION_LETTER))
            ->assertOk()
            ->getContent()
    );

    expect($xml)->toContain('August 2026 until I complete my required 500 training hours')
        ->and($xml)->not->toContain('${ojt_period_start}');
});
