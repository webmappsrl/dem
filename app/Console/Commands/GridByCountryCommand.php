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
     {codes : The country code to search for, can be comma separated for multiple countries}  
     {--geojson= : If provided, save the output in GeoJSON format to the specified file}
     {--generate-script-file= : If provided, save the output in ./generateSQLdem.sh to create the SQL file for the webmapp dem server}';

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
        $gridSquares = SquareGrid::listByCountry($this->argument('codes'));

        // Output the result
        $this->info("Grid squares within the bounding box:");
        foreach ($gridSquares as $square) {
            $this->line($square);
        }

        // Check if the --generate-script-file option is provided
        if ($this->option('generate-script-file')) {
            $scriptFile = $this->option('generate-script-file');
            $sqlData = '';
            foreach ($gridSquares as $square) {
                // Assuming the square format is Grid_X_Y, extract X and Y
                preg_match('/Grid_([m\d]+)_([m\d]+)/', $square, $matches);
                if ($matches) {
                    $x = $matches[1];
                    $y = $matches[2];
                    // Transform 'm' to '-' in $x and $y
                    if (strpos($x, 'm') !== false) {
                        $x = '-' . str_replace('m', '', $x);
                    }
                    if (strpos($y, 'm') !== false) {
                        $y = '-' . str_replace('m', '', $y);
                    }
                    $sqlData .= "./generateSQLdem.sh {$square} 4326 {$x} {$y} " . ($x + 1) . " " . ($y + 1) . "\n";
                }
            }

            // Save the SQL data to the specified file
            file_put_contents($scriptFile, $sqlData);
            $this->info("SQL script has been saved to {$scriptFile}");
        }

        // Check if the --geojson option is provided
        if ($this->option('geojson')) {
            $geojsonFile = $this->option('geojson');
            $geojsonData = SquareGrid::geojsonByCountry($this->argument('codes'));

            // Save the GeoJSON data to the specified file
            file_put_contents($geojsonFile, $geojsonData);
            $this->info("GeoJSON data has been saved to {$geojsonFile}");
        }

        return 0;
    }
}
