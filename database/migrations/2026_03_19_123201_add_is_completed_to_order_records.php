<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsCompletedToOrderRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('order_records', 'completed')) {
            Schema::table('order_records', function (Blueprint $table) {
                $table->boolean('completed')->default(0)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_records', function (Blueprint $table) {
            $table->dropColumn('completed');
        });
    }
}
