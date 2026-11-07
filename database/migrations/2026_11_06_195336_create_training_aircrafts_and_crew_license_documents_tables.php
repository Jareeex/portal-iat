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
        Schema::create('training_aircrafts', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 20)->nullable()->unique();
            $table->string('name', 120)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('training_aircrafts')->insert([
            ['group_key' => 'fw_atr', 'name' => 'ATR', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['group_key' => 'fw_rss', 'name' => 'EMB 135', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['group_key' => 'fw_jam', 'name' => 'Hawker 900XP', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['group_key' => 'rw', 'name' => 'EC 155', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('crew_license_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('path')->unique();
            $table->timestamps();

            $table->index(['crew_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crew_license_documents');
        Schema::dropIfExists('training_aircrafts');
    }
};
