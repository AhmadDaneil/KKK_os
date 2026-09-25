<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table): void {
            $table->json('progress_files')->nullable()->after('printed_at');
            $table->timestamp('progress_updated_at')->nullable()->after('progress_files');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table): void {
            $table->dropColumn(['progress_files', 'progress_updated_at']);
        });
    }
};
