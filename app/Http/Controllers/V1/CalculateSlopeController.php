<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Traits\SlopeAndElevationTrait;
use Illuminate\Http\Request;

class CalculateSlopeController extends Controller
{
    use SlopeAndElevationTrait;

    //
    public function getSlope(Request $request)
    {
        $track = Track::createTrackFromGeoJson($request->input());
        $geojson = $this->calcTrackSlope($track);
        return response()->json($geojson);
    }
}
