<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artwork_versions', function (Blueprint $table) {
            $table->json('source_files')->nullable()->after('checksum_sha256');
            $table->json('preview_files')->nullable()->after('preview_storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('artwork_versions', function (Blueprint $table) {
            $table->dropColumn(['source_files', 'preview_files']);
        });
    }
};
