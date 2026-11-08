<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Http\Requests\ExperienceRequest;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeExperience;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ExperienceController extends Controller
{
    public function store(ExperienceRequest $request, Employee $employee)
    {
        Gate::authorize('updateProfile', $employee);

        $experience = $employee->experiences()->create($request->validated());

        return $request->wantsJson()
            ? response()->json($experience, 201)
            : back()->with('status', 'Pengalaman ditambahkan.');
    }

    public function update(ExperienceRequest $request, Employee $employee, EmployeeExperience $experience)
    {
        Gate::authorize('updateProfile', $employee);
        abort_unless($experience->employee_id === $employee->id, 404);

        $experience->update($request->validated());

        return $request->wantsJson()
            ? response()->json($experience)
            : back()->with('status', 'Pengalaman diupdate.');
    }

    public function destroy(Request $request, Employee $employee, EmployeeExperience $experience)
    {
        Gate::authorize('updateProfile', $employee);
        abort_unless($experience->employee_id === $employee->id, 404);

        $experience->delete();

        return $request->wantsJson()
            ? response()->noContent()
            : back()->with('status', 'Pengalaman dihapus.');
    }
}
