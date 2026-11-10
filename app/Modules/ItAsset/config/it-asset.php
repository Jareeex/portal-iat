<?php

return [
    // null = pakai model dari config('auth.providers.users.model')
    'user_model' => null,

    'route' => [
        'prefix' => 'api/it/assets',
        'name' => 'it-assets.',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    'per_page' => 20,

    // Status yang dihitung di "TOTAL AKTIF" (harus sama persis dengan salah satu di 'statuses')
    'active_status' => 'Aktif',

    'statuses' => ['Aktif', 'Tidak Aktif', 'Dalam Perbaikan', 'Dihapus'],
    'conditions' => ['Baik', 'Kurang Baik', 'Rusak'],
];
