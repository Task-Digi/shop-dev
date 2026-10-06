<?php

namespace App\Services;

use InvalidArgumentException;

class SupplierOrderPriceCalculator
{
    private const LEGACY_GROSS_WHOLE_INDEX = 15; // P

    private const LEGACY_GROSS_DECIMAL_INDEX = 16; // Q

    private const LEGACY_DISCOUNT_1_WHOLE_INDEX = 17; // R

    private const LEGACY_DISCOUNT_1_DECIMAL_INDEX = 18; // S

    private const LEGACY_DISCOUNT_2_WHOLE_INDEX = 19; // T

    private const LEGACY_DISCOUNT_2_DECIMAL_INDEX = 20; // U

    /**
     * Resolve the net supplier unit price without changing standard headed imports.
     */
    public function calculate(array $header, array $row, array $data): float
    {
        if ($this->isLegacySplitPriceLayout($header)) {
            return $this->calculateLegacyPrice($row);
        }

        foreach (['Price', 'Pris', 'UnitPrice', 'Enhetspris'] as $priceHeader) {
            if (array_key_exists($priceHeader, $data)) {
                $price = $this->parseDecimal($data[$priceHeader], $priceHeader);

                if ($price < 0) {
                    throw new InvalidArgumentException("{$priceHeader} cannot be negative.");
                }

                return round($price, 2, PHP_ROUND_HALF_UP);
            }
        }

        throw new InvalidArgumentException('The supplier file does not contain a supported price column.');
    }

    public function isLegacySplitPriceLayout(array $header): bool
    {
        if (count($header) < 21) {
            return false;
        }

        $normalized = array_map(
            static fn ($value) => trim((string) $value, "\xEF\xBB\xBF \t\n\r\0\x0B"),
            $header
        );

        return ($normalized[12] ?? null) === 'Price'
            && ($normalized[13] ?? null) === 'LineDiscount'
            && ($normalized[14] ?? null) === 'SpecialDiscount'
            && ($normalized[15] ?? null) === 'InvoiceId'
            && ($normalized[16] ?? null) === 'InvoiceDate'
            && ($normalized[17] ?? '') === ''
            && ($normalized[18] ?? '') === ''
            && ($normalized[19] ?? '') === ''
            && ($normalized[20] ?? '') === '';
    }

    private function calculateLegacyPrice(array $row): float
    {
        $grossWhole = $this->parseWholeComponent(
            $row[self::LEGACY_GROSS_WHOLE_INDEX] ?? null,
            'gross price whole component (P)',
            false
        );
        $grossDecimalCents = $this->parseLegacyPriceDecimalCents(
            $row[self::LEGACY_GROSS_DECIMAL_INDEX] ?? null,
            'gross price decimal component (Q)'
        );

        if ($grossWhole < 0) {
            throw new InvalidArgumentException('Legacy gross price components P/Q are outside their valid ranges.');
        }

        $discount1Whole = $this->parseWholeComponent(
            $row[self::LEGACY_DISCOUNT_1_WHOLE_INDEX] ?? null,
            'discount 1 whole component (R)',
            true
        );
        $discount1Decimal = $this->parseWholeComponent(
            $row[self::LEGACY_DISCOUNT_1_DECIMAL_INDEX] ?? null,
            'discount 1 decimal component (S)',
            true
        );
        $discount2Whole = $this->parseWholeComponent(
            $row[self::LEGACY_DISCOUNT_2_WHOLE_INDEX] ?? null,
            'discount 2 whole component (T)',
            true
        );
        $discount2Decimal = $this->parseWholeComponent(
            $row[self::LEGACY_DISCOUNT_2_DECIMAL_INDEX] ?? null,
            'discount 2 decimal component (U)',
            true
        );

        foreach ([$discount1Decimal, $discount2Decimal] as $decimalComponent) {
            if ($decimalComponent < 0 || $decimalComponent > 9) {
                throw new InvalidArgumentException('Legacy discount decimal components S/U must be between 0 and 9.');
            }
        }

        $discount1Tenths = ($discount1Whole * 10) + $discount1Decimal;
        $discount2Tenths = ($discount2Whole * 10) + $discount2Decimal;

        foreach ([$discount1Tenths, $discount2Tenths] as $discountTenths) {
            if ($discountTenths < 0 || $discountTenths > 1000) {
                throw new InvalidArgumentException('Each supplier discount must be between 0% and 100%.');
            }
        }

        $grossCents = ($grossWhole * 100) + $grossDecimalCents;

        // Work in cents and discount tenths to keep the sequential calculation deterministic.
        $netCents = $grossCents
            * (1000 - $discount1Tenths)
            * (1000 - $discount2Tenths)
            / 1000000;

        return round($netCents, 0, PHP_ROUND_HALF_UP) / 100;
    }

    /**
     * The supplier stores the fractional price as decimal digits rather than
     * a fixed hundredths value: Q=4 means .40 and Q=84 means .84.
     */
    private function parseLegacyPriceDecimalCents(mixed $value, string $field): int
    {
        if ($value === null || trim((string) $value) === '') {
            return 0;
        }

        $digits = trim((string) $value);

        if (! preg_match('/^\d{1,2}$/', $digits)) {
            throw new InvalidArgumentException("{$field} must contain one or two decimal digits.");
        }

        if ($digits === '0' || $digits === '00') {
            return 0;
        }

        return strlen($digits) === 1
            ? ((int) $digits) * 10
            : (int) $digits;
    }

    private function parseWholeComponent(mixed $value, string $field, bool $blankIsZero): int
    {
        if ($value === null || trim((string) $value) === '') {
            if ($blankIsZero) {
                return 0;
            }

            throw new InvalidArgumentException("Missing {$field}.");
        }

        $number = $this->parseDecimal($value, $field);

        if (floor($number) !== $number) {
            throw new InvalidArgumentException("{$field} must be a whole number.");
        }

        return (int) $number;
    }

    private function parseDecimal(mixed $value, string $field): float
    {
        if ($value === null || trim((string) $value) === '') {
            throw new InvalidArgumentException("Missing {$field}.");
        }

        $normalized = str_replace([' ', ','], ['', '.'], trim((string) $value));

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("Malformed numeric value for {$field}.");
        }

        $number = (float) $normalized;

        if (! is_finite($number)) {
            throw new InvalidArgumentException("Malformed numeric value for {$field}.");
        }

        return $number;
    }
}
