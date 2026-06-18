<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'sale_data'] as $table) {
            DB::statement("
                UPDATE {$table}
                SET product_name = TRIM(LEADING '*' FROM TRIM(product_name))
                WHERE product_name LIKE '*%'
            ");
        }

        if (DB::getSchemaBuilder()->hasTable('order_items')) {
            DB::statement("
                UPDATE order_items
                SET item_name = TRIM(LEADING '*' FROM TRIM(item_name))
                WHERE item_name LIKE '*%'
            ");
        }
    }

    public function down(): void
    {
        // Cannot restore stripped asterisks.
    }
};
