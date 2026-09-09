<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 32)->unique();
            $table->unsignedTinyInteger('package_count');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->string('booking_payment_status', 32)->default('TEST');
            $table->string('status', 32)->default('DETAILS_INCOMPLETE');
            $table->timestamp('details_confirmed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('booking_payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
