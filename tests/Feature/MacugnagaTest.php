<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use \Illuminate\Support\Facades\File;

class MacugnagaTest extends TestCase
{
 
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/macugnaga_25x25_data.sql')]);
    }

    /**
    * Test the Macugnaga area (Italy) and other known points with the tests/Feature/Stubs/macugnaga_25x25_data.sql DEM data,
     * and check if the elevation is correct with the API /api/v1/ele/{lat}/{lon}
     */
    public function testElevation()
    {
        $tolerance = 0.05;
        
        $points = [
            ['lng' => 7.968022, 'lat' => 45.967325, 'expected' => 1305 ], // Staffa: https://www.openstreetmap.org/node/9427910483
            ['lng' => 7.932424, 'lat' => 45.947910, 'expected' => 2754 ], // Punta Battisti: https://www.openstreetmap.org/node/7817884364
            ['lng' => 7.954499, 'lat' => 45.947575, 'expected' => 2738 ], // Pizzo Nero: https://www.openstreetmap.org/node/2445421379
            ['lng' => 7.939741, 'lat' => 45.937182, 'expected' => 3215 ] // Pizzo Bianco: https://www.openstreetmap.org/node/401234279
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
