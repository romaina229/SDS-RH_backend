<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('employee_number')->unique();
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('position_id')->nullable()->constrained()->onDelete('set null');
            $table->date('hire_date');
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('nationality')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->json('bank_details')->nullable();
            $table->json('social_security')->nullable();
            $table->enum('status', ['active', 'on_leave', 'terminated', 'suspended'])->default('active');
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};