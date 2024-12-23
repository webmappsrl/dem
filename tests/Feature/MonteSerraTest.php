<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use \Illuminate\Support\Facades\File;

class MonteSerraTest extends TestCase
{
 
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/monte_serra_25x25_4.sql')]);
    }

    /**
     * Test the Monte Faeta and other known points with the tests/Feature/Stubs/monte_serra_25x25_4.sql DEM data,
     * and check if the elevation is correct with the API /api/v1/ele/{lat}/{lon}
     */
    public function testElevation()
    {
        $tolerance = 0.05;

        $points = [
            ['lng' => 10.553420, 'lat' => 43.751046, 'expected' => 917 ], // Monte Serra: https://www.openstreetmap.org/node/1733141336 
            ['lng' => 10.542267, 'lat' => 43.755979, 'expected' => 902 ], // Monte Cascetto: https://www.openstreetmap.org/node/1733141275
            ['lng' => 10.548479, 'lat' => 43.754344, 'expected' => 805 ] // Colle del Prato di Calci: https://www.openstreetmap.org/node/613390725 
        ];

        foreach ($points as $point) {
            $response = $this->get("/api/v1/elevation/{$point['lng']}/{$point['lat']}");
            $response->assertStatus(200);
            $data = $response->json();
            $this->assertArrayHasKey('ele', $data);
            $this->assertGreaterThanOrEqual($point['expected'] * (1 - $tolerance), $data['ele']);
            $this->assertLessThanOrEqual($point['expected'] * (1 + $tolerance), $data['ele']);
        }
    }
}
