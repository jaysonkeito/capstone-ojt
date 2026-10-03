<?php

use App\Models\DeviceToken;
use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;

uses(RefreshDatabase::class);

/*
 * Android push registration: the app posts its FCM token after login and
 * deletes it on logout. Tokens are device-scoped — a phone moving between
 * accounts follows the newest owner — and the fcm notification channel must
 * be a silent no-op until Firebase credentials are configured.
 */

test('guests cannot register a device token', function () {
    $this->postJson('/devices', ['token' => 'fcm-token-1'])->assertUnauthorized();
});

test('the app registers a device token for the signed-in user', function () {
    $intern = makeIntern();

    $this->actingAs($intern)
        ->postJson('/devices', ['token' => 'fcm-token-1', 'platform' => 'android'])
        ->assertOk()
        ->assertJson(['registered' => true]);

    expect(DeviceToken::where('token', 'fcm-token-1')->first())
        ->user_id->toBe($intern->id)
        ->platform->toBe('android');
});

test('re-registering the same device does not duplicate the token', function () {
    $intern = makeIntern();

    foreach ([1, 2] as $i) {
        $this->actingAs($intern)
            ->postJson('/devices', ['token' => 'fcm-token-1'])
            ->assertOk();
    }

    expect(DeviceToken::where('token', 'fcm-token-1')->count())->toBe(1);
});

test('a token follows the newest user when a phone changes hands', function () {
    $alice = makeIntern(['student_id' => 'T-2001']);
    $bob = makeIntern(['student_id' => 'T-2002']);

    $this->actingAs($alice)->postJson('/devices', ['token' => 'shared-phone'])->assertOk();

    $this->actingAs($bob)->postJson('/devices', ['token' => 'shared-phone'])->assertOk();

    $token = DeviceToken::where('token', 'shared-phone')->first();

    expect($token->user_id)->toBe($bob->id)
        ->and(DeviceToken::where('token', 'shared-phone')->count())->toBe(1);
});

test('a user can unregister their own device but not someone else\'s', function () {
    $owner = makeIntern(['student_id' => 'T-3001']);
    $other = makeIntern(['student_id' => 'T-3002']);

    $own = DeviceToken::create(['user_id' => $owner->id, 'token' => 'token-own', 'registered_at' => now()]);
    DeviceToken::create(['user_id' => $other->id, 'token' => 'token-other', 'registered_at' => now()]);

    $this->actingAs($owner)->deleteJson('/devices', ['token' => 'token-other'])->assertOk();
    expect(DeviceToken::where('token', 'token-other')->exists())->toBeTrue();

    $this->actingAs($owner)->deleteJson('/devices', ['token' => 'token-own'])->assertOk();
    expect(DeviceToken::where('token', 'token-own')->exists())->toBeFalse();
});

test('routeNotificationForFcm returns the user\'s registration tokens', function () {
    $intern = makeIntern();
    DeviceToken::create(['user_id' => $intern->id, 'token' => 'tok-a', 'registered_at' => now()]);
    DeviceToken::create(['user_id' => $intern->id, 'token' => 'tok-b', 'registered_at' => now()]);

    expect($intern->routeNotificationForFcm(new class extends Notification {}))
        ->toContain('tok-a')
        ->toContain('tok-b');
});

test('the fcm channel is a silent no-op without Firebase credentials', function () {
    // Test config has no services.fcm.credentials — sending a review
    // notification must neither throw nor block the database notification.
    $intern = makeIntern();
    $reviewer = makeStaff(['role' => 'supervisor']);
    $log = makeLog($intern, makeActiveEnrollment($intern));

    $intern->notify(new App\Notifications\LogReviewed($log, 'approved', null, $reviewer));

    expect($intern->fresh()->notifications()->count())->toBe(1)
        ->and($intern->fresh()->unreadNotifications()->count())->toBe(1);
});

test('deleting a user for good removes their registered devices', function () {
    $intern = makeIntern();
    DeviceToken::create(['user_id' => $intern->id, 'token' => 'gone-soon', 'registered_at' => now()]);

    // Soft delete is restorable, so tokens survive it; only force-delete
    // (account erased permanently) triggers the FK cascade.
    $intern->delete();
    expect(DeviceToken::where('token', 'gone-soon')->exists())->toBeTrue();

    $intern->forceDelete();
    expect(DeviceToken::where('token', 'gone-soon')->exists())->toBeFalse();
});
