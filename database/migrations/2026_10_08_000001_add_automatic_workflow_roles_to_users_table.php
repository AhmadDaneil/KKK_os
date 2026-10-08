<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_design_lead')->default(false)->after('is_active');
            $table->boolean('is_primary_production')->default(false)->after('is_design_lead');
            $table->index(['role', 'is_active', 'is_design_lead']);
            $table->index(['role', 'is_active', 'is_primary_production']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active', 'is_design_lead']);
            $table->dropIndex(['role', 'is_active', 'is_primary_production']);
            $table->dropColumn(['is_design_lead', 'is_primary_production']);
        });
    }
};
