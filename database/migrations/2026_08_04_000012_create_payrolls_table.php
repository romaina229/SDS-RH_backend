<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('month', 7);
            $table->decimal('base_salary', 15, 2);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('overtime_amount', 15, 2)->default(0);
            $table->decimal('bonuses', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('taxes', 15, 2)->default(0);
            $table->decimal('social_security', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2);
            $table->json('breakdown')->nullable();
            $table->string('pay_slip_url')->nullable();
            $table->enum('status', ['draft', 'processed', 'paid'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->unique(['employee_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};