<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesCoverageAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-22 12:00:00');

        Schema::dropIfExists('sales_reporting_days');
        Schema::dropIfExists('sale_data');
        Schema::create('sale_data', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->nullable();
            $table->string('location');
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

    public function test_it_keeps_sales_and_adds_a_zero_without_duplicates(): void
    {
        DB::table('sale_data')->insert(['date' => '2026-01-02', 'location' => 'MAJORSTUEN']);

        $arguments = [
            'year' => 2026,
            '--confirm-zero' => ['2026-01-01', '2026-01-02'],
            '--location' => 'MAJORSTUEN',
            '--source' => 'Signed daily close report',
        ];
        $this->artisan('sales:audit-coverage', $arguments)->assertExitCode(1);
        $this->artisan('sales:audit-coverage', $arguments)->assertExitCode(1);

        $this->assertDatabaseCount('sale_data', 1);
        $this->assertDatabaseCount('sales_reporting_days', 1);
        $this->assertDatabaseHas('sales_reporting_days', [
            'date' => '2026-01-01',
            'location' => 'MAJORSTUEN',
            'client' => 'The shop itself',
            'sales_amount' => 0,
        ]);
    }

    public function test_it_rejects_future_zero_sales_confirmation(): void
    {
        $this->artisan('sales:audit-coverage', [
            'year' => 2026,
            '--confirm-zero' => ['2026-09-23'],
            '--location' => 'MAJORSTUEN',
            '--source' => 'Close report',
        ])->assertExitCode(2);

        $this->assertDatabaseCount('sales_reporting_days', 0);
    }
}
