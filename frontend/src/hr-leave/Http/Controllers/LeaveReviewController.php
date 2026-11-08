<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Events\LeaveRequestReviewed;
use App\Modules\Hr\Http\Requests\ReviewLeaveRequest;
use App\Modules\Hr\Http\Resources\LeaveRequestResource;
use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/** Sisi HR: inbox pengajuan, approve, reject. */
class LeaveReviewController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        $status = $request->query('status', LeaveRequest::PENDING); // default: yang menunggu
        $status = $status === 'all' ? null : $status;

        $leaves = LeaveRequest::with('leaveType', 'employee.user')
            ->status($status)
            ->when($request->query('leave_type_id'), fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($request->query('q'), fn ($q, $term) => $q->whereHas('employee', fn ($e) => $e
                ->where('employee_number', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"))))
            ->latest()
            ->paginate(config('hr.per_page'))
            ->withQueryString();

        if ($request->wantsJson()) {
            return LeaveRequestResource::collection($leaves);
        }

        return view('hr::leaves.inbox', [
            'leaves' => $leaves,
            'pendingCount' => LeaveRequest::where('status', LeaveRequest::PENDING)->count(),
            'currentStatus' => $request->query('status', LeaveRequest::PENDING),
        ]);
    }

    public function approve(ReviewLeaveRequest $request, LeaveRequest $leaveRequest)
    {
        return $this->review($request, $leaveRequest, LeaveRequest::APPROVED, 'Pengajuan disetujui.');
    }

    public function reject(ReviewLeaveRequest $request, LeaveRequest $leaveRequest)
    {
        return $this->review($request, $leaveRequest, LeaveRequest::REJECTED, 'Pengajuan ditolak.');
    }

    private function review(ReviewLeaveRequest $request, LeaveRequest $leave, string $status, string $message)
    {
        Gate::authorize('review', $leave);

        $leave->forceFill([
            'status' => $status,
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
            'review_note' => $request->validated()['review_note'] ?? null,
        ])->save();

        LeaveRequestReviewed::dispatch($leave);

        if ($request->wantsJson()) {
            return new LeaveRequestResource($leave->load('leaveType', 'employee.user', 'reviewer'));
        }

        return redirect()->route(config('hr.route.name').'leave-requests.index')->with('status', $message);
    }
}
