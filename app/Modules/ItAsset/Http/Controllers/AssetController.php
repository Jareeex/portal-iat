<?php

namespace App\Modules\ItAsset\Http\Controllers;

use App\Modules\ItAsset\Http\Requests\AssetRequest;
use App\Modules\ItAsset\Http\Resources\AssetResource;
use App\Modules\ItAsset\Models\Asset;
use App\Modules\ItAsset\Models\AssetCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AssetController
{
    private const FILTERS = ['q', 'category', 'status', 'condition', 'division'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $assets = Asset::with(['category', 'user'])
            ->filter($request->only(self::FILTERS))
            ->orderBy('id')
            ->paginate(config('it-asset.per_page'))
            ->withQueryString();

        return AssetResource::collection($assets);
    }

    public function summary(): JsonResponse
    {
        return response()->json(AssetCategory::activeSummary());
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $assets = Asset::with('category')
            ->where('user_id', $request->user()->getKey())
            ->orderBy('asset_category_id')
            ->get();

        return AssetResource::collection($assets);
    }

    public function store(AssetRequest $request): JsonResponse
    {
        $asset = Asset::create($request->validated());

        return (new AssetResource($asset->load('category', 'user')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(AssetRequest $request, Asset $asset): AssetResource
    {
        $asset->update($request->validated());

        return new AssetResource($asset->load('category', 'user'));
    }

    public function destroy(Asset $asset): JsonResponse
    {
        Gate::authorize('it-asset.manage');
        $asset->delete();

        return response()->json([], 204);
    }
}
