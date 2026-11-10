<?php

namespace App\Modules\ItAsset\Models;

use App\Modules\ItAsset\Support\ItAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_category_id
 * @property string|null $brand
 * @property string|null $serial_number
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $division
 * @property string|null $condition
 * @property string|null $notes
 * @property string $status
 * @property-read AssetCategory|null $category
 * @property-read Model|null $user
 * @property-read string|null $holder_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Asset extends Model
{
    protected $table = 'assets';

    protected $guarded = ['id'];

    /** @return BelongsTo<AssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(ItAsset::userModel());
    }

    /** Nama pemakai untuk ditampilkan: user sistem dulu, kalau tidak ada pakai nama bebas. */
    public function getHolderNameAttribute(): ?string
    {
        $user = $this->getRelationValue('user');
        $userName = $user instanceof Model ? $user->getAttribute('name') : null;

        return is_string($userName) ? $userName : $this->user_name;
    }

    /**
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', config('it-asset.active_status'));
    }

    /**
     * @param  Builder<Asset>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Asset>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['category'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->where('asset_category_id', $value))
            ->when($filters['status'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->where('status', $value))
            ->when($filters['condition'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->where('condition', $value))
            ->when($filters['division'] ?? null, fn (Builder $builder, mixed $value): Builder => $builder->where('division', $value))
            ->when($filters['q'] ?? null, function (Builder $builder, mixed $term): void {
                $builder->where(function (Builder $nested) use ($term): void {
                    $nested->where('brand', 'like', "%{$term}%")
                        ->orWhere('serial_number', 'like', "%{$term}%")
                        ->orWhere('user_name', 'like', "%{$term}%")
                        ->orWhere('division', 'like', "%{$term}%")
                        ->orWhere('notes', 'like', "%{$term}%")
                        ->orWhereHas('user', fn (Builder $user): Builder => $user->where('name', 'like', "%{$term}%"));
                });
            });
    }
}
