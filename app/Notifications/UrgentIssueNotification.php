<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * In-app (database) notification for a high-severity issue. No email, SMS or push is ever sent.
 */
class UrgentIssueNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $payload  dedupe_key, issue_id, title, severity, department_name, summary_line, url
     */
    public function __construct(private readonly array $payload) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
