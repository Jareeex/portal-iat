<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('code')->unique();   // Kode: 1 Laptop, 2 PC, ...
            $t->string('name', 100)->unique();            // Kategori
            $t->timestamps();
        });

        $now = now();
        foreach ([1 => 'Laptop', 2 => 'PC', 3 => 'Printer', 4 => 'Monitor', 5 => 'Proyektor', 6 => 'Ipad', 7 => 'Handphone'] as $code => $name) {
            DB::table('asset_categories')->insert(['code' => $code, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
