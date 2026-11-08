<?php

namespace App\Modules\ItTickets\Providers;

use Illuminate\Support\ServiceProvider;

class ItTicketsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $base = dirname(__DIR__);

        $this->loadMigrationsFrom($base.'/database/migrations');
        $this->loadRoutesFrom($base.'/routes/tickets.php');
    }
}
