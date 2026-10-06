<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierOrderEvidenceValidator
{
    /**
     * Validate every file row against the current Order Delivery database.
     *
     * @param  array{orders: array<string, array{date: string, source: string, hash: string, rows: array<string, array{gross: float, discount1: float, discount2: float, net: float}>}>}  $evidence
     * @return array{orders: int, rows: int}
     */
    public function validate(array $evidence): array
    {
        $validatedRows = 0;

        foreach ($evidence['orders'] as $orderId => $order) {
            foreach ($order['rows'] as $sku => $row) {
                $matches = DB::table('order_items')
                    ->where('sku', $sku)
                    ->whereDate('order_date', $order['date'])
                    ->where(function ($query) use ($orderId): void {
                        $query->where('order_id2', $orderId)
                            ->orWhere(function ($fallback) use ($orderId): void {
                                $fallback->where(function ($blank): void {
                                    $blank->whereNull('order_id2')->orWhere('order_id2', '');
                                })->where('order_id', $orderId);
                            });
                    })
                    ->pluck('price');

                if ($matches->isEmpty()) {
                    throw new InvalidArgumentException(
                        "Evidence mismatch: {$order['source']} order {$orderId}, SKU {$sku} has no matching order_items row."
                    );
                }

                $grossPrices = $matches
                    ->map(static fn ($price) => number_format((float) $price, 2, '.', ''))
                    ->unique()
                    ->values();
                if ($grossPrices->count() !== 1 || abs((float) $grossPrices->first() - $row['gross']) >= 0.005) {
                    throw new InvalidArgumentException(
                        "Evidence mismatch: {$order['source']} order {$orderId}, SKU {$sku} gross price does not agree with order_items."
                    );
                }

                $validatedRows++;
            }
        }

        return ['orders' => count($evidence['orders']), 'rows' => $validatedRows];
    }
}
