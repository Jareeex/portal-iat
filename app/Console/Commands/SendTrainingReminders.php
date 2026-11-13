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
        'sixty_days' => 'akan kedaluwarsa dalam 60 hari',
        'thirty_days' => 'akan kedaluwarsa dalam 30 hari',
        'fourteen_days' => 'akan kedaluwarsa dalam 14 hari',
        'seven_days' => 'akan kedaluwarsa dalam 7 hari',
        'three_days' => 'akan kedaluwarsa dalam 3 hari',
        'english_proficiency_due' => 'jatuh tempo hari ini',
        'expired_daily' => 'sudah kedaluwarsa',
    ];

    public function handle(): int
    {
        $today = CarbonImmutable::today();
        $trainingPageId = Page::query()->where('key', 'training')->value('id');
        $remindersByRecipient = [];
        $sentCount = 0;

        CrewTraining::query()
            ->whereNotNull('next_check')
            ->with(['crew.user', 'type'])
            ->chunkById(200, function ($records) use ($today, $trainingPageId, &$remindersByRecipient): void {
                foreach ($records as $record) {
                    if ($record->next_check === null || $record->crew === null || $record->type === null) {
                        continue;
                    }

                    $nextCheck = CarbonImmutable::parse($record->next_check)->startOfDay();
                    $dueDate = $this->dueDate($nextCheck, $record->type->name);
                    $stage = $this->reminderStage($today, $dueDate, $nextCheck, $record->type->name);

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

                        $remindersByRecipient[$recipient->id]['recipient'] = $recipient;
                        $remindersByRecipient[$recipient->id]['reminders'][] = [
                            'record' => $record,
                            'due_date' => $dueDate,
                            'log' => $reminderLog,
                            'stage' => $stage,
                        ];
                    }
                }
            });

        foreach ($remindersByRecipient as $recipientReminder) {
            $recipient = $recipientReminder['recipient'];
            $reminderItems = $recipientReminder['reminders'];
            $crews = collect($reminderItems)
                ->map(fn (array $reminder) => $reminder['record']->crew)
                ->unique('id')
                ->values();
            $singleCrew = $crews->count() === 1 ? $crews->first() : null;

            $recipient->notify(new TrainingExpiryNotification(
                crewId: $singleCrew?->id,
                crewName: $singleCrew?->name ?? 'Semua crew',
                trainings: array_map(
                    fn (array $reminder): array => [
                        'crew_name' => $reminder['record']->crew->name,
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

        $this->info("Sent {$sentCount} training reminder notification(s).");

        return self::SUCCESS;
    }

    private function dueDate(CarbonImmutable $nextCheck, string $trainingName): CarbonImmutable
    {
        if (strcasecmp($trainingName, 'English Proficiency') === 0) {
            return $nextCheck;
        }

        return $nextCheck->startOfMonth()->addMonthNoOverflow();
    }

    private function reminderStage(
        CarbonImmutable $today,
        CarbonImmutable $dueDate,
        CarbonImmutable $nextCheck,
        string $trainingName,
    ): ?string {
        if (strcasecmp($trainingName, 'English Proficiency') === 0 && $today->equalTo($nextCheck)) {
            return 'english_proficiency_due';
        }

        if ($today->greaterThan($dueDate)) {
            return 'expired_daily';
        }

        $reminderOffsets = stripos($trainingName, 'simulator') !== false
            ? [60 => 'sixty_days', 30 => 'thirty_days', 14 => 'fourteen_days', 7 => 'seven_days']
            : [14 => 'fourteen_days', 7 => 'seven_days', 3 => 'three_days'];

        foreach ($reminderOffsets as $days => $stage) {
            if ($today->equalTo($dueDate->subDays($days))) {
                return $stage;
            }
        }

        return null;
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
