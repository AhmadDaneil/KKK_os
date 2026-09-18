<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('package_format', 32)->nullable()->after('package_count');
            $table->string('first_event_side', 16)->nullable()->after('package_format');
        });

        DB::table('orders')->where('package_count', 2)->update([
            'package_format' => 'SEPARATE',
            'first_event_side' => 'LELAKI',
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['package_format', 'first_event_side']);
        });
    }
};
