<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An announcement the intern's coordinator / supervisor / dean (or the
 * System Admin) posted — lands in their inbox and on their phone.
 */
class AnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(
        public Announcement $announcement,
        public string $authorName,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'fcm'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'announcement',
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'body' => \Illuminate\Support\Str::limit($this->announcement->body, 160),
            'author' => $this->authorName,
        ];
    }
}
