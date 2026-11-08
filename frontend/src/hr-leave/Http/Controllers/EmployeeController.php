<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Http\Requests\StoreEmployeeRequest;
use App\Modules\Hr\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Hr\Http\Resources\EmployeeResource;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Support\Hr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    private const NON_EMPLOYEE_KEYS = ['name', 'email', 'password', 'photo'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Employee::class);

        $employees = Employee::with('user')
            ->search($request->query('q'))
            ->when($request->query('department'), fn ($q, $v) => $q->where('department', $v))
            ->when($request->query('employment_status'), fn ($q, $v) => $q->where('employment_status', $v))
            ->when($request->query('office'), fn ($q, $v) => $q->where('office', $v))
            ->orderBy('employee_number')
            ->paginate(config('hr.per_page'))
            ->withQueryString();

        if ($request->wantsJson()) {
            return EmployeeResource::collection($employees);
        }

        $departments = Employee::whereNotNull('department')->distinct()->orderBy('department')->pluck('department');

        return view('hr::employees.index', compact('employees', 'departments'));
    }

    public function create()
    {
        Gate::authorize('create', Employee::class);

        return view('hr::employees.form', [
            'employee' => new Employee,
            'action' => route(config('hr.route.name').'employees.store'),
            'method' => 'POST',
            'mode' => 'admin',
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $employee = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $user = (new (Hr::userModel()))->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->save();

            // observer auto-create mungkin sudah bikin profil kosong
            $employee = Employee::forUser($user);
            $employee->fill(Arr::except($data, self::NON_EMPLOYEE_KEYS));

            if ($request->hasFile('photo')) {
                $employee->replacePhoto($request->file('photo'));
            }

            $employee->save();

            return $employee;
        });

        if ($request->wantsJson()) {
            return (new EmployeeResource($employee->load('user')))->response()->setStatusCode(201);
        }

        return redirect()->route(config('hr.route.name').'employees.show', $employee)
            ->with('status', 'Employee berhasil dibuat.');
    }

    public function show(Request $request, Employee $employee)
    {
        Gate::authorize('view', $employee);

        $employee->load('user', 'experiences');

        if ($request->wantsJson()) {
            return new EmployeeResource($employee);
        }

        return view('hr::employees.show', ['employee' => $employee, 'mode' => 'admin']);
    }

    public function edit(Employee $employee)
    {
        Gate::authorize('update', $employee);

        return view('hr::employees.form', [
            'employee' => $employee->load('user'),
            'action' => route(config('hr.route.name').'employees.update', $employee),
            'method' => 'PUT',
            'mode' => 'admin',
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        DB::transaction(function () use ($request, $employee) {
            $data = $request->validated();

            $userData = Arr::only($data, ['name', 'email']);
            if (! empty($data['password'])) {
                $userData['password'] = Hash::make($data['password']);
            }
            if ($userData) {
                $employee->user->forceFill($userData)->save();
            }

            $employee->fill(Arr::except($data, self::NON_EMPLOYEE_KEYS));

            if ($request->hasFile('photo')) {
                $employee->replacePhoto($request->file('photo'));
            }

            $employee->save();
        });

        if ($request->wantsJson()) {
            return new EmployeeResource($employee->fresh('user'));
        }

        return redirect()->route(config('hr.route.name').'employees.show', $employee)
            ->with('status', 'Employee berhasil diupdate.');
    }

    /**
     * Soft delete profil employee saja. Akun user TIDAK dihapus -
     * kalau mau sekalian nonaktifkan login, tambahin di sini.
     */
    public function destroy(Request $request, Employee $employee)
    {
        Gate::authorize('delete', $employee);

        $employee->delete();

        if ($request->wantsJson()) {
            return response()->noContent();
        }

        return redirect()->route(config('hr.route.name').'employees.index')
            ->with('status', 'Employee dihapus.');
    }
}
