<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportPeriodIntegrationTest extends TestCase
{
    public function test_report_pages_use_consistent_sales_day_periods_and_pagination(): void
    {
        $this->get('/report?days=28')
            ->assertOk()
            ->assertSee('Last 28 sales days')
            ->assertSee('Daily Sales pages');

        $this->get('/report/all?days=28')
            ->assertOk()
            ->assertSee('Last 28 sales days')
            ->assertSee('Customer report pages');

        $this->get('/report/all/product?days=28')
            ->assertOk()
            ->assertSee('Last 28 sales days')
            ->assertSee('Product report pages');
    }

    public function test_exports_include_selected_period_metadata(): void
    {
        $customerExport = $this->get('/report/customers/export?days=7');
        $customerExport->assertOk();
        $this->assertStringContainsString('Last 7 sales days', $customerExport->streamedContent());

        $productExport = $this->get('/report/products/export?days=28');
        $productExport->assertOk();
        $this->assertStringContainsString('Last 28 sales days', $productExport->streamedContent());
    }
}
