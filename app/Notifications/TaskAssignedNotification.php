<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Notification;

/**
 * In-app (database) assignment notification, written synchronously inside the approval transaction so it exists only
 * if the task does. No email, SMS, push or WhatsApp. Carries no transcript, evidence or provider data.
 */
class TaskAssignedNotification extends Notification
{
    public function __construct(private readonly Task $task, private readonly string $assignedByName, private readonly string $departmentName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'dedupe_key' => "task_assigned:{$this->task->id}:{$notifiable->id}",
            'task_id' => $this->task->id,
            'title' => 'New task: '.$this->task->title,
            'due_date' => $this->task->due_date->format('Y-m-d'),
            'assigned_by_name' => $this->assignedByName,
            'department_name' => $this->departmentName,
            'summary_line' => "Assigned by {$this->assignedByName}, due ".$this->task->due_date->format('j M Y').'.',
            'url' => route('tasks.show', $this->task, absolute: false),
        ];
    }
}
