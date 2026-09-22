<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_reporting_days', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('location')->collation('utf8mb4_general_ci');
            $table->string('client')->collation('utf8mb4_general_ci');
            $table->decimal('sales_amount', 14, 2)->default(0);
            $table->string('confirmation_source')->collation('utf8mb4_general_ci');
            $table->timestamps();

            $table->unique(['date', 'location'], 'sales_reporting_days_date_location_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_reporting_days');
    }
};
