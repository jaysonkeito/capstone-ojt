<?php

use App\Models\User;
use App\Notifications\ScanRecorded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;

uses(RefreshDatabase::class);

/*
 * The notification inbox: punch confirmations render with their details,
 * and every user can delete their own notifications (the swipe/delete
 * button UI rides the same route).
 */

test('a recorded punch notification shows the slot, time, and date', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: ['am_time_in' => '08:00']);

    $intern->notify(new ScanRecorded($log, 'AM Time In', '8:00 AM'));

    $this->actingAs($intern)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('AM Time In recorded at 8:00 AM on '.now()->format('M d, Y').'.')
        ->assertDontSee('You have a new notification.');
});

test('a user can delete their own notification', function () {
    $intern = makeIntern();
    $intern->notify(new ScanRecorded(
        makeLog($intern, makeActiveEnrollment($intern), times: ['am_time_in' => '08:00']),
        'AM Time In',
        '8:00 AM',
    ));

    $notification = $intern->notifications()->firstOrFail();

    $this->actingAs($intern)
        ->delete(route('notifications.destroy', $notification))
        ->assertRedirect();

    expect($intern->notifications()->whereKey($notification->getKey())->exists())->toBeFalse();
});

test('a user cannot delete someone else\'s notification', function () {
    $owner = makeIntern();
    $owner->notify(new ScanRecorded(
        makeLog($owner, makeActiveEnrollment($owner), times: ['am_time_in' => '08:00']),
        'AM Time In',
        '8:00 AM',
    ));

    $notification = $owner->notifications()->firstOrFail();
    $stranger = makeIntern(['student_id' => 'T-5555']);

    $this->actingAs($stranger)
        ->delete(route('notifications.destroy', $notification))
        ->assertNotFound();

    expect($owner->notifications()->count())->toBe(1);
});
