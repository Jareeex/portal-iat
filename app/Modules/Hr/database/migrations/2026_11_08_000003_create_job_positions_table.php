<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_leave_manager')->default(false);
            $table->timestamps();
        });

        $now = now();
        DB::table('job_positions')->insert([
            ['name' => 'OJT', 'sort_order' => 1, 'is_active' => true, 'is_leave_manager' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Officer', 'sort_order' => 2, 'is_active' => true, 'is_leave_manager' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'SPV', 'sort_order' => 3, 'is_active' => true, 'is_leave_manager' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chief', 'sort_order' => 4, 'is_active' => true, 'is_leave_manager' => false, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Manager', 'sort_order' => 5, 'is_active' => true, 'is_leave_manager' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'BOD', 'sort_order' => 6, 'is_active' => true, 'is_leave_manager' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $legacyPositions = DB::table('employees')
            ->whereNotNull('job_title')
            ->distinct()
            ->pluck('job_title');
        $sortOrder = 7;

        foreach ($legacyPositions as $name) {
            if (! DB::table('job_positions')->where('name', $name)->exists()) {
                DB::table('job_positions')->insert([
                    'name' => $name,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                    'is_leave_manager' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_positions');
    }
};
