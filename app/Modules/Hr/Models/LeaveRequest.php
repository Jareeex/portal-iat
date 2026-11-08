<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int|null $manager_id
 * @property int|null $manager_reviewed_by
 * @property int $days
 * @property string $status
 * @property string $reason
 * @property string|null $contact_phone
 * @property string|null $attachment_path
 * @property int|null $reviewed_by
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $manager_reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $review_note
 * @property string|null $manager_review_note
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 * @property-read User|null $reviewer
 * @property-read User|null $manager
 * @property-read User|null $managerReviewer
 */
class LeaveRequest extends Model
{
    public const MANAGER_PENDING = 'manager_pending';

    public const HR_PENDING = 'hr_pending';

    public const MANAGER_REJECTED = 'manager_rejected';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::MANAGER_PENDING,
        self::HR_PENDING,
        self::MANAGER_REJECTED,
        self::APPROVED,
        self::REJECTED,
        self::CANCELLED,
    ];

    protected $table = 'leave_requests';

    protected $guarded = [
        'id',
        'employee_id',
        'manager_id',
        'days',
        'status',
        'reviewed_by',
        'manager_reviewed_by',
        'reviewed_at',
        'review_note',
        'manager_review_note',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
            'manager_reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return BelongsTo<User, $this> */
    public function managerReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_reviewed_by');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::MANAGER_PENDING, self::HR_PENDING], true);
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }
}
