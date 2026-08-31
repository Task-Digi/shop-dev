<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_lists', function (Blueprint $table): void {
            $table->index(['orderid', 'productid'], 'sales_lists_order_product_index');
            $table->index(['date', 'location'], 'sales_lists_date_location_index');
            $table->index('customerid', 'sales_lists_customer_index');
        });

        Schema::table('sale_data', function (Blueprint $table): void {
            $table->index(['date', 'customer_id'], 'sale_data_date_customer_index');
            $table->index(['date', 'product_id'], 'sale_data_date_product_index');
            $table->index('orderid', 'sale_data_order_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index('product_id', 'products_product_id_index');
            $table->index('ean_code', 'products_ean_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_product_id_index');
            $table->dropIndex('products_ean_code_index');
        });

        Schema::table('sale_data', function (Blueprint $table): void {
            $table->dropIndex('sale_data_date_customer_index');
            $table->dropIndex('sale_data_date_product_index');
            $table->dropIndex('sale_data_order_index');
        });

        Schema::table('sales_lists', function (Blueprint $table): void {
            $table->dropIndex('sales_lists_order_product_index');
            $table->dropIndex('sales_lists_date_location_index');
            $table->dropIndex('sales_lists_customer_index');
        });
    }
};
