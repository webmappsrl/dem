<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SquareGrid;

/**
 * Seeder class to populate the square_grids table with 1x1 degree squares
 * covering the entire Earth. Each square is represented by a name and
 * a polygon geometry in WGS84 coordinate system (SRID 4326).
 */
class SquareGridSeeder extends Seeder
{
    public function run()
    {
        $inserts = [];
        for ($lat = -90; $lat < 90; $lat++) {
            for ($lon = -180; $lon < 180; $lon++) {
                $sg = SquareGrid::createEntry($lon, $lat);
                Log::info("Inserted square grid: " . $sg->name);
            }
        }        
        Log::info("All Earth's squares have been successfully inserted into the square_grids table.");
    }
}