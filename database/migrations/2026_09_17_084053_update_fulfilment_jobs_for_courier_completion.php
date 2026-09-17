<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE fulfilment_jobs
                MODIFY status ENUM(
                    'READY',
                    'SHIPPED',
                    'DELIVERED',
                    'COLLECTED',
                    'COMPLETED'
                ) NOT NULL DEFAULT 'READY'
            ");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('fulfilment_jobs', function (Blueprint $table) {
                $table->string('status')
                    ->default('READY')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('fulfilment_jobs')
                ->where('status', 'COMPLETED')
                ->update(['status' => 'SHIPPED']);

            DB::statement("
                ALTER TABLE fulfilment_jobs
                MODIFY status ENUM(
                    'READY',
                    'SHIPPED',
                    'DELIVERED',
                    'COLLECTED'
                ) NOT NULL DEFAULT 'READY'
            ");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::table('fulfilment_jobs')
                ->where('status', 'COMPLETED')
                ->update(['status' => 'SHIPPED']);

            Schema::table('fulfilment_jobs', function (Blueprint $table) {
                $table->enum('status', [
                    'READY',
                    'SHIPPED',
                    'DELIVERED',
                    'COLLECTED',
                ])->default('READY')->change();
            });
        }
    }
};