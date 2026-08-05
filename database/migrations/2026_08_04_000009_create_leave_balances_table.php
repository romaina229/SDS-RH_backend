<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('year');

            $table->decimal('annual_entitled', 6, 2)->default(0);
            $table->decimal('annual_taken', 6, 2)->default(0);
            $table->decimal('annual_remaining', 6, 2)->default(0);

            $table->decimal('sick_entitled', 6, 2)->default(0);
            $table->decimal('sick_taken', 6, 2)->default(0);
            $table->decimal('sick_remaining', 6, 2)->default(0);

            $table->timestamps();

            $table->unique(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
