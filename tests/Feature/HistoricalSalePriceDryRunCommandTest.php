<?php

namespace Tests\Feature;

use App\Services\VerifiedSupplierDiscountReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class HistoricalSalePriceDryRunCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('sale_data');
        Schema::dropIfExists('order_items');
        Schema::create('sale_data', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('orderid');
            $table->string('product_id');
            $table->integer('count');
            $table->decimal('price', 10, 2)->nullable();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id');
            $table->string('order_id2')->nullable();
            $table->date('order_date');
            $table->string('sku');
            $table->decimal('price', 10, 2);
        });
    }

    public function test_command_is_read_only(): void
    {
        DB::table('sale_data')->insert([
            'id' => 1, 'date' => '2026-08-27', 'orderid' => 'S1',
            'product_id' => '199909', 'count' => 1, 'price' => 2085.00,
        ]);
        DB::table('order_items')->insert([
            'id' => 1, 'order_id' => '750553', 'order_id2' => '750553',
            'order_date' => '2026-08-26', 'sku' => '199909', 'price' => 2085.00,
        ]);

        $reader = Mockery::mock(VerifiedSupplierDiscountReader::class);
        $reader->shouldReceive('read')->once()->with(['verified.xls'])->andReturn([
            'files' => ['ordre_750553.xls'],
            'orders' => [
                '750553' => [
                    'date' => '2026-08-26',
                    'source' => 'ordre_750553.xls',
                    'hash' => str_repeat('a', 64),
                    'rows' => [
                        '199909' => [
                            'gross' => 2085.0,
                            'discount1' => 15.0,
                            'discount2' => 0.0,
                            'net' => 1772.25,
                        ],
                    ],
                ],
            ],
        ]);
        $this->app->instance(VerifiedSupplierDiscountReader::class, $reader);

        $this->artisan('task-b:dry-run', ['supplier-files' => ['verified.xls']])
            ->expectsOutputToContain('DRY RUN ONLY')
            ->assertSuccessful();

        $this->assertDatabaseCount('sale_data', 1);
        $this->assertDatabaseHas('sale_data', ['id' => 1, 'price' => 2085.00]);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('order_items', ['id' => 1, 'price' => 2085.00]);
    }
}
