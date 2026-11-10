<?php

namespace App\Modules\ItAsset\Http\Controllers;

use App\Modules\ItAsset\Http\Requests\AssetCategoryRequest;
use App\Modules\ItAsset\Models\AssetCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AssetCategoryController
{
    public function index(): JsonResponse
    {
        return response()->json(
            AssetCategory::withCount('assets')->orderBy('code')->get(),
        );
    }

    public function store(AssetCategoryRequest $request): JsonResponse
    {
        $category = AssetCategory::create($request->validated());

        return response()->json($category, 201);
    }

    public function update(AssetCategoryRequest $request, AssetCategory $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json($category);
    }

    public function destroy(AssetCategory $category): JsonResponse
    {
        Gate::authorize('it-asset.manage');

        if ($category->assets()->exists()) {
            return response()->json(['message' => 'Kategori masih dipakai aset.'], 422);
        }

        $category->delete();

        return response()->json([], 204);
    }
}
