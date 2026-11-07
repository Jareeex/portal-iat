<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crew extends Model
{
    public const GROUPS = [
        'fa' => ['label' => 'Flight Attendant', 'category' => 'Flight Attendant'],
        'foo_hlo' => ['label' => 'FOO & HLO', 'category' => 'FOO & HLO'],
    ];

    protected $fillable = [
        'user_id', 'group', 'pilot_role', 'name', 'code', 'category', 'fleet', 'position', 'sort_order',
        'dob', 'address', 'passport_no', 'passport_expiry', 'nationality',
        'joined_iat', 'licence', 'rating', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
            'passport_expiry' => 'date:Y-m-d',
            'joined_iat' => 'date:Y-m-d',
        ];
    }

    /** @return array<string, array{label: string, category: string}> */
    public static function groupOptions(): array
    {
        $aircraft = TrainingAircraft::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (TrainingAircraft $aircraft): array => [
                $aircraft->group_key => ['label' => $aircraft->name, 'category' => 'Pilot'],
            ])
            ->all();

        return $aircraft + self::GROUPS;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TrainingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TrainingItem::class);
    }

    /** @return HasMany<CrewTraining, $this> */
    public function trainings(): HasMany
    {
        return $this->hasMany(CrewTraining::class);
    }

    /** @return HasMany<CrewLicenseDocument, $this> */
    public function licenseDocuments(): HasMany
    {
        return $this->hasMany(CrewLicenseDocument::class)->latest();
    }

    /** @return BelongsToMany<TrainingAircraft, $this> */
    public function trainingAircrafts(): BelongsToMany
    {
        return $this->belongsToMany(TrainingAircraft::class, 'crew_training_aircraft')->withTimestamps();
    }

    public function hasTrainingGroup(string $group): bool
    {
        if (isset(self::GROUPS[$group])) {
            return $this->group === $group;
        }

        $aircrafts = $this->trainingAircrafts;

        return $aircrafts->contains('group_key', $group)
            || ($aircrafts->isEmpty() && $this->group === $group);
    }

    public function appliesToTrainingType(TrainingType $type): bool
    {
        $trainingRole = TrainingType::pilotRoleFromName($type->name);

        return $this->hasTrainingGroup($type->group)
            && ($trainingRole === null || $this->pilot_role === null || $trainingRole === $this->pilot_role);
    }
}
