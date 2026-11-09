<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Hr\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService
{
    /** @return LengthAwarePaginator<int, User> */
    public function paginate(?string $search = null, ?int $departmentId = null, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->with('department:id,name,code,has_full_access')
            ->withCount('pages')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($search, function ($q) use ($search) {
                $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate($perPage);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        $data['role'] ??= User::ROLE_USER;

        return DB::transaction(function () use ($data): User {
            $employeeId = isset($data['employee_id']) ? (int) $data['employee_id'] : null;
            unset($data['employee_id']);

            $employee = $employeeId === null
                ? null
                : Employee::query()
                    ->whereNull('user_id')
                    ->lockForUpdate()
                    ->findOrFail($employeeId);

            $user = User::create($data);

            if ($employee) {
                $user->employee()->delete();
                $employee->user()->associate($user);
                $employee->save();
            }

            return $user->load('department');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        // Akun dinonaktifkan -> cabut semua token supaya langsung ke-logout.
        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $user->tokens()->delete();
        }

        return $user->refresh()->load('department');
    }

    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }
}
