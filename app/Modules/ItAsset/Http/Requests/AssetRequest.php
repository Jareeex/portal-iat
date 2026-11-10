<?php

namespace App\Modules\ItAsset\Http\Requests;

use App\Modules\ItAsset\Models\Asset;
use App\Modules\ItAsset\Support\ItAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('it-asset.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $usersTable = (new (ItAsset::userModel()))->getTable();
        $asset = $this->route('asset');
        $assetId = $asset instanceof Asset ? $asset->id : null;

        return [
            'asset_category_id' => ['required', 'exists:asset_categories,id'],
            'brand' => ['required', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100',
                Rule::unique('assets', 'serial_number')->ignore($assetId)],
            'user_id' => ['nullable', Rule::exists($usersTable, 'id')],
            'user_name' => ['nullable', 'string', 'max:150'],
            'division' => ['nullable', 'string', 'max:120'],
            'condition' => ['nullable', Rule::in(config('it-asset.conditions'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(config('it-asset.statuses'))],
        ];
    }

    /** Kalau user sistem dipilih, nama bebas dikosongkan supaya tidak dobel. */
    protected function passedValidation(): void
    {
        if ($this->filled('user_id')) {
            $this->merge(['user_name' => null]);
        }
    }
}
