<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    public function run()
    {
        $json = file_get_contents(database_path('seeders/countries.geo.json'));
        $geojson = json_decode($json, true);
        $countries = $geojson['features'];

        foreach ($countries as $country) {

            if (Country::where('code', $country['id'])->exists()) {
                Log::info("Country already exists: " . $country['properties']['name']);
                continue;
            }

            Country::create([
                'name' => $country['properties']['name'],
                'code' => $country['id'],
                'geom' => DB::raw("ST_GeomFromGeoJSON('" . json_encode($country['geometry']) . "')")
            ]);
            Log::info("Inserted country: " . $country['properties']['name']);
        }
    }
}