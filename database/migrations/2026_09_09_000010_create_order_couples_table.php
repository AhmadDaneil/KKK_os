<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_couples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('couple_number')->default(1);

            $table->string('groom_name')->nullable();
            $table->string('groom_abbreviation')->nullable();
            $table->string('bride_name')->nullable();
            $table->string('bride_abbreviation')->nullable();

            $table->timestamps();

            $table->unique(['order_id', 'couple_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_couples');
    }
};
