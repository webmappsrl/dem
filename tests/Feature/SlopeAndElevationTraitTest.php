<?php

use PHPUnit\Framework\TestCase;
use App\Traits\SlopeAndElevationTrait; // Import the SlopeAndElevationTrait class
class SlopeAndElevationTraitTest extends TestCase
{
    public function testCalcSmoothedElevation()
    {
        $trait = new SlopeAndElevationTrait();

        // Test case 1: Empty data
        $data = [];
        $expectedResult = [];
        $this->assertEquals($expectedResult, $trait->calcSmoothedElevation($data));

        // Test case 2: Single data point
        $data = [
            (object) ['index' => 0, 'ele' => 10, 'geom' => 'point'],
        ];
        $expectedResult = [
            (object) ['index' => 0, 'ele' => 10, 'geom' => 'point', 'smoothed_ele' => 10],
        ];
        $this->assertEquals($expectedResult, $trait->calcSmoothedElevation($data));

        // Test case 3: Multiple data points
        $data = [
            (object) ['index' => 0, 'ele' => 10, 'geom' => 'point'],
            (object) ['index' => 1, 'ele' => 20, 'geom' => 'point'],
            (object) ['index' => 2, 'ele' => 30, 'geom' => 'point'],
            (object) ['index' => 3, 'ele' => 40, 'geom' => 'point'],
            (object) ['index' => 4, 'ele' => 50, 'geom' => 'point'],
        ];
        $expectedResult = [
            (object) ['index' => 0, 'ele' => 10, 'geom' => 'point', 'smoothed_ele' => 16],
            (object) ['index' => 1, 'ele' => 20, 'geom' => 'point', 'smoothed_ele' => 22],
            (object) ['index' => 2, 'ele' => 30, 'geom' => 'point', 'smoothed_ele' => 28],
            (object) ['index' => 3, 'ele' => 40, 'geom' => 'point', 'smoothed_ele' => 34],
            (object) ['index' => 4, 'ele' => 50, 'geom' => 'point', 'smoothed_ele' => 40],
        ];
        $this->assertEquals($expectedResult, $trait->calcSmoothedElevation($data, 1));
    }
}
