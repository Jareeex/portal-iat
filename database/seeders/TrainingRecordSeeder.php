<?php

namespace Database\Seeders;

use App\Models\Crew;
use App\Models\CrewTraining;
use App\Models\TrainingType;
use Illuminate\Database\Seeder;
use RuntimeException;

class TrainingRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/training.json');

        if (! is_file($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read training records: {$path}");
        }

        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $this->call(TrainingTypeSeeder::class);

        $typeIds = [];

        foreach ($data['types'] as $group => $types) {
            foreach ($types as $index => $type) {
                $trainingType = TrainingType::query()
                    ->where('group', $group)
                    ->where('section', $type['section'])
                    ->where('name', $type['name'])
                    ->firstOrFail();

                $typeIds[$group][$index] = $trainingType->id;
            }
        }

        foreach ($data['crews'] as $sortOrder => $crewData) {
            $records = $crewData['records'];
            unset($crewData['records']);

            $group = $crewData['group'];
            $groupInfo = Crew::groupOptions()[$group];
            $crewData['category'] = $groupInfo['category'];
            $crewData['fleet'] = $groupInfo['label'];
            $crewData['position'] ??= '';
            $crewData['sort_order'] = $sortOrder;

            $crew = Crew::query()
                ->where('group', $group)
                ->where('name', $crewData['name'])
                ->first();

            if (! $crew) {
                $crew = Crew::query()->whereNull('group')->where('name', $crewData['name'])->first();
            }

            if ($crew) {
                $crew->update($crewData);
            } else {
                $crew = Crew::create($crewData);
            }

            foreach ($records as [$index, $lastCheck, $nextCheck, $planCheck]) {
                if (! isset($typeIds[$group][$index])) {
                    continue;
                }

                CrewTraining::updateOrCreate(
                    ['crew_id' => $crew->id, 'training_type_id' => $typeIds[$group][$index]],
                    ['last_check' => $lastCheck, 'next_check' => $nextCheck, 'plan_check' => $planCheck],
                );
            }
        }
    }
}
