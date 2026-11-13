<?php

namespace App\Models;

use App\Modules\Hr\Concerns\HasEmployee;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property-read Department|null $department
 */
class User extends Authenticatable
{
    use HasApiTokens, HasEmployee, Notifiable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public const ROLE_MASTER = 'master';

    public const ROLE_USER = 'user';

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'department_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'training_reminder_enabled' => 'boolean',
        ];
    }

    public function isMaster(): bool
    {
        return $this->role === self::ROLE_MASTER;
    }

    /** Master atau anggota departemen full-access (BOD) -> lihat semua halaman. */
    public function hasFullAccess(): bool
    {
        return $this->isMaster() || (bool) $this->department?->has_full_access;
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasOne<Crew, $this> */
    public function crew(): HasOne
    {
        return $this->hasOne(Crew::class);
    }

    /**
     * Halaman tambahan di luar departemen.
     *
     * @return BelongsToMany<Page, $this, UserPageAccess, 'pivot'>
     */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'user_page_access')
            ->using(UserPageAccess::class)
            ->withPivot('access_level')
            ->withTimestamps();
    }
}
