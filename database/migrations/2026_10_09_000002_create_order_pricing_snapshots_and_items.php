<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_pricing_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('pricing_profile_id')->nullable()->constrained('order_pricing_profiles')->nullOnDelete();
            $table->string('currency', 3)->default('MYR');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('postage_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('booking_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 12, 2);
            $table->json('calculation_snapshot');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_catalog_item_id')->nullable()->constrained('price_catalog_items')->nullOnDelete();
            $table->string('item_type', 24);
            $table->string('item_code', 64);
            $table->string('description', 160);
            $table->string('side', 16)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_pricing_snapshots');
    }
};
