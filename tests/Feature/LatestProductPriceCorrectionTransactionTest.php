<?php

namespace Tests\Feature;

use App\Services\VerifiedSupplierDiscountReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class LatestProductPriceCorrectionTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('historical_product_price_corrections');
        Schema::dropIfExists('products');
        Schema::dropIfExists('order_items');
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id');
            $table->string('product_name');
            $table->decimal('price', 10, 2);
            $table->decimal('retail', 10, 2);
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id');
            $table->string('order_id2')->nullable();
            $table->date('order_date');
            $table->string('sku');
            $table->decimal('price', 10, 2);
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('historical_product_price_corrections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_uuid');
            $table->unsignedBigInteger('product_row_id');
            $table->string('product_id');
            $table->string('source_order_id');
            $table->date('source_order_date');
            $table->string('source_file');
            $table->char('source_sha256', 64);
            $table->decimal('gross_price', 10, 2);
            $table->decimal('discount_1', 5, 1);
            $table->decimal('discount_2', 5, 1);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('new_price', 10, 2);
            $table->json('calculation_evidence');
            $table->string('status');
            $table->timestamp('applied_at');
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });

        DB::table('products')->insert([
            ['id' => 1, 'product_id' => '227426', 'product_name' => 'Quick', 'price' => 207.36, 'retail' => 300],
            ['id' => 2, 'product_id' => '503510', 'product_name' => 'Baronesse', 'price' => 188.83, 'retail' => 250],
            ['id' => 3, 'product_id' => 'OTHER', 'product_name' => 'Other', 'price' => 99.00, 'retail' => 120],
        ]);
        DB::table('order_items')->insert([
            ['id' => 1, 'order_id' => '750553', 'order_id2' => '750553', 'order_date' => '2026-08-26', 'sku' => '227426', 'price' => 207.36, 'created_at' => '2026-08-27 10:00:00'],
            ['id' => 2, 'order_id' => '750553', 'order_id2' => '750553', 'order_date' => '2026-08-26', 'sku' => '503510', 'price' => 188.83, 'created_at' => '2026-08-27 10:00:00'],
        ]);

        $reader = Mockery::mock(VerifiedSupplierDiscountReader::class);
        $reader->shouldReceive('read')->andReturn($this->evidence());
        $this->app->instance(VerifiedSupplierDiscountReader::class, $reader);
    }

    public function test_preview_changes_nothing(): void
    {
        $this->artisan('task-c:historical-product-prices', ['supplier-files' => ['verified.xls']])
            ->expectsOutputToContain('PREVIEW ONLY')
            ->assertSuccessful();

        $this->assertDatabaseHas('products', ['product_id' => '227426', 'price' => 207.36, 'retail' => 300]);
        $this->assertDatabaseHas('products', ['product_id' => '503510', 'price' => 188.83, 'retail' => 250]);
        $this->assertDatabaseCount('historical_product_price_corrections', 0);
    }

    public function test_approved_run_updates_only_two_prices_audits_them_and_rolls_back(): void
    {
        $this->artisan('task-c:historical-product-prices', [
            'supplier-files' => ['verified.xls'],
            '--execute' => true,
            '--approval' => 'APPLY_TASK_C',
            '--expected-database' => DB::connection()->getDatabaseName(),
            '--expected-count' => 2,
            '--expected-order' => '750553',
            '--expected-products' => '227426,503510',
            '--expected-source-sha256' => str_repeat('a', 64),
        ])->assertSuccessful();

        $this->assertDatabaseHas('products', ['product_id' => '227426', 'price' => 176.26, 'retail' => 300]);
        $this->assertDatabaseHas('products', ['product_id' => '503510', 'price' => 47.21, 'retail' => 250]);
        $this->assertDatabaseHas('products', ['product_id' => 'OTHER', 'price' => 99.00, 'retail' => 120]);
        $this->assertDatabaseCount('historical_product_price_corrections', 2);
        $run = DB::table('historical_product_price_corrections')->value('run_uuid');
        $this->assertDatabaseMissing('historical_product_price_corrections', ['calculation_evidence' => null]);

        $this->artisan('task-c:rollback-product-prices', [
            'run' => $run, '--approval' => 'ROLLBACK_TASK_C',
        ])->assertSuccessful();

        $this->assertDatabaseHas('products', ['product_id' => '227426', 'price' => 207.36, 'retail' => 300]);
        $this->assertDatabaseHas('products', ['product_id' => '503510', 'price' => 188.83, 'retail' => 250]);
        $this->assertSame(2, DB::table('historical_product_price_corrections')->where('status', 'ROLLED_BACK')->count());
    }

    private function evidence(): array
    {
        return [
            'files' => ['ordre_750553.xls'],
            'orders' => ['750553' => [
                'date' => '2026-08-26', 'source' => 'ordre_750553.xls', 'hash' => str_repeat('a', 64),
                'rows' => [
                    '227426' => ['gross' => 207.36, 'discount1' => 15.0, 'discount2' => 0.0, 'net' => 176.26],
                    '503510' => ['gross' => 188.83, 'discount1' => 75.0, 'discount2' => 0.0, 'net' => 47.21],
                ],
            ]],
        ];
    }
}
