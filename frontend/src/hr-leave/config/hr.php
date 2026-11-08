<?php

return [
    // null = pakai model dari config('auth.providers.users.model')
    'user_model' => null,

    // Buat profil employee kosong otomatis setiap ada User baru
    'auto_create_profile' => true,

    'route' => [
        'prefix' => 'hr',
        'name' => 'hr.',
        // API murni? ganti jadi ['api', 'auth:sanctum']
        'middleware' => ['web', 'auth'],
    ],

    // Layout Blade bawaan project lu + nama section isinya
    'layout' => 'layouts.app',
    'section' => 'content',

    'photo_disk' => 'public',
    'per_page' => 15,

    'leave' => [
        // Sabtu/Minggu tidak dihitung sebagai hari cuti
        'exclude_weekends' => true,
        // Tanggal libur nasional / cuti bersama (format Y-m-d) yang tidak dihitung
        'holidays' => [
            // '2026-12-25',
        ],
        // Boleh mengajukan mundur sekian hari (mis. cuti sakit)
        'backdate_days' => 7,
        // Disk private; lampiran diakses lewat controller + policy
        'attachment_disk' => 'local',
    ],

    'options' => [
        'genders' => ['Laki-laki', 'Perempuan'],
        'marital_statuses' => ['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'],
        'religions' => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'],
        'blood_types' => ['A', 'B', 'AB', 'O'],
        'educations' => ['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'],
        'employment_statuses' => ['Tetap', 'Kontrak', 'Probation', 'Magang', 'Resign'],
    ],
];
