<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production databases may already contain some of these indexes, and
        // MySQL DDL is not transactional. Check each index independently so a
        // partially completed migration can be rerun safely.
        $this->addIndexIfMissing('sales_lists', ['orderid', 'productid'], 'sales_lists_order_product_index');
        $this->addIndexIfMissing('sales_lists', ['date', 'location'], 'sales_lists_date_location_index');
        $this->addIndexIfMissing('sales_lists', ['customerid'], 'sales_lists_customer_index');

        $this->addIndexIfMissing('sale_data', ['date', 'customer_id'], 'sale_data_date_customer_index');
        $this->addIndexIfMissing('sale_data', ['date', 'product_id'], 'sale_data_date_product_index');
        $this->addIndexIfMissing('sale_data', ['orderid'], 'sale_data_order_index');

        $this->addIndexIfMissing('products', ['product_id'], 'products_product_id_index');
        $this->addIndexIfMissing('products', ['ean_code'], 'products_ean_code_index');
    }

    public function down(): void
    {
        $this->dropIndexIfPresent('products', 'products_product_id_index');
        $this->dropIndexIfPresent('products', 'products_ean_code_index');

        $this->dropIndexIfPresent('sale_data', 'sale_data_date_customer_index');
        $this->dropIndexIfPresent('sale_data', 'sale_data_date_product_index');
        $this->dropIndexIfPresent('sale_data', 'sale_data_order_index');

        $this->dropIndexIfPresent('sales_lists', 'sales_lists_order_product_index');
        $this->dropIndexIfPresent('sales_lists', 'sales_lists_date_location_index');
        $this->dropIndexIfPresent('sales_lists', 'sales_lists_customer_index');
    }

    /** @param list<string> $columns */
    private function addIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
                $table->index($columns, $indexName);
            });
        }
    }

    private function dropIndexIfPresent(string $tableName, string $indexName): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return collect(Schema::getIndexes($tableName))
            ->contains(fn (array $index): bool => $index['name'] === $indexName);
    }
};
