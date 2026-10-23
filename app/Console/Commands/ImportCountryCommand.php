<?php

namespace App\Console\Commands;

use App\Models\SquareGrid;
use App\Services\GridImporterService;
use Symfony\Component\Console\Helper\ProgressBar;

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
    public function handle(GridImporterService $service)
    {
        ProgressBar::setFormatDefinition('custom', ' [%bar%] %current%/%max% -- %message%');

        // Call the model method
        $gridSquares = SquareGrid::listByCountry($this->argument('codes'));

        $bar = $this->output->createProgressBar(count($gridSquares));
        $bar->setFormat('custom');
        $bar->setMessage("Starting");
        $bar->start();

        foreach ($gridSquares as $gridSquare) {
            $count = $service->dispatchGridImportBatch($gridSquare);
            $bar->setMessage("$gridSquare - Dispatched $count jobs");
            $bar->advance();
        }

        $bar->finish();

        $this->info("Enqueued of {$this->argument('codes')} countries completed");
    }
}
