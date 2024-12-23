<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use \Illuminate\Support\Facades\File;

class MonteFaetaTest extends TestCase
{
 
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/monte_faeta_25x25_4.sql')]);
    }

    /**
     * Test the Monte Faeta and other known points with the tests/Feature/Stubs/monte_faeta_25.sql DEM data,
     * and check if the elevation is correct with the API /api/v1/ele/{lat}/{lon}
     */
    public function testElevation()
    {
        $tolerance = 0.05;

        $points = [
            ['lng' => 10.495607, 'lat' => 43.758098, 'expected' => 830], // Faeta https://www.openstreetmap.org/node/306949256
            ['lng' => 10.501342, 'lat' => 43.752100, 'expected' => 775], // Verruchino https://www.openstreetmap.org/node/1733141351
            ['lng' => 10.492689, 'lat' => 43.751871, 'expected' => 504], // Foce di Pennecchio: https://www.openstreetmap.org/node/613390389
            ['lng' => 10.506669, 'lat' => 43.755781, 'expected' => 612], // Campo di Croce: https://www.openstreetmap.org/node/1798411637
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
