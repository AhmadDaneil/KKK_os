<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_event_id')->constrained('order_events')->restrictOnDelete();
            $table->unsignedTinyInteger('contact_number');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();

            $table->unique(['order_event_id', 'contact_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_contacts');
    }
};
