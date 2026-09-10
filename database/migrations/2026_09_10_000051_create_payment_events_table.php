<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_transaction_id')
                ->constrained('payment_transactions')
                ->restrictOnDelete();

            $table->string('event_type');
            $table->string('provider_event_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['payment_transaction_id', 'provider_event_id']);
            $table->index(['payment_transaction_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
