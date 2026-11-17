<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Models\User;
use App\Modules\Hr\Http\Requests\StoreAttendancePhotoRequest;
use App\Modules\Hr\Http\Requests\UpdateAttendanceRequest;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $canManage = Gate::allows('hr.manage');
        $employee = Employee::forUser($user);
        $timezone = (string) config('hr.attendance.timezone');
        $today = now($timezone)->toDateString();
        $date = $filters['date'] ?? $today;

        $records = Attendance::query()
            ->with(['employee.user.department'])
            ->whereDate('attendance_date', $date)
            ->when(! $canManage, fn (Builder $query) => $query->where('employee_id', $employee->id))
            ->when($canManage && isset($filters['q']), function (Builder $query) use ($filters): void {
                $term = $filters['q'];
                $query->whereHas('employee', function (Builder $employeeQuery) use ($term): void {
                    $employeeQuery
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('employee_number', 'like', "%{$term}%")
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderBy('clock_in_at')
            ->paginate(config('hr.per_page'))
            ->withQueryString();

        $todayRecord = Attendance::query()
            ->with(['employee.user.department'])
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $today)
            ->first();

        return response()->json([
            'today' => $todayRecord ? $this->attendanceData($todayRecord) : null,
            'timezone' => $timezone,
            'records' => [
                'data' => $records->getCollection()->map(
                    fn (Attendance $attendance): array => $this->attendanceData($attendance),
                )->values(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
                'per_page' => $records->perPage(),
            ],
        ]);
    }

    public function clockIn(StoreAttendancePhotoRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $employee = Employee::forUser($user);
        $localNow = now((string) config('hr.attendance.timezone'));
        $photo = $request->file('photo');
        if (! $photo instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'photo' => 'Foto clock-in wajib diunggah.',
            ]);
        }

        $attendance = DB::transaction(function () use ($employee, $localNow, $photo): Attendance {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            $existing = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $localNow->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'attendance' => 'Clock-in hari ini sudah tercatat.',
                ]);
            }

            $photoPath = $photo->store(
                'hr/attendance',
                config('hr.attendance.disk'),
            );
            if ($photoPath === false) {
                throw new RuntimeException('Foto clock-in gagal disimpan.');
            }

            return Attendance::query()->create([
                'employee_id' => $employee->id,
                'attendance_date' => $localNow->toDateString(),
                'clock_in_at' => $localNow->clone()->utc(),
                'clock_in_photo_path' => $photoPath,
            ])->load(['employee.user.department']);
        });

        return response()->json(['data' => $this->attendanceData($attendance)], 201);
    }

    public function clockOut(StoreAttendancePhotoRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $employee = Employee::forUser($user);
        $localNow = now((string) config('hr.attendance.timezone'));
        $photo = $request->file('photo');
        if (! $photo instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'photo' => 'Foto clock-out wajib diunggah.',
            ]);
        }

        $attendance = DB::transaction(function () use ($employee, $localNow, $photo): Attendance {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $localNow->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                throw ValidationException::withMessages([
                    'attendance' => 'Clock-in harus dilakukan sebelum clock-out.',
                ]);
            }

            if ($attendance->clock_out_at !== null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Clock-out hari ini sudah tercatat.',
                ]);
            }

            $photoPath = $photo->store(
                'hr/attendance',
                config('hr.attendance.disk'),
            );
            if ($photoPath === false) {
                throw new RuntimeException('Foto clock-out gagal disimpan.');
            }

            $attendance->forceFill([
                'clock_out_at' => $localNow->clone()->utc(),
                'clock_out_photo_path' => $photoPath,
            ])->save();

            return $attendance->load(['employee.user.department']);
        });

        return response()->json(['data' => $this->attendanceData($attendance)]);
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        $data = $request->validated();
        $date = $attendance->attendance_date->toDateString();
        $timezone = (string) config('hr.attendance.timezone');

        $attendance->forceFill([
            'clock_in_at' => Carbon::parse("{$date} {$data['clock_in_time']}", $timezone)->utc(),
            'clock_out_at' => isset($data['clock_out_time']) && $data['clock_out_time'] !== ''
                ? Carbon::parse("{$date} {$data['clock_out_time']}", $timezone)->utc()
                : null,
            'edited_by' => $request->user()->getAuthIdentifier(),
        ])->save();

        return response()->json([
            'data' => $this->attendanceData($attendance->fresh(['employee.user.department'])),
        ]);
    }

    public function photo(Request $request, Attendance $attendance, string $type): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! Gate::allows('hr.manage') && $attendance->employee->user_id !== $user->getKey()) {
            abort(403);
        }

        $path = match ($type) {
            'clock-in' => $attendance->clock_in_photo_path,
            'clock-out' => $attendance->clock_out_photo_path,
            default => null,
        };
        abort_if($path === null || ! Storage::disk(config('hr.attendance.disk'))->exists($path), 404);

        return Storage::disk(config('hr.attendance.disk'))->response(
            $path,
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }

    /**
     * @return array{
     *     id: int,
     *     attendance_date: string,
     *     clock_in_at: string,
     *     clock_out_at: string|null,
     *     clock_in_photo_available: bool,
     *     clock_out_photo_available: bool,
     *     employee: array{id: int, name: string, employee_number: string|null, department: string|null}
     * }
     */
    private function attendanceData(Attendance $attendance): array
    {
        $employee = $attendance->employee;

        return [
            'id' => $attendance->id,
            'attendance_date' => $attendance->attendance_date->toDateString(),
            'clock_in_at' => $attendance->clock_in_at->toIso8601String(),
            'clock_out_at' => $attendance->clock_out_at?->toIso8601String(),
            'clock_in_photo_available' => true,
            'clock_out_photo_available' => $attendance->clock_out_photo_path !== null,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user_id === null
                    ? ($employee->name ?? 'Employee')
                    : $employee->user->name,
                'employee_number' => $employee->employee_number,
                'department' => $employee->user_id === null
                    ? null
                    : $employee->user->department?->name,
            ],
        ];
    }
}
