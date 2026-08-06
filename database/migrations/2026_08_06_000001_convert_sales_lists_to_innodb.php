<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sale creation writes to sales_lists and sale_data in one transaction.
        // MyISAM ignores rollbacks and can leave an order that has no report row.
        DB::statement('ALTER TABLE sales_lists ENGINE = InnoDB');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sales_lists ENGINE = MyISAM');
    }
};
