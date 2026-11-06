<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crew extends Model
{
    public const GROUPS = [
        'fw_atr' => ['label' => 'ATR 42 / EMB 135', 'category' => 'Pilot'],
        'fw_rss' => ['label' => 'EMB 135 BJ (RSS)', 'category' => 'Pilot'],
        'fw_jam' => ['label' => 'Hawker 900XP (JAM)', 'category' => 'Pilot'],
        'rw' => ['label' => 'EC 155 B1 (Rotary)', 'category' => 'Pilot'],
        'fa' => ['label' => 'Flight Attendant', 'category' => 'Flight Attendant'],
        'foo_hlo' => ['label' => 'FOO & HLO', 'category' => 'FOO & HLO'],
    ];

    protected $fillable = [
        'group', 'name', 'code', 'category', 'fleet', 'position', 'sort_order',
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

    public function items(): HasMany
    {
        return $this->hasMany(TrainingItem::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(CrewTraining::class);
    }
}
