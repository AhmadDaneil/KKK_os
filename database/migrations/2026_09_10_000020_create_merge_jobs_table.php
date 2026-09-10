<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merge_jobs', function (Blueprint $table) {
            $table->id();

            $table->string('job_id')->unique();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('order_package_side_id')
                ->constrained('order_package_sides')
                ->restrictOnDelete();

            $table->enum('side', ['LELAKI', 'PEREMPUAN']);

            // Internal engine state only. This is not the final designer workflow status model.
            $table->string('status')->default('PENDING_EXPORT');

            // Internal canonical snapshot. Not the final Photoshop CSV/Sheet contract.
            $table->string('payload_schema_version')->default('kkk_merge_internal_v1');
            $table->json('canonical_payload');

            $table->timestamp('generated_at');
            $table->timestamp('exported_at')->nullable();

            $table->timestamps();

            // One merge job per package side. Re-generation updates the same job.
            $table->unique('order_package_side_id');
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merge_jobs');
    }
};
