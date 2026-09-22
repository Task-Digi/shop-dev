<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_reporting_days') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sales_reporting_days CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_reporting_days') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sales_reporting_days CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
    }
};
