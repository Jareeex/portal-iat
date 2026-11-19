<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_program_plans', function (Blueprint $table): void {
            $table->boolean('not_executed')->default(false);
            $table->text('remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('training_program_plans', function (Blueprint $table): void {
            $table->dropColumn(['not_executed', 'remarks']);
        });
    }
};
