<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fulfilment_jobs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('order_fulfilment_id')
                ->constrained('order_fulfilments')
                ->restrictOnDelete();

            $table->foreignId('packing_job_id')
                ->constrained('packing_jobs')
                ->restrictOnDelete();

            $table->enum('method', ['COURIER', 'PICKUP']);

            // Internal operational state.
            // Business order remains PACKED until fulfilment is actually complete.
            $table->enum('status', [
                'READY',
                'SHIPPED',
                'DELIVERED',
                'COLLECTED',
            ])->default('READY');

            // Courier integration is intentionally provider-agnostic in V1 foundation.
            $table->string('courier_provider')->nullable();
            $table->string('tracking_number')->nullable();

            // Pickup/courier operational notes only; no new customer policy implied.
            $table->string('completion_reference')->nullable();
            $table->text('internal_note')->nullable();

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('collected_at')->nullable();

            $table->timestamps();

            // Exactly one fulfilment job per business order.
            $table->unique('order_id');
            $table->unique('order_fulfilment_id');
            $table->unique('packing_job_id');

            $table->index(['method', 'status']);
            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfilment_jobs');
    }
};
