<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            // 1 user = 1 employee
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Employment
            $t->string('employee_number', 50)->nullable()->unique();
            $t->string('job_title', 120)->nullable();        // "Function"
            $t->string('department', 120)->nullable();
            $t->string('office', 120)->nullable();
            $t->string('employment_status', 30)->nullable();
            $t->date('join_date')->nullable();
            $t->date('end_date')->nullable();

            // Personal
            $t->string('place_of_birth', 100)->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('gender', 20)->nullable();
            $t->string('marital_status', 30)->nullable();
            $t->string('religion', 30)->nullable();
            $t->text('address')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('photo_path')->nullable();

            // Identity (di-encrypt lewat cast di model => kolom text)
            $t->text('ktp_number')->nullable();
            $t->date('ktp_issued_at')->nullable();
            $t->date('ktp_expired_at')->nullable();
            $t->string('ktp_place', 100)->nullable();
            $t->text('passport_number')->nullable();
            $t->date('passport_issued_at')->nullable();
            $t->date('passport_expired_at')->nullable();
            $t->string('passport_place', 100)->nullable();
            $t->text('npwp_number')->nullable();

            // Physical
            $t->decimal('weight_kg', 5, 2)->nullable();
            $t->decimal('height_cm', 5, 2)->nullable();
            $t->string('blood_type', 3)->nullable();

            // Highest formal education
            $t->string('highest_education', 20)->nullable();
            $t->string('education_institution', 150)->nullable();
            $t->string('education_major', 150)->nullable();
            $t->unsignedSmallInteger('graduated_year')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index('department');
            $t->index('employment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
