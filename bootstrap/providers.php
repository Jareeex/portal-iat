<?php

use App\Modules\Hr\Providers\HrServiceProvider;
use App\Modules\ItTickets\Providers\ItTicketsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    HrServiceProvider::class,
    AppServiceProvider::class,
    ItTicketsServiceProvider::class,
];
