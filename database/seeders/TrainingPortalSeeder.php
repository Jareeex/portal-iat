<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Page;
use Illuminate\Database\Seeder;

class TrainingPortalSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::updateOrCreate(
            ['code' => 'TR'],
            ['name' => 'Training', 'has_full_access' => false],
        );

        Page::updateOrCreate(
            ['key' => 'training'],
            [
                'department_id' => $department->id,
                'name' => 'Training',
                'description' => 'Training record dan masa berlaku crew.',
                'route' => '/training',
                'icon' => 'graduation-cap',
                'sort_order' => 30,
                'is_active' => true,
            ],
        );

        $this->call(TrainingRecordSeeder::class);
        $this->call(TrainingProgramSeeder::class);
    }
}
