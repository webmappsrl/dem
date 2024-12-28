<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SquareGrid;

class GridByBboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 
    'dem:grid-by-bbox 
     {minLon : The minimum longitude of the bounding box} 
     {minLat : The minimum latitude of the bounding box}
     {maxLon : The maximum longitude of the bounding box}
     {maxLat : The maximum latitude of the bounding box}
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
        // Retrieve input parameters
        $minLon = (float) $this->argument('minLon');
        $minLat = (float) $this->argument('minLat');
        $maxLon = (float) $this->argument('maxLon');
        $maxLat = (float) $this->argument('maxLat');

        // Call the model method
        $gridSquares = SquareGrid::listByBbox($minLon, $minLat, $maxLon, $maxLat);

        // Output the result
        $this->info("Grid squares within the bounding box:");
        foreach ($gridSquares as $square) {
            $this->line($square);
        }

        // Check if the --geojson option is provided
        if ($this->option('geojson')) {
            $geojsonFile = $this->option('geojson');
            $geojsonData = SquareGrid::geojsonByBbox($minLon, $minLat, $maxLon, $maxLat);

            // Save the GeoJSON data to the specified file
            file_put_contents($geojsonFile, $geojsonData);
            $this->info("GeoJSON data has been saved to {$geojsonFile}");
        }

        return 0;
    }
}
