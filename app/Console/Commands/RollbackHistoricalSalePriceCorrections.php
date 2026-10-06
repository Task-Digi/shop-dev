<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RollbackHistoricalSalePriceCorrections extends Command
{
    protected $signature = 'task-b:rollback-historical-prices
        {run : Audit run UUID to roll back}
        {--approval= : Required literal ROLLBACK_TASK_B}';

    protected $description = 'Transactionally restore sale_data prices from a Task B audit run';

    public function handle(): int
    {
        if ($this->option('approval') !== 'ROLLBACK_TASK_B') {
            $this->error('Rollback blocked. Use --approval=ROLLBACK_TASK_B after reviewing the audit run.');

            return self::INVALID;
        }
        if (! Schema::hasTable('historical_sale_price_corrections')) {
            $this->error('Rollback blocked: audit table does not exist.');

            return self::FAILURE;
        }

        $run = (string) $this->argument('run');
        try {
            $restored = DB::transaction(function () use ($run): int {
                $audits = DB::table('historical_sale_price_corrections')
                    ->where('run_uuid', $run)
                    ->where('status', 'APPLIED')
                    ->lockForUpdate()
                    ->get();
                if ($audits->isEmpty()) {
                    throw new RuntimeException("No applied audit rows found for run {$run}.");
                }

                foreach ($audits as $audit) {
                    $sale = DB::table('sale_data')->where('id', $audit->sale_data_id)->lockForUpdate()->first();
                    if ($sale === null || abs((float) $sale->price - (float) $audit->new_price) >= 0.005) {
                        throw new RuntimeException("Sale {$audit->sale_data_id} changed after Task B; entire rollback aborted.");
                    }
                }

                $now = now();
                foreach ($audits as $audit) {
                    DB::table('sale_data')->where('id', $audit->sale_data_id)->update([
                        'price' => $audit->old_price,
                    ]);
                    DB::table('historical_sale_price_corrections')->where('id', $audit->id)->update([
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

        $this->info("Rolled back {$restored} sale price(s) from run {$run}.");

        return self::SUCCESS;
    }
}
