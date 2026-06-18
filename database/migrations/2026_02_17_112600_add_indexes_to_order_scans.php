<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToOrderScans extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('order_scans', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('ean_code');
            $table->index('scan_date_time');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_scans', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['ean_code']);
            $table->dropIndex(['scan_date_time']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['sku']);
        });
    }
}
