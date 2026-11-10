<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
            $t->string('brand', 100)->nullable();                 // Merk Barang
            $t->string('serial_number', 100)->nullable()->unique();

            // User pemakai: link ke tabel users, atau nama bebas kalau bukan user sistem
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('user_name', 150)->nullable();

            $t->string('division', 120)->nullable();              // Divisi
            $t->string('condition', 30)->nullable();              // Kondisi
            $t->text('notes')->nullable();                        // Keterangan
            $t->string('status', 30)->default('Aktif');           // Status

            $t->timestamps();

            $t->index('status');
            $t->index('division');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
