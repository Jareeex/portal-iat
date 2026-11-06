<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()      // null = shared, semua user login boleh
                ->constrained('departments')->nullOnDelete();
            $table->string('key')->unique();      // slug dipakai di middleware & frontend
            $table->string('name');               // label di sidebar
            $table->string('description')->nullable();
            $table->string('route');              // path frontend
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
