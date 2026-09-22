<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZeroSalesDataEntryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-22 12:00:00');
        Schema::dropIfExists('sales_reporting_days');
        Schema::dropIfExists('sale_data');
        Schema::create('sale_data', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('location');
            $table->string('customer_id')->nullable();
            $table->string('orderid')->nullable();
            $table->integer('count')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->decimal('retail', 14, 2)->nullable();
            $table->string('type')->nullable();
        });
        Schema::create('sales_reporting_days', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('location');
            $table->string('client');
            $table->decimal('sales_amount', 14, 2);
            $table->string('confirmation_source');
            $table->timestamps();
            $table->unique(['date', 'location']);
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_zero_sales_entry_needs_only_location_and_date(): void
    {
        $this->post(route('saleitems.zero-sales.store'), [
            'zero_sales_date' => '2026-09-21',
            'zero_sales_location' => 'ALNABRU',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('sales_reporting_days', [
            'date' => '2026-09-21',
            'location' => 'ALNABRU',
            'client' => 'The shop itself',
            'sales_amount' => 0,
        ]);
    }

    public function test_duplicate_zero_or_actual_sales_are_rejected(): void
    {
        $payload = ['zero_sales_date' => '2026-09-20', 'zero_sales_location' => 'MAJORSTUEN'];
        $this->post(route('saleitems.zero-sales.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('saleitems.zero-sales.store'), $payload)->assertSessionHasErrors('zero_sales_date');

        DB::table('sale_data')->insert(['date' => '2026-09-19', 'location' => 'ALNABRU']);
        $this->post(route('saleitems.zero-sales.store'), [
            'zero_sales_date' => '2026-09-19',
            'zero_sales_location' => 'ALNABRU',
        ])->assertSessionHasErrors('zero_sales_date');

        $this->assertDatabaseCount('sales_reporting_days', 1);
    }

    public function test_zero_sales_entry_appears_in_daily_sales_report(): void
    {
        $this->post(route('saleitems.zero-sales.store'), [
            'zero_sales_date' => '2026-09-18',
            'zero_sales_location' => 'ALNABRU',
        ])->assertSessionHasNoErrors();

        $response = $this->get(route('report', ['searchDate' => '2026-09-18']));
        $response->assertOk();
        $row = $response->viewData('salesData')->first();

        $this->assertSame('2026-09-18', $row->date);
        $this->assertSame('ALNABRU', $row->location);
        $this->assertEquals(0, $row->total_products_price);
    }
}
