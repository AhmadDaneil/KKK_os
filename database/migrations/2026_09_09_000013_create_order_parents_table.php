<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_package_side_id')->constrained('order_package_sides')->restrictOnDelete();

            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();

            $table->timestamps();

            $table->unique('order_package_side_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_parents');
    }
};
