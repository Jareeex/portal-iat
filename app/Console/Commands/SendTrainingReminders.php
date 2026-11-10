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
        $sentCount = 0;

        CrewTraining::query()
            ->whereNotNull('next_check')
            ->with(['crew.user', 'type'])
            ->chunkById(200, function ($records) use ($today, $trainingPageId, &$sentCount): void {
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
                        $alreadySent = $record->reminderLogs()
                            ->whereDate('due_date', $dueDate->toDateString())
                            ->where('stage', $stage)
                            ->where('recipient_user_id', $recipient->id)
                            ->exists();

                        if ($alreadySent) {
                            continue;
                        }

                        $recipient->notify(new TrainingExpiryNotification(
                            crewId: $record->crew->id,
                            crewName: $record->crew->name,
                            trainingName: $record->type->name,
                            dueDate: $dueDate->format('d/m/Y'),
                            reminderLabel: self::REMINDER_LABELS[$stage],
                            stage: $stage,
                        ));

                        $record->reminderLogs()->create([
                            'recipient_user_id' => $recipient->id,
                            'due_date' => $dueDate->toDateString(),
                            'stage' => $stage,
                        ]);
                        $sentCount++;
                    }
                }
            });

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
