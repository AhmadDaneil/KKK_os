<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('artwork_review_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('design_job_id')
                ->constrained('design_jobs')
                ->restrictOnDelete();

            $table->foreignId('artwork_version_id')
                ->constrained('artwork_versions')
                ->restrictOnDelete();

            $table->enum('action', [
                'DESIGN_READY',
                'CORRECTION_REQUESTED',
                'DESIGN_APPROVED',
            ]);

            $table->text('customer_comment')->nullable();

            // Customer actions use the secure order token flow in V1.
            // Internal staff actor support remains optional.
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['design_job_id', 'acted_at']);
            $table->index(['artwork_version_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artwork_review_actions');
    }
};
