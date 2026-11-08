<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained('leave_types');

            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedSmallInteger('days');               // hari kerja, dihitung server
            $t->text('reason');
            $t->string('contact_phone', 30)->nullable();    // kontak selama cuti
            $t->string('attachment_path')->nullable();

            $t->string('status', 20)->default('pending');   // pending|approved|rejected|cancelled
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('review_note')->nullable();

            $t->timestamps();

            $t->index('status');
            $t->index(['employee_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
