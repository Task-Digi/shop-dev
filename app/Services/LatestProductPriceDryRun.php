<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class LatestProductPriceDryRun
{
    /**
     * @param  iterable<object|array<string, mixed>>  $products
     * @param  iterable<object|array<string, mixed>>  $orderItems
     * @param  array{orders: array<string, array{date: string, source: string, hash: string, rows: array<string, array{gross: float, discount1: float, discount2: float, net: float}>}>}  $evidence
     * @return array{rows: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function analyze(iterable $products, iterable $orderItems, array $evidence, CarbonImmutable $taskAEffectiveAt): array
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
        foreach ($products as $product) {
            $product = (array) $product;
            $sku = trim((string) ($product['product_id'] ?? ''));
            $base = [
                'product_id' => $sku,
                'product_name' => $product['product_name'] ?? null,
                'current_price' => $product['price'] === null ? null : (float) $product['price'],
                'latest_supplier_order_id' => null,
                'supplier_order_date' => null,
                'gross_price' => null,
                'discount1' => null,
                'discount2' => null,
                'verified_net_price' => null,
                'difference' => null,
                'source_file' => null,
                'source_sha256' => null,
            ];
            $history = $ordersBySku[$sku] ?? [];
            if ($history === []) {
                $rows[] = $base + [
                    'classification' => 'NO_SUPPLIER_HISTORY',
                    'action' => 'SKIPPED',
                    'reason' => 'No order_items history exists for this product SKU.',
                ];

                continue;
            }

            $latestDate = max(array_map(static fn (array $item) => substr((string) $item['order_date'], 0, 10), $history));
            $latest = array_values(array_filter(
                $history,
                static fn (array $item) => substr((string) $item['order_date'], 0, 10) === $latestDate
            ));
            $prices = array_values(array_unique(array_map(
                static fn (array $item) => number_format((float) $item['price'], 2, '.', ''),
                $latest
            )));
            $orderIds = array_values(array_unique(array_filter(array_map(
                static fn (array $item) => trim((string) (($item['order_id2'] ?? '') ?: ($item['order_id'] ?? ''))),
                $latest
            ))));
            $base['supplier_order_date'] = $latestDate;
            $base['latest_supplier_order_id'] = implode('|', $orderIds);

            if (count($prices) !== 1 || count($orderIds) !== 1) {
                $rows[] = $base + [
                    'classification' => 'CONFLICT',
                    'action' => 'SKIPPED',
                    'reason' => count($prices) !== 1
                        ? 'Latest supplier date contains conflicting stored prices.'
                        : 'Latest supplier date contains more than one supplier order identifier.',
                ];

                continue;
            }

            $storedPrice = (float) $prices[0];
            $orderId = $orderIds[0];
            if (isset($evidence['orders'][$orderId])) {
                $orderEvidence = $evidence['orders'][$orderId];
                $evidenceRow = $orderEvidence['rows'][$sku] ?? null;
                if ($evidenceRow === null
                    || $orderEvidence['date'] !== $latestDate
                    || abs($evidenceRow['gross'] - $storedPrice) >= 0.005) {
                    $rows[] = array_merge($base, ['gross_price' => $storedPrice], [
                        'classification' => 'CONFLICT',
                        'action' => 'SKIPPED',
                        'reason' => 'Latest supplier order conflicts with the available original-file evidence.',
                    ]);

                    continue;
                }

                $rows[] = $this->safeRow(
                    $base,
                    'EXACT_FILE_VERIFIED',
                    $evidenceRow['gross'],
                    $evidenceRow['discount1'],
                    $evidenceRow['discount2'],
                    $evidenceRow['net'],
                    $orderEvidence['source'],
                    $orderEvidence['hash']
                );

                continue;
            }

            $createdTimes = array_map(
                static fn (array $item) => CarbonImmutable::parse((string) $item['created_at']),
                $latest
            );
            $allPostTaskA = array_reduce(
                $createdTimes,
                static fn (bool $carry, CarbonImmutable $created) => $carry && $created->greaterThanOrEqualTo($taskAEffectiveAt),
                true
            );
            if ($allPostTaskA) {
                $rows[] = $this->safeRow($base, 'POST_TASK_A_NET', null, null, null, $storedPrice, null, null);

                continue;
            }

            $rows[] = array_merge($base, ['gross_price' => $storedPrice], [
                'classification' => 'GROSS_DISCOUNT_UNKNOWN',
                'action' => 'SKIPPED',
                'reason' => 'Latest supplier price predates Task A and exact discount components are unavailable.',
            ]);
        }

        return ['rows' => $rows, 'summary' => $this->summarize($rows)];
    }

    /** @return array<string, mixed> */
    private function safeRow(
        array $base,
        string $classification,
        ?float $gross,
        ?float $discount1,
        ?float $discount2,
        float $net,
        ?string $source,
        ?string $hash
    ): array {
        $current = $base['current_price'];
        $alreadyCorrect = $current !== null && abs($current - $net) < 0.005;

        return array_merge($base, [
            'gross_price' => $gross,
            'discount1' => $discount1,
            'discount2' => $discount2,
            'verified_net_price' => $net,
            'difference' => $current === null ? null : round($net - $current, 2, PHP_ROUND_HALF_UP),
            'source_file' => $source,
            'source_sha256' => $hash,
            'classification' => $classification,
            'action' => $alreadyCorrect ? 'ALREADY_CORRECT' : 'SAFE_UPDATE',
            'reason' => $classification === 'EXACT_FILE_VERIFIED'
                ? 'Latest supplier order has validated row-level file evidence.'
                : 'Latest supplier row was imported after the verified Task A effective timestamp.',
        ]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function summarize(array $rows): array
    {
        $classes = $actions = [];
        foreach ($rows as $row) {
            $classes[$row['classification']] = ($classes[$row['classification']] ?? 0) + 1;
            $actions[$row['action']] = ($actions[$row['action']] ?? 0) + 1;
        }

        return [
            'total_products' => count($rows),
            'products_with_supplier_history' => count($rows) - ($classes['NO_SUPPLIER_HISTORY'] ?? 0),
            'classifications' => $classes,
            'safe_updates_required' => $actions['SAFE_UPDATE'] ?? 0,
            'already_correct' => $actions['ALREADY_CORRECT'] ?? 0,
            'skipped' => $actions['SKIPPED'] ?? 0,
        ];
    }
}
