<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('employees')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Link or remove unlinked employees before rolling back this migration.');
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
