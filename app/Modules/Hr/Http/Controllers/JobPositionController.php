<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\JobPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class JobPositionController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('hr.manage');

        return response()->json(
            JobPosition::query()->orderBy('sort_order')->orderBy('name')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('hr.manage');

        $position = JobPosition::create($this->validatedData($request));

        return response()->json($position, 201);
    }

    public function update(Request $request, JobPosition $jobPosition): JsonResponse
    {
        Gate::authorize('hr.manage');

        $data = $this->validatedData($request, $jobPosition);
        DB::transaction(function () use ($data, $jobPosition): void {
            if ($data['name'] !== $jobPosition->name) {
                Employee::query()
                    ->where('job_title', $jobPosition->name)
                    ->update(['job_title' => $data['name']]);
            }

            $jobPosition->update($data);
        });

        return response()->json($jobPosition->refresh());
    }

    /**
     * @return array{name: string, sort_order: int, is_active: bool, is_leave_manager: bool}
     */
    private function validatedData(Request $request, ?JobPosition $jobPosition = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('job_positions', 'name')->ignore($jobPosition?->id),
            ],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['required', 'boolean'],
            'is_leave_manager' => ['required', 'boolean'],
        ]);
    }
}
