<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingExpiryNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{training_name: string, due_date: string, reminder_label: string, stage: string}>  $trainings
     */
    public function __construct(
        public int $crewId,
        public string $crewName,
        public array $trainings,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Pengingat training crew: {$this->crewName}")
            ->greeting("Pengingat Training Crew: {$this->crewName}")
            ->line('Training berikut perlu diperhatikan:');

        foreach ($this->trainings as $training) {
            $message->line("- {$training['training_name']}: {$training['reminder_label']} (jatuh tempo {$training['due_date']})");
        }

        return $message->action('Lihat training', url("/training/crews/{$this->crewId}"));
    }

    /** @return array<string, int|string|array<int, array{training_name: string, due_date: string, reminder_label: string, stage: string}>> */
    public function toArray(object $notifiable): array
    {
        return [
            'crew_id' => $this->crewId,
            'crew_name' => $this->crewName,
            'trainings' => $this->trainings,
            'url' => "/training/crews/{$this->crewId}",
        ];
    }
}
