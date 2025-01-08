<?php

namespace App\Console\Commands;

use App\Models\SquareGrid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Support\Facades\Artisan;

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
    public function handle()
    {
        $this->importSquareSql("Grid_" . $this->argument('square'));
        $this->info("Import of {$this->argument('square')} square completed");
    }

    protected function importSquareSql($gridSquare): bool
    {
        $squareSize = '25x25';
        $srid = 4326;
        $awsFilePath = "eu_original/{$squareSize}/SQL/{$gridSquare}_{$squareSize}_{$srid}.sql";
        $fileContent = Storage::disk('wmmapdata')->get($awsFilePath);
        if (is_null($fileContent)) {
            throw new \Exception("File not found: {$awsFilePath}");
        }

        $localFilename = basename($awsFilePath);
        $success = Storage::put($localFilename, $fileContent);
        $localPath = Storage::path($localFilename);

        $returnState = Artisan::call('dem:import', ['file' => $localPath]);
        Storage::delete($localPath);
        return true;
    }
}
