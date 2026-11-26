<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_email_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_progress_update_id')
                ->nullable()
                ->constrained('order_progress_updates')
                ->nullOnDelete();
            $table->string('event_type', 32);
            $table->char('event_key', 64)->unique();
            $table->string('status_to', 60)->nullable();
            $table->string('status', 16)->default('PENDING');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('error_code', 48)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_email_notifications');
    }
};
