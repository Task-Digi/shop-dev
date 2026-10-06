<?php

namespace App\Console\Commands;

use App\Services\LatestProductPriceDryRun;
use App\Services\SupplierOrderEvidenceValidator;
use App\Services\VerifiedSupplierDiscountReader;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DryRunLatestProductPrices extends Command
{
    protected $signature = 'task-c:dry-run
        {supplier-files* : Exact supplier XLS/XLSX evidence files}
        {--task-a-effective-at=2026-10-05 15:06:12 : Verified Task A implementation effective timestamp}
        {--output= : Optional complete CSV report path}';

    protected $description = 'Read-only classification of current product prices against latest supplier evidence';

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
            DB::table('products')->orderBy('id')->get(['product_id', 'product_name', 'price']),
            DB::table('order_items')->orderBy('order_date')->orderBy('id')->get([
                'id', 'order_id', 'order_id2', 'order_date', 'sku', 'price', 'created_at',
            ]),
            $evidence,
            $effectiveAt
        );

        $summary = $result['summary'];
        $this->table(['Metric', 'Count'], [
            ['Total products', $summary['total_products']],
            ['Products with supplier history', $summary['products_with_supplier_history']],
            ['EXACT_FILE_VERIFIED', $summary['classifications']['EXACT_FILE_VERIFIED'] ?? 0],
            ['POST_TASK_A_NET', $summary['classifications']['POST_TASK_A_NET'] ?? 0],
            ['GROSS_DISCOUNT_UNKNOWN', $summary['classifications']['GROSS_DISCOUNT_UNKNOWN'] ?? 0],
            ['NO_SUPPLIER_HISTORY', $summary['classifications']['NO_SUPPLIER_HISTORY'] ?? 0],
            ['CONFLICT', $summary['classifications']['CONFLICT'] ?? 0],
            ['Safe updates required', $summary['safe_updates_required']],
            ['Already correct', $summary['already_correct']],
            ['Skipped', $summary['skipped']],
        ]);

        $changes = array_values(array_filter($result['rows'], static fn (array $row) => $row['action'] === 'SAFE_UPDATE'));
        $this->table(
            ['SKU', 'Product', 'Current', 'Supplier order', 'Order date', 'Gross', 'D1', 'D2', 'Net', 'Difference', 'Evidence'],
            array_map(static fn (array $row) => [
                $row['product_id'], $row['product_name'], $row['current_price'], $row['latest_supplier_order_id'],
                $row['supplier_order_date'], $row['gross_price'], $row['discount1'], $row['discount2'],
                $row['verified_net_price'], $row['difference'], $row['classification'],
            ], $changes)
        );

        if (($output = trim((string) $this->option('output'))) !== '') {
            $this->writeCsv($output, $result['rows']);
            $this->info("Complete Task C report written to {$output}");
        }

        $this->warn('DRY RUN ONLY: products, sale_data, order_items, and sales_lists were not modified.');

        return self::SUCCESS;
    }

    /** @param list<array<string, mixed>> $rows */
    private function writeCsv(string $path, array $rows): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new InvalidArgumentException("Output directory is not writable: {$directory}");
        }
        $stream = fopen($path, 'wb');
        if ($stream === false) {
            throw new InvalidArgumentException("Cannot open output report: {$path}");
        }
        fputcsv($stream, array_keys($rows[0] ?? []));
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        fclose($stream);
    }
}
