<?php

namespace App\Console\Commands;

use App\Models\Pos\Sale;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixProfitOnSale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:profit {id} {date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command fixes sales that has negative profits by manually entering the Item/id and each line updating the new price - cost.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        $date = Carbon::parse($this->argument('date'));
        $start_date = $date->startOfDay()->toDateTimeString();
        $end_date = $date->endOfDay()->toDateTimeString();
        $sales = Sale::whereBetween('created_at', [$start_date, $end_date])
            ->with(['lines' => function ($query) {
                $query->with(['item' => function ($query) {
                    $query->select('id', 'cost');
                }]);
            }])
            ->where(function ($query) use ($id) {
                $query->whereRelation('lines', 'item_id', $id);
            })
            ->get();

        // Start progress bar here
        $bar = $this->output->createProgressBar(count($sales));
        $bar->start();
        foreach ($sales as $sale) {
            $profit = 0;
            foreach ($sale->lines as $line) {
                $sub_profit = $line->qty * ($line->price - $line->item->cost);
                // Update Item Line
                $line->profit = $sub_profit;
                $line->save();
                //            $this->info('Cost: ' . $line->item->cost . ' Price: ' . $line->price . ', Qty: ' . $line->qty .' Profit: ' . $sub_profit . ', Sale: ' . $sale->id . ', Line: ' . $line->id);
                $profit += $sub_profit;
            }
            $sale->profit = $profit;
            $sale->save();
            $bar->advance();
        }
        $bar->finish();
        $this->info('   Successfully updated sales profits.');

        return Command::SUCCESS;
    }
}
