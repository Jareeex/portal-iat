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
        Schema::table('crews', function (Blueprint $table) {
            $table->string('pilot_role', 3)->nullable()->after('group');
        });

        foreach (['PIC', 'SIC'] as $role) {
            DB::table('crews')
                ->whereRaw('UPPER(TRIM(position)) = ?', [$role])
                ->update(['pilot_role' => $role]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crews', function (Blueprint $table) {
            $table->dropColumn('pilot_role');
        });
    }
};
