<?php

namespace App\Notifications;

use App\Models\ClassMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A new message on the intern's class board — inbox + phone, unless the
 * member muted the class.
 */
class ClassMessagePosted extends Notification
{
    use Queueable;

    public function __construct(
        public ClassMessage $message,
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
            'kind' => 'class_message',
            'message_id' => $this->message->id,
            'author' => $this->authorName,
            'preview' => \Illuminate\Support\Str::limit($this->message->body, 120),
            'url' => route('class-board.index'),
        ];
    }
}
