<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingExpiryNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{training_name: string, due_date: string, reminder_label: string, stage: string, crew_name?: string}>  $trainings
     */
    public function __construct(
        public ?int $crewId,
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

        /** @var array<string, array{training_name: string, due_date: string, crew_names: array<int, string>}> $trainingGroups */
        $trainingGroups = [];
        foreach ($this->trainings as $training) {
            $groupKey = $training['training_name'].'|'.$training['due_date'];
            $trainingGroups[$groupKey]['training_name'] = $training['training_name'];
            $trainingGroups[$groupKey]['due_date'] = $training['due_date'];
            $trainingGroups[$groupKey]['crew_names'][] = $training['crew_name'] ?? $this->crewName;
        }

        foreach ($trainingGroups as $trainingGroup) {
            $message->line(
                strtoupper($trainingGroup['training_name']).' '.$this->formatDueDate($trainingGroup['due_date']).':',
            );

            foreach (array_unique($trainingGroup['crew_names']) as $crewName) {
                $message->line("- {$crewName}");
            }
        }

        $url = $this->crewId === null
            ? url('/training')
            : url("/training/crews/{$this->crewId}");

        return $message->action('Lihat training', $url);
    }

    /** @return array<string, int|string|null|array<int, array{training_name: string, due_date: string, reminder_label: string, stage: string, crew_name?: string}>> */
    public function toArray(object $notifiable): array
    {
        return [
            'crew_id' => $this->crewId,
            'crew_name' => $this->crewName,
            'trainings' => $this->trainings,
            'url' => $this->crewId === null
                ? '/training'
                : "/training/crews/{$this->crewId}",
        ];
    }

    private function formatDueDate(string $dueDate): string
    {
        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $dueDate);

        if ($date === false) {
            throw new \InvalidArgumentException("Invalid training reminder date [{$dueDate}].");
        }

        return strtoupper($date->format('d M Y'));
    }
}
