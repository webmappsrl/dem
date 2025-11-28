<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApiTrackMatrix125Test extends TestCase
{
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('dem:delete', ['--force' => true]);
        Artisan::call('dem:import', ['file' => base_path('tests/Feature/Stubs/montepisano_25x25_4326.sql')]);
    }

    /**
     * Test with real CAI track 125: Agnano - Foce di Calci
     * This test uses a real hiking trail from the Monte Pisano area
     * to verify the matrix calculation with realistic data.
     */
    public function testRealCAITrack125()
    {
        $trackJson = File::get(base_path('tests/Feature/Stubs/track_125_Agnano_FoceCalci.geojson'));
        $track = json_decode($trackJson, true);

        // Select waypoints along the track (start, 25%, 50%, 75%, end)
        // Total: 183 points in the track
        $payload = [
            'track' => $track,
            'points' => [
                [
                    'id' => 'P0',
                    'name' => 'Agnano (Partenza)',
                    'lat' => 43.7365189,
                    'lng' => 10.4843031
                ],
                [
                    'id' => 'P1',
                    'name' => 'Bivio 1',
                    'lat' => 43.7403408,
                    'lng' => 10.4887234
                ],
                [
                    'id' => 'P2',
                    'name' => 'Punto Intermedio',
                    'lat' => 43.7452919,
                    'lng' => 10.4910274
                ],
                [
                    'id' => 'P3',
                    'name' => 'Bivio 2',
                    'lat' => 43.744651,
                    'lng' => 10.4945071
                ],
                [
                    'id' => 'P4',
                    'name' => 'Foce di Calci (Arrivo)',
                    'lat' => 43.745965,
                    'lng' => 10.503729
                ],
            ]
        ];

        $response = $this->postJson('/api/v1/track/matrix', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure(['points', 'matrix']);

        $data = $response->json();

        // Verify we have 5 points
        $this->assertCount(5, $data['points']);

        // Verify points are ordered
        $this->assertEquals('P0', $data['points'][0]['id']);
        $this->assertEquals('P4', $data['points'][4]['id']);

        // Verify start and end flags
        $this->assertTrue($data['points'][0]['is_start']);
        $this->assertFalse($data['points'][0]['is_end']);
        $this->assertFalse($data['points'][4]['is_start']);
        $this->assertTrue($data['points'][4]['is_end']);

        // Verify matrix is complete (5x5)
        $this->assertCount(5, $data['matrix']);
        foreach ($data['points'] as $point) {
            $this->assertArrayHasKey($point['id'], $data['matrix']);
            $this->assertCount(5, $data['matrix'][$point['id']]);
        }

        // Test full route P0 -> P4 (Agnano to Foce di Calci)
        $fullRoute = $data['matrix']['P0']['P4'];
        $this->assertNotNull($fullRoute);
        
        // Distance should be approximately 4km = 4000m (with some tolerance)
        $this->assertGreaterThan(3500, $fullRoute['distance']);
        $this->assertLessThan(5000, $fullRoute['distance']);

        // Elevation gain should be significant (going uphill)
        $this->assertGreaterThan(400, $fullRoute['ascent']);
        
        // Time should be reasonable for a 4km uphill hike
        $this->assertGreaterThan(60, $fullRoute['time_hiking']); // at least 60 minutes
        $this->assertLessThan(180, $fullRoute['time_hiking']); // less than 3 hours

        // Verify reverse route has less time (downhill)
        $reverseRoute = $data['matrix']['P4']['P0'];
        $this->assertLessThan($fullRoute['time_hiking'], $reverseRoute['time_hiking']);

        // Verify distance is the same in both directions
        $this->assertEquals($fullRoute['distance'], $reverseRoute['distance']);

        // Verify ascent/descent are swapped
        $this->assertEqualsWithDelta(
            $fullRoute['ascent'],
            $reverseRoute['descent'],
            $fullRoute['ascent'] * 0.1,
            "Ascent forward should equal descent backward"
        );
    }

    /**
     * Test that intermediate segments sum up to total distance
     */
    public function testCAITrack125SegmentAdditivity()
    {
        $trackJson = File::get(base_path('tests/Feature/Stubs/track_125_Agnano_FoceCalci.geojson'));
        $track = json_decode($trackJson, true);

        $payload = [
            'track' => $track,
            'points' => [
                ['id' => 'P0', 'name' => 'Agnano', 'lat' => 43.7365189, 'lng' => 10.4843031],
                ['id' => 'P1', 'name' => 'Bivio 1', 'lat' => 43.7403408, 'lng' => 10.4887234],
                ['id' => 'P2', 'name' => 'Intermedio', 'lat' => 43.7452919, 'lng' => 10.4910274],
                ['id' => 'P3', 'name' => 'Bivio 2', 'lat' => 43.744651, 'lng' => 10.4945071],
                ['id' => 'P4', 'name' => 'Foce di Calci', 'lat' => 43.745965, 'lng' => 10.503729],
            ]
        ];

        $response = $this->postJson('/api/v1/track/matrix', $payload);
        $data = $response->json();
        $matrix = $data['matrix'];

        // Test additivity: P0->P4 should equal sum of segments
        $total_distance = $matrix['P0']['P4']['distance'];
        $segment_sum = $matrix['P0']['P1']['distance'] +
                       $matrix['P1']['P2']['distance'] +
                       $matrix['P2']['P3']['distance'] +
                       $matrix['P3']['P4']['distance'];

        $this->assertEqualsWithDelta(
            $total_distance,
            $segment_sum,
            $total_distance * 0.05,
            "Total distance should equal sum of segments"
        );

        // Test additivity for ascent
        $total_ascent = $matrix['P0']['P4']['ascent'];
        $ascent_sum = $matrix['P0']['P1']['ascent'] +
                      $matrix['P1']['P2']['ascent'] +
                      $matrix['P2']['P3']['ascent'] +
                      $matrix['P3']['P4']['ascent'];

        $this->assertEqualsWithDelta(
            $total_ascent,
            $ascent_sum,
            max($total_ascent * 0.1, 5),
            "Total ascent should equal sum of segments"
        );
    }

    /**
     * Test CAI signage use case: generate data for all waypoint signs
     * This test simulates the real-world scenario of generating
     * data for CAI trail signs at each waypoint.
     */
    public function testCAISignageGeneration()
    {
        $trackJson = File::get(base_path('tests/Feature/Stubs/track_125_Agnano_FoceCalci.geojson'));
        $track = json_decode($trackJson, true);

        $payload = [
            'track' => $track,
            'points' => [
                ['id' => 'P0', 'name' => 'Agnano', 'lat' => 43.7365189, 'lng' => 10.4843031],
                ['id' => 'P1', 'name' => 'Bivio 1', 'lat' => 43.7403408, 'lng' => 10.4887234],
                ['id' => 'P2', 'name' => 'Intermedio', 'lat' => 43.7452919, 'lng' => 10.4910274],
                ['id' => 'P3', 'name' => 'Bivio 2', 'lat' => 43.744651, 'lng' => 10.4945071],
                ['id' => 'P4', 'name' => 'Foce di Calci', 'lat' => 43.745965, 'lng' => 10.503729],
            ]
        ];

        $response = $this->postJson('/api/v1/track/matrix', $payload);
        $data = $response->json();

        // Simulate generating CAI sign data for waypoint P2 (Punto Intermedio)
        $waypoint = $data['points'][2];
        $this->assertEquals('P2', $waypoint['id']);
        $this->assertFalse($waypoint['is_start']);
        $this->assertFalse($waypoint['is_end']);

        // From P2, we can go forward or backward
        $matrix = $data['matrix'];

        // Forward direction options (meta ravvicinata, intermedia, itinerario)
        $forward_close = $matrix['P2']['P3']; // Meta ravvicinata
        $forward_far = $matrix['P2']['P4'];   // Meta d'itinerario

        $this->assertNotNull($forward_close);
        $this->assertNotNull($forward_far);
        $this->assertArrayHasKey('distance', $forward_close);
        $this->assertArrayHasKey('time_hiking', $forward_close);
        $this->assertArrayHasKey('elevation_to', $forward_close);

        // Backward direction options
        $backward_close = $matrix['P2']['P1']; // Meta ravvicinata
        $backward_far = $matrix['P2']['P0'];   // Meta d'itinerario

        $this->assertNotNull($backward_close);
        $this->assertNotNull($backward_far);

        // Verify all data needed for CAI sign is present
        $this->assertArrayHasKey('elevation', $waypoint);
        $this->assertArrayHasKey('name', $waypoint);
        
        // For each direction, we have distance, time, and elevation difference
        foreach ([$forward_close, $forward_far, $backward_close, $backward_far] as $segment) {
            $this->assertArrayHasKey('distance', $segment);
            $this->assertArrayHasKey('time_hiking', $segment);
            $this->assertArrayHasKey('elevation_from', $segment);
            $this->assertArrayHasKey('elevation_to', $segment);
        }
    }
}

