<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Baris program di Excel (mis. "CRM", "Line Check ATR 42-500")
        Schema::create('training_programs', function (Blueprint $t) {
            $t->id();
            $t->string('category', 20)->index();   // rw, fw, fa, foo, mandatory, additional
            $t->string('name', 150);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        // Program ini mengambil data dari item training (training_types) yang mana -> sumber plan
        Schema::create('training_program_types', function (Blueprint $t) {
            $t->foreignId('program_id')->constrained('training_programs')->cascadeOnDelete();
            $t->foreignId('type_id')->constrained('training_types')->cascadeOnDelete();
            $t->primary(['program_id', 'type_id']);
        });

        // PLAN (kuning): snapshot jatuh tempo per crew per tahun, disusun dari last check
        Schema::create('training_program_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained('training_programs')->cascadeOnDelete();
            $t->foreignId('crew_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedTinyInteger('month');                  // 1-12
            $t->date('due_date')->nullable();                  // jatuh tempo asal (last check + masa berlaku)
            $t->boolean('overdue')->default(false);            // sudah lewat sebelum tahun ini -> ditaruh di Januari
            $t->timestamps();
            $t->unique(['program_id', 'crew_id', 'year']);
            $t->index(['year', 'month']);
        });

        // ACTUAL / Implementation (biru): diinput staff training
        Schema::create('training_program_actuals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_id')->constrained('training_programs')->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedTinyInteger('month');
            $t->date('held_on');
            $t->date('end_on')->nullable();
            $t->string('venue', 150)->nullable();
            $t->string('notes', 1000)->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['program_id', 'year', 'month']);
        });

        Schema::create('program_actual_crew', function (Blueprint $t) {
            $t->foreignId('actual_id')->constrained('training_program_actuals')->cascadeOnDelete();
            $t->foreignId('crew_id')->constrained()->cascadeOnDelete();
            $t->primary(['actual_id', 'crew_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_actual_crew');
        Schema::dropIfExists('training_program_actuals');
        Schema::dropIfExists('training_program_plans');
        Schema::dropIfExists('training_program_types');
        Schema::dropIfExists('training_programs');
    }
};
