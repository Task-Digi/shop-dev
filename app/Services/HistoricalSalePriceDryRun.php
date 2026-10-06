<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class HistoricalSalePriceDryRun
{
    /**
     * @param  iterable<object|array<string, mixed>>  $sales
     * @param  iterable<object|array<string, mixed>>  $orderItems
     * @param  array{files: list<string>, orders: array<string, array{date: string, source: string, hash: string, rows: array<string, array{gross: float, discount1: float, discount2: float, net: float}>}>}  $evidence
     * @return array{rows: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function analyze(iterable $sales, iterable $orderItems, array $evidence): array
    {
        $ordersBySku = [];
        foreach ($orderItems as $item) {
            $item = (array) $item;
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku !== '') {
                $ordersBySku[$sku][] = $item;
            }
        }

        $rows = [];
        foreach ($sales as $sale) {
            $rows[] = $this->analyzeSale((array) $sale, $ordersBySku, $evidence['orders']);
        }

        return [
            'rows' => $rows,
            'summary' => $this->summarize($rows, $evidence),
        ];
    }

    /** @return array<string, mixed> */
    private function analyzeSale(array $sale, array $ordersBySku, array $evidenceOrders): array
    {
        $sku = trim((string) ($sale['product_id'] ?? ''));
        $base = [
            'sale_data_id' => $sale['id'] ?? null,
            'sale_date' => $sale['date'] ?? null,
            'sale_order_id' => $sale['orderid'] ?? null,
            'sku' => $sku,
            'quantity' => (float) ($sale['count'] ?? 0),
            'old_price' => $sale['price'] === null ? null : (float) $sale['price'],
            'supplier_order_id' => null,
            'supplier_order_date' => null,
            'supplier_source_file' => null,
            'supplier_source_hash' => null,
            'historical_gross_price' => null,
            'discount1' => null,
            'discount2' => null,
            'calculated_net_price' => null,
            'proposed_price' => null,
            'unit_difference' => null,
            'total_difference' => null,
        ];

        if ($sku === '') {
            return $base + ['status' => 'SKU_NOT_FOUND', 'reason' => 'sale_data.product_id is blank.'];
        }

        $saleDate = CarbonImmutable::parse((string) $sale['date'])->toDateString();
        $eligible = array_values(array_filter(
            $ordersBySku[$sku] ?? [],
            static fn (array $item) => substr((string) ($item['order_date'] ?? ''), 0, 10) <= $saleDate
        ));
        if ($eligible === []) {
            return $base + ['status' => 'NO_PRIOR_SUPPLIER_ORDER', 'reason' => 'No supplier order exists on or before the sale date.'];
        }

        $latestDate = max(array_map(static fn (array $item) => substr((string) $item['order_date'], 0, 10), $eligible));
        $latest = array_values(array_filter(
            $eligible,
            static fn (array $item) => substr((string) $item['order_date'], 0, 10) === $latestDate
        ));
        $grossPrices = array_values(array_unique(array_map(
            static fn (array $item) => number_format((float) $item['price'], 2, '.', ''),
            $latest
        )));
        if (count($grossPrices) !== 1) {
            return $base + [
                'supplier_order_date' => $latestDate,
                'status' => 'AMBIGUOUS_SUPPLIER_PRICE',
                'reason' => 'Latest supplier order date contains conflicting gross prices: '.implode(', ', $grossPrices),
            ];
        }

        $orderIds = array_values(array_unique(array_filter(array_map(
            static fn (array $item) => trim((string) (($item['order_id2'] ?? '') ?: ($item['order_id'] ?? ''))),
            $latest
        ))));
        $availableFiles = array_values(array_filter($orderIds, static fn (string $id) => isset($evidenceOrders[$id])));
        if ($availableFiles === []) {
            return $base + [
                'supplier_order_id' => implode('|', $orderIds),
                'supplier_order_date' => $latestDate,
                'historical_gross_price' => (float) $grossPrices[0],
                'status' => 'NO_SUPPLIER_FILE',
                'reason' => 'The corresponding original supplier order file is unavailable.',
            ];
        }

        $matchedRows = [];
        foreach ($availableFiles as $orderId) {
            if (isset($evidenceOrders[$orderId]['rows'][$sku])) {
                $matchedRows[] = [
                    'order_id' => $orderId,
                    'date' => $evidenceOrders[$orderId]['date'],
                    'source' => $evidenceOrders[$orderId]['source'],
                    'hash' => $evidenceOrders[$orderId]['hash'],
                    'row' => $evidenceOrders[$orderId]['rows'][$sku],
                ];
            }
        }
        if ($matchedRows === []) {
            return $base + [
                'supplier_order_id' => implode('|', $availableFiles),
                'supplier_order_date' => $latestDate,
                'supplier_source_file' => implode('|', array_unique(array_map(static fn (string $id) => $evidenceOrders[$id]['source'], $availableFiles))),
                'historical_gross_price' => (float) $grossPrices[0],
                'status' => 'SKU_NOT_IN_SUPPLIER_FILE',
                'reason' => 'Supplier file exists, but it does not contain this SKU.',
            ];
        }

        $signatures = array_unique(array_map(static fn (array $match) => json_encode($match['row']), $matchedRows));
        if (count($signatures) !== 1 || abs($matchedRows[0]['row']['gross'] - (float) $grossPrices[0]) >= 0.005) {
            return $base + [
                'supplier_order_id' => implode('|', array_column($matchedRows, 'order_id')),
                'supplier_order_date' => $latestDate,
                'supplier_source_file' => implode('|', array_unique(array_column($matchedRows, 'source'))),
                'supplier_source_hash' => implode('|', array_unique(array_column($matchedRows, 'hash'))),
                'historical_gross_price' => (float) $grossPrices[0],
                'status' => 'AMBIGUOUS_SUPPLIER_PRICE',
                'reason' => 'Database gross price and exact supplier-file evidence do not agree.',
            ];
        }

        $match = $matchedRows[0];
        $evidenceRow = $match['row'];
        $old = $base['old_price'];
        $unitDifference = $old === null ? null : round($evidenceRow['net'] - $old, 2, PHP_ROUND_HALF_UP);
        $alreadyCorrect = $old !== null && abs($old - $evidenceRow['net']) < 0.005;

        return array_merge($base, [
            'supplier_order_id' => $match['order_id'],
            'supplier_order_date' => $match['date'],
            'supplier_source_file' => $match['source'],
            'supplier_source_hash' => $match['hash'],
            'historical_gross_price' => $evidenceRow['gross'],
            'discount1' => $evidenceRow['discount1'],
            'discount2' => $evidenceRow['discount2'],
            'calculated_net_price' => $evidenceRow['net'],
            'proposed_price' => $evidenceRow['net'],
            'unit_difference' => $unitDifference,
            'total_difference' => $unitDifference === null ? null : round($unitDifference * $base['quantity'], 2, PHP_ROUND_HALF_UP),
            'status' => $alreadyCorrect ? 'ALREADY_CORRECT' : 'EXACT_FILE_VERIFIED',
            'reason' => $alreadyCorrect
                ? 'Existing price already equals the exact supplier-file net price.'
                : 'Gross price and product-specific discounts come from the matched supplier order file.',
        ]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function summarize(array $rows, array $evidence): array
    {
        $statuses = [];
        $oldCost = $proposedCost = 0.0;
        foreach ($rows as $row) {
            $statuses[$row['status']] = ($statuses[$row['status']] ?? 0) + 1;
            if (in_array($row['status'], ['EXACT_FILE_VERIFIED', 'ALREADY_CORRECT'], true)) {
                $oldCost += (float) $row['quantity'] * (float) ($row['old_price'] ?? 0);
                $proposedCost += (float) $row['quantity'] * (float) $row['proposed_price'];
            }
        }

        $uniqueSkus = [];
        foreach ($evidence['orders'] as $order) {
            foreach (array_keys($order['rows']) as $sku) {
                $uniqueSkus[$sku] = true;
            }
        }

        return [
            'total_rows' => count($rows),
            'supplier_files' => count(array_unique($evidence['files'])),
            'supplier_orders' => count($evidence['orders']),
            'verified_skus' => count($uniqueSkus),
            'correctable' => $statuses['EXACT_FILE_VERIFIED'] ?? 0,
            'already_correct' => $statuses['ALREADY_CORRECT'] ?? 0,
            'skipped' => count($rows) - (($statuses['EXACT_FILE_VERIFIED'] ?? 0) + ($statuses['ALREADY_CORRECT'] ?? 0)),
            'statuses' => $statuses,
            'total_old_cost' => round($oldCost, 2, PHP_ROUND_HALF_UP),
            'total_proposed_cost' => round($proposedCost, 2, PHP_ROUND_HALF_UP),
            'total_difference' => round($proposedCost - $oldCost, 2, PHP_ROUND_HALF_UP),
        ];
    }
}
