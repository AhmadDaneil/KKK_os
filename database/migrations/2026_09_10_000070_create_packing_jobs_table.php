<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('packing_jobs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->string('status')->default('READY_FOR_PACKING');

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('packed_at')->nullable();

            $table->timestamps();

            // One packing job per business order.
            $table->unique('order_id');
            $table->index(['status', 'assigned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_jobs');
    }
};
