<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_experiences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->string('type', 10);                 // outside | inside
            $t->string('company', 150);
            $t->string('position', 150)->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->text('description')->nullable();
            $t->timestamps();

            $t->index(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_experiences');
    }
};
