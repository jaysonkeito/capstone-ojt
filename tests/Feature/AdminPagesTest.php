<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Every admin page renders. This is the safety net for template-level
 * regressions (a deleted route, a missing view variable) that data-level
 * tests don't catch — exactly how the old dashboard broke when the staff
 * module was removed.
 */

test('all admin pages render for an admin', function () {
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    makeLog($intern, $intern->currentEnrollment, times: ['am_time_in' => '08:00', 'am_time_out' => '12:00']);

    $admin = makeStaff(['role' => 'admin']);
    $this->actingAs($admin);

    $pages = [
        ['admin.dashboard'],
        ['admin.approvals.index'],
        ['admin.interns.index'],
        ['admin.interns.create'],
        ['admin.interns.edit', ['intern' => $intern]],
        ['admin.logs.show', ['intern' => $intern]],
        ['admin.logs.index'],
        ['admin.document-templates.index'],
        ['admin.settings.edit'],
    ];

    foreach ($pages as $page) {
        $url = route($page[0], $page[1] ?? []);
        $status = $this->get($url)->status();

        if ($status !== 200) {
            $this->fail("{$url} returned {$status}");
        }
    }

    expect(true)->toBeTrue();
});

test('admin pages are closed to interns', function () {
    $intern = makeIntern();

    $this->actingAs($intern)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($intern)->get(route('admin.interns.index'))->assertForbidden();
    $this->actingAs($intern)->get(route('admin.settings.edit'))->assertForbidden();
});

test('the intern history page links to the timesheet and shows a Daily Report column', function () {
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    makeLog($intern, $intern->currentEnrollment, times: ['am_time_in' => '08:00', 'am_time_out' => '12:00']);

    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.logs.show', ['intern' => $intern]))
        ->assertOk()
        ->assertSee(route('admin.interns.timesheet', $intern))
        ->assertSee('Daily Report')
        ->assertDontSee('Monthly Summary');
});
