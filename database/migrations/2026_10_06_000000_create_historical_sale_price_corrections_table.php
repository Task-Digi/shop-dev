<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_sale_price_corrections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_uuid')->index();
            $table->unsignedBigInteger('sale_data_id');
            $table->string('sku');
            $table->string('source_order_id');
            $table->date('source_order_date');
            $table->string('source_file');
            $table->char('source_sha256', 64);
            $table->decimal('gross_price', 10, 2);
            $table->decimal('discount_1', 5, 1);
            $table->decimal('discount_2', 5, 1);
            $table->integer('quantity');
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('new_price', 10, 2);
            $table->decimal('old_cost', 14, 2);
            $table->decimal('new_cost', 14, 2);
            $table->decimal('difference', 14, 2);
            $table->json('calculation_evidence');
            $table->string('status')->default('APPLIED');
            $table->timestamp('applied_at');
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->unique(['run_uuid', 'sale_data_id']);
            $table->index(['sale_data_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_sale_price_corrections');
    }
};
