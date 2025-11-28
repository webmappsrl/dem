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

        $response = $this->postJson('/api/v1/track/matrix', $payload, [
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

        $response = $this->postJson('/api/v1/track/matrix', $payload, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        // Verifica che la risposta contenga le chiavi 'points' e 'matrix'
        $response->assertJsonStructure(['points', 'matrix']);

        // Verifica la struttura dei punti
        $response->assertJsonStructure([
            'points' => [
                '*' => [
                    'id',
                    'name',
                    'lat',
                    'lng',
                    'elevation',
                    'position_on_track',
                    'is_start',
                    'is_end'
                ]
            ]
        ]);

        // Verifica che la matrice sia presente
        $this->assertArrayHasKey('matrix', $response->json());
    }

    /**
     * Test that points are correctly ordered along the track.
     */
    public function testPointsOrdering()
    {
        $payload = $this->getTestPayload();

        $response = $this->postJson('/api/v1/track/matrix', $payload);
        $data = $response->json();

        // Verifica che i punti siano ordinati
        $points = $data['points'];
        $this->assertCount(4, $points);

        // Verifica che position_on_track sia crescente
        for ($i = 0; $i < count($points) - 1; $i++) {
            $this->assertLessThan(
                $points[$i + 1]['position_on_track'],
                $points[$i]['position_on_track'],
                "Points should be ordered by position_on_track"
            );
        }

        // Verifica che il primo punto abbia is_start=true
        $this->assertTrue($points[0]['is_start']);
        $this->assertFalse($points[0]['is_end']);

        // Verifica che l'ultimo punto abbia is_end=true
        $this->assertFalse($points[count($points) - 1]['is_start']);
        $this->assertTrue($points[count($points) - 1]['is_end']);

        // Verifica che i punti intermedi non siano né start né end
        for ($i = 1; $i < count($points) - 1; $i++) {
            $this->assertFalse($points[$i]['is_start']);
            $this->assertFalse($points[$i]['is_end']);
        }
    }

    /**
     * Test that elevations are calculated for all points.
     */
    public function testPointsElevation()
    {
        $payload = $this->getTestPayload();

        $response = $this->postJson('/api/v1/track/matrix', $payload);
        $data = $response->json();

        // Verifica che tutti i punti abbiano un'elevazione
        foreach ($data['points'] as $point) {
            $this->assertArrayHasKey('elevation', $point);
            $this->assertIsInt($point['elevation']);
            $this->assertGreaterThan(0, $point['elevation']);
        }
    }

    /**
     * Test matrix structure and symmetry.
     */
    public function testMatrixStructure()
    {
        $payload = $this->getTestPayload();

        $response = $this->postJson('/api/v1/track/matrix', $payload);
        $data = $response->json();

        $matrix = $data['matrix'];
        $points = $data['points'];

        // Verifica che la matrice sia completa (n x n)
        $this->assertCount(count($points), $matrix);

        foreach ($points as $point_i) {
            // Verifica che ogni punto abbia una riga nella matrice
            $this->assertArrayHasKey($point_i['id'], $matrix);
            
            // Verifica che ogni riga abbia n colonne
            $this->assertCount(count($points), $matrix[$point_i['id']]);

            foreach ($points as $point_j) {
                // Verifica che ogni cella esista
                $this->assertArrayHasKey($point_j['id'], $matrix[$point_i['id']]);

                // La diagonale deve essere null
                if ($point_i['id'] === $point_j['id']) {
                    $this->assertNull($matrix[$point_i['id']][$point_j['id']]);
                } else {
                    // Le celle non diagonali devono avere dati
                    $cell = $matrix[$point_i['id']][$point_j['id']];
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

    /**
     * Test that matrix values are reasonable.
     */
    public function testMatrixValues()
    {
        $payload = $this->getTestPayload();

        $response = $this->postJson('/api/v1/track/matrix', $payload);
        $data = $response->json();

        $matrix = $data['matrix'];
        $points = $data['points'];

        // Test P0 -> P1 (primo segmento)
        $cell_p0_p1 = $matrix['P0']['P1'];
        $this->assertGreaterThan(0, $cell_p0_p1['distance']);
        $this->assertGreaterThan(0, $cell_p0_p1['time_hiking']);
        $this->assertGreaterThan(0, $cell_p0_p1['time_bike']);

        // Test che la distanza aumenti per punti più lontani
        // P0->P1 < P0->P2 < P0->P3
        $this->assertLessThan(
            $matrix['P0']['P2']['distance'],
            $matrix['P0']['P1']['distance'],
            "Distance should increase for farther points"
        );
        $this->assertLessThan(
            $matrix['P0']['P3']['distance'],
            $matrix['P0']['P2']['distance'],
            "Distance should increase for farther points"
        );

        // Test che le distanze siano simmetriche (stessa distanza in entrambe le direzioni)
        $this->assertEquals(
            $matrix['P0']['P1']['distance'],
            $matrix['P1']['P0']['distance'],
            "Distance should be the same in both directions"
        );

        // Test che ascent e descent siano invertiti nelle direzioni opposte
        // Se P0->P1 ha ascent=X e descent=Y, allora P1->P0 dovrebbe avere ascent≈Y e descent≈X
        $p0_p1_ascent = $matrix['P0']['P1']['ascent'];
        $p0_p1_descent = $matrix['P0']['P1']['descent'];
        $p1_p0_ascent = $matrix['P1']['P0']['ascent'];
        $p1_p0_descent = $matrix['P1']['P0']['descent'];

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

        $response = $this->postJson('/api/v1/track/matrix', $payload);
        $data = $response->json();

        $matrix = $data['matrix'];

        // Test che P0->P2 = P0->P1 + P1->P2 (approssimativamente, per la distanza)
        $dist_p0_p2 = $matrix['P0']['P2']['distance'];
        $dist_p0_p1 = $matrix['P0']['P1']['distance'];
        $dist_p1_p2 = $matrix['P1']['P2']['distance'];

        // Tolleranza del 5% per arrotondamenti e approssimazioni
        $tolerance = 0.05;
        $this->assertEqualsWithDelta(
            $dist_p0_p2,
            $dist_p0_p1 + $dist_p1_p2,
            $dist_p0_p2 * $tolerance,
            "Distance should be additive for consecutive segments"
        );

        // Test additività anche per ascent
        $ascent_p0_p2 = $matrix['P0']['P2']['ascent'];
        $ascent_p0_p1 = $matrix['P0']['P1']['ascent'];
        $ascent_p1_p2 = $matrix['P1']['P2']['ascent'];

        $this->assertEqualsWithDelta(
            $ascent_p0_p2,
            $ascent_p0_p1 + $ascent_p1_p2,
            max($ascent_p0_p2 * $tolerance, 2),
            "Ascent should be additive for consecutive segments"
        );
    }

    /**
     * Test with invalid geometry type (MultiLineString).
     */
    public function testInvalidGeometryType()
    {
        $payload = [
            'track' => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'MultiLineString',
                    'coordinates' => [
                        [[10.5158, 43.7889], [10.5156, 43.7886]],
                        [[10.5155, 43.7885], [10.5153, 43.7883]]
                    ]
                ]
            ],
            'points' => [
                ['id' => 'P0', 'name' => 'Point 0', 'lat' => 43.7889, 'lng' => 10.5158]
            ]
        ];

        $response = $this->postJson('/api/v1/track/matrix', $payload);
        $response->assertStatus(400);
        $response->assertJsonFragment(['error' => 'Invalid geometry type']);
    }

    /**
     * Helper method to get test payload with a simplified track.
     */
    protected function getTestPayload()
    {
        return [
            'track' => [
                'type' => 'Feature',
                'properties' => ['id' => 1],
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
                ]
            ],
            'points' => [
                ['id' => 'P0', 'name' => 'Inizio Sentiero', 'lat' => 43.7889613, 'lng' => 10.5158256],
                ['id' => 'P1', 'name' => 'Bivio Intermedio', 'lat' => 43.7878844, 'lng' => 10.5158353],
                ['id' => 'P2', 'name' => 'Punto Panoramico', 'lat' => 43.782659, 'lng' => 10.515032],
                ['id' => 'P3', 'name' => 'Fine Sentiero', 'lat' => 43.778706, 'lng' => 10.516022],
            ]
        ];
    }
}

