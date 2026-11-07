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
        $explicitPages = $user->pages->keyBy('id');

        return $this->accessibleQuery($user)
            ->orderBy('pages.sort_order')
            ->get(['pages.id', 'pages.department_id', 'pages.key', 'pages.name',
                'pages.description', 'pages.route', 'pages.icon'])
            ->each(function (Page $page) use ($explicitPages, $user): void {
                $page->setAttribute(
                    'access_level',
                    $user->isMaster() || $explicitPages->get($page->id)?->pivot->access_level === 'edit'
                        ? 'edit'
                        : 'view',
                );
            });
    }

    public function canAccess(User $user, string $pageKey): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $this->accessibleQuery($user)->where('pages.key', $pageKey)->exists();
    }

    public function canEdit(User $user, string $pageKey): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isMaster()) {
            return true;
        }

        if (! $this->canAccess($user, $pageKey)) {
            return false;
        }

        return $user->pages()
            ->where('pages.key', $pageKey)
            ->wherePivot('access_level', 'edit')
            ->exists();
    }

    /**
     * Replace the user's explicit page grants and editor overrides.
     *
     * @param  list<int>  $pageIds  Pages granted view or edit access.
     * @param  list<int>  $editPageIds  Pages granted edit access.
     */
    public function sync(User $user, array $pageIds, array $editPageIds = []): Collection
    {
        $editIds = array_fill_keys($editPageIds, true);
        $pages = Page::query()->whereIn('id', array_unique([...$pageIds, ...$editPageIds]))->get();
        $sync = [];

        foreach ($pages as $page) {
            $isAutomatic = $user->hasFullAccess()
                || $page->department_id === null
                || $page->department_id === $user->department_id;
            $isEditor = isset($editIds[$page->id]);

            if ($isAutomatic && ! $isEditor) {
                continue;
            }

            $sync[$page->id] = ['access_level' => $isEditor ? 'edit' : 'view'];
        }

        $user->pages()->sync($sync);

        return $this->pagesFor($user);
    }
}
