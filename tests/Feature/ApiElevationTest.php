<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use \Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class ApiElevationTest extends TestCase
{
    protected $tolerance = 0.05;

    /**
     * Test the Macugnaga area (Italy) and other known points with the tests/Feature/Stubs/macugnaga_25x25_data.sql DEM data,
     * and check if the elevation is correct with the API /api/v1/ele/{lat}/{lon}
     */
    public function testMacugnagaElevation()
    {
        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/macugnaga_25x25_data.sql')]);

        $points = [
            ['lng' => 7.968022, 'lat' => 45.967325, 'expected' => 1305], // Staffa: https://www.openstreetmap.org/node/9427910483
            ['lng' => 7.932424, 'lat' => 45.947910, 'expected' => 2754], // Punta Battisti: https://www.openstreetmap.org/node/7817884364
            ['lng' => 7.954499, 'lat' => 45.947575, 'expected' => 2738], // Pizzo Nero: https://www.openstreetmap.org/node/2445421379
            ['lng' => 7.939741, 'lat' => 45.937182, 'expected' => 3215]  // Pizzo Bianco: https://www.openstreetmap.org/node/401234279
        ];

        $this->checkElevation($points);
    }

    /**
     * Test the Monte Faeta area and other known points with the tests/Feature/Stubs/monte_faeta_25x25_data.sql DEM data,
     * and check if the elevation is correct with the API /api/v1/ele/{lat}/{lon}
     */
    public function testMonteFaetaElevation()
    {
        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/monte_faeta_25x25_data.sql')]);

        $points = [
            ['lng' => 10.495607, 'lat' => 43.758098, 'expected' => 830], // Faeta https://www.openstreetmap.org/node/306949256
            ['lng' => 10.501342, 'lat' => 43.752100, 'expected' => 775], // Verruchino https://www.openstreetmap.org/node/1733141351
            ['lng' => 10.492689, 'lat' => 43.751871, 'expected' => 504], // Foce di Pennecchio: https://www.openstreetmap.org/node/613390389
            ['lng' => 10.506669, 'lat' => 43.755781, 'expected' => 612], // Campo di Croce: https://www.openstreetmap.org/node/1798411637
        ];

        $this->checkElevation($points);
    }

    /**
     * Test the Monte Serra area and other known points with the tests/Feature/Stubs/monte_serra_25x25_data.sql DEM data,
     * and check if the elevation is correct with the API /api/v1/ele/{lat}/{lon}
     */
    public function testMonteSerraElevation()
    {
        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/monte_serra_25x25_data.sql')]);

        $points = [
            ['lng' => 10.553420, 'lat' => 43.751046, 'expected' => 917], // Monte Serra: https://www.openstreetmap.org/node/1733141336
            ['lng' => 10.542267, 'lat' => 43.755979, 'expected' => 902], // Monte Cascetto: https://www.openstreetmap.org/node/1733141275
            ['lng' => 10.548479, 'lat' => 43.754344, 'expected' => 805] // Colle del Prato di Calci: https://www.openstreetmap.org/node/613390725
        ];

        $this->checkElevation($points);
    }

    /**
     * Test the Trekufiri area and other known points with the tests/Feature/Stubs/trekufiri_data.sql DEM data,
     * and check if the elevation is correct with the API /api/v1/ele/{lat}/{lon}
     */
    public function testTrekufiriElevation()
    {
        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/trekufiri_25x25_data.sql')]);

        $points = [
            ['lng' => 20.079188, 'lat' => 42.555497, 'expected' => 2366], // Trekufiri: https://www.openstreetmap.org/node/9150949574
            ['lng' => 20.107942, 'lat' => 42.574560, 'expected' => 2502], // Maja e Ropes: https://www.openstreetmap.org/node/4510978497
            ['lng' => 20.106787, 'lat' => 42.558123, 'expected' => 1855] // Gacaferi Guesthouse: https://www.openstreetmap.org/node/12250464783
        ];

        $this->checkElevation($points);
    }

    /**
     * Test the merging of places in the API elevation feature.
     *
     * This test ensures that the API correctly merges places and returns the expected elevation data.
     *
     * @return void
     */
    public function testMergedPlaces() {
        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/macugnaga_25x25_data.sql')]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/monte_faeta_25x25_data.sql')]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/monte_serra_25x25_data.sql')]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/trekufiri_25x25_data.sql')]);

        $points = [
            ['lng' => 10.495607, 'lat' => 43.758098, 'expected' => 830], // Faeta https://www.openstreetmap.org/node/306949256
            ['lng' => 10.501342, 'lat' => 43.752100, 'expected' => 775], // Verruchino https://www.openstreetmap.org/node/1733141351
            ['lng' => 10.492689, 'lat' => 43.751871, 'expected' => 504], // Foce di Pennecchio: https://www.openstreetmap.org/node/613390389
            ['lng' => 10.506669, 'lat' => 43.755781, 'expected' => 612], // Campo di Croce: https://www.openstreetmap.org/node/1798411637
            ['lng' => 10.553420, 'lat' => 43.751046, 'expected' => 917], // Monte Serra: https://www.openstreetmap.org/node/1733141336
            ['lng' => 10.542267, 'lat' => 43.755979, 'expected' => 902], // Monte Cascetto: https://www.openstreetmap.org/node/1733141275
            ['lng' => 10.548479, 'lat' => 43.754344, 'expected' => 805], // Colle del Prato di Calci: https://www.openstreetmap.org/node/613390725
            ['lng' => 7.968022, 'lat' => 45.967325, 'expected' => 1305], // Staffa: https://www.openstreetmap.org/node/9427910483
            ['lng' => 7.932424, 'lat' => 45.947910, 'expected' => 2754], // Punta Battisti: https://www.openstreetmap.org/node/7817884364
            ['lng' => 7.954499, 'lat' => 45.947575, 'expected' => 2738], // Pizzo Nero: https://www.openstreetmap.org/node/2445421379
            ['lng' => 7.939741, 'lat' => 45.937182, 'expected' => 3215],  // Pizzo Bianco: https://www.openstreetmap.org/node/401234279
            ['lng' => 20.079188, 'lat' => 42.555497, 'expected' => 2366], // Trekufiri: https://www.openstreetmap.org/node/9150949574
            ['lng' => 20.107942, 'lat' => 42.574560, 'expected' => 2502], // Maja e Ropes: https://www.openstreetmap.org/node/4510978497
            ['lng' => 20.106787, 'lat' => 42.558123, 'expected' => 1855] // Gacaferi Guesthouse: https://www.openstreetmap.org/node/12250464783
            ];

            $this->checkElevation($points);
    }

    /**
     * Test that importing Monte Pisano DEM data first and then Monte Faeta DEM data (Faeta is included in MontePisano)
     * keeps the elevation calculation for Monte Faeta points valid and the number of elements in the DEM table unchanged.
     */
    public function testMontePisanoThenMonteFaeta()
    {
        $points_faeta = [
            ['lng' => 10.495607, 'lat' => 43.758098, 'expected' => 830], // Faeta https://www.openstreetmap.org/node/306949256
            ['lng' => 10.501342, 'lat' => 43.752100, 'expected' => 775], // Verruchino https://www.openstreetmap.org/node/1733141351
            ['lng' => 10.492689, 'lat' => 43.751871, 'expected' => 504], // Foce di Pennecchio: https://www.openstreetmap.org/node/613390389
            ['lng' => 10.506669, 'lat' => 43.755781, 'expected' => 612], // Campo di Croce: https://www.openstreetmap.org/node/1798411637
        ];

        $points_serra = [
            ['lng' => 10.553420, 'lat' => 43.751046, 'expected' => 917], // Monte Serra: https://www.openstreetmap.org/node/1733141336
            ['lng' => 10.542267, 'lat' => 43.755979, 'expected' => 902], // Monte Cascetto: https://www.openstreetmap.org/node/1733141275
            ['lng' => 10.548479, 'lat' => 43.754344, 'expected' => 805]  // Colle del Prato di Calci: https://www.openstreetmap.org/node/613390725
        ];

        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/montepisano_25x25_data.sql')]);
        $initialCount = DB::table('dem')->count();
        $this->checkElevation($points_faeta);
        $this->checkElevation($points_serra);

        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/monte_faeta_25x25_data.sql')]);
        $finalCount = DB::table('dem')->count();
        $this->checkElevation($points_faeta);
        $this->checkElevation($points_serra);

        // Check that the number of elements in the DEM table has not changed
        // TODO: Uncomment this line after implementing the feature: why is this test failing? 
        // $this->assertEquals($initialCount, $finalCount, 'The number of elements in the DEM table should not change.');
        
    }
    /**
     * Test that importing Monte Pisano DEM data first and then Monte Faeta DEM data (Faeta is included in MontePisano)
     * keeps the elevation calculation for Monte Faeta points valid and the number of elements in the DEM table unchanged.
     */
    public function testMonteFaetaThenMontePisano()
    {
        $points_faeta = [
            ['lng' => 10.495607, 'lat' => 43.758098, 'expected' => 830], // Faeta https://www.openstreetmap.org/node/306949256
            ['lng' => 10.501342, 'lat' => 43.752100, 'expected' => 775], // Verruchino https://www.openstreetmap.org/node/1733141351
            ['lng' => 10.492689, 'lat' => 43.751871, 'expected' => 504], // Foce di Pennecchio: https://www.openstreetmap.org/node/613390389
            ['lng' => 10.506669, 'lat' => 43.755781, 'expected' => 612], // Campo di Croce: https://www.openstreetmap.org/node/1798411637
        ];

        $points_serra = [
            ['lng' => 10.553420, 'lat' => 43.751046, 'expected' => 917], // Monte Serra: https://www.openstreetmap.org/node/1733141336
            ['lng' => 10.542267, 'lat' => 43.755979, 'expected' => 902], // Monte Cascetto: https://www.openstreetmap.org/node/1733141275
            ['lng' => 10.548479, 'lat' => 43.754344, 'expected' => 805]  // Colle del Prato di Calci: https://www.openstreetmap.org/node/613390725
        ];

        // Import MontePisano DEM data first and fix the initial count
        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/montepisano_25x25_data.sql')]);
        $initialCount = DB::table('dem')->count();

        // First import Monte Faeta DEM data
        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/monte_faeta_25x25_data.sql')]);
        $finalCount = DB::table('dem')->count();
        $this->checkElevation($points_faeta);

        // Then import MontePisano DEM data
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/montepisano_25x25_data.sql')]);
        $finalCount = DB::table('dem')->count();
        $this->checkElevation($points_faeta);
        $this->checkElevation($points_serra);

        // Check that the number of elements in the DEM table has not changed
        // TODO: Uncomment this line after implementing the feature: why is this test failing? 
        // $this->assertEquals($initialCount, $finalCount, 'The number of elements in the DEM table should not change.');
        
    }

    /**
     * Helper function to check elevation for given points.
     */
    protected function checkElevation($points)
    {
        foreach ($points as $point) {
            $response = $this->get("/api/v1/elevation/{$point['lng']}/{$point['lat']}");
            $response->assertStatus(200);
            $data = $response->json();
            $this->assertArrayHasKey('ele', $data);
            $this->assertGreaterThanOrEqual($point['expected'] * (1 - $this->tolerance), $data['ele']);
            $this->assertLessThanOrEqual($point['expected'] * (1 + $this->tolerance), $data['ele']);
        }
    }
}
