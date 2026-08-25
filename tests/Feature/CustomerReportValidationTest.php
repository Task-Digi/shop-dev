<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerReportValidationTest extends TestCase
{
    public function test_customer_order_drill_down_rejects_missing_filters(): void
    {
        $this->getJson('/CustomerReport/details')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid customer report filters.');
    }

    public function test_customer_order_drill_down_returns_latest_sales_first(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $query, array $params): bool {
                return str_contains($query, 'sales_date DESC')
                    && str_contains($query, 'orderid DESC')
                    && $params[0] === 'MAJORSTUEN'
                    && $params[1] === '10363';
            })
            ->andReturn([
                (object) ['orderid' => '740043', 'sales_date' => '2026-08-21', 'total_products_sold' => 3],
                (object) ['orderid' => '739344', 'sales_date' => '2026-08-21', 'total_products_sold' => 7],
                (object) ['orderid' => '729862', 'sales_date' => '2026-08-18', 'total_products_sold' => 2],
            ]);

        $this->getJson('/CustomerReport/details?location=MAJORSTUEN&customer_id=10363&days=0')
            ->assertOk()
            ->assertJsonPath('0.sales_date', '2026-08-21')
            ->assertJsonPath('0.daily_total_orders', 10)
            ->assertJsonPath('1.daily_total_orders', 10)
            ->assertJsonPath('2.sales_date', '2026-08-18')
            ->assertJsonPath('2.daily_total_orders', 2);
    }

    public function test_customer_order_drill_down_uses_the_same_sales_dates_as_the_summary(): void
    {
        DB::shouldReceive('table->select->distinct->orderBy->limit->pluck')
            ->once()
            ->with('date')
            ->andReturn(collect(['2026-08-21', '2026-08-19', '2026-08-18']));

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $query, array $params): bool {
                return str_contains($query, 'date IN (?, ?, ?)')
                    && $params === [
                        'MAJORSTUEN',
                        '10363',
                        '2026-08-21',
                        '2026-08-19',
                        '2026-08-18',
                    ];
            })
            ->andReturn([]);

        $this->getJson('/CustomerReport/details?location=MAJORSTUEN&customer_id=10363&days=7')
            ->assertOk();
    }

    public function test_customer_order_items_reject_missing_identifiers(): void
    {
        $this->getJson('/customer/finaldetails')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid order details request.');
    }

    public function test_customer_page_rejects_unsupported_periods_before_querying(): void
    {
        $this->get('/report/all?days=999')
            ->assertSessionHasErrors('days');
    }

    public function test_customer_export_rejects_unsupported_periods_before_querying(): void
    {
        $this->get('/report/customers/export?days=999')
            ->assertSessionHasErrors('days');
    }
}
