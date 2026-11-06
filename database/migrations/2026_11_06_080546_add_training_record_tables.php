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
        Schema::table('crews', function (Blueprint $table) {
            $table->string('group', 20)->nullable()->index();
            $table->date('dob')->nullable();
            $table->text('address')->nullable();
            $table->string('passport_no', 50)->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('nationality', 50)->nullable();
            $table->date('joined_iat')->nullable();
            $table->string('licence', 100)->nullable();
            $table->string('rating', 100)->nullable();
            $table->string('remarks', 500)->nullable();
        });

        Schema::create('training_types', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20);
            $table->string('section', 60);
            $table->string('name', 120);
            $table->string('validity', 30)->default('');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['group', 'section', 'name']);
            $table->index(['group', 'sort_order']);
        });

        Schema::create('crew_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_type_id')->constrained()->cascadeOnDelete();
            $table->date('last_check')->nullable();
            $table->date('next_check')->nullable()->index();
            $table->date('plan_check')->nullable();
            $table->timestamps();
            $table->unique(['crew_id', 'training_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crew_trainings');
        Schema::dropIfExists('training_types');

        Schema::table('crews', function (Blueprint $table) {
            $table->dropColumn([
                'group',
                'dob',
                'address',
                'passport_no',
                'passport_expiry',
                'nationality',
                'joined_iat',
                'licence',
                'rating',
                'remarks',
            ]);
        });
    }
};
