<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Events\LeaveRequestSubmitted;
use App\Modules\Hr\Http\Requests\StoreLeaveRequest;
use App\Modules\Hr\Http\Resources\LeaveRequestResource;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Support\LeaveService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/** Sisi karyawan: ajukan, lihat, batalkan cuti sendiri. */
class LeaveRequestController extends Controller
{
    /** Daftar pengajuan milik user yang login. */
    public function index(Request $request)
    {
        $employee = Employee::forUser($request->user());

        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->status($request->query('status'))
            ->latest()
            ->paginate(config('hr.per_page'))
            ->withQueryString();

        if ($request->wantsJson()) {
            return LeaveRequestResource::collection($leaves);
        }

        return view('hr::leaves.index', [
            'leaves' => $leaves,
            'balances' => $this->balances($employee, now()->year),
        ]);
    }

    public function create(Request $request)
    {
        $employee = Employee::forUser($request->user());

        return view('hr::leaves.create', [
            'balances' => $this->balances($employee, now()->year),
        ]);
    }

    public function store(StoreLeaveRequest $request)
    {
        $employee = Employee::forUser($request->user());
        $data = $request->validated();

        $leave = new LeaveRequest($data);
        $leave->forceFill([
            'employee_id' => $employee->id,
            'days' => LeaveService::countDays(Carbon::parse($data['start_date']), Carbon::parse($data['end_date'])),
            'status' => LeaveRequest::PENDING,
        ]);

        if ($request->hasFile('attachment')) {
            $leave->attachment_path = $request->file('attachment')
                ->store('hr/leave-attachments', config('hr.leave.attachment_disk'));
        }

        $leave->save();

        LeaveRequestSubmitted::dispatch($leave);

        if ($request->wantsJson()) {
            return (new LeaveRequestResource($leave->load('leaveType', 'employee.user')))->response()->setStatusCode(201);
        }

        return redirect()->route(config('hr.route.name').'leaves.show', $leave)
            ->with('status', 'Pengajuan cuti terkirim, menunggu persetujuan HR.');
    }

    public function show(Request $request, LeaveRequest $leaveRequest)
    {
        Gate::authorize('view', $leaveRequest);

        $leaveRequest->load('leaveType', 'employee.user', 'reviewer');

        if ($request->wantsJson()) {
            return new LeaveRequestResource($leaveRequest);
        }

        return view('hr::leaves.show', ['leave' => $leaveRequest]);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        Gate::authorize('cancel', $leaveRequest);

        $leaveRequest->forceFill(['status' => LeaveRequest::CANCELLED])->save();

        if ($request->wantsJson()) {
            return new LeaveRequestResource($leaveRequest->load('leaveType'));
        }

        return redirect()->route(config('hr.route.name').'leaves.index')
            ->with('status', 'Pengajuan dibatalkan.');
    }

    /** Download lampiran (disk private, jadi lewat controller + policy). */
    public function attachment(LeaveRequest $leaveRequest)
    {
        Gate::authorize('view', $leaveRequest);
        abort_unless($leaveRequest->attachment_path, 404);

        return Storage::disk(config('hr.leave.attachment_disk'))->download($leaveRequest->attachment_path);
    }

    /** Sisa jatah per jenis cuti aktif. */
    private function balances(Employee $employee, int $year): array
    {
        return LeaveType::active()->orderBy('name')->get()->map(fn (LeaveType $t) => [
            'type' => $t,
            'used' => LeaveService::used($employee, $t, $year),
            'remaining' => LeaveService::remaining($employee, $t, $year),
        ])->all();
    }
}
