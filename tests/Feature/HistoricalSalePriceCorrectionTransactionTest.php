<?php

namespace Tests\Feature;

use App\Services\VerifiedSupplierDiscountReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class HistoricalSalePriceCorrectionTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('historical_sale_price_corrections');
        Schema::dropIfExists('sale_data');
        Schema::dropIfExists('order_items');
        Schema::create('sale_data', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('orderid');
            $table->string('product_id');
            $table->integer('count');
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id');
            $table->string('order_id2')->nullable();
            $table->date('order_date');
            $table->string('sku');
            $table->decimal('price', 10, 2);
        });
        Schema::create('historical_sale_price_corrections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_uuid');
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
            $table->string('status');
            $table->timestamp('applied_at');
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });

        DB::table('sale_data')->insert([
            'id' => 1, 'date' => '2026-08-27', 'orderid' => 'S1', 'product_id' => '199909',
            'count' => 2, 'price' => 2085.00, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'id' => 1, 'order_id' => '750553', 'order_id2' => '750553',
            'order_date' => '2026-08-26', 'sku' => '199909', 'price' => 2085.00,
        ]);

        $reader = Mockery::mock(VerifiedSupplierDiscountReader::class);
        $reader->shouldReceive('read')->andReturn($this->evidence());
        $this->app->instance(VerifiedSupplierDiscountReader::class, $reader);
    }

    public function test_preview_does_not_modify_any_database_row(): void
    {
        $this->artisan('task-b:historical-prices', ['supplier-files' => ['verified.xls']])
            ->expectsOutputToContain('PREVIEW ONLY')
            ->assertSuccessful();

        $this->assertDatabaseHas('sale_data', ['id' => 1, 'price' => 2085.00]);
        $this->assertDatabaseCount('historical_sale_price_corrections', 0);
    }

    public function test_approved_execution_is_audited_and_can_be_transactionally_rolled_back(): void
    {
        $this->artisan('task-b:historical-prices', [
            'supplier-files' => ['verified.xls'], '--execute' => true, '--approval' => 'APPLY_TASK_B',
            '--expected-database' => DB::connection()->getDatabaseName(),
            '--expected-count' => 1,
            '--expected-order' => '750553',
            '--expected-old-cost' => '4170.00',
            '--expected-new-cost' => '3544.50',
            '--expected-difference' => '-625.50',
        ])->assertSuccessful();

        $this->assertDatabaseHas('sale_data', ['id' => 1, 'price' => 1772.25]);
        $audit = DB::table('historical_sale_price_corrections')->first();
        $this->assertNotNull($audit);
        $this->assertSame('APPLIED', $audit->status);
        $this->assertEqualsWithDelta(2085.00, (float) $audit->old_price, 0.001);
        $this->assertEqualsWithDelta(1772.25, (float) $audit->new_price, 0.001);
        $this->assertSame(str_repeat('a', 64), $audit->source_sha256);

        $this->artisan('task-b:rollback-historical-prices', [
            'run' => $audit->run_uuid, '--approval' => 'ROLLBACK_TASK_B',
        ])->assertSuccessful();

        $this->assertDatabaseHas('sale_data', ['id' => 1, 'price' => 2085.00]);
        $this->assertDatabaseHas('historical_sale_price_corrections', [
            'id' => $audit->id, 'status' => 'ROLLED_BACK',
        ]);
    }

    private function evidence(): array
    {
        return [
            'files' => ['ordre_750553.xls'],
            'orders' => ['750553' => [
                'date' => '2026-08-26', 'source' => 'ordre_750553.xls', 'hash' => str_repeat('a', 64),
                'rows' => ['199909' => [
                    'gross' => 2085.0, 'discount1' => 15.0, 'discount2' => 0.0, 'net' => 1772.25,
                ]],
            ]],
        ];
    }
}
