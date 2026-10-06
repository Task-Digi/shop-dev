<?php

namespace Tests\Unit;

use App\Services\VerifiedSupplierDiscountReader;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PHPUnit\Framework\TestCase;

class VerifiedSupplierDiscountReaderTest extends TestCase
{
    private string $path;

    protected function tearDown(): void
    {
        if (isset($this->path) && is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    public function test_it_reads_product_specific_split_discounts_and_calculates_sequential_net_prices(): void
    {
        $rows = [
            ['100001', 100, 0, 14, 1, null, null],
            ['100002', 100, 0, 15, null, null, null],
            ['100003', 100, 0, 15, null, 8, null],
            ['100004', 100, 0, null, null, 8, null],
            ['302085', 331, 84, 23, 1, null, null],
        ];
        $this->path = $this->supplierFile($rows);

        $result = (new VerifiedSupplierDiscountReader)->read([$this->path]);
        $evidence = $result['orders']['750553']['rows'];

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['orders']['750553']['hash']);
        $this->assertSame(14.1, $evidence['100001']['discount1']);
        $this->assertSame(85.90, $evidence['100001']['net']);
        $this->assertSame(15.0, $evidence['100002']['discount1']);
        $this->assertSame(85.0, $evidence['100002']['net']);
        $this->assertSame(15.0, $evidence['100003']['discount1']);
        $this->assertSame(8.0, $evidence['100003']['discount2']);
        $this->assertSame(78.20, $evidence['100003']['net']);
        $this->assertSame(0.0, $evidence['100004']['discount1']);
        $this->assertSame(8.0, $evidence['100004']['discount2']);
        $this->assertSame(92.0, $evidence['100004']['net']);
        $this->assertSame(23.1, $evidence['302085']['discount1']);
        $this->assertSame(255.18, $evidence['302085']['net']);
    }

    /** @param list<array{string, int, int, int|null, int|null, int|null, int|null}> $rows */
    private function supplierFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = array_fill(0, 21, '');
        $headers[6] = 'StockKeepingUnit';
        $headers[15] = 'InvoiceId';
        $headers[16] = 'InvoiceDate';
        $sheet->fromArray($headers, null, 'A1');

        foreach ($rows as $offset => [$sku, $whole, $decimal, $d1Whole, $d1Decimal, $d2Whole, $d2Decimal]) {
            $row = $offset + 2;
            $sheet->setCellValue("A{$row}", 750553);
            $sheet->setCellValue("B{$row}", '26.08.2026 07:58:53');
            $sheet->setCellValueExplicit("G{$row}", $sku, DataType::TYPE_STRING);
            $sheet->setCellValue("P{$row}", $whole);
            $sheet->setCellValue("Q{$row}", $decimal);
            $sheet->setCellValue("R{$row}", $d1Whole);
            $sheet->setCellValue("S{$row}", $d1Decimal);
            $sheet->setCellValue("T{$row}", $d2Whole);
            $sheet->setCellValue("U{$row}", $d2Decimal);
        }

        $path = tempnam(sys_get_temp_dir(), 'supplier-evidence-').'.xls';
        (new Xls($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
