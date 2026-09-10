<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->enum('payment_type', ['BOOKING_DEPOSIT', 'BALANCE']);
            $table->string('provider')->default('TEST');
            $table->string('provider_reference')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('MYR');

            $table->enum('status', [
                'PENDING',
                'PAID',
                'FAILED',
                'CANCELLED',
            ])->default('PENDING');

            $table->json('metadata')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['order_id', 'payment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
