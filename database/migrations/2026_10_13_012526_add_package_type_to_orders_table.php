<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('package_type', 24)
                ->nullable()
                ->after('package_count');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'package_type')) {
            return;
        }

        if (DB::table('orders')->whereNotNull('package_type')->exists()) {
            throw new \RuntimeException(
                'Cannot remove package_type while orders contain a package type.'
            );
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('package_type');
        });
    }
};