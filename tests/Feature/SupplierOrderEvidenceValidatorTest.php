<?php

namespace Tests\Feature;

use App\Services\SupplierOrderEvidenceValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class SupplierOrderEvidenceValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('order_items');
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id');
            $table->string('order_id2')->nullable();
            $table->date('order_date');
            $table->string('sku');
            $table->decimal('price', 10, 2);
        });
    }

    public function test_it_validates_order_id_sku_date_and_gross_price(): void
    {
        DB::table('order_items')->insert([
            'order_id' => '750553', 'order_id2' => '750553', 'order_date' => '2026-08-26',
            'sku' => '264980', 'price' => 653.40,
        ]);

        $result = (new SupplierOrderEvidenceValidator)->validate($this->evidence(653.40));

        $this->assertSame(['orders' => 1, 'rows' => 1], $result);
    }

    public function test_it_rejects_a_file_gross_price_that_disagrees_with_order_items(): void
    {
        DB::table('order_items')->insert([
            'order_id' => '750553', 'order_id2' => '750553', 'order_date' => '2026-08-26',
            'sku' => '264980', 'price' => 605.00,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('gross price does not agree');
        (new SupplierOrderEvidenceValidator)->validate($this->evidence(653.40));
    }

    private function evidence(float $gross): array
    {
        return ['orders' => ['750553' => [
            'date' => '2026-08-26', 'source' => 'ordre_750553.xls', 'hash' => str_repeat('a', 64),
            'rows' => ['264980' => [
                'gross' => $gross, 'discount1' => 15.0, 'discount2' => 8.0, 'net' => 510.96,
            ]],
        ]]];
    }
}
