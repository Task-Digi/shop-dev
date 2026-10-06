<?php

namespace App\Console\Commands;

use App\Services\HistoricalSalePriceDryRun;
use App\Services\SupplierOrderEvidenceValidator;
use App\Services\VerifiedSupplierDiscountReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ApplyHistoricalSalePriceCorrections extends Command
{
    protected $signature = 'task-b:historical-prices
        {supplier-files* : Exact supplier XLS/XLSX evidence files}
        {--output= : Optional CSV preview path}
        {--execute : Apply the exact preview inside an audited transaction}
        {--approval= : Required literal APPLY_TASK_B when --execute is used}
        {--expected-database= : Required database name when --execute is used}
        {--expected-count= : Required correction count when --execute is used}
        {--expected-order= : Required sole supplier order when --execute is used}
        {--expected-old-cost= : Required old total cost when --execute is used}
        {--expected-new-cost= : Required new total cost when --execute is used}
        {--expected-difference= : Required total difference when --execute is used}';

    protected $description = 'Preview exact-evidence Task B corrections; execution requires an explicit approval gate';

    public function handle(
        VerifiedSupplierDiscountReader $reader,
        SupplierOrderEvidenceValidator $validator,
        HistoricalSalePriceDryRun $dryRun
    ): int {
        try {
            $evidence = $reader->read(array_values((array) $this->argument('supplier-files')));
            $validated = $validator->validate($evidence);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $sales = DB::table('sale_data')->orderBy('id')->get([
            'id', 'date', 'orderid', 'product_id', 'count', 'price',
        ]);
        $orderItems = DB::table('order_items')->orderBy('order_date')->orderBy('id')->get([
            'id', 'order_id', 'order_id2', 'order_date', 'sku', 'price',
        ]);
        $result = $dryRun->analyze($sales, $orderItems, $evidence);
        $proposals = array_values(array_filter(
            $result['rows'],
            static fn (array $row) => $row['status'] === 'EXACT_FILE_VERIFIED'
        ));

        $this->info("Validated {$validated['rows']} supplier rows across {$validated['orders']} order(s).");
        $this->table([
            'Sale ID', 'Sale date', 'Sale order', 'SKU', 'Supplier order', 'Supplier date',
            'Gross', 'D1', 'D2', 'Net', 'Qty', 'Old cost', 'New cost', 'Difference',
        ], array_map(static fn (array $row) => [
            $row['sale_data_id'], $row['sale_date'], $row['sale_order_id'], $row['sku'],
            $row['supplier_order_id'], $row['supplier_order_date'],
            number_format($row['historical_gross_price'], 2, '.', ''),
            number_format($row['discount1'], 1, '.', ''),
            number_format($row['discount2'], 1, '.', ''),
            number_format($row['proposed_price'], 2, '.', ''),
            $row['quantity'],
            number_format((float) $row['old_price'] * $row['quantity'], 2, '.', ''),
            number_format($row['proposed_price'] * $row['quantity'], 2, '.', ''),
            number_format($row['total_difference'], 2, '.', ''),
        ], $proposals));
        $this->line('Proposals: '.count($proposals));
        $this->line('Old cost: '.number_format($result['summary']['total_old_cost'], 2, '.', ''));
        $this->line('New cost: '.number_format($result['summary']['total_proposed_cost'], 2, '.', ''));
        $this->line('Difference: '.number_format($result['summary']['total_difference'], 2, '.', ''));

        if (($output = trim((string) $this->option('output'))) !== '') {
            $this->writeCsv($output, $result['rows']);
            $this->info("Complete preview written to {$output}");
        }

        if (! $this->option('execute')) {
            $this->warn('PREVIEW ONLY: no database rows were modified.');

            return self::SUCCESS;
        }
        if ($this->option('approval') !== 'APPLY_TASK_B') {
            $this->error('Execution blocked. Use --approval=APPLY_TASK_B only after the preview is approved.');

            return self::INVALID;
        }
        $manifestError = $this->executionManifestError($proposals, $result['summary']);
        if ($manifestError !== null) {
            $this->error('Execution blocked: '.$manifestError);

            return self::INVALID;
        }
        if (! Schema::hasTable('historical_sale_price_corrections')) {
            $this->error('Execution blocked: run the audit-table migration first.');

            return self::FAILURE;
        }

        $runUuid = (string) Str::uuid();
        try {
            DB::transaction(function () use ($proposals, $runUuid): void {
                foreach ($proposals as $proposal) {
                    $sale = DB::table('sale_data')->where('id', $proposal['sale_data_id'])->lockForUpdate()->first();
                    if ($sale === null) {
                        throw new RuntimeException("Sale {$proposal['sale_data_id']} disappeared after preview.");
                    }
                    $current = $sale->price === null ? null : (float) $sale->price;
                    if (($current === null) !== ($proposal['old_price'] === null)
                        || ($current !== null && abs($current - $proposal['old_price']) >= 0.005)) {
                        throw new RuntimeException("Sale {$proposal['sale_data_id']} price changed after preview; entire run aborted.");
                    }

                    $now = now();
                    DB::table('historical_sale_price_corrections')->insert([
                        'run_uuid' => $runUuid,
                        'sale_data_id' => $proposal['sale_data_id'],
                        'sku' => $proposal['sku'],
                        'source_order_id' => $proposal['supplier_order_id'],
                        'source_order_date' => $proposal['supplier_order_date'],
                        'source_file' => $proposal['supplier_source_file'],
                        'source_sha256' => $proposal['supplier_source_hash'],
                        'gross_price' => $proposal['historical_gross_price'],
                        'discount_1' => $proposal['discount1'],
                        'discount_2' => $proposal['discount2'],
                        'quantity' => $proposal['quantity'],
                        'old_price' => $proposal['old_price'],
                        'new_price' => $proposal['proposed_price'],
                        'old_cost' => round((float) $proposal['old_price'] * $proposal['quantity'], 2, PHP_ROUND_HALF_UP),
                        'new_cost' => round($proposal['proposed_price'] * $proposal['quantity'], 2, PHP_ROUND_HALF_UP),
                        'difference' => $proposal['total_difference'],
                        'calculation_evidence' => json_encode([
                            'formula' => 'round_half_up(gross * (1 - d1/100) * (1 - d2/100), 2)',
                            'gross' => $proposal['historical_gross_price'],
                            'discount1' => $proposal['discount1'],
                            'discount2' => $proposal['discount2'],
                            'matched_on' => 'sale_data.product_id = order_items.sku; latest order_date <= sale date',
                        ], JSON_THROW_ON_ERROR),
                        'status' => 'APPLIED',
                        'applied_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('sale_data')->where('id', $proposal['sale_data_id'])->update([
                        'price' => $proposal['proposed_price'],
                    ]);
                }
            }, 3);
        } catch (\Throwable $exception) {
            $this->error('No changes committed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Applied audited Task B run {$runUuid}.");

        return self::SUCCESS;
    }

    /** @param list<array<string, mixed>> $proposals */
    private function executionManifestError(array $proposals, array $summary): ?string
    {
        $required = [
            'expected-database', 'expected-count', 'expected-order',
            'expected-old-cost', 'expected-new-cost', 'expected-difference',
        ];
        foreach ($required as $option) {
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
        if (array_values(array_unique(array_column($proposals, 'status'))) !== ['EXACT_FILE_VERIFIED']) {
            return 'not every proposal is EXACT_FILE_VERIFIED.';
        }
        if (array_values(array_unique(array_column($proposals, 'supplier_order_id'))) !== [(string) $this->option('expected-order')]) {
            return 'supplier order scope does not match the approved order.';
        }

        $totals = [
            'old cost' => [$summary['total_old_cost'], $this->option('expected-old-cost')],
            'new cost' => [$summary['total_proposed_cost'], $this->option('expected-new-cost')],
            'difference' => [$summary['total_difference'], $this->option('expected-difference')],
        ];
        foreach ($totals as $label => [$actual, $expected]) {
            if (! is_numeric($expected) || abs((float) $actual - (float) $expected) >= 0.005) {
                return "{$label} does not match the approved total.";
            }
        }

        return null;
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
