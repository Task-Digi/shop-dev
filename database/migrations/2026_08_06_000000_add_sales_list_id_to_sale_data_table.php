<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_data', function (Blueprint $table) {
            $table->unsignedInteger('sales_list_id')
                ->nullable()
                ->after('count')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('sale_data', function (Blueprint $table) {
            $table->dropIndex(['sales_list_id']);
            $table->dropColumn('sales_list_id');
        });
    }
};
