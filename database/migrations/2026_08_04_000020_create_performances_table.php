<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('employees')->onDelete('cascade');
            $table->string('period'); 
            $table->json('ratings')->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('achievements')->nullable();
            $table->text('goals_achieved')->nullable();
            $table->text('recommendations')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->enum('status', ['draft', 'submitted', 'reviewed', 'approved'])->default('draft');
            $table->timestamp('review_date')->nullable();
            $table->timestamps();
            
            $table->unique(['employee_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};