<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('packing_jobs', function (Blueprint $table) {
            $table->string('proof_storage_path')->nullable()->after('packed_at');
            $table->string('proof_original_name')->nullable()->after('proof_storage_path');
            $table->string('proof_mime_type', 100)->nullable()->after('proof_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('packing_jobs', function (Blueprint $table) {
            $table->dropColumn(['proof_storage_path', 'proof_original_name', 'proof_mime_type']);
        });
    }
};
