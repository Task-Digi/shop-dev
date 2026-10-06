<?php

namespace App\Console\Commands;

use App\Services\LatestProductPriceDryRun;
use App\Services\SupplierOrderEvidenceValidator;
use App\Services\VerifiedSupplierDiscountReader;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ApplyLatestProductPriceCorrections extends Command
{
    protected $signature = 'task-c:historical-product-prices
        {supplier-files* : Exact supplier XLS/XLSX evidence files}
        {--task-a-effective-at=2026-10-05 15:06:12 : Verified Task A implementation effective timestamp}
        {--execute : Apply exact-file product corrections transactionally}
        {--approval= : Required literal APPLY_TASK_C when --execute is used}
        {--expected-database= : Required database name when --execute is used}
        {--expected-count= : Required product count when --execute is used}
        {--expected-order= : Required sole supplier order when --execute is used}
        {--expected-products= : Required comma-separated product IDs when --execute is used}
        {--expected-source-sha256= : Required source hash when --execute is used}';

    protected $description = 'Preview exact-evidence Task C changes; execution requires explicit approval and manifest gates';

    public function handle(
        VerifiedSupplierDiscountReader $reader,
        SupplierOrderEvidenceValidator $validator,
        LatestProductPriceDryRun $dryRun
    ): int {
        try {
            $evidence = $reader->read(array_values((array) $this->argument('supplier-files')));
            $validator->validate($evidence);
            $effectiveAt = CarbonImmutable::parse((string) $this->option('task-a-effective-at'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $result = $dryRun->analyze(
            DB::table('products')->orderBy('id')->get(['id', 'product_id', 'product_name', 'price']),
            DB::table('order_items')->orderBy('order_date')->orderBy('id')->get([
                'id', 'order_id', 'order_id2', 'order_date', 'sku', 'price', 'created_at',
            ]),
            $evidence,
            $effectiveAt
        );
        $proposals = array_values(array_filter(
            $result['rows'],
            static fn (array $row) => $row['classification'] === 'EXACT_FILE_VERIFIED' && $row['action'] === 'SAFE_UPDATE'
        ));

        $this->table(
            ['SKU', 'Product', 'Current', 'Supplier order', 'Order date', 'Gross', 'D1', 'D2', 'Net', 'Difference', 'Evidence'],
            array_map(static fn (array $row) => [
                $row['product_id'], $row['product_name'], $row['current_price'], $row['latest_supplier_order_id'],
                $row['supplier_order_date'], $row['gross_price'], $row['discount1'], $row['discount2'],
                $row['verified_net_price'], $row['difference'], $row['classification'],
            ], $proposals)
        );
        $this->line('Proposals: '.count($proposals));

        if (! $this->option('execute')) {
            $this->warn('PREVIEW ONLY: no database rows were modified.');

            return self::SUCCESS;
        }
        if ($this->option('approval') !== 'APPLY_TASK_C') {
            $this->error('Execution blocked. Use --approval=APPLY_TASK_C only after final approval.');

            return self::INVALID;
        }
        if (($error = $this->manifestError($proposals)) !== null) {
            $this->error('Execution blocked: '.$error);

            return self::INVALID;
        }
        if (! Schema::hasTable('historical_product_price_corrections')) {
            $this->error('Execution blocked: run the Task C audit-table migration first.');

            return self::FAILURE;
        }

        $runUuid = (string) Str::uuid();
        try {
            DB::transaction(function () use ($proposals, $evidence, $runUuid): void {
                foreach ($proposals as $proposal) {
                    $product = DB::table('products')->where('product_id', $proposal['product_id'])->lockForUpdate()->first();
                    if ($product === null) {
                        throw new RuntimeException("Product {$proposal['product_id']} disappeared after preview.");
                    }
                    $current = $product->price === null ? null : (float) $product->price;
                    if (($current === null) !== ($proposal['current_price'] === null)
                        || ($current !== null && abs($current - $proposal['current_price']) >= 0.005)) {
                        throw new RuntimeException("Product {$proposal['product_id']} price changed after preview; entire run aborted.");
                    }

                    $verified = $this->revalidateLatestEvidence($proposal, $evidence);
                    $now = now();
                    DB::table('historical_product_price_corrections')->insert([
                        'run_uuid' => $runUuid,
                        'product_row_id' => $product->id,
                        'product_id' => $proposal['product_id'],
                        'source_order_id' => $proposal['latest_supplier_order_id'],
                        'source_order_date' => $proposal['supplier_order_date'],
                        'source_file' => $proposal['source_file'],
                        'source_sha256' => $proposal['source_sha256'],
                        'gross_price' => $verified['gross'],
                        'discount_1' => $verified['discount1'],
                        'discount_2' => $verified['discount2'],
                        'old_price' => $current,
                        'new_price' => $verified['net'],
                        'calculation_evidence' => json_encode([
                            'formula' => 'round_half_up(gross * (1 - d1/100) * (1 - d2/100), 2)',
                            'gross' => $verified['gross'],
                            'discount1' => $verified['discount1'],
                            'discount2' => $verified['discount2'],
                            'latest_order_validation' => 'products.product_id = order_items.sku; maximum order_date; one order ID and one price',
                        ], JSON_THROW_ON_ERROR),
                        'status' => 'APPLIED',
                        'applied_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('products')->where('id', $product->id)->update(['price' => $verified['net']]);
                }
            }, 3);
        } catch (\Throwable $exception) {
            $this->error('No changes committed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Applied audited Task C run {$runUuid}.");

        return self::SUCCESS;
    }

    /** @param list<array<string, mixed>> $proposals */
    private function manifestError(array $proposals): ?string
    {
        foreach (['expected-database', 'expected-count', 'expected-order', 'expected-products', 'expected-source-sha256'] as $option) {
            if ($this->option($option) === null || trim((string) $this->option($option)) === '') {
                return "--{$option} is required for execution.";
            }
        }
        if (DB::connection()->getDatabaseName() !== (string) $this->option('expected-database')) {
            return 'current database does not match the approved database.';
        }
        if (count($proposals) !== (int) $this->option('expected-count')) {
            return 'proposal count does not match the approved count.';
        }
        if (array_values(array_unique(array_column($proposals, 'classification'))) !== ['EXACT_FILE_VERIFIED']) {
            return 'not every proposal is EXACT_FILE_VERIFIED.';
        }
        if (array_values(array_unique(array_column($proposals, 'latest_supplier_order_id'))) !== [(string) $this->option('expected-order')]) {
            return 'supplier order scope does not match the approved order.';
        }
        $actualProducts = array_column($proposals, 'product_id');
        $expectedProducts = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('expected-products')))));
        sort($actualProducts);
        sort($expectedProducts);
        if ($actualProducts !== $expectedProducts) {
            return 'product ID scope does not match the approved products.';
        }
        if (array_values(array_unique(array_column($proposals, 'source_sha256'))) !== [(string) $this->option('expected-source-sha256')]) {
            return 'source SHA-256 does not match the approved evidence.';
        }

        return null;
    }

    /** @return array{gross: float, discount1: float, discount2: float, net: float} */
    private function revalidateLatestEvidence(array $proposal, array $evidence): array
    {
        $latestDate = DB::table('order_items')->where('sku', $proposal['product_id'])->max('order_date');
        $latest = DB::table('order_items')
            ->where('sku', $proposal['product_id'])
            ->whereDate('order_date', $latestDate)
            ->lockForUpdate()
            ->get(['order_id', 'order_id2', 'price']);
        $orderIds = $latest->map(static fn ($item) => trim((string) (($item->order_id2 ?? '') ?: $item->order_id)))->unique()->values();
        $prices = $latest->map(static fn ($item) => number_format((float) $item->price, 2, '.', ''))->unique()->values();
        if ($latestDate !== $proposal['supplier_order_date']
            || $orderIds->count() !== 1
            || $orderIds->first() !== $proposal['latest_supplier_order_id']
            || $prices->count() !== 1) {
            throw new RuntimeException("Latest supplier order changed for product {$proposal['product_id']}; entire run aborted.");
        }

        $order = $evidence['orders'][$proposal['latest_supplier_order_id']] ?? null;
        $row = $order['rows'][$proposal['product_id']] ?? null;
        if ($order === null || $row === null
            || $order['date'] !== $latestDate
            || $order['source'] !== $proposal['source_file']
            || $order['hash'] !== $proposal['source_sha256']
            || abs($row['gross'] - (float) $prices->first()) >= 0.005) {
            throw new RuntimeException("Supplier evidence changed for product {$proposal['product_id']}; entire run aborted.");
        }

        $net = round($row['gross'] * (1 - $row['discount1'] / 100) * (1 - $row['discount2'] / 100), 2, PHP_ROUND_HALF_UP);
        if (abs($net - $proposal['verified_net_price']) >= 0.005) {
            throw new RuntimeException("Calculated net price changed for product {$proposal['product_id']}; entire run aborted.");
        }

        return ['gross' => $row['gross'], 'discount1' => $row['discount1'], 'discount2' => $row['discount2'], 'net' => $net];
    }
}
