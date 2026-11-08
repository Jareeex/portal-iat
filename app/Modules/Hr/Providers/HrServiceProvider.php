<?php

namespace App\Modules\Hr\Providers;

use App\Models\User;
use App\Modules\Hr\Console\SyncEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Policies\EmployeePolicy;
use App\Modules\Hr\Policies\LeaveRequestPolicy;
use App\Services\AccessService;
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
        $this->loadRoutesFrom($this->base('routes/hr.php'));

        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);

        if (! Gate::has('hr.manage')) {
            Gate::define('hr.manage', fn (User $user): bool => app(AccessService::class)->canEdit($user, 'hr-employees'));
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SyncEmployees::class]);

            $this->publishes([$this->base('config/hr.php') => config_path('hr.php')], 'hr-config');
        }
    }
}
