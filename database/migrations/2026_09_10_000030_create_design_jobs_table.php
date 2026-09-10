<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('design_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merge_job_id')->constrained('merge_jobs')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_package_side_id')->constrained('order_package_sides')->restrictOnDelete();
            $table->enum('side', ['LELAKI', 'PEREMPUAN']);
            $table->string('status')->default('READY_FOR_DESIGN');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('design_ready_at')->nullable();
            $table->timestamps();

            $table->unique('merge_job_id');
            $table->unique('order_package_side_id');
            $table->index(['order_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_jobs');
    }
};
