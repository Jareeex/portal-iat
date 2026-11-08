<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->unsignedSmallInteger('annual_quota_days')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('leave_types')->insert([
            ['name' => 'Cuti Tahunan', 'code' => 'ANNUAL', 'annual_quota_days' => 12, 'requires_attachment' => false, 'is_paid' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cuti Sakit', 'code' => 'SICK', 'annual_quota_days' => null, 'requires_attachment' => true, 'is_paid' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Izin Khusus', 'code' => 'SPECIAL', 'annual_quota_days' => null, 'requires_attachment' => false, 'is_paid' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cuti Melahirkan', 'code' => 'MATERNITY', 'annual_quota_days' => 90, 'requires_attachment' => true, 'is_paid' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cuti Tanpa Gaji', 'code' => 'UNPAID', 'annual_quota_days' => null, 'requires_attachment' => false, 'is_paid' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
