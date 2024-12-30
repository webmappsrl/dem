<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SquareGrid extends Model
{
    use HasFactory;

    /**
     * Retrieves the name of the grid square for the specified longitude and latitude.
     *
     * @param int $lon The longitude of the grid square.
     * @param int $lat The latitude of the grid square.
     *
     * @return string|null The name of the grid square, or null if not found.
     */
    public static function getNameByLonLat(int $lon, int $lat): string
    {
        return 'Grid_' . str_replace('-', 'm', $lon) . '_' . str_replace('-', 'm', $lat);
    }

    /**
     * Creates a new grid square entry with the specified longitude and latitude.
     *
     * @param int $lon The longitude of the grid square.
     * @param int $lat The latitude of the grid square.
     *
     * @return void
     */
    public static function createEntry(int $lon, int $lat)
    {
        $name = self::getNameByLonLat($lon,$lat);
        $existingEntry = DB::table('square_grids')->where('name', $name)->first();
        if ($existingEntry) {
            Log::info("Grid square $name already exists in the database.");
            return $existingEntry;
        }
        DB::table('square_grids')->insert([
            'name' => $name,
            'geom' => DB::raw("ST_MakeEnvelope($lon, $lat, $lon + 1, $lat + 1,4326)")
        ]);

        return DB::table('square_grids')
            ->where('name', $name)
            ->first();
    }

    /**
     * Retrieves a list of grid squares within the specified bounding box.
     *
     * @param float $minLon The minimum longitude of the bounding box.
     * @param float $minLat The minimum latitude of the bounding box.
     * @param float $maxLon The maximum longitude of the bounding box.
     * @param float $maxLat The maximum latitude of the bounding box.
     *
     * @return array The list of grid squares within the bounding box.
     */
    public static function listByBbox(float $minLon, float $minLat, float $maxLon, float $maxLat)
    {
        return DB::table('square_grids')
            ->select('name')
            ->whereRaw("ST_Intersects(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))", [$minLon, $minLat, $maxLon, $maxLat])
            ->pluck('name')
            ->toArray();
    }

    /**
     * Retrieves a GeoJSON FeatureCollection of grid squares within the specified bounding box.
     *
     * @param float $minLon The minimum longitude of the bounding box.
     * @param float $minLat The minimum latitude of the bounding box.
     * @param float $maxLon The maximum longitude of the bounding box.
     * @param float $maxLat The maximum latitude of the bounding box.
     *
     * @return string The GeoJSON FeatureCollection of grid squares.
     */
    public static function geojsonByBbox(float $minLon, float $minLat, float $maxLon, float $maxLat): string
    {
        $gridSquares = DB::table('square_grids')
            ->select('name', DB::raw('ST_AsGeoJSON(geom) as geom'))
            ->whereRaw("ST_Intersects(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))", [$minLon, $minLat, $maxLon, $maxLat])
            ->get();

        $features = $gridSquares->map(function ($gridSquare) {
            return [
                'type' => 'Feature',
                'geometry' => json_decode($gridSquare->geom),
                'properties' => [
                    'name' => $gridSquare->name,
                ],
            ];
        });

        return json_encode([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * List square grids by country codes.
     *
     * This method retrieves an array of square grids filtered by the specified country codes.
     *
     * @param string $countryCodes A comma-separated string of ISO 3166-1 alpha-2 country codes.
     * @return array An array of square grids for the specified countries.
     */
    public static function listByCountry(string $countryCodes): array
    {
        $countryCodeArray = explode(',', $countryCodes);
        $gridSquares = [];

        foreach ($countryCodeArray as $countryCode) {
            $squares = DB::table('square_grids')
            ->select('name')
            ->whereRaw("ST_Intersects(geom, (SELECT geom FROM countries WHERE code = ?))", [$countryCode])
            ->pluck('name')
            ->toArray();

            $gridSquares = array_merge($gridSquares, $squares);
        }

        return array_unique($gridSquares);
    }

    /**
     * Retrieve GeoJSON data for grid squares by country codes.
     *
     * This method retrieves a GeoJSON FeatureCollection of grid squares filtered by the specified country codes.
     *
     * @param string $countryCodes A comma-separated string of ISO 3166-1 alpha-2 country codes.
     * @return string The GeoJSON FeatureCollection of grid squares for the specified countries.
     * 
     * TODO: remove duplicates
     */
    public static function geojsonByCountry(string $countryCodes): string
    {
        $countryCodeArray = explode(',', $countryCodes);
        $gridSquares = collect();

        foreach ($countryCodeArray as $countryCode) {
            $squares = DB::table('square_grids')
                ->select('name', DB::raw('ST_AsGeoJSON(geom) as geom'))
                ->whereRaw("ST_Intersects(geom, (SELECT geom FROM countries WHERE code = ?))", [$countryCode])
                ->get();

            $gridSquares = $gridSquares->merge($squares);
        }

        $features = $gridSquares->map(function ($gridSquare) {
            return [
                'type' => 'Feature',
                'geometry' => json_decode($gridSquare->geom),
                'properties' => [
                    'name' => $gridSquare->name,
                ],
            ];
        });

        return json_encode([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }


}
