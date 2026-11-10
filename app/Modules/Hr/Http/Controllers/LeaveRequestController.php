<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Models\User;
use App\Modules\Hr\Http\Requests\StoreLeaveRequest as StoreLeaveRequestForm;
use App\Modules\Hr\Http\Requests\UpdateLeaveRequest as UpdateLeaveRequestForm;
use App\Modules\Hr\Http\Resources\LeaveRequestResource;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\JobPosition;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Support\LeaveService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    public function types(): JsonResponse
    {
        return response()->json(
            LeaveType::active()->orderBy('name')->get([
                'id',
                'name',
                'annual_quota_days',
                'requires_attachment',
                'is_paid',
            ]),
        );
    }

    public function managers(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $requesterJobTitle = $user->employeeProfile()->job_title;
        $requesterIsManager = $requesterJobTitle === 'Manager';
        $requesterIsOfficer = $requesterJobTitle === 'Officer';
        $canSelectCrossDepartmentApprover = $requesterIsManager || $requesterIsOfficer;
        $managerPositions = $requesterIsManager
            ? collect(['BOD'])
            : JobPosition::active()->where('is_leave_manager', true)->pluck('name');

        $managers = User::query()
            ->with('employee:id,user_id,name,employee_number,job_title')
            ->where('is_active', true)
            ->when(! $canSelectCrossDepartmentApprover, function ($query) use ($user): void {
                $query->whereNotNull('department_id')
                    ->where('department_id', $user->department_id);
            })
            ->where('id', '!=', $user->getKey())
            ->whereHas('employee', fn ($query) => $query->whereIn('job_title', $managerPositions))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($managers->map(fn (User $manager): array => [
            'id' => $manager->id,
            'name' => $manager->name,
            'job_title' => $manager->employee->job_title,
        ]));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in([
                LeaveRequest::HR_PENDING,
                LeaveRequest::APPROVED,
                LeaveRequest::REJECTED,
                'all',
            ])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = is_string($filters['status'] ?? null)
            ? $filters['status']
            : LeaveRequest::HR_PENDING;
        $showAll = $status === 'all';
        if ($status === 'all') {
            $status = null;
        }

        $query = LeaveRequest::with([
            'leaveType',
            'employee.user.department',
            'manager',
            'managerReviewer',
            'reviewer',
        ])
            ->status($status)
            ->latest();

        if ($showAll) {
            $query->whereIn('status', [
                LeaveRequest::HR_PENDING,
                LeaveRequest::APPROVED,
                LeaveRequest::REJECTED,
            ]);
        }

        if (isset($filters['q']) && is_string($filters['q']) && $filters['q'] !== '') {
            $term = $filters['q'];
            $query->whereHas('employee', function (Builder $employeeQuery) use ($term): void {
                $employeeQuery
                    ->where('employee_number', 'like', "%{$term}%")
                    ->orWhereHas(
                        'user',
                        fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$term}%"),
                    );
            });
        }

        return LeaveRequestResource::collection(
            $query->paginate(config('hr.per_page'))->withQueryString(),
        );
    }

    public function store(StoreLeaveRequestForm $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $data = $request->validated();
        $employee = Employee::forUser($user);
        $leave = new LeaveRequest($data);
        $leave->forceFill([
            'employee_id' => $employee->id,
            'manager_id' => $data['manager_id'],
            'days' => LeaveService::countDays(
                Carbon::parse($data['start_date']),
                Carbon::parse($data['end_date']),
            ),
            'status' => LeaveRequest::MANAGER_PENDING,
        ]);

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store(
                'hr/leave-attachments',
                config('hr.leave.attachment_disk'),
            );
            if ($path === false) {
                throw new RuntimeException('Lampiran pengajuan cuti gagal disimpan.');
            }

            $leave->attachment_path = $path;
        }

        $leave->save();

        return response()->json([
            'message' => 'Pengajuan cuti berhasil dikirim ke manager.',
        ], 201);
    }

    public function update(
        UpdateLeaveRequestForm $request,
        LeaveRequest $leaveRequest,
    ): LeaveRequestResource {
        Gate::authorize('view', $leaveRequest);

        $data = $request->validated();
        $leaveRequest->fill($data);
        $leaveRequest->days = LeaveService::countDays(
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
        );
        $leaveRequest->save();

        return new LeaveRequestResource($leaveRequest->fresh()->load(
            'leaveType',
            'employee.user.department',
            'manager',
            'managerReviewer',
            'reviewer',
        ));
    }

    public function attachment(LeaveRequest $leaveRequest): StreamedResponse
    {
        Gate::authorize('view', $leaveRequest);
        abort_unless($leaveRequest->attachment_path !== null, 404);

        return Storage::disk(config('hr.leave.attachment_disk'))->download($leaveRequest->attachment_path);
    }
}
