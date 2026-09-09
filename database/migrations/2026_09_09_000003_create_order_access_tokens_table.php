<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_fk')
                ->constrained('orders')
                ->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['order_fk', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_access_tokens');
    }
};
