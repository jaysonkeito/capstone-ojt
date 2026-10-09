<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmMessage;

/**
 * Laravel notification channel that delivers a notification to every Android
 * device the recipient has registered (the OJT Tracker app).
 *
 * Designed to be safe when Firebase is not set up yet: with no credentials
 * configured (or no device tokens, or an FCM error) it logs and returns —
 * a push must never break the database notification that lands alongside it.
 *
 * Registered as the "fcm" channel in AppServiceProvider.
 */
class FcmChannel
{
    private ?Messaging $messaging = null;

    /**
     * Human-readable copy per notification payload kind, so the phone shows
     * "Your duty entry was approved" instead of raw JSON. Each notification
     * class may also define its own toFcm($notifiable) to override this.
     *
     * @var array<string, string>
     */
    private const KIND_TITLES = [
        'log_reviewed' => 'Duty entry reviewed',
        'log_flagged' => 'Duty entry flagged',
        'manual_entry_submitted' => 'Manual time entry',
        'request_submitted' => 'New request',
        'request_decided' => 'Request decided',
        'attendance_request_submitted' => 'Attendance request',
        'attendance_request_decided' => 'Attendance request decided',
        'timesheet_certified' => 'Timesheet certified',
        'template_changed' => 'Document template changed',
        'scan_recorded' => 'Time recorded',
    ];

    public function send($notifiable, Notification $notification): void
    {
        $tokens = $notifiable->routeNotificationForFcm($notification);

        if ($tokens === [] || ! $this->credentialsConfigured()) {
            return;
        }

        $payload = method_exists($notification, 'toFcm')
            ? $notification->toFcm($notifiable)
            : $this->fromDatabasePayload($notifiable, $notification);

        if ($payload === null) {
            return;
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(FcmMessage::create($payload['title'], $payload['body']))
                ->withData([
                    'kind' => (string) $payload['kind'],
                    'url' => (string) ($payload['url'] ?? '/notifications'),
                ]);

            $report = $this->messaging()->sendEachForMulticast($message, $tokens);

            // Prune tokens FCM has permanently retired (app uninstalled,
            // token rotated) so the fan-out list stays clean.
            $dead = collect($report->getItems())
                ->filter(fn ($item) => $item->getError()?->isTokenNotRegistered() ?? false)
                ->keys();

            if ($dead->isNotEmpty()) {
                $notifiable->deviceTokens()->whereIn('token', $dead)->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('FCM push failed: '.$e->getMessage(), [
                'user_id' => $notifiable->getKey() ?? null,
                'kind' => $payload['kind'] ?? null,
            ]);
        }
    }

    /**
     * Derives title/body from the toArray() payload every notification class
     * already provides (its "kind" key picks the title; the payload's text
     * fields become the body).
     */
    private function fromDatabasePayload(object $notifiable, Notification $notification): ?array
    {
        if (! method_exists($notification, 'toArray')) {
            return null;
        }

        $data = $notification->toArray($notifiable);
        $kind = (string) ($data['kind'] ?? '');

        if ($kind === '' || ! isset(self::KIND_TITLES[$kind])) {
            return null;
        }

        return [
            'kind' => $kind,
            'title' => self::KIND_TITLES[$kind],
            'body' => $this->bodyFor($kind, $data),
            'url' => $data['url'] ?? '/notifications',
        ];
    }

    private function bodyFor(string $kind, array $data): string
    {
        return match ($kind) {
            'log_reviewed' => $data['decision'] === 'approved'
                ? 'Your duty entry for '.($data['date'] ?? '').' was approved.'
                : 'Your duty entry for '.($data['date'] ?? '').' was rejected: '.($data['comment'] ?? ''),
            'log_flagged' => 'Your entry for '.($data['date'] ?? '').' was flagged — open the app for details.',
            'manual_entry_submitted' => ($data['intern'] ?? 'An intern').' needs your review on a manual time entry.',
            'request_submitted' => ($data['intern'] ?? 'An intern').' submitted a '.($data['request_kind'] ?? 'request').' for your decision.',
            'request_decided' => 'Your '.($data['request_kind'] ?? 'request').' was '.($data['decision'] ?? 'decided').'.',
            'attendance_request_submitted' => ($data['intern'] ?? 'An intern').' submitted an attendance request.',
            'attendance_request_decided' => 'Your attendance request was '.($data['decision'] ?? 'decided').'.',
            'timesheet_certified' => ($data['certified_by'] ?? 'Your supervisor').' certified '.($data['period'] ?? 'a timesheet period').'.',
            'template_changed' => ($data['by'] ?? 'A coordinator').' '.$data['action'].' the '.($data['form'] ?? 'document').' template for '.($data['college'] ?? 'your college').'.',
            'scan_recorded' => ($data['slot'] ?? 'Time').' recorded at '.($data['recorded_at'] ?? '').' ('.($data['date'] ?? '').').',
            default => 'You have a new update in OJT Tracker.',
        };
    }

    private function credentialsConfigured(): bool
    {
        return (bool) config('services.fcm.credentials');
    }

    private function messaging(): Messaging
    {
        if ($this->messaging === null) {
            $this->messaging = (new Factory)
                ->withServiceAccount(config('services.fcm.credentials'))
                ->createMessaging();
        }

        return $this->messaging;
    }
}
