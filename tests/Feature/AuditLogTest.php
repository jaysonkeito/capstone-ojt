<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = makeStaff(['role' => 'admin']);
    $this->actingAs($this->admin);
});

/*
 * The System Admin's activity trail: every create/update/delete on the
 * tracked records lands in audit_logs with a snapshot of who did it —
 * including a field-level diff for updates and sign-in entries. Only the
 * System Admin can open the page; the JSON entries endpoint feeds the live
 * polling. Secrets never reach the trail.
 */

test('the activity log page is admin-only', function () {
    $this->actingAs(makeIntern())->get(route('admin.audit-log.index'))->assertForbidden();
    $this->actingAs(makeStaff(['role' => 'coordinator']))->get(route('admin.audit-log.index'))->assertForbidden();
    $this->actingAs(makeStaff(['role' => 'supervisor']))->get(route('admin.audit-log.index'))->assertForbidden();

    $this->actingAs($this->admin)->get(route('admin.audit-log.index'))->assertOk()->assertSee('Activity Log');
});

test('creating an intern records who created it', function () {
    $intern = makeIntern(['first_name' => 'Trail', 'last_name' => 'Blazer']);

    $entry = AuditLog::where('action', 'created')
        ->where('subject_type', 'User')
        ->where('subject_id', $intern->id)
        ->firstOrFail();

    expect($entry->user_id)->toBe($this->admin->id)
        ->and($entry->user_name)->toBe($this->admin->full_name)
        ->and($entry->subject_label)->toContain('Blazer, Trail');
});

test('updating a record writes the field level old and new values', function () {
    $intern = makeIntern(['first_name' => 'Old']);

    $intern->update(['first_name' => 'New', 'email' => $intern->email]);

    $entry = AuditLog::where('action', 'updated')->where('subject_id', $intern->id)->latest('id')->firstOrFail();
    $changes = $entry->changes;

    expect($changes['first_name']['old'])->toBe('Old')
        ->and($changes['first_name']['new'])->toBe('New')
        // Timestamp-only touches are not noise on the trail.
        ->and($entry->changes)->not->toHaveKey('updated_at');
});

test('secrets never reach the trail', function () {
    $intern = makeIntern();
    $intern->update(['password' => Hash::make('a-brand-new-secret')]);

    $entries = AuditLog::where('subject_type', 'User')->get();

    expect($entries->whereNotNull('changes')->flatMap(fn ($e) => array_keys($e->changes))->contains('password'))->toBeFalse();
});

test('sign in and sign out land on the trail', function () {
    $intern = makeIntern();
    Auth::login($intern);
    Auth::logout();

    expect(AuditLog::where('action', 'logged-in')->where('user_id', $intern->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'logged-out')->where('user_id', $intern->id)->exists())->toBeTrue();
});

test('the live entries endpoint returns only rows newer than the cursor', function () {
    makeIntern(['first_name' => 'First', 'student_id' => 'T-AL-1']);
    $latest = AuditLog::latest('id')->value('id');

    makeIntern(['first_name' => 'Second', 'student_id' => 'T-AL-2']);

    $rows = $this->get(route('admin.audit-log.entries', ['after' => $latest]))
        ->assertOk();

    expect(substr_count($rows->getContent(), 'audit-row'))->toBeGreaterThan(0)
        ->and($rows->getContent())->toContain('Second')
        ->and($rows->getContent())->not->toContain('T-AL-1');
});

test('the trail survives deleting the user who made the change', function () {
    $staff = makeStaff(['role' => 'coordinator']);
    $this->actingAs($staff)->post(route('admin.staff.store'), [
        'role' => 'coordinator',
        'first_name' => 'Short',
        'last_name' => 'Lived',
        'email' => 'shortlived@norsubscojt.online',
        'password' => 'password123',
        'college_code' => 'cas',
    ]);

    $staff->forceDelete();

    $entry = AuditLog::where('subject_type', 'User')->where('action', 'created')->latest('id')->firstOrFail();
    // The trail keeps the acting user's id as a snapshot even though the
    // account is gone — the name column is what the page shows.
    expect($entry->user_name)->toBe($staff->full_name)
        ->and(AuditLog::find($entry->id)->user_name)->toBe($staff->full_name);
});
