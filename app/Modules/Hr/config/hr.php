<?php

return [
    'route' => [
        'prefix' => 'api/hr',
        'name' => 'hr.',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    'photo_disk' => 'public',
    'per_page' => 15,

    'options' => [
        'genders' => ['Laki-laki', 'Perempuan'],
        'marital_statuses' => ['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'],
        'religions' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'],
        'blood_types' => ['A', 'B', 'AB', 'O'],
        'educations' => ['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'],
        'employment_statuses' => ['Tetap', 'Kontrak', 'Probation', 'Magang', 'Resign'],
    ],
];
