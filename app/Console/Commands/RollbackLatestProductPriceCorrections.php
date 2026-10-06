<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RollbackLatestProductPriceCorrections extends Command
{
    protected $signature = 'task-c:rollback-product-prices
        {run : Task C audit run UUID}
        {--approval= : Required literal ROLLBACK_TASK_C}';

    protected $description = 'Transactionally restore product prices from a Task C audit run';

    public function handle(): int
    {
        if ($this->option('approval') !== 'ROLLBACK_TASK_C') {
            $this->error('Rollback blocked. Use --approval=ROLLBACK_TASK_C after reviewing the audit run.');

            return self::INVALID;
        }
        if (! Schema::hasTable('historical_product_price_corrections')) {
            $this->error('Rollback blocked: Task C audit table does not exist.');

            return self::FAILURE;
        }

        $run = (string) $this->argument('run');
        try {
            $restored = DB::transaction(function () use ($run): int {
                $audits = DB::table('historical_product_price_corrections')
                    ->where('run_uuid', $run)
                    ->where('status', 'APPLIED')
                    ->lockForUpdate()
                    ->get();
                if ($audits->isEmpty()) {
                    throw new RuntimeException("No applied Task C audit rows found for run {$run}.");
                }

                foreach ($audits as $audit) {
                    $product = DB::table('products')->where('id', $audit->product_row_id)->lockForUpdate()->first();
                    if ($product === null
                        || $product->product_id !== $audit->product_id
                        || abs((float) $product->price - (float) $audit->new_price) >= 0.005) {
                        throw new RuntimeException("Product {$audit->product_id} changed after Task C; entire rollback aborted.");
                    }
                }

                $now = now();
                foreach ($audits as $audit) {
                    DB::table('products')->where('id', $audit->product_row_id)->update(['price' => $audit->old_price]);
                    DB::table('historical_product_price_corrections')->where('id', $audit->id)->update([
                        'status' => 'ROLLED_BACK',
                        'rolled_back_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                return $audits->count();
            }, 3);
        } catch (\Throwable $exception) {
            $this->error('No changes committed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Rolled back {$restored} product price(s) from Task C run {$run}.");

        return self::SUCCESS;
    }
}
