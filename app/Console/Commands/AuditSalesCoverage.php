<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class AuditSalesCoverage extends Command
{
    protected $signature = 'sales:audit-coverage
        {year=2026 : Four-digit reporting year}
        {--confirm-zero=* : Confirmed zero-sales date (repeatable or comma-separated)}
        {--location= : Shop location for confirmed zero-sales dates}
        {--source= : Required evidence/source description when confirming dates}';

    protected $description = 'Audit every calendar day and record only explicitly confirmed zero-sales days';

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        if ($year < 1000 || $year > 9999) {
            $this->error('The year must contain four digits.');
            return self::INVALID;
        }

        if (! Schema::hasTable('sales_reporting_days')) {
            $this->error('Run migrations before auditing sales coverage.');
            return self::FAILURE;
        }

        try {
            $confirmedDates = $this->confirmedDates($year);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::INVALID;
        }

        $source = trim((string) $this->option('source'));
        $location = strtoupper(trim((string) $this->option('location')));
        if ($confirmedDates !== [] && $source === '') {
            $this->error('--source is required so every zero-sales confirmation is auditable.');
            return self::INVALID;
        }
        if ($confirmedDates !== [] && ! in_array($location, ['ALNABRU', 'MAJORSTUEN'], true)) {
            $this->error('--location must be ALNABRU or MAJORSTUEN when confirming dates.');
            return self::INVALID;
        }

        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $end = CarbonImmutable::create($year, 12, 31)->startOfDay();
        $actualDates = DB::table('sale_data')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('date')
            ->distinct()
            ->when($location !== '', fn ($query) => $query->where('location', $location))
            ->pluck('date')
            ->mapWithKeys(fn ($date) => [CarbonImmutable::parse($date)->toDateString() => true])
            ->all();

        $added = 0;
        foreach ($confirmedDates as $date) {
            if (isset($actualDates[$date])) {
                $this->warn("{$date}: actual sales exist; zero-sales record skipped.");
                continue;
            }

            $added += DB::table('sales_reporting_days')->insertOrIgnore([
                'date' => $date,
                'location' => $location,
                'client' => 'The shop itself',
                'sales_amount' => 0,
                'confirmation_source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $zeroDates = DB::table('sales_reporting_days')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('client', 'The shop itself')
            ->where('sales_amount', 0)
            ->when($location !== '', fn ($query) => $query->where('location', $location))
            ->distinct()
            ->pluck('date')
            ->mapWithKeys(fn ($date) => [CarbonImmutable::parse($date)->toDateString() => true])
            ->all();

        $missing = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            if (! isset($actualDates[$key]) && ! isset($zeroDates[$key])) {
                $missing[] = $key;
            }
        }

        $this->table(['Metric', 'Count'], [
            ['Total days checked', $start->diffInDays($end) + 1],
            ['Days with sales', count($actualDates)],
            ['Confirmed zero-sales days', count($zeroDates)],
            ['Missing days found', count($missing)],
            ['Zero-sales records added', $added],
        ]);
        $this->line('Dates requiring investigation: '.($missing === [] ? 'None' : implode(', ', $missing)));

        return $missing === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    private function confirmedDates(int $year): array
    {
        $values = [];
        foreach ((array) $this->option('confirm-zero') as $option) {
            array_push($values, ...array_filter(array_map('trim', explode(',', (string) $option))));
        }

        $dates = [];
        foreach (array_unique($values) as $value) {
            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
            } catch (\Throwable) {
                throw new InvalidArgumentException("Invalid confirmed date: {$value}. Use YYYY-MM-DD.");
            }
            if (! $date || $date->format('Y-m-d') !== $value || $date->year !== $year) {
                throw new InvalidArgumentException("Invalid confirmed date for {$year}: {$value}.");
            }
            if ($date->isFuture()) {
                throw new InvalidArgumentException("Future date cannot be confirmed as zero sales: {$value}.");
            }
            $dates[] = $value;
        }

        sort($dates);
        return $dates;
    }
}
