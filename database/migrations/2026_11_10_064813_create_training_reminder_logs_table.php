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
        Schema::create('training_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('due_date');
            $table->string('stage', 24);
            $table->unique(
                ['crew_training_id', 'due_date', 'stage', 'recipient_user_id'],
                'training_reminders_unique_delivery',
            );
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_reminder_logs');
    }
};
