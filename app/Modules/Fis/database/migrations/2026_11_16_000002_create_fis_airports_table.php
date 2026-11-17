<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fis_airports', function (Blueprint $table) {
            $table->string('ident', 20)->primary();
            $table->string('type', 30);
            $table->string('name');
            $table->string('iso_country', 2)->nullable();
            $table->string('country_name')->nullable();
            $table->string('municipality')->nullable();
            $table->string('icao_code', 4)->nullable();
            $table->string('iata_code', 3)->nullable();
            $table->boolean('scheduled_service')->default(false);
            $table->timestamps();

            $table->index('iata_code');
            $table->index('icao_code');
            $table->index('municipality');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fis_airports');
    }
};
