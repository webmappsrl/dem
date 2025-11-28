<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\track;
use App\Traits\SlopeAndElevationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CalculateTrackTechDataController extends Controller
{
    use SlopeAndElevationTrait;

    /**
     * @OA\Post(
     *     path="/api/v1/track",
     *     summary="Get technical data for a track",
     *     tags={"Tech Data"},
     *     @OA\RequestBody(
     *         description="GeoJSON object that needs to be added to the database",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Examples(
     *                 example="geojson_example",
     *                 summary="Example GeoJSON Feature",
     *                 value={
     *                     "type": "Feature",
     *                     "properties": {
     *                         "id": 1
     *                     },
     *                     "geometry": {
     *                         "type": "LineString",
     *                         "coordinates": {{10.5158256, 43.7889613}, {10.5156075, 43.7886481}, {10.5155837, 43.7885842}}
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Examples(
     *                 example="response_example",
     *                 summary="Example response",
     *                 value={
     *                     "type": "Feature",
     *                     "properties": {
     *                         "id": 1
     *                     },
     *                     "geometry": {
     *                         "type": "LineString",
     *                         "coordinates": {{10.5158256, 43.7889613}, {10.5156075, 43.7886481}, {10.5155837, 43.7885842}}
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *     )
     * )
     */

    public function getTechData(Request $request)
    {
        $feature = $request->input();
        $geometry = DB::select("SELECT ST_Force2D(ST_LineMerge(ST_GeomFromGeoJSON('" . json_encode($feature['geometry']) . "'))) As wkt")[0]->wkt;

        $track = Track::create([
            'source_id' => $feature['properties']['id'],
            'geometry' => $geometry
        ]);

        $geojson = $this->calcTrackTechData($track);

        $track->delete();

        if (isset($geojson['error'])) {

            return response()->json($geojson, 400);
        }

        return response()->json($geojson);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/track3d",
     *     summary="Get 3D data for a track",
     *     tags={"3D Data"},
     *     @OA\RequestBody(
     *         description="GeoJSON object that needs to be added to the database",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Examples(
     *                 example="geojson_3d_example",
     *                 summary="Example GeoJSON Feature for 3D",
     *                 value={
     *                     "type": "Feature",
     *                     "properties": {
     *                         "id": 27060,
     *                         "created_at": "2022-09-07T07:47:11.000000Z",
     *                         "updated_at": "2024-01-07T22:59:43.000000Z",
     *                         "name": {
     *                             "it": "119 - Asciano - Scarpa d'Orlando",
     *                             "en": "119 - Asciano - Scarpa d'Orlando"
     *                         }
     *                     },
     *                     "geometry": {
     *                         "type": "LineString",
     *                         "coordinates": {{10.4685531, 43.7494983}, {10.4686648, 43.7494112}}
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Examples(
     *                 example="response_3d_example",
     *                 summary="Example 3D response",
     *                 value={
     *                     "type": "Feature",
     *                     "properties": {
     *                         "id": 27060,
     *                         "created_at": "2022-09-07T07:47:11.000000Z",
     *                         "updated_at": "2024-01-07T22:59:43.000000Z",
     *                         "name": {
     *                             "it": "119 - Asciano - Scarpa d'Orlando",
     *                             "en": "119 - Asciano - Scarpa d'Orlando"
     *                         }
     *                     },
     *                     "geometry": {
     *                         "type": "LineString",
     *                         "coordinates": {{10.4685531, 43.7494983}, {10.4686648, 43.7494112}}
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *     )
     * )
     */
    public function get3DData(Request $request)
    {
        $feature = $request->input();

        $original_track_points = DB::select("SELECT (dp).path[1] AS index, (dp).geom AS geom FROM (SELECT (ST_DumpPoints(ST_GeomFromGeoJSON('" . json_encode($feature['geometry']) . "'))) as dp) as Foo");

        $geojson_coordinates = $this->calcOriginalTrackElevations($original_track_points);

        $geojson['geometry'] = [
            'type' => 'LineString',
            'coordinates' => $geojson_coordinates
        ];

        return response()->json($geojson);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/track/matrix",
     *     summary="Calculate travel time matrix for CAI signage",
     *     description="Calculate distance and travel times between all pairs of points on a track. Optimized for generating CAI hiking signage data.",
     *     tags={"Track Matrix"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Track GeoJSON and array of waypoints",
     *         @OA\JsonContent(
     *             required={"track", "points"},
     *             @OA\Examples(
     *                 example="matrix_example",
     *                 summary="Example from test",
     *                 value={
     *                     "track": {
     *                         "type": "Feature",
     *                         "properties": {
     *                             "id": 1
     *                         },
     *                         "geometry": {
     *                             "type": "LineString",
     *                             "coordinates": {
     *                                 {10.5158256, 43.7889613},
     *                                 {10.5156075, 43.7886481},
     *                                 {10.5155837, 43.7885842},
     *                                 {10.51563, 43.78856},
     *                                 {10.51576, 43.78852},
     *                                 {10.5156411, 43.7881923},
     *                                 {10.5157253, 43.7881353},
     *                                 {10.515915, 43.7880872},
     *                                 {10.5158963, 43.7879696},
     *                                 {10.5158353, 43.7878844},
     *                                 {10.5157631, 43.7877896},
     *                                 {10.515636, 43.787774},
     *                                 {10.515334, 43.78753},
     *                                 {10.51523, 43.7874},
     *                                 {10.514838, 43.786734},
     *                                 {10.51455, 43.786413},
     *                                 {10.514479, 43.786251},
     *                                 {10.514458, 43.786083},
     *                                 {10.514471, 43.785787},
     *                                 {10.514533, 43.785514},
     *                                 {10.514695, 43.785046},
     *                                 {10.514691, 43.7847},
     *                                 {10.514712, 43.784396},
     *                                 {10.514742, 43.784275},
     *                                 {10.514937, 43.783833},
     *                                 {10.515052, 43.783505},
     *                                 {10.515146, 43.783321},
     *                                 {10.515149, 43.783215},
     *                                 {10.515101, 43.782904},
     *                                 {10.515032, 43.782659},
     *                                 {10.514994, 43.782366},
     *                                 {10.515027, 43.78201},
     *                                 {10.515002, 43.781715},
     *                                 {10.515013, 43.780893},
     *                                 {10.515056, 43.780636},
     *                                 {10.515148, 43.780433},
     *                                 {10.515202, 43.780232},
     *                                 {10.515215, 43.779855},
     *                                 {10.515177, 43.779382},
     *                                 {10.515193, 43.779195},
     *                                 {10.515241, 43.779084},
     *                                 {10.51535, 43.778979},
     *                                 {10.51547, 43.778916},
     *                                 {10.516022, 43.778706}
     *                             }
     *                         }
     *                     },
     *                     "points": {
     *                         {"id": "P0", "name": "Inizio Sentiero", "lat": 43.7889613, "lng": 10.5158256},
     *                         {"id": "P1", "name": "Bivio Intermedio", "lat": 43.7878844, "lng": 10.5158353},
     *                         {"id": "P2", "name": "Punto Panoramico", "lat": 43.782659, "lng": 10.515032},
     *                         {"id": "P3", "name": "Fine Sentiero", "lat": 43.778706, "lng": 10.516022}
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Matrix calculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="points",
     *                 type="array",
     *                 description="Ordered points with calculated data",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="lat", type="number"),
     *                     @OA\Property(property="lng", type="number"),
     *                     @OA\Property(property="elevation", type="integer"),
     *                     @OA\Property(property="position_on_track", type="number"),
     *                     @OA\Property(property="is_start", type="boolean"),
     *                     @OA\Property(property="is_end", type="boolean")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="matrix",
     *                 type="object",
     *                 description="Travel time matrix between all point pairs"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *     )
     * )
     */
    public function getMatrix(Request $request)
    {
        $data = $request->input();
        $track_geojson = $data['track'];
        $points = $data['points'];

        $geometry = DB::select("SELECT ST_Force2D(ST_LineMerge(ST_GeomFromGeoJSON('" . json_encode($track_geojson['geometry']) . "'))) As wkt")[0]->wkt;

        $track = Track::create([
            'source_id' => 0,
            'geometry' => $geometry
        ]);

        $result = $this->calcTrackMatrix($track, $points);

        $track->delete();

        if (isset($result['error'])) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }
}
