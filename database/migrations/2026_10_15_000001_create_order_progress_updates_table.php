<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_progress_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('uploader_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploader_role', 40);
            $table->string('order_status', 60);
            $table->text('message')->nullable();
            $table->string('visibility', 20)->default('INTERNAL');
            $table->timestamp('published_at')->nullable();
            $table->string('attachment_disk', 40)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 120)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->char('attachment_sha256', 64)->nullable();
            $table->char('deduplication_key', 64)->unique();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['order_id', 'visibility', 'published_at'], 'order_progress_customer_index');
            $table->index(['uploader_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_progress_updates');
    }
};
