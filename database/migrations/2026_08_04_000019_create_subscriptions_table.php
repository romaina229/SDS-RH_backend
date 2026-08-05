<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('plan');
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('XOF');
            $table->string('billing_cycle');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable(); // Changé en nullable
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->json('features')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};