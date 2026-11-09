<?php

namespace Database\Seeders;

use App\Models\TrainingType;
use Illuminate\Database\Seeder;
use RuntimeException;

class TrainingTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/training.json');

        if (! is_file($path)) {
            throw new RuntimeException("Training catalog not found: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read training catalog: {$path}");
        }

        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        foreach ($data['types'] as $group => $types) {
            foreach ($types as $index => $type) {
                TrainingType::updateOrCreate(
                    ['group' => $group, 'section' => $type['section'], 'name' => $type['name']],
                    ['validity' => $type['validity'], 'sort_order' => $index],
                );
            }
        }
    }
}
