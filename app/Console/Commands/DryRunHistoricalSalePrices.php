<?php

namespace App\Console\Commands;

use App\Services\HistoricalSalePriceDryRun;
use App\Services\VerifiedSupplierDiscountReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DryRunHistoricalSalePrices extends Command
{
    protected $signature = 'task-b:dry-run
        {supplier-files* : Exact legacy supplier XLS/XLSX evidence files}
        {--output= : Optional CSV report path}';

    protected $description = 'Read-only Task B historical purchase-price dry-run; never updates database records';

    public function handle(VerifiedSupplierDiscountReader $reader, HistoricalSalePriceDryRun $dryRun): int
    {
        try {
            $evidence = $reader->read(array_values((array) $this->argument('supplier-files')));
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

        $summaryRows = [];
        foreach ($result['summary']['statuses'] as $status => $count) {
            $summaryRows[] = [$status, $count];
        }
        $summaryRows[] = ['TOTAL', $result['summary']['total_rows']];
        $summaryRows[] = ['SUPPLIER FILES', $result['summary']['supplier_files']];
        $summaryRows[] = ['SUPPLIER ORDERS', $result['summary']['supplier_orders']];
        $summaryRows[] = ['VERIFIED SKUS', $result['summary']['verified_skus']];
        $summaryRows[] = ['CORRECTABLE', $result['summary']['correctable']];
        $summaryRows[] = ['ALREADY CORRECT', $result['summary']['already_correct']];
        $summaryRows[] = ['SKIPPED', $result['summary']['skipped']];
        $summaryRows[] = ['OLD COST (quantity × unit price)', number_format($result['summary']['total_old_cost'], 2, '.', '')];
        $summaryRows[] = ['PROPOSED COST (quantity × unit price)', number_format($result['summary']['total_proposed_cost'], 2, '.', '')];
        $summaryRows[] = ['DIFFERENCE', number_format($result['summary']['total_difference'], 2, '.', '')];
        $this->table(['Metric/status', 'Value'], $summaryRows);

        if (($output = trim((string) $this->option('output'))) !== '') {
            $this->writeCsv($output, $result['rows']);
            $this->info("Dry-run report written to {$output}");
        }

        $this->warn('DRY RUN ONLY: no sale_data, order_items, or products records were modified.');

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
