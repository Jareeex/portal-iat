<?php

namespace App\Modules\ItAsset\Providers;

use App\Models\User;
use App\Services\AccessService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ItAssetServiceProvider extends ServiceProvider
{
    private function base(string $path = ''): string
    {
        return dirname(__DIR__).($path ? '/'.$path : '');
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->base('config/it-asset.php'), 'it-asset');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->base('database/migrations'));
        $this->loadViewsFrom($this->base('resources/views'), 'it-asset');
        $this->loadRoutesFrom($this->base('routes/it-asset.php'));

        if (! Gate::has('it-asset.manage')) {
            Gate::define(
                'it-asset.manage',
                fn (User $user): bool => app(AccessService::class)->canEdit($user, 'it-assets'),
            );
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([$this->base('config/it-asset.php') => config_path('it-asset.php')], 'it-asset-config');
        }
    }
}
