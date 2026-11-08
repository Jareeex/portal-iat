<?php

namespace App\Modules\Hr\Providers;

use App\Modules\Hr\Console\SyncEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Policies\EmployeePolicy;
use App\Modules\Hr\Policies\LeaveRequestPolicy;
use App\Modules\Hr\Support\Hr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class HrServiceProvider extends ServiceProvider
{
    private function base(string $path = ''): string
    {
        return dirname(__DIR__).($path ? '/'.$path : '');
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->base('config/hr.php'), 'hr');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->base('database/migrations'));
        $this->loadViewsFrom($this->base('resources/views'), 'hr');
        $this->loadRoutesFrom($this->base('routes/hr.php'));
        $this->loadRoutesFrom($this->base('routes/leave.php'));

        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);

        // Gate default: pakai $user->isHrAdmin(). Define ulang 'hr.manage' di
        // AppServiceProvider lu kalau mau pakai role/permission package.
        if (! Gate::has('hr.manage')) {
            Gate::define('hr.manage', fn ($user) => method_exists($user, 'isHrAdmin') && $user->isHrAdmin());
        }

        if (config('hr.auto_create_profile')) {
            (Hr::userModel())::created(fn ($user) => Employee::forUser($user));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SyncEmployees::class]);

            $this->publishes([$this->base('config/hr.php') => config_path('hr.php')], 'hr-config');
            $this->publishes([$this->base('resources/views') => resource_path('views/vendor/hr')], 'hr-views');
        }
    }
}
