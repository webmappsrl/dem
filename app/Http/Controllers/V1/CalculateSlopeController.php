<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Traits\SlopeAndElevationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalculateSlopeController extends Controller
{
    use SlopeAndElevationTrait;

    //
    public function getSlope(Request $request)
    {
        $feature = $request->input();
        $geometry = DB::select("SELECT ST_Force2D(ST_LineMerge(ST_GeomFromGeoJSON('" . json_encode($feature['geometry']) . "'))) As wkt")[0]->wkt;

        $track = Track::create([
            'source_id' => $feature['properties']['id'],
            'geometry' => $geometry,
            // 'tags' => json_encode($feature['properties']['tags']),
            // 'distance' => $feature['properties']['distance'],
            // 'ele_min' => $feature['properties']['ele_min'],
            // 'ele_max' => $feature['properties']['ele_max'],
            // 'ele_from' => $feature['properties']['ele_from'],
            // 'ele_to' => $feature['properties']['ele_to'],
            // 'ascent' => $feature['properties']['ascent'],
            // 'descent' => $feature['properties']['descent'],
            // 'duration_forward_hiking' => $feature['properties']['duration_forward_hiking'],
            // 'duration_backward_hiking' => $feature['properties']['duration_backward_hiking'],
            // 'round_trip' => $feature['properties']['round_trip'],
        ]);

        $geojson = $this->calcTrackTechData($track);

        return response()->json($geojson);
    }
}
