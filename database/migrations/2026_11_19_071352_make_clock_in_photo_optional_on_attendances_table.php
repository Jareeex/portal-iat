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
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('clock_in_photo_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('attendances')
            ->whereNull('clock_in_photo_path')
            ->update(['clock_in_photo_path' => '']);

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('clock_in_photo_path')->nullable(false)->change();
        });
    }
};
