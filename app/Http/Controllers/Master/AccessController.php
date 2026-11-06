<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAccessRequest;
use App\Models\Page;
use App\Models\User;
use App\Services\AccessService;
use Illuminate\Http\JsonResponse;

class AccessController extends Controller
{
    public function __construct(private AccessService $access)
    {
    }

    /**
     * Semua halaman + status akses user ini:
     *  - by_department: sudah otomatis terbuka (shared / departemennya / full-access)
     *  - granted: akses tambahan yang di-assign manual lewat checklist
     */
    public function show(User $user): JsonResponse
    {
        $user->load('department');
        $extra = $user->pages()->pluck('pages.id')->all();
        $full = $user->hasFullAccess();

        $pages = Page::query()->with('department:id,name,code')->orderBy('sort_order')->get()
            ->map(fn (Page $p) => [
                'id' => $p->id,
                'key' => $p->key,
                'name' => $p->name,
                'is_active' => $p->is_active,
                'department' => $p->department?->only(['id', 'name', 'code']),
                'by_department' => $full || $p->department_id === null || $p->department_id === $user->department_id,
                'granted' => in_array($p->id, $extra, true),
            ]);

        return response()->json([
            'user_id' => $user->id,
            'full_access' => $full,
            'pages' => $pages,
        ]);
    }

    public function update(UpdateAccessRequest $request, User $user): JsonResponse
    {
        $pages = $this->access->sync($user, $request->validated('page_ids'));

        return response()->json(['user_id' => $user->id, 'pages' => $pages]);
    }
}
