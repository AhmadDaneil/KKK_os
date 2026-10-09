<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('item_type', 24);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('calculation_method', 24)->default('UNIT');
            $table->decimal('unit_price', 12, 2);
            $table->string('unit_label', 32)->default('unit');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['item_type', 'is_active']);
        });

        Schema::create('order_pricing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 160);
            $table->string('currency', 3)->default('MYR');
            $table->string('deposit_method', 24);
            $table->decimal('deposit_value', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pricing_profiles');
        Schema::dropIfExists('price_catalog_items');
    }
};
