<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VerifiedSupplierDiscountReader
{
    /**
     * Read exact, row-level evidence from one or more legacy supplier order files.
     *
     * @param  list<string>  $paths
     * @return array{files: list<string>, orders: array<string, array{date: string, source: string, hash: string, rows: array<string, array{gross: float, discount1: float, discount2: float, net: float}>}>}
     */
    public function read(array $paths): array
    {
        if ($paths === []) {
            throw new InvalidArgumentException('At least one supplier evidence file is required.');
        }

        $evidence = ['files' => [], 'orders' => []];
        foreach ($paths as $path) {
            $this->readFile($path, $evidence);
        }

        if ($evidence['orders'] === []) {
            throw new InvalidArgumentException('No supplier order evidence was found.');
        }

        return $evidence;
    }

    /** @param array{files: list<string>, orders: array<string, mixed>} $evidence */
    private function readFile(string $path, array &$evidence): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Supplier evidence file is not readable: {$path}");
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $header = array_map(
            static fn ($value) => trim((string) $value),
            $sheet->rangeToArray('A1:U1', null, true, false)[0]
        );
        if (($header[6] ?? null) !== 'StockKeepingUnit'
            || ($header[15] ?? null) !== 'InvoiceId'
            || ($header[16] ?? null) !== 'InvoiceDate') {
            throw new InvalidArgumentException("Unsupported supplier layout: {$path}");
        }

        $source = basename($path);
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new InvalidArgumentException("Cannot hash supplier evidence file: {$path}");
        }
        $evidence['files'][] = $source;
        for ($rowNumber = 2; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $sku = trim((string) $sheet->getCell("G{$rowNumber}")->getValue());
            if ($sku === '') {
                continue;
            }
            if (! preg_match('/^\d+$/', $sku)) {
                throw new InvalidArgumentException("Malformed SKU in {$source}, row {$rowNumber}.");
            }

            $orderId = (string) $this->wholeNumber($sheet->getCell("A{$rowNumber}")->getValue(), "order ID in {$source}, row {$rowNumber}");
            $date = $this->date($sheet->getCell("B{$rowNumber}")->getValue(), "order date in {$source}, row {$rowNumber}");
            $gross = $this->gross(
                $sheet->getCell("P{$rowNumber}")->getValue(),
                $sheet->getCell("Q{$rowNumber}")->getValue(),
                "gross price in {$source}, row {$rowNumber}"
            );
            $discount1 = $this->discount(
                $sheet->getCell("R{$rowNumber}")->getValue(),
                $sheet->getCell("S{$rowNumber}")->getValue(),
                "discount 1 in {$source}, row {$rowNumber}"
            );
            $discount2 = $this->discount(
                $sheet->getCell("T{$rowNumber}")->getValue(),
                $sheet->getCell("U{$rowNumber}")->getValue(),
                "discount 2 in {$source}, row {$rowNumber}"
            );
            $row = [
                'gross' => $gross,
                'discount1' => $discount1,
                'discount2' => $discount2,
                'net' => round($gross * (1 - $discount1 / 100) * (1 - $discount2 / 100), 2, PHP_ROUND_HALF_UP),
            ];

            if (isset($evidence['orders'][$orderId])
                && ($evidence['orders'][$orderId]['date'] !== $date
                    || $evidence['orders'][$orderId]['source'] !== $source
                    || $evidence['orders'][$orderId]['hash'] !== $hash)) {
                throw new InvalidArgumentException("Conflicting source evidence for supplier order {$orderId}.");
            }
            $evidence['orders'][$orderId] ??= ['date' => $date, 'source' => $source, 'hash' => $hash, 'rows' => []];
            if (isset($evidence['orders'][$orderId]['rows'][$sku])
                && $evidence['orders'][$orderId]['rows'][$sku] !== $row) {
                throw new InvalidArgumentException("Conflicting rows for order {$orderId}, SKU {$sku}.");
            }
            $evidence['orders'][$orderId]['rows'][$sku] = $row;
        }
    }

    private function date(mixed $value, string $field): string
    {
        $text = trim((string) $value);
        foreach (['d.m.Y H:i:s', 'd.m.Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $text);
                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable) {
                // Try the next explicitly supported supplier format.
            }
        }

        throw new InvalidArgumentException("Malformed {$field}.");
    }

    private function gross(mixed $whole, mixed $decimal, string $field): float
    {
        $whole = $this->wholeNumber($whole, "{$field} whole component");
        $digits = trim((string) ($decimal ?? ''));
        if ($digits === '') {
            $digits = '0';
        }
        if ($whole < 0 || ! preg_match('/^\d{1,2}$/', $digits)) {
            throw new InvalidArgumentException("Malformed {$field} components.");
        }

        $cents = strlen($digits) === 1 ? ((int) $digits) * 10 : (int) $digits;

        return $whole + ($cents / 100);
    }

    private function discount(mixed $whole, mixed $decimal, string $field): float
    {
        $whole = $this->blankOrWholeNumber($whole, "{$field} whole component");
        $decimal = $this->blankOrWholeNumber($decimal, "{$field} decimal component");
        if ($decimal < 0 || $decimal > 9) {
            throw new InvalidArgumentException("{$field} decimal component must be between 0 and 9.");
        }

        $discount = $whole + ($decimal / 10);
        if ($discount < 0 || $discount > 100) {
            throw new InvalidArgumentException("{$field} must be between 0% and 100%.");
        }

        return $discount;
    }

    private function blankOrWholeNumber(mixed $value, string $field): int
    {
        return $value === null || trim((string) $value) === '' ? 0 : $this->wholeNumber($value, $field);
    }

    private function wholeNumber(mixed $value, string $field): int
    {
        if (! is_numeric($value) || (float) $value !== floor((float) $value)) {
            throw new InvalidArgumentException("Malformed {$field}.");
        }

        return (int) $value;
    }
}
