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

    public function test_last_seven_days_contains_exactly_seven_calendar_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 12:00:00'));
        $method = new ReflectionMethod(ReportController::class, 'buildDailySalesChartSeries');
        $method->setAccessible(true);

        $series = $method->invoke(new ReportController(), null, 7, new Collection());

        $this->assertCount(7, $series['labels']);
        $this->assertSame('2026-07-31', $series['labels'][0]);
        $this->assertSame('2026-08-06', $series['labels'][6]);
    }
}
