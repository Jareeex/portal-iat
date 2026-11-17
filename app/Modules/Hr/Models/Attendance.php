<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $attendance_date
 * @property Carbon $clock_in_at
 * @property string $clock_in_photo_path
 * @property float|null $clock_in_latitude
 * @property float|null $clock_in_longitude
 * @property int|null $clock_in_distance_meters
 * @property bool $clock_in_is_offsite
 * @property string|null $clock_in_reason
 * @property string|null $clock_in_reason_details
 * @property Carbon|null $clock_out_at
 * @property string|null $clock_out_photo_path
 * @property float|null $clock_out_latitude
 * @property float|null $clock_out_longitude
 * @property int|null $clock_out_distance_meters
 * @property bool $clock_out_is_offsite
 * @property string|null $clock_out_reason
 * @property string|null $clock_out_reason_details
 * @property int|null $edited_by
 * @property-read Employee $employee
 * @property-read User|null $editor
 */
class Attendance extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_date',
        'clock_in_at',
        'clock_in_photo_path',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_distance_meters',
        'clock_in_is_offsite',
        'clock_in_reason',
        'clock_in_reason_details',
        'clock_out_at',
        'clock_out_photo_path',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_distance_meters',
        'clock_out_is_offsite',
        'clock_out_reason',
        'clock_out_reason_details',
        'edited_by',
    ];

    protected $hidden = [
        'clock_in_photo_path',
        'clock_out_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'clock_in_at' => 'datetime',
            'clock_out_at' => 'datetime',
            'clock_in_latitude' => 'float',
            'clock_in_longitude' => 'float',
            'clock_in_distance_meters' => 'integer',
            'clock_in_is_offsite' => 'boolean',
            'clock_out_latitude' => 'float',
            'clock_out_longitude' => 'float',
            'clock_out_distance_meters' => 'integer',
            'clock_out_is_offsite' => 'boolean',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
