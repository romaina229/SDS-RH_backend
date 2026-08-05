<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['cdi', 'cdd', 'stage', 'consultant', 'freelance']);
            $table->enum('status', ['active', 'expired', 'terminated', 'pending'])->default('pending');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->decimal('base_salary', 15, 2);
            $table->string('currency', 3)->default('XOF');
            $table->json('benefits')->nullable();
            $table->text('terms')->nullable();
            $table->string('contract_file')->nullable();
            $table->text('termination_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};