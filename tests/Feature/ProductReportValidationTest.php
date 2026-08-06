<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductReportValidationTest extends TestCase
{
    public function test_product_drill_down_rejects_missing_filters(): void
    {
        $this->getJson('/product/details')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_product_sale_details_reject_missing_filters(): void
    {
        $this->getJson('/product/finaldetails')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_product_page_rejects_unsupported_periods(): void
    {
        $this->get('/report/all/product?days=999')
            ->assertSessionHasErrors('days');
    }

    public function test_product_export_rejects_unsupported_periods(): void
    {
        $this->get('/report/products/export?days=999')
            ->assertSessionHasErrors('days');
    }
}
