<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_requests')
            ->where('status', 'pending')
            ->update(['status' => 'hr_pending']);

        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->foreignId('manager_id')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
            $table->foreignId('manager_reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            $table->timestamp('manager_reviewed_at')->nullable()->after('manager_reviewed_by');
            $table->text('manager_review_note')->nullable()->after('manager_reviewed_at');
            $table->index(['manager_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropIndex(['manager_id', 'status']);
            $table->dropConstrainedForeignId('manager_reviewed_by');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['manager_reviewed_at', 'manager_review_note']);
        });
    }
};
