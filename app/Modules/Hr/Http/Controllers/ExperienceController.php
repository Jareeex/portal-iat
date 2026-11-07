<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Http\Requests\ExperienceRequest;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeExperience;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ExperienceController extends Controller
{
    public function store(ExperienceRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('updateProfile', $employee);

        $experience = $employee->experiences()->create($request->validated());

        return response()->json($experience, 201);
    }

    public function update(ExperienceRequest $request, Employee $employee, EmployeeExperience $experience): JsonResponse
    {
        Gate::authorize('updateProfile', $employee);
        abort_unless($experience->employee_id === $employee->id, 404);

        $experience->update($request->validated());

        return response()->json($experience);
    }

    public function destroy(Employee $employee, EmployeeExperience $experience): Response
    {
        Gate::authorize('updateProfile', $employee);
        abort_unless($experience->employee_id === $employee->id, 404);

        $experience->delete();

        return response()->noContent();
    }
}
