<?php

namespace App\Console\Commands;

use App\Services\Bi\DailyAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BiAggregateDaily extends Command
{
    protected $signature = 'bi:aggregate-daily
                            {--date= : Aggregate a single date (Y-m-d)}
                            {--from= : Range start date (Y-m-d), requires --to}
                            {--to= : Range end date (Y-m-d), requires --from}
                            {--user= : Limit to a specific tenant user ID}
                            {--window=3 : Trailing days to re-aggregate on the default run}
                            {--backfill : Aggregate from the earliest sale through yesterday}';

    protected $description = 'Rebuild the daily BI summary tables (store, item, customer metrics)';

    public function handle(DailyAggregationService $aggregationService): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $today = Carbon::today(config('app.timezone'));

        if ($this->option('backfill')) {
            return $this->backfill($aggregationService, $userId, $today);
        }

        [$from, $to] = $this->resolveRange($today);

        if ($from === null || $to === null) {
            return self::FAILURE;
        }

        if ($from->gt($to)) {
            $this->error('--from must be on or before --to.');

            return self::FAILURE;
        }

        if ($to->gt($today)) {
            $this->error('Cannot aggregate future dates.');

            return self::FAILURE;
        }

        $rows = $aggregationService->aggregateRange($from, $to, $userId);
        $this->info("Aggregated {$from->toDateString()} to {$to->toDateString()}: {$rows} summary rows written.");

        return self::SUCCESS;
    }

    /**
     * Resolution order: --from/--to → --date → default trailing window
     * (today - window … yesterday). Returns [null, null] on bad input.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function resolveRange(Carbon $today): array
    {
        if ($this->option('from') || $this->option('to')) {
            if (! $this->option('from') || ! $this->option('to')) {
                $this->error('--from and --to must be used together.');

                return [null, null];
            }

            return [
                $this->parseDate($this->option('from'), '--from'),
                $this->parseDate($this->option('to'), '--to'),
            ];
        }

        if ($this->option('date')) {
            $date = $this->parseDate($this->option('date'), '--date');

            return [$date, $date];
        }

        $window = max(1, (int) $this->option('window'));

        return [$today->copy()->subDays($window), $today->copy()->subDay()];
    }

    protected function parseDate(string $value, string $option): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            $this->error("Invalid {$option} date \"{$value}\" — expected Y-m-d.");

            return null;
        }
    }

    /**
     * Aggregate from the earliest sale through yesterday in one-month
     * chunks so a multi-year history is rebuilt in bounded queries.
     */
    protected function backfill(DailyAggregationService $aggregationService, ?int $userId, Carbon $today): int
    {
        $earliest = $aggregationService->earliestSaleDate($userId);

        if ($earliest === null) {
            $this->info('No sales found — nothing to backfill.');

            return self::SUCCESS;
        }

        $end = $today->copy()->subDay();

        if ($earliest->gt($end)) {
            $this->info('No completed days to backfill yet.');

            return self::SUCCESS;
        }

        $chunks = [];
        $cursor = $earliest->copy();

        while ($cursor->lte($end)) {
            $chunkEnd = $cursor->copy()->addMonth()->subDay()->min($end);
            $chunks[] = [$cursor->copy(), $chunkEnd];
            $cursor = $chunkEnd->copy()->addDay();
        }

        $this->info("Backfilling {$earliest->toDateString()} to {$end->toDateString()} in ".count($chunks).' chunk(s)...');

        $totalRows = 0;
        $bar = $this->output->createProgressBar(count($chunks));
        $bar->start();

        foreach ($chunks as [$chunkFrom, $chunkTo]) {
            $totalRows += $aggregationService->aggregateRange($chunkFrom, $chunkTo, $userId);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Backfill complete: {$totalRows} summary rows written.");

        return self::SUCCESS;
    }
}
