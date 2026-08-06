<?php

namespace Tests\Feature;

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
