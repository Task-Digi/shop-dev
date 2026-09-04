<?php

namespace Tests\Unit;

use App\Models\OrderRecord;
use Tests\TestCase;

class OrderDeliverySortingTest extends TestCase
{
    public function test_order_delivery_list_is_sorted_by_latest_order_date_first(): void
    {
        $query = OrderRecord::query()->latestOrderDateFirst();

        $this->assertSame(
            'select * from `order_records` order by `order_date` desc, CAST(order_id AS UNSIGNED) DESC',
            $query->toSql()
        );
    }
}
