<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('artwork_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_job_id')->constrained('design_jobs')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk')->default('local');
            $table->string('storage_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('preview_storage_path')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['design_job_id', 'version_number']);
            $table->index(['design_job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artwork_versions');
    }
};
