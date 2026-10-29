<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packing_job_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packing_job_id')
                ->constrained('packing_jobs')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('storage_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->timestamps();

            $table->index(['packing_job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_job_proofs');
    }
};
