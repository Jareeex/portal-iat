<?php

namespace App\Modules\Hr\Http\Resources;

use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveRequest */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => $this->days,
            'reason' => $this->reason,
            'contact_phone' => $this->contact_phone,
            'attachment_url' => $this->attachment_path
                ? route(config('hr.route.name').'leave-requests.attachment', $this->resource)
                : null,
            'leave_type' => $this->whenLoaded('leaveType', fn (): array => [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'is_paid' => $this->leaveType->is_paid,
            ]),
            'employee' => $this->whenLoaded('employee', fn (): array => [
                'id' => $this->employee->id,
                'user_id' => $this->employee->user_id,
                'employee_number' => $this->employee->employee_number,
                'name' => $this->employee->user->name,
                'department' => $this->employee->user->department?->name,
            ]),
            'manager' => $this->whenLoaded('manager', fn (): ?array => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ] : null),
            'manager_reviewed_by' => $this->whenLoaded(
                'managerReviewer',
                fn (): ?string => $this->managerReviewer?->name,
            ),
            'manager_reviewed_at' => $this->manager_reviewed_at,
            'manager_review_note' => $this->manager_review_note,
            'reviewed_by' => $this->whenLoaded('reviewer', fn (): ?string => $this->reviewer?->name),
            'reviewed_at' => $this->reviewed_at,
            'review_note' => $this->review_note,
            'created_at' => $this->created_at,
        ];
    }
}
