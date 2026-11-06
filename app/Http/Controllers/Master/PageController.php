<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\PageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function __construct(private PageService $pages)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->pages->all($request->filled('department_id') ? (int) $request->query('department_id') : null));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'], // null = shared
            'key' => ['required', 'alpha_dash', 'max:100', 'unique:pages,key'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'route' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->pages->create($data)->load('department'), 201);
    }

    public function show(Page $page): JsonResponse
    {
        return response()->json($page->load('department'));
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'key' => ['sometimes', 'alpha_dash', 'max:100', Rule::unique('pages', 'key')->ignore($page->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'route' => ['sometimes', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->pages->update($page, $data)->load('department'));
    }

    public function destroy(Page $page): JsonResponse
    {
        $this->pages->delete($page);

        return response()->json(null, 204);
    }
}
