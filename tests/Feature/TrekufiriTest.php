<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use \Illuminate\Support\Facades\File;

class TrekufiriTest extends TestCase
{
 
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/trekufiri_25x25.sql')]);
    }

    /**
     * Test the Trekufiri peak (Kosovo) and other known points with the tests/Feature/Stubs/trekufiri_25x25_4.sql DEM data,
     * and check if the elevation is correct with the API /api/v1/ele/{lat}/{lon}
     */
    public function testElevation()
    {
        $tolerance = 0.05;
        
        $points = [
            ['lng' => 20.079188, 'lat' => 42.555497, 'expected' => 2366 ], // Trekufiri: https://www.openstreetmap.org/node/9150949574
            ['lng' => 20.107942, 'lat' => 42.574560, 'expected' => 2502 ], // Maja e Ropes: https://www.openstreetmap.org/node/4510978497
            ['lng' => 20.106787, 'lat' => 42.558123, 'expected' =>  1855 ] // Gacaferi Guesthouse: https://www.openstreetmap.org/node/12250464783 
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
