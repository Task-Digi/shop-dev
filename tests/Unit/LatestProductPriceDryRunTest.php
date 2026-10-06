<?php

namespace Tests\Unit;

use App\Services\LatestProductPriceDryRun;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LatestProductPriceDryRunTest extends TestCase
{
    public function test_it_classifies_exact_post_task_a_gross_unknown_no_history_and_conflict(): void
    {
        $products = [
            $this->product('A', 100),
            $this->product('B', 78.20),
            $this->product('C', 50),
            $this->product('D', 10),
            $this->product('E', 20),
        ];
        $orders = [
            $this->order(1, '750553', '2026-08-26', 'A', 100, '2026-08-27 10:00:00'),
            $this->order(2, '900001', '2026-10-06', 'B', 78.20, '2026-10-06 10:00:00'),
            $this->order(3, '700001', '2026-09-01', 'C', 50, '2026-09-02 10:00:00'),
            $this->order(4, '800001', '2026-09-01', 'E', 20, '2026-09-02 10:00:00'),
            $this->order(5, '800002', '2026-09-01', 'E', 20, '2026-09-02 10:00:00'),
        ];
        $evidence = [
            'orders' => ['750553' => [
                'date' => '2026-08-26', 'source' => 'ordre_750553.xls', 'hash' => str_repeat('a', 64),
                'rows' => ['A' => ['gross' => 100.0, 'discount1' => 15.0, 'discount2' => 8.0, 'net' => 78.20]],
            ]],
        ];

        $result = (new LatestProductPriceDryRun)->analyze(
            $products,
            $orders,
            $evidence,
            CarbonImmutable::parse('2026-10-05 15:06:12')
        );
        $rows = collect($result['rows'])->keyBy('product_id');

        $this->assertSame('EXACT_FILE_VERIFIED', $rows['A']['classification']);
        $this->assertSame('SAFE_UPDATE', $rows['A']['action']);
        $this->assertSame(78.20, $rows['A']['verified_net_price']);
        $this->assertSame('POST_TASK_A_NET', $rows['B']['classification']);
        $this->assertSame('ALREADY_CORRECT', $rows['B']['action']);
        $this->assertSame('GROSS_DISCOUNT_UNKNOWN', $rows['C']['classification']);
        $this->assertSame('NO_SUPPLIER_HISTORY', $rows['D']['classification']);
        $this->assertSame('CONFLICT', $rows['E']['classification']);
        $this->assertSame(1, $result['summary']['safe_updates_required']);
        $this->assertSame(1, $result['summary']['already_correct']);
        $this->assertSame(3, $result['summary']['skipped']);
    }

    private function product(string $sku, float $price): array
    {
        return ['product_id' => $sku, 'product_name' => "Product {$sku}", 'price' => $price];
    }

    private function order(int $id, string $order, string $date, string $sku, float $price, string $created): array
    {
        return [
            'id' => $id, 'order_id' => $order, 'order_id2' => $order, 'order_date' => $date,
            'sku' => $sku, 'price' => $price, 'created_at' => $created,
        ];
    }
}
