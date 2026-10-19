<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wabot_route_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('next_index')->default(0);
            $table->timestamps();
        });

        DB::table('wabot_route_counters')->insert([
            'id' => 1,
            'next_index' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wabot_route_counters');
    }
};
