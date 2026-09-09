<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_package_side_id')->constrained('order_package_sides')->restrictOnDelete();

            $table->string('day_name')->nullable();
            $table->date('event_date')->nullable();
            $table->string('hijri_date')->nullable();
            $table->time('meal_time')->nullable();
            $table->time('bersanding_time')->nullable();
            $table->string('venue_name')->nullable();
            $table->text('full_address')->nullable();
            $table->text('google_maps_url')->nullable();

            $table->timestamps();

            $table->unique('order_package_side_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
