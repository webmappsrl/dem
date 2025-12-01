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

        // Convert to FeatureCollection format
        $payload = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4843031, 43.7365189]
                    ],
                    'properties' => [
                        'id' => 'P0',
                        'name' => 'Agnano (Partenza)'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4887234, 43.7403408]
                    ],
                    'properties' => [
                        'id' => 'P1',
                        'name' => 'Bivio 1'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4910274, 43.7452919]
                    ],
                    'properties' => [
                        'id' => 'P2',
                        'name' => 'Punto Intermedio'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4945071, 43.744651]
                    ],
                    'properties' => [
                        'id' => 'P3',
                        'name' => 'Bivio 2'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.503729, 43.745965]
                    ],
                    'properties' => [
                        'id' => 'P4',
                        'name' => 'Foce di Calci (Arrivo)'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => $track['geometry'],
                    'properties' => [
                        'id' => 'track_125'
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/feature-collection/point-matrix', $payload);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals('FeatureCollection', $data['type']);
        $this->assertArrayHasKey('features', $data);

        // Estrai solo i Point features
        $pointFeatures = array_filter($data['features'], function ($feature) {
            return $feature['geometry']['type'] === 'Point';
        });
        $pointFeatures = array_values($pointFeatures);

        // Verify we have 5 points
        $this->assertCount(5, $pointFeatures);

        // Extract dem data
        $points = array_map(function ($feature) {
            return $feature['properties']['dem'];
        }, $pointFeatures);

        // Verify points are ordered
        $this->assertEquals('P0', $points[0]['id']);
        $this->assertEquals('P4', $points[4]['id']);

        // Estrai le LineString features per verificare points_order
        $lineFeatures = array_filter($data['features'], function ($feature) {
            return in_array($feature['geometry']['type'], ['LineString', 'MultiLineString']);
        });
        $lineFeatures = array_values($lineFeatures);

        $this->assertGreaterThan(0, count($lineFeatures), "Should have at least one LineString feature");

        // Verifica che la LineString feature abbia points_order
        $trackFeature = $lineFeatures[0];
        $this->assertArrayHasKey('dem', $trackFeature['properties']);
        $this->assertArrayHasKey('points_order', $trackFeature['properties']['dem']);

        $pointsOrder = $trackFeature['properties']['dem']['points_order'];
        $this->assertIsArray($pointsOrder);
        $this->assertCount(5, $pointsOrder, "Should have 5 points in order");

        // Verifica che il primo punto in points_order sia il punto di partenza
        $firstPointId = $pointsOrder[0];
        $this->assertEquals('P0', $firstPointId, "First point should be P0");

        // Verifica che l'ultimo punto in points_order sia il punto di arrivo
        $lastPointId = $pointsOrder[count($pointsOrder) - 1];
        $this->assertEquals('P4', $lastPointId, "Last point should be P4");

        // Verify matrix_row structure
        $firstPoint = $points[0];
        $this->assertArrayHasKey('matrix_row', $firstPoint);
        $this->assertArrayHasKey('track_125', $firstPoint['matrix_row']);

        $matrix = $firstPoint['matrix_row']['track_125'];
        $this->assertCount(4, $matrix); // 4 other points (P1, P2, P3, P4)

        // Test full route P0 -> P4 (Agnano to Foce di Calci)
        $fullRoute = $matrix['P4'];
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
        $p4Matrix = $points[4]['matrix_row']['track_125'];
        $reverseRoute = $p4Matrix['P0'];
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
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4843031, 43.7365189]
                    ],
                    'properties' => [
                        'id' => 'P0',
                        'name' => 'Agnano'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4887234, 43.7403408]
                    ],
                    'properties' => [
                        'id' => 'P1',
                        'name' => 'Bivio 1'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4910274, 43.7452919]
                    ],
                    'properties' => [
                        'id' => 'P2',
                        'name' => 'Intermedio'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4945071, 43.744651]
                    ],
                    'properties' => [
                        'id' => 'P3',
                        'name' => 'Bivio 2'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.503729, 43.745965]
                    ],
                    'properties' => [
                        'id' => 'P4',
                        'name' => 'Foce di Calci'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => $track['geometry'],
                    'properties' => [
                        'id' => 'track_125'
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/feature-collection/point-matrix', $payload);
        $data = $response->json();

        // Estrai solo i Point features
        $pointFeatures = array_filter($data['features'], function ($feature) {
            return $feature['geometry']['type'] === 'Point';
        });
        $pointFeatures = array_values($pointFeatures);

        $points = array_map(function ($feature) {
            return $feature['properties']['dem'];
        }, $pointFeatures);

        $p0Matrix = $points[0]['matrix_row']['track_125'];
        $p1Matrix = $points[1]['matrix_row']['track_125'];
        $p2Matrix = $points[2]['matrix_row']['track_125'];
        $p3Matrix = $points[3]['matrix_row']['track_125'];

        // Test additivity: P0->P4 should equal sum of segments
        $total_distance = $p0Matrix['P4']['distance'];
        $segment_sum = $p0Matrix['P1']['distance'] +
            $p1Matrix['P2']['distance'] +
            $p2Matrix['P3']['distance'] +
            $p3Matrix['P4']['distance'];

        $this->assertEqualsWithDelta(
            $total_distance,
            $segment_sum,
            $total_distance * 0.05,
            "Total distance should equal sum of segments"
        );

        // Test additivity for ascent
        $total_ascent = $p0Matrix['P4']['ascent'];
        $ascent_sum = $p0Matrix['P1']['ascent'] +
            $p1Matrix['P2']['ascent'] +
            $p2Matrix['P3']['ascent'] +
            $p3Matrix['P4']['ascent'];

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
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4843031, 43.7365189]
                    ],
                    'properties' => [
                        'id' => 'P0',
                        'name' => 'Agnano'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4887234, 43.7403408]
                    ],
                    'properties' => [
                        'id' => 'P1',
                        'name' => 'Bivio 1'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4910274, 43.7452919]
                    ],
                    'properties' => [
                        'id' => 'P2',
                        'name' => 'Intermedio'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.4945071, 43.744651]
                    ],
                    'properties' => [
                        'id' => 'P3',
                        'name' => 'Bivio 2'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.503729, 43.745965]
                    ],
                    'properties' => [
                        'id' => 'P4',
                        'name' => 'Foce di Calci'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => $track['geometry'],
                    'properties' => [
                        'id' => 'track_125'
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/feature-collection/point-matrix', $payload);
        $data = $response->json();

        // Estrai solo i Point features
        $pointFeatures = array_filter($data['features'], function ($feature) {
            return $feature['geometry']['type'] === 'Point';
        });
        $pointFeatures = array_values($pointFeatures);

        // Simulate generating CAI sign data for waypoint P2 (Punto Intermedio)
        $waypointFeature = $pointFeatures[2];
        $waypoint = $waypointFeature['properties']['dem'];
        $this->assertEquals('P2', $waypoint['id']);

        // Verifica che P2 non sia né il primo né l'ultimo punto in points_order
        $lineFeatures = array_filter($data['features'], function ($feature) {
            return in_array($feature['geometry']['type'], ['LineString', 'MultiLineString']);
        });
        $lineFeatures = array_values($lineFeatures);
        $trackFeature = $lineFeatures[0];
        $pointsOrder = $trackFeature['properties']['dem']['points_order'];

        $this->assertNotEquals('P2', $pointsOrder[0], "P2 should not be the start point");
        $this->assertNotEquals('P2', $pointsOrder[count($pointsOrder) - 1], "P2 should not be the end point");
        $this->assertContains('P2', $pointsOrder, "P2 should be in the points_order array");

        // From P2, we can go forward or backward
        $matrix = $waypoint['matrix_row']['track_125'];

        // Forward direction options (meta ravvicinata, intermedia, itinerario)
        $forward_close = $matrix['P3']; // Meta ravvicinata
        $forward_far = $matrix['P4'];   // Meta d'itinerario

        $this->assertNotNull($forward_close);
        $this->assertNotNull($forward_far);
        $this->assertArrayHasKey('distance', $forward_close);
        $this->assertArrayHasKey('time_hiking', $forward_close);
        $this->assertArrayHasKey('elevation_to', $forward_close);

        // Backward direction options
        $backward_close = $matrix['P1']; // Meta ravvicinata
        $backward_far = $matrix['P0'];   // Meta d'itinerario

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
