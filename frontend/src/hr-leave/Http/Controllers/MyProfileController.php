<?php

namespace App\Modules\Hr\Http\Controllers;

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
        $employee = Employee::forUser($request->user())->load('user', 'experiences');
        Gate::authorize('view', $employee);

        return $employee;
    }

    public function show(Request $request)
    {
        $employee = $this->mine($request);

        if ($request->wantsJson()) {
            return new EmployeeResource($employee);
        }

        return view('hr::employees.show', ['employee' => $employee, 'mode' => 'self']);
    }

    public function edit(Request $request)
    {
        return view('hr::employees.form', [
            'employee' => $this->mine($request),
            'action' => route(config('hr.route.name').'me.update'),
            'method' => 'PUT',
            'mode' => 'self',
        ]);
    }

    public function update(UpdateMyProfileRequest $request)
    {
        $employee = $this->mine($request);

        $employee->fill(Arr::except($request->validated(), ['photo']));

        if ($request->hasFile('photo')) {
            $employee->replacePhoto($request->file('photo'));
        }

        $employee->save();

        if ($request->wantsJson()) {
            return new EmployeeResource($employee->fresh(['user', 'experiences']));
        }

        return redirect()->route(config('hr.route.name').'me.show')->with('status', 'Profil diupdate.');
    }
}
