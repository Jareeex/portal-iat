<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Hr\Http\Resources\EmployeeResource;
use App\Modules\Hr\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $employees = Employee::with('user.department')
            ->search($request->query('q'))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas(
                'user',
                fn ($user) => $user->where('department_id', $request->integer('department_id')),
            ))
            ->when($request->query('employment_status'), fn ($q, $v) => $q->where('employment_status', $v))
            ->when($request->query('office'), fn ($q, $v) => $q->where('office', $v))
            ->orderBy('employee_number')
            ->paginate(config('hr.per_page'))
            ->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function show(Employee $employee): EmployeeResource
    {
        Gate::authorize('view', $employee);

        $employee->load('user.department', 'experiences');

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $data = $request->validated();
        $photo = $request->file('photo');
        unset($data['photo']);

        $employee->fill($data);

        if ($photo) {
            $employee->replacePhoto($photo);
        }

        $employee->save();

        return new EmployeeResource($employee->fresh(['user.department']));
    }
}
