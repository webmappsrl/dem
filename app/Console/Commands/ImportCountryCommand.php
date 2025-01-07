<?php

namespace App\Console\Commands;

use App\Models\SquareGrid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Support\Facades\Artisan;

class ImportCountryCommand extends ImportSquareCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dem:import-country
    {codes : The country code to import, can be comma separated for multiple countries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import grid squares within a specified country or set of countries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ProgressBar::setFormatDefinition('custom', ' [%bar%] %current%/%max% -- %message%');

        // Call the model method
        $gridSquares = SquareGrid::listByCountry($this->argument('codes'));

        $bar = $this->output->createProgressBar(count($gridSquares));
        $bar->setFormat('custom');
        $bar->setMessage("Starting");
        $bar->start();
        try {
            foreach ($gridSquares as $gridSquare) {
                $bar->setMessage("Handling $gridSquare");

                $status = $this->importSquareSql($gridSquare);
                if ($status === false) {
                    $this->error("No file found for $gridSquare");
                    $bar->advance();
                    continue;
                }

                $bar->advance();
            }

            $bar->finish();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error($e->getMessage());
        }
        $this->info("Import of {$this->argument('codes')} countries completed");
    }
}
