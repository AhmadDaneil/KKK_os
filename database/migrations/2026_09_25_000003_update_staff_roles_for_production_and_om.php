<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'PRINTING')
            ->update(['role' => 'PRODUCTION']);

        DB::table('users')
            ->where('role', 'PACKING')
            ->update(['role' => 'OPERATION_MANAGEMENT']);
    }

    public function down(): void
    {
        // This change intentionally has no reverse data migration: both the
        // former packing role and OM now represent OPERATION_MANAGEMENT.
    }
};
