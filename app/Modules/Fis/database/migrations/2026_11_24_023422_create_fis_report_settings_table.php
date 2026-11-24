<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fis_report_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('header_title', 150);
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fis_report_settings');
    }
};
