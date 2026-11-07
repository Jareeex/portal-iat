<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** @property-read UserPageAccess $pivot */
class Page extends Model
{
    protected $fillable = ['department_id', 'key', 'name', 'description', 'route', 'icon', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsToMany<User, $this, UserPageAccess, 'pivot'> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_page_access')
            ->using(UserPageAccess::class)
            ->withTimestamps();
    }
}
