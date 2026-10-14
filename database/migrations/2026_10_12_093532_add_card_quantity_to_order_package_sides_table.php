<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_package_sides', function (Blueprint $table): void {
            $table->unsignedInteger('card_quantity')
                ->nullable()
                ->after('side');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_package_sides', 'card_quantity')) {
            return;
        }

        if (DB::table('order_package_sides')->whereNotNull('card_quantity')->exists()) {
            throw new RuntimeException(
                'Cannot remove side card quantities while values exist.'
            );
        }

        Schema::table('order_package_sides', function (Blueprint $table): void {
            $table->dropColumn('card_quantity');
        });
    }
};