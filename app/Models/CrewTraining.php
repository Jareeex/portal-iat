<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewTraining extends Model
{
    public const SOON_DAYS = 60;

    protected $fillable = ['crew_id', 'training_type_id', 'last_check', 'next_check', 'plan_check'];

    protected function casts(): array
    {
        return [
            'last_check' => 'date:Y-m-d',
            'next_check' => 'date:Y-m-d',
            'plan_check' => 'date:Y-m-d',
        ];
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TrainingType::class, 'training_type_id');
    }

    public static function daysLeft(?CarbonInterface $next): ?int
    {
        return $next ? (int) Carbon::today()->diffInDays(Carbon::parse($next)->startOfDay(), false) : null;
    }

    public static function statusFor(?CarbonInterface $next): ?string
    {
        $daysLeft = self::daysLeft($next);

        return $daysLeft === null ? null : ($daysLeft < 0 ? 'expired' : ($daysLeft <= self::SOON_DAYS ? 'soon' : 'valid'));
    }
}
