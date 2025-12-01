<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ApiTrackMatrixTest extends TestCase
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
     * Test that the API responds with a 200 status code.
     */
    public function testItResponds200()
    {
        $payload = $this->getTestPayload();

        $response = $this->postJson('/api/v1/feature-collection/point-matrix', $payload, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test that the API response structure is correct.
     */
    public function testResponseStructure()
    {
        $payload = $this->getTestPayload();

        $response = $this->postJson('/api/v1/feature-collection/point-matrix', $payload, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        // Verifica che la risposta sia una FeatureCollection
        $response->assertJsonStructure([
            'type',
            'features' => [
                '*' => [
                    'type',
                    'geometry',
                    'properties'
                ]
            ]
        ]);

        $data = $response->json();
        $this->assertEquals('FeatureCollection', $data['type']);

        // Verifica che ci siano features di tipo Point
        $pointFeatures = array_filter($data['features'], function ($feature) {
            return $feature['geometry']['type'] === 'Point';
        });

        $this->assertGreaterThan(0, count($pointFeatures));

        // Verifica la struttura delle properties con dem
        $firstPoint = reset($pointFeatures);
        $this->assertArrayHasKey('dem', $firstPoint['properties']);
        $this->assertArrayHasKey('matrix_row', $firstPoint['properties']['dem']);
    }

    /**
     * Test that points are correctly ordered along the track.
     */
    public function testPointsOrdering()
    {
        $payload = $this->getTestPayload();

        $response = $this->postJson('/api/v1/feature-collection/point-matrix', $payload);
        $data = $response->json();

        // Estrai solo i Point features
        $pointFeatures = array_filter($data['features'], function ($feature) {
            return $feature['geometry']['type'] === 'Point';
        });
        $pointFeatures = array_values($pointFeatures);

        $this->assertCount(4, $pointFeatures);

        // Estrai i dati dem per ordinare
        $points = array_map(function ($feature) {
            return $feature['properties']['dem'];
        }, $pointFeatures);

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
        $this->assertCount(4, $pointsOrder, "Should have 4 points in order");

        // Verifica che il primo punto in points_order sia il punto di partenza
        $firstPointId = $pointsOrder[0];
        $this->assertEquals('P0', $firstPointId, "First point should be P0");

        // Verifica che l'ultimo punto in points_order sia il punto di arrivo
        $lastPointId = $pointsOrder[count($pointsOrder) - 1];
        $this->assertEquals('P3', $lastPointId, "Last point should be P3");
    }

    /**
     * Test that elevations are calculated for all points.
     */
    public function testPointsElevation()
    {
        $payload = $this->getTestPayload();

        $response = $this->postJson('/api/v1/feature-collection/point-matrix', $payload);
        $data = $response->json();

        // Estrai solo i Point features
        $pointFeatures = array_filter($data['features'], function ($feature) {
            return $feature['geometry']['type'] === 'Point';
        });

        // Verifica che tutti i punti abbiano un'elevazione
        foreach ($pointFeatures as $feature) {
            $dem = $feature['properties']['dem'];
            $this->assertArrayHasKey('elevation', $dem);
            $this->assertIsInt($dem['elevation']);
            $this->assertGreaterThan(0, $dem['elevation']);
        }
    }

    /**
     * Test matrix structure organized by track.
     */
    public function testMatrixStructure()
    {
        $payload = $this->getTestPayload();

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

        // Verifica che ogni punto abbia matrix_row
        foreach ($points as $point) {
            $this->assertArrayHasKey('matrix_row', $point);
            $matrixRow = $point['matrix_row'];

            // Verifica che ci sia almeno un track nella matrix_row
            $this->assertNotEmpty($matrixRow);

            // Per ogni track nella matrix_row
            foreach ($matrixRow as $trackId => $trackMatrix) {
                $this->assertIsArray($trackMatrix);

                // Verifica che ogni punto abbia una entry nella matrice del track
                foreach ($points as $targetPoint) {
                    $targetId = $targetPoint['id'];

                    if ($targetId === $point['id']) {
                        // La diagonale non dovrebbe essere presente
                        $this->assertArrayNotHasKey($targetId, $trackMatrix);
                    } else {
                        // Le celle non diagonali devono avere dati
                        if (isset($trackMatrix[$targetId])) {
                            $cell = $trackMatrix[$targetId];
                            $this->assertIsArray($cell);
                            $this->assertArrayHasKey('distance', $cell);
                            $this->assertArrayHasKey('time_hiking', $cell);
                            $this->assertArrayHasKey('time_bike', $cell);
                            $this->assertArrayHasKey('ascent', $cell);
                            $this->assertArrayHasKey('descent', $cell);
                            $this->assertArrayHasKey('elevation_from', $cell);
                            $this->assertArrayHasKey('elevation_to', $cell);
                        }
                    }
                }
            }
        }
    }

    /**
     * Test that matrix values are reasonable.
     */
    public function testMatrixValues()
    {
        $payload = $this->getTestPayload();

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

        // Prendi il primo track disponibile
        $firstPoint = $points[0];
        $trackId = array_key_first($firstPoint['matrix_row']);
        $matrix = $firstPoint['matrix_row'][$trackId];

        // Test P0 -> P1 (primo segmento)
        $cell_p0_p1 = $matrix['P1'];
        $this->assertGreaterThan(0, $cell_p0_p1['distance']);
        $this->assertGreaterThan(0, $cell_p0_p1['time_hiking']);
        $this->assertGreaterThan(0, $cell_p0_p1['time_bike']);

        // Test che la distanza aumenti per punti più lontani
        // P0->P1 < P0->P2 < P0->P3
        $p0Matrix = $points[0]['matrix_row'][$trackId];
        $this->assertLessThan(
            $p0Matrix['P2']['distance'],
            $p0Matrix['P1']['distance'],
            "Distance should increase for farther points"
        );
        $this->assertLessThan(
            $p0Matrix['P3']['distance'],
            $p0Matrix['P2']['distance'],
            "Distance should increase for farther points"
        );

        // Test che le distanze siano simmetriche (stessa distanza in entrambe le direzioni)
        $p1Matrix = $points[1]['matrix_row'][$trackId];
        $this->assertEquals(
            $p0Matrix['P1']['distance'],
            $p1Matrix['P0']['distance'],
            "Distance should be the same in both directions"
        );

        // Test che ascent e descent siano invertiti nelle direzioni opposte
        $p0_p1_ascent = $p0Matrix['P1']['ascent'];
        $p0_p1_descent = $p0Matrix['P1']['descent'];
        $p1_p0_ascent = $p1Matrix['P0']['ascent'];
        $p1_p0_descent = $p1Matrix['P0']['descent'];

        // Tolleranza del 10% per arrotondamenti
        $tolerance = 0.1;
        $this->assertEqualsWithDelta(
            $p0_p1_ascent,
            $p1_p0_descent,
            $p0_p1_ascent * $tolerance,
            "Ascent in one direction should match descent in opposite direction"
        );
        $this->assertEqualsWithDelta(
            $p0_p1_descent,
            $p1_p0_ascent,
            max($p0_p1_descent * $tolerance, 1),
            "Descent in one direction should match ascent in opposite direction"
        );
    }

    /**
     * Test additivity of segments for consecutive points.
     */
    public function testSegmentAdditivity()
    {
        $payload = $this->getTestPayload();

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

        // Prendi il primo track disponibile
        $firstPoint = $points[0];
        $trackId = array_key_first($firstPoint['matrix_row']);
        $p0Matrix = $points[0]['matrix_row'][$trackId];
        $p1Matrix = $points[1]['matrix_row'][$trackId];

        // Test che P0->P2 = P0->P1 + P1->P2 (approssimativamente, per la distanza)
        $dist_p0_p2 = $p0Matrix['P2']['distance'];
        $dist_p0_p1 = $p0Matrix['P1']['distance'];
        $dist_p1_p2 = $p1Matrix['P2']['distance'];

        // Tolleranza del 5% per arrotondamenti e approssimazioni
        $tolerance = 0.05;
        $this->assertEqualsWithDelta(
            $dist_p0_p2,
            $dist_p0_p1 + $dist_p1_p2,
            $dist_p0_p2 * $tolerance,
            "Distance should be additive for consecutive segments"
        );

        // Test additività anche per ascent
        $ascent_p0_p2 = $p0Matrix['P2']['ascent'];
        $ascent_p0_p1 = $p0Matrix['P1']['ascent'];
        $ascent_p1_p2 = $p1Matrix['P2']['ascent'];

        $this->assertEqualsWithDelta(
            $ascent_p0_p2,
            $ascent_p0_p1 + $ascent_p1_p2,
            max($ascent_p0_p2 * $tolerance, 2),
            "Ascent should be additive for consecutive segments"
        );
    }

    /**
     * Test with FeatureCollection input (new format).
     */
    public function testFeatureCollectionInput()
    {
        $payload = $this->getTestPayload();

        $response = $this->postJson('/api/v1/feature-collection/point-matrix', $payload);
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals('FeatureCollection', $data['type']);
        $this->assertArrayHasKey('features', $data);
    }

    /**
     * Test with invalid input (not FeatureCollection).
     */
    public function testInvalidInputFormat()
    {
        $payload = [
            'track' => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[10.5158, 43.7889], [10.5156, 43.7886]]
                ]
            ],
            'points' => [
                ['id' => 'P0', 'name' => 'Point 0', 'lat' => 43.7889, 'lng' => 10.5158]
            ]
        ];

        $response = $this->postJson('/api/v1/feature-collection/point-matrix', $payload);
        $response->assertStatus(400);
        $response->assertJsonFragment(['error' => 'Input must be a GeoJSON FeatureCollection']);
    }

    /**
     * Helper method to get test payload with a FeatureCollection.
     */
    protected function getTestPayload()
    {
        return [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.5158256, 43.7889613]
                    ],
                    'properties' => [
                        'id' => 'P0',
                        'name' => 'Inizio Sentiero'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.5158353, 43.7878844]
                    ],
                    'properties' => [
                        'id' => 'P1',
                        'name' => 'Bivio Intermedio'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.515032, 43.782659]
                    ],
                    'properties' => [
                        'id' => 'P2',
                        'name' => 'Punto Panoramico'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [10.516022, 43.778706]
                    ],
                    'properties' => [
                        'id' => 'P3',
                        'name' => 'Fine Sentiero'
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [10.5158256, 43.7889613],
                            [10.5156075, 43.7886481],
                            [10.5155837, 43.7885842],
                            [10.51563, 43.78856],
                            [10.51576, 43.78852],
                            [10.5156411, 43.7881923],
                            [10.5157253, 43.7881353],
                            [10.515915, 43.7880872],
                            [10.5158963, 43.7879696],
                            [10.5158353, 43.7878844],
                            [10.5157631, 43.7877896],
                            [10.515636, 43.787774],
                            [10.515334, 43.78753],
                            [10.51523, 43.7874],
                            [10.514838, 43.786734],
                            [10.51455, 43.786413],
                            [10.514479, 43.786251],
                            [10.514458, 43.786083],
                            [10.514471, 43.785787],
                            [10.514533, 43.785514],
                            [10.514695, 43.785046],
                            [10.514691, 43.7847],
                            [10.514712, 43.784396],
                            [10.514742, 43.784275],
                            [10.514937, 43.783833],
                            [10.515052, 43.783505],
                            [10.515146, 43.783321],
                            [10.515149, 43.783215],
                            [10.515101, 43.782904],
                            [10.515032, 43.782659],
                            [10.514994, 43.782366],
                            [10.515027, 43.78201],
                            [10.515002, 43.781715],
                            [10.515013, 43.780893],
                            [10.515056, 43.780636],
                            [10.515148, 43.780433],
                            [10.515202, 43.780232],
                            [10.515215, 43.779855],
                            [10.515177, 43.779382],
                            [10.515193, 43.779195],
                            [10.515241, 43.779084],
                            [10.51535, 43.778979],
                            [10.51547, 43.778916],
                            [10.516022, 43.778706],
                        ]
                    ],
                    'properties' => [
                        'id' => 'track_1'
                    ]
                ]
            ]
        ];
    }
}
