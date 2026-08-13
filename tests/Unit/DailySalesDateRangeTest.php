<?php

namespace Tests\Unit;

use App\Http\Controllers\ReportController;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DailySalesDateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_last_seven_days_contains_only_dates_with_sales(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 12:00:00'));
        $method = new ReflectionMethod(ReportController::class, 'buildDailySalesChartSeries');
        $method->setAccessible(true);

        $sales = new Collection([
            (object) ['date' => '2026-08-01', 'total_sales' => 100.0],
            (object) ['date' => '2026-08-03', 'total_sales' => 250.0],
        ]);
        $series = $method->invoke(new ReportController(), null, 7, $sales);

        $this->assertSame(['2026-08-01', '2026-08-03'], $series['labels']);
        $this->assertSame([100.0, 250.0], $series['values']);
    }
}
