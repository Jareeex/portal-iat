<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crew_training_aircraft', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_aircraft_id')->constrained('training_aircrafts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['crew_id', 'training_aircraft_id']);
        });

        DB::table('crews')
            ->join('training_aircrafts', 'training_aircrafts.group_key', '=', 'crews.group')
            ->select('crews.id as crew_id', 'training_aircrafts.id as training_aircraft_id')
            ->orderBy('crews.id')
            ->get()
            ->each(function (object $assignment): void {
                DB::table('crew_training_aircraft')->insert([
                    'crew_id' => $assignment->crew_id,
                    'training_aircraft_id' => $assignment->training_aircraft_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crew_training_aircraft');
    }
};
