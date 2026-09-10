<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('packing_job_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('packing_job_id')
                ->constrained('packing_jobs')
                ->restrictOnDelete();

            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['packing_job_id', 'occurred_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_job_events');
    }
};
