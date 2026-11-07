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
    public function __construct(private AccessService $access) {}

    /**
     * Semua halaman + status akses user ini:
     *  - by_department: sudah otomatis terbuka (shared / departemennya / full-access)
     *  - granted: akses tambahan yang di-assign manual lewat checklist
     */
    public function show(User $user): JsonResponse
    {
        $user->load('department');
        $explicitPages = $user->pages->keyBy('id');
        $full = $user->hasFullAccess();

        $pages = Page::query()->with('department:id,name,code')->orderBy('sort_order')->get()
            ->map(function (Page $page) use ($explicitPages, $full, $user): array {
                $byDepartment = $full
                    || $page->department_id === null
                    || $page->department_id === $user->department_id;
                $explicit = $explicitPages->get($page->id);

                return [
                    'id' => $page->id,
                    'key' => $page->key,
                    'name' => $page->name,
                    'is_active' => $page->is_active,
                    'department' => $page->department?->only(['id', 'name', 'code']),
                    'by_department' => $byDepartment,
                    'granted' => $explicit !== null,
                    'permission' => $user->isMaster()
                        ? 'edit'
                        : ($explicit?->pivot->access_level ?? ($byDepartment ? 'view' : null)),
                ];
            });

        return response()->json([
            'user_id' => $user->id,
            'full_access' => $full,
            'is_master' => $user->isMaster(),
            'pages' => $pages,
        ]);
    }

    public function update(UpdateAccessRequest $request, User $user): JsonResponse
    {
        $pages = $this->access->sync(
            $user,
            $request->validated('page_ids'),
            $request->validated('edit_page_ids', []),
        );

        return response()->json(['user_id' => $user->id, 'pages' => $pages]);
    }
}
