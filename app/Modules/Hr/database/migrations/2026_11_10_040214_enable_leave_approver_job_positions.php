<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const APPROVER_POSITIONS = ['BOD', 'Officer', 'SPV', 'Chief'];

    public function up(): void
    {
        DB::table('job_positions')
            ->whereIn('name', self::APPROVER_POSITIONS)
            ->update([
                'is_leave_manager' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('job_positions')
            ->whereIn('name', self::APPROVER_POSITIONS)
            ->update([
                'is_leave_manager' => false,
                'updated_at' => now(),
            ]);
    }
};
