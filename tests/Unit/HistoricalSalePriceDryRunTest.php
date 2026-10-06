<?php

namespace Tests\Unit;

use App\Services\HistoricalSalePriceDryRun;
use PHPUnit\Framework\TestCase;

class HistoricalSalePriceDryRunTest extends TestCase
{
    private HistoricalSalePriceDryRun $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HistoricalSalePriceDryRun;
    }

    public function test_it_uses_exact_file_evidence_for_latest_order_on_or_before_sale(): void
    {
        $result = $this->service->analyze(
            [$this->sale(1, '2026-08-28', '264980', 653.40, 2)],
            [
                $this->order(1, '700000', '2026-03-18', '264980', 605.00),
                $this->order(2, '750553', '2026-08-26', '264980', 653.40),
                $this->order(3, '800000', '2026-09-01', '264980', 700.00),
            ],
            $this->evidence('750553', '264980', 653.40, 15, 8)
        );

        $row = $result['rows'][0];
        $this->assertSame('EXACT_FILE_VERIFIED', $row['status']);
        $this->assertSame('750553', $row['supplier_order_id']);
        $this->assertSame(510.96, $row['proposed_price']);
        $this->assertSame(-142.44, $row['unit_difference']);
        $this->assertSame(-284.88, $row['total_difference']);
    }

    public function test_it_does_not_use_future_supplier_orders(): void
    {
        $result = $this->service->analyze(
            [$this->sale(2, '2026-08-25', '264980', 653.40)],
            [$this->order(1, '750553', '2026-08-26', '264980', 653.40)],
            $this->evidence('750553', '264980', 653.40, 15, 8)
        );

        $this->assertSame('NO_PRIOR_SUPPLIER_ORDER', $result['rows'][0]['status']);
        $this->assertNull($result['rows'][0]['proposed_price']);
    }

    public function test_it_skips_when_corresponding_supplier_file_is_missing_instead_of_propagating_discount(): void
    {
        $result = $this->service->analyze(
            [$this->sale(3, '2026-04-01', '264980', 605.00)],
            [$this->order(1, '700000', '2026-03-18', '264980', 605.00)],
            $this->evidence('750553', '264980', 653.40, 15, 8)
        );

        $this->assertSame('NO_SUPPLIER_FILE', $result['rows'][0]['status']);
        $this->assertNull($result['rows'][0]['discount1']);
        $this->assertNull($result['rows'][0]['proposed_price']);
    }

    public function test_it_skips_when_sku_is_missing_from_matching_supplier_file(): void
    {
        $result = $this->service->analyze(
            [$this->sale(4, '2026-08-27', '264982', 653.40)],
            [$this->order(1, '750553', '2026-08-26', '264982', 653.40)],
            $this->evidence('750553', '264980', 653.40, 15, 8)
        );

        $this->assertSame('SKU_NOT_IN_SUPPLIER_FILE', $result['rows'][0]['status']);
        $this->assertNull($result['rows'][0]['proposed_price']);
    }

    public function test_it_rejects_database_and_file_gross_price_mismatch(): void
    {
        $result = $this->service->analyze(
            [$this->sale(5, '2026-08-27', '264980', 605.00)],
            [$this->order(1, '750553', '2026-08-26', '264980', 605.00)],
            $this->evidence('750553', '264980', 653.40, 15, 8)
        );

        $this->assertSame('AMBIGUOUS_SUPPLIER_PRICE', $result['rows'][0]['status']);
        $this->assertNull($result['rows'][0]['proposed_price']);
    }

    public function test_it_marks_an_exact_file_price_already_correct(): void
    {
        $result = $this->service->analyze(
            [$this->sale(6, '2026-08-27', '199909', 1772.25)],
            [$this->order(1, '750553', '2026-08-26', '199909', 2085.00)],
            $this->evidence('750553', '199909', 2085.00, 15, 0)
        );

        $this->assertSame('ALREADY_CORRECT', $result['rows'][0]['status']);
        $this->assertSame(1772.25, $result['rows'][0]['proposed_price']);
        $this->assertSame(1, $result['summary']['already_correct']);
    }

    private function sale(int $id, string $date, string $sku, float $price, int $count = 1): array
    {
        return ['id' => $id, 'date' => $date, 'orderid' => "S{$id}", 'product_id' => $sku, 'count' => $count, 'price' => $price];
    }

    private function order(int $id, string $orderId, string $date, string $sku, float $price): array
    {
        return ['id' => $id, 'order_id' => $orderId, 'order_id2' => $orderId, 'order_date' => $date, 'sku' => $sku, 'price' => $price];
    }

    private function evidence(string $orderId, string $sku, float $gross, float $discount1, float $discount2): array
    {
        return [
            'files' => ['ordre_'.$orderId.'.xls'],
            'orders' => [
                $orderId => [
                    'date' => '2026-08-26',
                    'source' => 'ordre_'.$orderId.'.xls',
                    'hash' => str_repeat('a', 64),
                    'rows' => [
                        $sku => [
                            'gross' => $gross,
                            'discount1' => $discount1,
                            'discount2' => $discount2,
                            'net' => round($gross * (1 - $discount1 / 100) * (1 - $discount2 / 100), 2, PHP_ROUND_HALF_UP),
                        ],
                    ],
                ],
            ],
        ];
    }
}
