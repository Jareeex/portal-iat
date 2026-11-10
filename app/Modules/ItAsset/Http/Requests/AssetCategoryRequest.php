<?php

namespace App\Modules\ItAsset\Http\Requests;

use App\Modules\ItAsset\Models\AssetCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('it-asset.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $category = $this->route('category');
        $id = $category instanceof AssetCategory ? $category->id : null;

        return [
            'code' => ['required', 'integer', 'min:1', 'max:65000', Rule::unique('asset_categories', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100', Rule::unique('asset_categories', 'name')->ignore($id)],
        ];
    }
}
