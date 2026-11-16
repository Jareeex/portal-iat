<?php

namespace App\Modules\Fis\Providers;

use App\Models\User;
use App\Services\AccessService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FisServiceProvider extends ServiceProvider
{
    private function base(string $path = ''): string
    {
        return dirname(__DIR__).($path ? '/'.$path : '');
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->base('config/fis.php'), 'fis');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->base('database/migrations'));
        $this->loadViewsFrom($this->base('resources/views'), 'fis');
        $this->loadRoutesFrom($this->base('routes/fis.php'));

        if (! Gate::has('fis.manage')) {
            Gate::define(
                'fis.manage',
                fn (User $user): bool => app(AccessService::class)->canEdit($user, 'fis'),
            );
        }
    }
}
