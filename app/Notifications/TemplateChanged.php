<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the System Admins and the other OJT Coordinators when a
 * coordinator changes a document template (uploads a new design or removes
 * one) — changes made by a System Admin are the authority and stay silent.
 * The in-app inbox is the primary surface; the fcm channel fans out to
 * registered phones.
 */
class TemplateChanged extends Notification
{
    use Queueable;

    public function __construct(
        public string $action, // 'uploaded' | 'removed'
        public string $formLabel,
        public string $collegeName,
        public User $actor,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'fcm'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'template_changed',
            'action' => $this->action,
            'form' => $this->formLabel,
            'college' => $this->collegeName,
            'by' => $this->actor->full_name,
        ];
    }
}
