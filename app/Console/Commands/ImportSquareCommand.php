<?php

namespace App\Console\Commands;

use App\Services\GridImporterService;
use Illuminate\Console\Command;

class ImportSquareCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dem:import-square
    {square : The grid square to import in the format "lng_lat" or "mlng_mlat"}';

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
        $count = $service->dispatchGridImportBatch("Grid_" . $this->argument('square'));
        $this->info("Dispatched {$count} jobs for the import of {$this->argument('square')} square");
    }
}
