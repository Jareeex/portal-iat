<?php

namespace App\Console\Commands;

use App\Models\CrewTraining;
use App\Models\Page;
use App\Models\User;
use App\Notifications\TrainingExpiryNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('app:send-training-reminders')]
#[Description('Send crew training expiry reminders')]
class SendTrainingReminders extends Command
{
    private const array REMINDER_LABELS = [
        'two_months' => 'akan kedaluwarsa dalam 2 bulan',
        'one_month' => 'akan kedaluwarsa dalam 1 bulan',
        'one_week' => 'akan kedaluwarsa dalam 1 minggu',
        'expired_daily' => 'sudah kedaluwarsa',
    ];

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $trainingPageId = Page::query()->where('key', 'training')->value('id');
        $remindersByCrew = [];
        $sentCount = 0;

        CrewTraining::query()
            ->whereNotNull('next_check')
            ->with(['crew.user', 'type'])
            ->chunkById(200, function ($records) use ($today, $trainingPageId, &$remindersByCrew): void {
                foreach ($records as $record) {
                    if ($record->next_check === null || $record->crew === null || $record->type === null) {
                        continue;
                    }

                    $dueDate = CarbonImmutable::parse($record->next_check)->startOfDay();
                    $stage = $this->reminderStage($today, $dueDate);

                    if ($stage === null) {
                        continue;
                    }

                    foreach ($this->recipients($record, $trainingPageId) as $recipient) {
                        $reminderLog = $record->reminderLogs()
                            ->whereDate('due_date', $dueDate->toDateString())
                            ->where('stage', $stage)
                            ->where('recipient_user_id', $recipient->id)
                            ->first();

                        if ($reminderLog !== null
                            && ($stage !== 'expired_daily' || $reminderLog->updated_at?->toDateString() === $today->toDateString())) {
                            continue;
                        }

                        $remindersByCrew[$record->crew_id]['crew'] = $record->crew;
                        $remindersByCrew[$record->crew_id]['recipients'][$recipient->id]['recipient'] = $recipient;
                        $remindersByCrew[$record->crew_id]['recipients'][$recipient->id]['reminders'][] = [
                            'record' => $record,
                            'due_date' => $dueDate,
                            'log' => $reminderLog,
                            'stage' => $stage,
                        ];
                    }
                }
            });

        foreach ($remindersByCrew as $crewReminder) {
            foreach ($crewReminder['recipients'] as $recipientReminder) {
                $recipient = $recipientReminder['recipient'];
                $reminderItems = $recipientReminder['reminders'];

                $recipient->notify(new TrainingExpiryNotification(
                    crewId: $crewReminder['crew']->id,
                    crewName: $crewReminder['crew']->name,
                    trainings: array_map(
                        fn (array $reminder): array => [
                            'training_name' => $reminder['record']->type->name,
                            'due_date' => $reminder['due_date']->format('d/m/Y'),
                            'reminder_label' => self::REMINDER_LABELS[$reminder['stage']],
                            'stage' => $reminder['stage'],
                        ],
                        $reminderItems,
                    ),
                ));

                foreach ($reminderItems as $reminder) {
                    if ($reminder['log'] === null) {
                        $reminder['record']->reminderLogs()->create([
                            'recipient_user_id' => $recipient->id,
                            'due_date' => $reminder['due_date']->toDateString(),
                            'stage' => $reminder['stage'],
                        ]);
                    } else {
                        $reminder['log']->touch();
                    }
                }

                $sentCount++;
            }
        }

        $this->info("Sent {$sentCount} training reminder notification(s).");

        return self::SUCCESS;
    }

    private function reminderStage(CarbonImmutable $today, CarbonImmutable $dueDate): ?string
    {
        return match (true) {
            $today->equalTo($dueDate->subMonthsNoOverflow(2)) => 'two_months',
            $today->equalTo($dueDate->subMonthNoOverflow()) => 'one_month',
            $today->equalTo($dueDate->subDays(7)) => 'one_week',
            $today->greaterThan($dueDate) => 'expired_daily',
            default => null,
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(CrewTraining $record, ?int $trainingPageId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($trainingPageId, $record): void {
                $query->where('role', User::ROLE_MASTER);

                if ($trainingPageId !== null) {
                    $query->orWhereHas('pages', function (Builder $pageQuery) use ($trainingPageId): void {
                        $pageQuery->whereKey($trainingPageId)
                            ->where('user_page_access.access_level', 'edit');
                    });
                }

                $query->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('has_full_access', true));

                if ($record->crew?->user_id !== null) {
                    $query->orWhereKey($record->crew->user_id);
                }
            })
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->filter(fn (User $user): bool => filter_var(trim($user->email), FILTER_VALIDATE_EMAIL) !== false)
            ->values();
    }
}
