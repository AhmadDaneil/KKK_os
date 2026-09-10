<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('packing_job_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('packing_job_id')
                ->constrained('packing_jobs')
                ->restrictOnDelete();

            $table->foreignId('print_job_id')
                ->constrained('print_jobs')
                ->restrictOnDelete();

            $table->foreignId('order_package_side_id')
                ->constrained('order_package_sides')
                ->restrictOnDelete();

            $table->enum('side', ['LELAKI', 'PEREMPUAN']);

            $table->boolean('verified_present')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique('print_job_id');
            $table->unique(['packing_job_id', 'order_package_side_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packing_job_items');
    }
};
