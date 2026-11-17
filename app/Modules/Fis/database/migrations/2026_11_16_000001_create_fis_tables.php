<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aircraft_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('registration', 20)->unique();
            $table->string('aircraft_type', 100);
            $table->unsignedSmallInteger('seat_capacity');
            $table->timestamps();
        });

        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->string('flight_no', 20);
            $table->date('date');
            $table->string('chartered_by', 150);
            $table->foreignId('aircraft_registration_id')
                ->constrained('aircraft_registrations')
                ->restrictOnDelete();
            $table->json('schedules');
            $table->json('crews')->nullable();
            $table->json('handling_agents')->nullable();
            $table->json('passengers')->nullable();
            $table->json('accommodations')->nullable();
            $table->json('other')->nullable();
            $table->timestamps();

            $table->index(['flight_no', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
        Schema::dropIfExists('aircraft_registrations');
    }
};
