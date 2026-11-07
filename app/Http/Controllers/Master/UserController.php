<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    /** @return LengthAwarePaginator<int, User> */
    public function index(Request $request): LengthAwarePaginator
    {
        return $this->users->paginate(
            $request->query('search'),
            $request->filled('department_id') ? (int) $request->query('department_id') : null,
            (int) $request->query('per_page', 15)
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('department')->loadCount('pages'));
    }

    public function update(StoreUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // Master tidak boleh menurunkan role / menonaktifkan dirinya sendiri.
        if ($request->user()->is($user)) {
            unset($data['role'], $data['is_active']);
        }

        return response()->json($this->users->update($user, $data));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri.'], 422);
        }

        $this->users->delete($user);

        return response()->json(null, 204);
    }
}
