<?php

namespace App\Services;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccessService
{
    /**
     * Aturan akses:
     *  - master / departemen full-access (BOD): semua halaman aktif
     *  - user biasa: halaman shared (tanpa departemen) + halaman departemennya
     *    + halaman tambahan yang di-assign master (user_page_access)
     */
    private function accessibleQuery(User $user): Builder
    {
        $query = Page::query()->where('pages.is_active', true);

        if ($user->hasFullAccess()) {
            return $query;
        }

        $extraIds = $user->pages()->pluck('pages.id');

        return $query->where(function (Builder $q) use ($user, $extraIds) {
            $q->whereNull('pages.department_id')
                ->orWhereIn('pages.id', $extraIds);

            if ($user->department_id) {
                $q->orWhere('pages.department_id', $user->department_id);
            }
        });
    }

    public function pagesFor(User $user): Collection
    {
        return $this->accessibleQuery($user)
            ->orderBy('pages.sort_order')
            ->get(['pages.id', 'pages.department_id', 'pages.key', 'pages.name',
                'pages.description', 'pages.route', 'pages.icon']);
    }

    public function canAccess(User $user, string $pageKey): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $this->accessibleQuery($user)->where('pages.key', $pageKey)->exists();
    }

    /** Ganti seluruh akses TAMBAHAN user dengan daftar page_id baru. */
    public function sync(User $user, array $pageIds): Collection
    {
        $user->pages()->sync($pageIds);

        return $this->pagesFor($user);
    }
}
