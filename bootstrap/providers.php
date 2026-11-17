<?php

use App\Modules\Fis\Providers\FisServiceProvider;
use App\Modules\Hr\Providers\HrServiceProvider;
use App\Modules\ItAsset\Providers\ItAssetServiceProvider;
use App\Modules\ItTickets\Providers\ItTicketsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    HrServiceProvider::class,
    FisServiceProvider::class,
    ItAssetServiceProvider::class,
    AppServiceProvider::class,
    ItTicketsServiceProvider::class,
];
