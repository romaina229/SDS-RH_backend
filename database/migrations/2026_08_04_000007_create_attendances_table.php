<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->decimal('total_hours', 8, 2)->nullable();
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'holiday', 'leave'])->default('present');
            $table->enum('method', ['qr_code', 'face_recognition', 'manual', 'badge', 'mobile'])->default('manual');
            $table->string('qr_code')->nullable();
            $table->text('notes')->nullable();
            $table->json('location_data')->nullable();
            $table->timestamps();
            
            $table->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};