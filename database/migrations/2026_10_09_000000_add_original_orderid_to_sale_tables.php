<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sale_data') && !Schema::hasColumn('sale_data', 'original_orderid')) {
            Schema::table('sale_data', function (Blueprint $table) {
                $table->string('original_orderid', 255)->nullable()->after('orderid')->index();
            });
        }

        if (Schema::hasTable('sales_lists') && !Schema::hasColumn('sales_lists', 'original_orderid')) {
            Schema::table('sales_lists', function (Blueprint $table) {
                $table->string('original_orderid', 255)->nullable()->after('orderid')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sale_data') && Schema::hasColumn('sale_data', 'original_orderid')) {
            Schema::table('sale_data', function (Blueprint $table) {
                $table->dropIndex(['original_orderid']);
                $table->dropColumn('original_orderid');
            });
        }

        if (Schema::hasTable('sales_lists') && Schema::hasColumn('sales_lists', 'original_orderid')) {
            Schema::table('sales_lists', function (Blueprint $table) {
                $table->dropIndex(['original_orderid']);
                $table->dropColumn('original_orderid');
            });
        }
    }
};
