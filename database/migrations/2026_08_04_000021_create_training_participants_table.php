<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('training_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['enrolled', 'in_progress', 'completed', 'cancelled'])->default('enrolled');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->string('certificate_path')->nullable();
            $table->date('completion_date')->nullable();
            $table->timestamps();
            
            $table->unique(['training_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_participants');
    }
};