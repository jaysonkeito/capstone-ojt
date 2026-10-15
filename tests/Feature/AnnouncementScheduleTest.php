<?php

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publishAnnouncement(object $test, array $dates = []): Announcement
{
    $admin = makeStaff(['role' => 'admin']);

    $test->actingAs($admin)->post(route('admin.announcements.store'), [
        'title' => 'Range title',
        'body' => 'Range body.',
        ...$dates,
    ])->assertRedirect(route('admin.announcements.index'));

    return Announcement::firstOrFail();
}

it('removes the banner by itself once the window ends', function () {
    $intern = makeIntern();

    publishAnnouncement($this, [
        'starts_at' => today()->subDays(2)->toDateString(),
        'ends_at' => today()->subDay()->toDateString(),
    ]);

    expect(Announcement::currentlyShowing()->count())->toBe(0);

    $this->actingAs($intern)->get(route('intern.dashboard'))
        ->assertOk()
        ->assertDontSee('Range title');
});

it('keeps a scheduled announcement hidden until its start date', function () {
    $intern = makeIntern();

    publishAnnouncement($this, ['starts_at' => today()->addWeek()->toDateString()]);

    expect(Announcement::currentlyShowing()->count())->toBe(0);

    $this->actingAs($intern)->get(route('intern.dashboard'))
        ->assertOk()
        ->assertDontSee('Range title');
});

it('shows an in-window announcement and defaults an open start to today', function () {
    $intern = makeIntern();

    $announcement = publishAnnouncement($this, ['ends_at' => today()->addDays(3)->toDateString()]);

    expect($announcement->starts_at->toDateString())->toBe(today()->toDateString())
        ->and(Announcement::currentlyShowing()->count())->toBe(1);

    $this->actingAs($intern)->get(route('intern.dashboard'))
        ->assertOk()
        ->assertSee('Range title');
});

it('keeps a windowless announcement showing indefinitely', function () {
    $intern = makeIntern();

    publishAnnouncement($this);

    expect(Announcement::currentlyShowing()->count())->toBe(1);

    $this->actingAs($intern)->get(route('intern.dashboard'))
        ->assertOk()
        ->assertSee('Range title');
});

it('rejects a window that ends before it starts', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.announcements.store'), [
        'title' => 'Backwards',
        'body' => 'The window runs the wrong way.',
        'starts_at' => today()->toDateString(),
        'ends_at' => today()->subDay()->toDateString(),
    ])->assertInvalid('ends_at');
});

it('marks ended posts on the staff history list', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.announcements.store'), [
        'title' => 'Past post',
        'body' => 'Already over.',
        'starts_at' => today()->subDays(5)->toDateString(),
        'ends_at' => today()->subDay()->toDateString(),
    ])->assertRedirect();

    $this->actingAs($admin)->get(route('admin.announcements.index'))
        ->assertOk()
        ->assertSee('Ended')
        ->assertSee('shows '.today()->subDays(5)->format('M d').' – '.today()->subDay()->format('M d, Y'));
});
