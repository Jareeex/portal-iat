<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->unsignedInteger('clock_in_distance_meters')->nullable();
            $table->boolean('clock_in_is_offsite')->default(false);
            $table->string('clock_in_reason')->nullable();
            $table->string('clock_in_reason_details')->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('clock_out_distance_meters')->nullable();
            $table->boolean('clock_out_is_offsite')->default(false);
            $table->string('clock_out_reason')->nullable();
            $table->string('clock_out_reason_details')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_latitude',
                'clock_in_longitude',
                'clock_in_distance_meters',
                'clock_in_is_offsite',
                'clock_in_reason',
                'clock_in_reason_details',
                'clock_out_latitude',
                'clock_out_longitude',
                'clock_out_distance_meters',
                'clock_out_is_offsite',
                'clock_out_reason',
                'clock_out_reason_details',
            ]);
        });
    }
};
