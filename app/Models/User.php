<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public const ROLE_MASTER = 'master';

    public const ROLE_USER = 'user';

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'department_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Halaman tambahan di luar departemen. */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'user_page_access')->withTimestamps();
    }
}
