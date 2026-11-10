<?php

namespace App\Modules\ItAsset\Http\Resources;

use App\Modules\ItAsset\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asset */
class AssetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id, 'code' => $this->category->code, 'name' => $this->category->name,
            ]),
            'brand' => $this->brand,
            'serial_number' => $this->serial_number,
            'user' => $this->whenLoaded('user', fn () => $this->user
                ? [
                    'id' => $this->user->getKey(),
                    'name' => $this->user->getAttribute('name'),
                ] : null),
            'user_name' => $this->user_name,
            'holder' => $this->holder_name,
            'division' => $this->division,
            'condition' => $this->condition,
            'notes' => $this->notes,
            'status' => $this->status,
            'updated_at' => $this->updated_at,
        ];
    }
}
