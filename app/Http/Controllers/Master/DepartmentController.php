<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Department::query()->withCount(['users', 'pages'])->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'code' => ['required', 'alpha_dash', 'max:20', 'unique:departments,code'],
            'has_full_access' => ['sometimes', 'boolean'],
        ]);

        return response()->json(Department::create($data), 201);
    }

    public function show(Department $department): JsonResponse
    {
        return response()->json($department->loadCount(['users', 'pages']));
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department->id)],
            'code' => ['sometimes', 'alpha_dash', 'max:20', Rule::unique('departments', 'code')->ignore($department->id)],
            'has_full_access' => ['sometimes', 'boolean'],
        ]);

        $department->update($data);

        return response()->json($department->refresh());
    }

    public function destroy(Department $department): JsonResponse
    {
        if ($department->users()->exists()) {
            return response()->json(['message' => 'Departemen masih punya user. Pindahkan dulu.'], 422);
        }

        $department->delete(); // halaman miliknya jadi shared (nullOnDelete)

        return response()->json(null, 204);
    }
}
