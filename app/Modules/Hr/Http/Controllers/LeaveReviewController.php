<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Models\User;
use App\Modules\Hr\Events\LeaveRequestReviewed;
use App\Modules\Hr\Http\Requests\ReviewLeaveRequest as ReviewLeaveRequestForm;
use App\Modules\Hr\Http\Resources\LeaveRequestResource;
use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class LeaveReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        return response()->json([
            'pending_count' => LeaveRequest::where('status', LeaveRequest::HR_PENDING)->count(),
        ]);
    }

    public function managerInbox(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $requests = LeaveRequest::with(['leaveType', 'employee.user.department', 'manager'])
            ->where('manager_id', $user->getKey())
            ->where('status', LeaveRequest::MANAGER_PENDING)
            ->latest()
            ->paginate(config('hr.per_page'));

        return LeaveRequestResource::collection($requests);
    }

    public function approve(ReviewLeaveRequestForm $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        return $this->review($request, $leaveRequest, LeaveRequest::APPROVED);
    }

    public function reject(ReviewLeaveRequestForm $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        return $this->review($request, $leaveRequest, LeaveRequest::REJECTED);
    }

    public function managerApprove(ReviewLeaveRequestForm $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        return $this->managerReview($request, $leaveRequest, LeaveRequest::HR_PENDING);
    }

    public function managerReject(ReviewLeaveRequestForm $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        return $this->managerReview($request, $leaveRequest, LeaveRequest::MANAGER_REJECTED);
    }

    private function managerReview(
        ReviewLeaveRequestForm $request,
        LeaveRequest $leave,
        string $status,
    ): LeaveRequestResource {
        Gate::authorize('managerReview', $leave);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $leave->forceFill([
            'status' => $status,
            'manager_reviewed_by' => $user->getKey(),
            'manager_reviewed_at' => now(),
            'manager_review_note' => $request->validated()['review_note'] ?? null,
        ])->save();

        return new LeaveRequestResource($leave->load(
            'leaveType',
            'employee.user.department',
            'manager',
            'managerReviewer',
        ));
    }

    private function review(
        ReviewLeaveRequestForm $request,
        LeaveRequest $leave,
        string $status,
    ): LeaveRequestResource {
        Gate::authorize('review', $leave);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $leave->forceFill([
            'status' => $status,
            'reviewed_by' => $user->getKey(),
            'reviewed_at' => now(),
            'review_note' => $request->validated()['review_note'] ?? null,
        ])->save();

        LeaveRequestReviewed::dispatch($leave);

        return new LeaveRequestResource($leave->load(
            'leaveType',
            'employee.user.department',
            'manager',
            'managerReviewer',
            'reviewer',
        ));
    }
}
