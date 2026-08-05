<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->enum('type', [
                'contract', 'diploma', 'id_card', 'pay_slip', 
                'certificate', 'cv', 'photo', 'medical', 'other'
            ]);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->string('uploaded_by')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};