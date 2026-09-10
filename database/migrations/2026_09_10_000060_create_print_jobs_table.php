<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('design_job_id')
                ->constrained('design_jobs')
                ->restrictOnDelete();

            $table->foreignId('order_package_side_id')
                ->constrained('order_package_sides')
                ->restrictOnDelete();

            $table->foreignId('artwork_version_id')
                ->constrained('artwork_versions')
                ->restrictOnDelete();

            $table->enum('side', ['LELAKI', 'PEREMPUAN']);

            $table->string('status')->default('READY_FOR_PRINT');

            $table->unsignedInteger('quantity')->nullable();

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('printed_at')->nullable();

            $table->timestamps();

            $table->unique('design_job_id');
            $table->unique('order_package_side_id');
            $table->index(['order_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
