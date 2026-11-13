<?php

namespace App\Services;

use App\Models\CrewTraining;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TrainingReminderRecipientService
{
    /**
     * @return Collection<int, User>
     */
    public function recipients(
        ?int $trainingPageId,
        ?CrewTraining $record = null,
        bool $enabledOnly = false,
    ): Collection {
        $query = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when($enabledOnly, fn (Builder $query) => $query->where('training_reminder_enabled', true))
            ->where(function (Builder $query) use ($trainingPageId, $record): void {
                $query->where('role', User::ROLE_MASTER);

                if ($trainingPageId !== null) {
                    $query->orWhereHas('pages', function (Builder $pageQuery) use ($trainingPageId): void {
                        $pageQuery->whereKey($trainingPageId)
                            ->where('user_page_access.access_level', 'edit');
                    });
                }

                $query->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('has_full_access', true));

                if ($record?->crew?->user_id !== null) {
                    $query->orWhereKey($record->crew->user_id);
                } elseif ($record === null) {
                    $query->orWhereHas('crew');
                }
            });

        return $query->get()
            ->filter(fn (User $user): bool => filter_var(trim($user->email), FILTER_VALIDATE_EMAIL) !== false)
            ->values();
    }
}
