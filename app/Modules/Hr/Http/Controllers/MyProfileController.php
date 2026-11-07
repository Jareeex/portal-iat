<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Models\User;
use App\Modules\Hr\Http\Requests\UpdateMyProfileRequest;
use App\Modules\Hr\Http\Resources\EmployeeResource;
use App\Modules\Hr\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class MyProfileController extends Controller
{
    private function mine(Request $request): Employee
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $employee = Employee::forUser($user)->load('user.department', 'experiences');
        Gate::authorize('view', $employee);

        return $employee;
    }

    public function show(Request $request): EmployeeResource
    {
        $employee = $this->mine($request);

        return new EmployeeResource($employee);
    }

    public function update(UpdateMyProfileRequest $request): EmployeeResource
    {
        $employee = $this->mine($request);

        $employee->fill(Arr::except($request->validated(), ['photo']));

        if ($request->hasFile('photo')) {
            $employee->replacePhoto($request->file('photo'));
        }

        $employee->save();

        return new EmployeeResource($employee->fresh(['user.department', 'experiences']));
    }
}
