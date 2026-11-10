<?php

namespace App\Modules\ItAsset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $code
 * @property string $name
 * @property-read int|null $assets_count
 * @property-read int|null $active_count
 */
class AssetCategory extends Model
{
    protected $table = 'asset_categories';

    protected $fillable = ['code', 'name'];

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * TOTAL AKTIF: jumlah aset berstatus aktif per kategori (hanya yang > 0) + grand total.
     *
     * @return array{rows: array<int, array{code:int,name:string,total:int}>, total:int}
     */
    public static function activeSummary(): array
    {
        $categories = static::withCount([
            'assets as active_count' => fn ($q) => $q->where('status', config('it-asset.active_status')),
        ])->orderBy('code')->get();

        return [
            'rows' => $categories->where('active_count', '>', 0)->values()
                ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'total' => (int) $c->active_count])
                ->all(),
            'total' => (int) $categories->sum('active_count'),
        ];
    }
}
