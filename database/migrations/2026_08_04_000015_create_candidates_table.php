<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('recruitment_id')->constrained()->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('cv_path')->nullable();
            $table->text('cover_letter')->nullable();
            $table->json('education')->nullable();
            $table->json('experience')->nullable();
            $table->json('skills')->nullable();
            $table->enum('status', ['new', 'screened', 'interviewed', 'offered', 'hired', 'rejected'])->default('new');
            $table->text('feedback')->nullable();
            $table->decimal('expected_salary', 15, 2)->nullable();
            $table->date('available_from')->nullable();
            $table->timestamps();
            
            $table->unique(['recruitment_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};