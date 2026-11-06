<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Departemen. BOD = full access (semua halaman) tapi bukan master.
        $departments = [
            ['code' => 'BOD', 'name' => 'Board of Directors', 'has_full_access' => true],
            ['code' => 'HR',  'name' => 'Human Resources',    'has_full_access' => false],
            ['code' => 'IT',  'name' => 'Information Technology', 'has_full_access' => false],
        ];

        $dept = [];
        foreach ($departments as $d) {
            $dept[$d['code']] = Department::updateOrCreate(['code' => $d['code']], $d);
        }

        // Akun master awal (tanpa departemen). GANTI password ini sebelum deploy!
        User::updateOrCreate(
            ['email' => 'master@portal.test'],
            [
                'name' => 'Master Admin',
                'password' => 'ChangeMe123!',
                'role' => User::ROLE_MASTER,
                'is_active' => true,
            ]
        );

        // Halaman. department_id null = shared untuk semua user yang login.
        $pages = [
            ['department_id' => null,               'key' => 'dashboard',     'name' => 'Dashboard',    'route' => '/dashboard',     'icon' => 'layout-dashboard', 'sort_order' => 1],
            ['department_id' => $dept['HR']->id,    'key' => 'hr-employees',  'name' => 'Employees',    'route' => '/hr/employees',  'icon' => 'users',            'sort_order' => 10],
            ['department_id' => $dept['HR']->id,    'key' => 'hr-leave',      'name' => 'Leave',        'route' => '/hr/leave',      'icon' => 'calendar',         'sort_order' => 11],
            ['department_id' => $dept['IT']->id,    'key' => 'it-assets',     'name' => 'IT Assets',    'route' => '/it/assets',     'icon' => 'server',           'sort_order' => 20],
            ['department_id' => $dept['IT']->id,    'key' => 'it-tickets',    'name' => 'IT Tickets',   'route' => '/it/tickets',    'icon' => 'ticket',           'sort_order' => 21],
            ['department_id' => $dept['IT']->id,    'key' => 'reports',       'name' => 'Reports',      'route' => '/reports',       'icon' => 'file-text',        'sort_order' => 22],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(['key' => $page['key']], $page + ['is_active' => true]);
        }

        $this->call(TrainingPortalSeeder::class);
    }
}
