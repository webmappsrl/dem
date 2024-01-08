<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CalculatePointElevationControllerTest extends TestCase
{
    // use RefreshDatabase;
    use WithFaker;
    /**
     * @test
     * Test the getElevation api.
     *
     * @return void
     */
    public function get_elevation_api_responds_200()
    {
        Artisan::call('dem:import-monte-pisano-dem');

        // Define a longitude and latitude for the test
        $lng = 10.553454123437408;
        $lat = 43.750621703966026;

        // Make a GET request to the endpoint
        $response = $this->get("/api/v1/elevation/{$lng}/{$lat}");

        // Assert that the response status is 200
        $response->assertStatus(200);

        // Assert that the response contains the expected elevation value
        $response->assertJson(['ele' => 908]);
    }

    /**
     * @test
     * Test the getElevation method of the CalculatePointElevationController.
     *
     * @return void
     */
    public function get_elevation_api_responds_currectly()
    {
        Artisan::call('dem:import-monte-pisano-dem');

        // Define a longitude and latitude for the test
        $lng = 10.553454123437408;
        $lat = 43.750621703966026;

        // Make a GET request to the endpoint
        $response = $this->get("/api/v1/elevation/{$lng}/{$lat}");

        // Assert that the response contains the expected elevation value
        $response->assertJson(['ele' => 908]);
    }
}
