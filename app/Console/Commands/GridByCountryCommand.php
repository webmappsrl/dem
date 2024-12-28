<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SquareGrid;

class GridByCountryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 
    'dem:grid-by-country
     {code : The country code to search for} 
     {--geojson= : If provided, save the output in GeoJSON format to the specified file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List grid squares within a specified bounding box';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        // Call the model method
        $gridSquares = SquareGrid::listByCountry($this->argument('code'));

        // Output the result
        $this->info("Grid squares within the bounding box:");
        foreach ($gridSquares as $square) {
            $this->line($square);
        }

        // Check if the --geojson option is provided
        if ($this->option('geojson')) {
            $geojsonFile = $this->option('geojson');
            $geojsonData = SquareGrid::geojsonByCountry($this->argument('code'));

            // Save the GeoJSON data to the specified file
            file_put_contents($geojsonFile, $geojsonData);
            $this->info("GeoJSON data has been saved to {$geojsonFile}");
        }

        return 0;
    }
}
