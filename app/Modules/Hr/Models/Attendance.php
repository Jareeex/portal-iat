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
 * @property Carbon|null $clock_out_at
 * @property string|null $clock_out_photo_path
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
        'clock_out_at',
        'clock_out_photo_path',
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
