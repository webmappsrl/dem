<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use \Illuminate\Support\Facades\File;

class ApiTrackMontePisano109Test extends TestCase
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
        // Carica il contenuto del file GeoJSON
        $payload = File::get(base_path('tests/Feature/Stubs/track_MontePisano_109_2D.geojson'));

        // Effettua una richiesta POST all'API con il GeoJSON come body
        $response = $this->postJson('/api/v1/track', json_decode($payload, true), [
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
        // Carica il contenuto del file GeoJSON
        $payload = File::get(base_path('tests/Feature/Stubs/track_MontePisano_109_2D.geojson'));

        // Effettua una richiesta POST all'API con il GeoJSON come body
        $response = $this->postJson('/api/v1/track', json_decode($payload, true), [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        // Verifica che la risposta contenga le chiavi 'type', 'properties' e 'geometry'
        $response->assertJsonStructure(['type']);
        $response->assertJsonStructure(['properties']);
        $response->assertJsonStructure(['geometry']);
    }

    /**
     * Test the response structure and contents.
     */
    public function testResponseStructureAndContents()
    {
        // Carica il contenuto del file GeoJSON
        $payload = File::get(base_path('tests/Feature/Stubs/track_MontePisano_109_2D.geojson'));

        // Effettua una richiesta POST all'API con il GeoJSON come body
        $response = $this->postJson('/api/v1/track', json_decode($payload, true), [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        // Verifica che la risposta contenga le chiavi 'type', 'properties' e 'geometry'
        $response->assertJsonStructure(['type']);
        $response->assertJsonStructure(['properties']);
        $response->assertJsonStructure(['geometry']);

        // Verifica che 'type' sia 'Feature'
        $response->assertJson(['type' => 'Feature']);
        
        // Verifica che 'properties' contenga i valori specificati
        $this->assertEquals(14, $response['properties']['ele_min']);
        $this->assertEquals(366, $response['properties']['ele_max']);
        $this->assertEquals(14, $response['properties']['ele_from']);
        $this->assertEquals(366, $response['properties']['ele_to']);
        $this->assertEquals(372, $response['properties']['ascent']);
        $this->assertEquals(20, $response['properties']['descent']);
        $this->assertEquals(3.2, $response['properties']['distance']);
        $this->assertEquals(120, $response['properties']['duration_forward_hiking']);
        $this->assertEquals(60, $response['properties']['duration_backward_hiking']);
        $this->assertEquals(90, $response['properties']['duration_forward_bike']);
        $this->assertEquals(30, $response['properties']['duration_backward_bike']);
        $this->assertFalse($response['properties']['round_trip']);

    }
}
