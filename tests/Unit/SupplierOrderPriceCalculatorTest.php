<?php

namespace Tests\Unit;

use App\Services\SupplierOrderPriceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SupplierOrderPriceCalculatorTest extends TestCase
{
    #[DataProvider('legacyPriceProvider')]
    public function test_it_calculates_legacy_supplier_prices(
        int $grossWhole,
        int $grossDecimal,
        int|string|null $discount1Whole,
        int|string|null $discount1Decimal,
        int|string|null $discount2Whole,
        int|string|null $discount2Decimal,
        float $expected
    ): void {
        $calculator = new SupplierOrderPriceCalculator;

        $actual = $calculator->calculate(
            $this->legacyHeader(),
            $this->legacyRow(
                $grossWhole,
                $grossDecimal,
                $discount1Whole,
                $discount1Decimal,
                $discount2Whole,
                $discount2Decimal
            ),
            []
        );

        $this->assertSame($expected, $actual);
    }

    public static function legacyPriceProvider(): array
    {
        return [
            'no discount' => [100, 0, 0, 0, 0, 0, 100.00],
            'single discount - SKU 199909' => [2085, 0, 15, 0, 0, 0, 1772.25],
            'decimal discount - SKU 302085' => [331, 84, 23, 1, 0, 0, 255.18],
            'double sequential discount' => [100, 0, 15, 0, 8, 0, 78.20],
            'discount 2 only' => [100, 0, 0, 0, 8, 0, 92.00],
            'blank discounts' => [100, 0, '', null, '', null, 100.00],
            'one digit decimal component - SKU 264980' => [653, 4, 15, 0, 8, 0, 510.96],
        ];
    }

    public function test_standard_headed_price_import_remains_supported(): void
    {
        $calculator = new SupplierOrderPriceCalculator;

        $this->assertSame(2085.00, $calculator->calculate(
            ['OrderId', 'Price'],
            ['123', '2085,00'],
            ['OrderId' => '123', 'Price' => '2085,00']
        ));
    }

    public function test_malformed_legacy_discount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Malformed numeric value for discount 1 whole component (R).');

        (new SupplierOrderPriceCalculator)->calculate(
            $this->legacyHeader(),
            $this->legacyRow(100, 0, 'not-a-number', 0, 0, 0),
            []
        );
    }

    public function test_malformed_legacy_gross_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Malformed numeric value for gross price whole component (P).');

        (new SupplierOrderPriceCalculator)->calculate(
            $this->legacyHeader(),
            $this->legacyRow('bad', 0, 0, 0, 0, 0),
            []
        );
    }

    public function test_out_of_range_legacy_discount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each supplier discount must be between 0% and 100%.');

        (new SupplierOrderPriceCalculator)->calculate(
            $this->legacyHeader(),
            $this->legacyRow(100, 0, 101, 0, 0, 0),
            []
        );
    }

    private function legacyHeader(): array
    {
        return [
            'OrderId', 'OrderDate', 'OrderedBy', 'PlannedDelivery', 'Status',
            'YourReference', 'StockKeepingUnit', 'ItemName', 'PackagingQuantity',
            'PackagingUnit', 'OrderedQuantity', 'DeliveredQuantity', 'Price',
            'LineDiscount', 'SpecialDiscount', 'InvoiceId', 'InvoiceDate',
            '', '', '', '', '', '',
        ];
    }

    private function legacyRow(
        int|string $grossWhole,
        int $grossDecimal,
        int|string|null $discount1Whole,
        int|string|null $discount1Decimal,
        int|string|null $discount2Whole,
        int|string|null $discount2Decimal
    ): array {
        $row = array_fill(0, 23, '');
        $row[15] = $grossWhole; // P
        $row[16] = $grossDecimal; // Q
        $row[17] = $discount1Whole; // R
        $row[18] = $discount1Decimal; // S
        $row[19] = $discount2Whole; // T
        $row[20] = $discount2Decimal; // U

        return $row;
    }
}
