<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $crewId,
        public string $crewName,
        public string $trainingName,
        public string $dueDate,
        public string $reminderLabel,
        public string $stage,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Pengingat training: {$this->crewName} - {$this->trainingName}")
            ->greeting("Pengingat Training Crew: {$this->crewName}")
            ->line("Training {$this->trainingName} {$this->reminderLabel}.")
            ->line("Tanggal kedaluwarsa: {$this->dueDate}")
            ->action('Lihat training', url("/training/crews/{$this->crewId}"));
    }

    /** @return array<string, int|string> */
    public function toArray(object $notifiable): array
    {
        return [
            'crew_id' => $this->crewId,
            'crew_name' => $this->crewName,
            'training_name' => $this->trainingName,
            'due_date' => $this->dueDate,
            'reminder_label' => $this->reminderLabel,
            'stage' => $this->stage,
            'url' => "/training/crews/{$this->crewId}",
        ];
    }
}
