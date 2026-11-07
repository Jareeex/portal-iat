<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Database\Eloquent\Collection;

class PageService
{
    /** @return Collection<int, Page> */
    public function all(?int $departmentId = null): Collection
    {
        return Page::query()
            ->with('department:id,name,code')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('sort_order')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Page
    {
        return Page::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Page $page, array $data): Page
    {
        $page->update($data);

        return $page->refresh();
    }

    public function delete(Page $page): void
    {
        $page->delete(); // pivot ikut terhapus (cascade)
    }
}
