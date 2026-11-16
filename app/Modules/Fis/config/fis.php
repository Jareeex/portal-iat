<?php

return [
    'route' => [
        'prefix' => 'api/fis',
        'name' => 'fis.',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    'per_page' => 15,
];
