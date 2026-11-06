<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
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

    public function create(array $data): User
    {
        $data['role'] ??= User::ROLE_USER;

        return User::create($data)->load('department');
    }

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
