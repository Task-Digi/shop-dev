<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE sale_data sd
            INNER JOIN products p ON sd.product_id = p.product_id
            SET sd.product_name = p.product_name
            WHERE (sd.product_name IS NULL OR TRIM(sd.product_name) = '')
              AND p.product_name IS NOT NULL
              AND TRIM(p.product_name) != ''
        ");

        DB::statement("
            UPDATE sale_data
            SET product_name = product_id
            WHERE (product_name IS NULL OR TRIM(product_name) = '')
              AND product_id IS NOT NULL
              AND TRIM(product_id) != ''
        ");
    }

    public function down(): void
    {
        // Non-reversible: restored rows would not match prior null/empty state.
    }
};
