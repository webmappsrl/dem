<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\track;
use App\Traits\SlopeAndElevationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     *             example={
     *                 "type": "Feature",
     *                 "properties": {
     *                     "id": 27060,
     *                     "created_at": "2022-09-07T07:47:11.000000Z",
     *                     "updated_at": "2024-01-07T22:59:43.000000Z",
     *                     "name": {
     *                         "it": "119 - Asciano - Scarpa d'Orlando",
     *                         "en": "119 - Asciano - Scarpa d'Orlando"
     *                     }
     *                 },
     *                 "geometry": {
     *                     "type": "LineString",
     *                     "coordinates": {
     *                         {10.4685531,43.7494983},
     *                         {10.4686648,43.7494112}
     *                     }
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             example={
     *                 "type": "Feature",
     *                 "properties": {
     *                     "id": 27060,
     *                     "created_at": "2022-09-07T07:47:11.000000Z",
     *                     "updated_at": "2024-01-07T22:59:43.000000Z",
     *                     "name": {
     *                         "it": "119 - Asciano - Scarpa d'Orlando",
     *                         "en": "119 - Asciano - Scarpa d'Orlando"
     *                     }
     *                 },
     *                 "geometry": {
     *                     "type": "LineString",
     *                     "coordinates": {
     *                         {10.4685531,43.7494983},
     *                         {10.4686648,43.7494112}
     *                     }
     *                 }
     *             }
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
     *             example={
     *                 "type": "Feature",
     *                 "properties": {
     *                     "id": 27060,
     *                     "created_at": "2022-09-07T07:47:11.000000Z",
     *                     "updated_at": "2024-01-07T22:59:43.000000Z",
     *                     "name": {
     *                         "it": "119 - Asciano - Scarpa d'Orlando",
     *                         "en": "119 - Asciano - Scarpa d'Orlando"
     *                     }
     *                 },
     *                 "geometry": {
     *                     "type": "LineString",
     *                     "coordinates": {
     *                         {10.4685531,43.7494983},
     *                         {10.4686648,43.7494112}
     *                     }
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             example={
     *                 "type": "Feature",
     *                 "properties": {
     *                     "id": 27060,
     *                     "created_at": "2022-09-07T07:47:11.000000Z",
     *                     "updated_at": "2024-01-07T22:59:43.000000Z",
     *                     "name": {
     *                         "it": "119 - Asciano - Scarpa d'Orlando",
     *                         "en": "119 - Asciano - Scarpa d'Orlando"
     *                     }
     *                 },
     *                 "geometry": {
     *                     "type": "LineString",
     *                     "coordinates": {
     *                         {10.4685531,43.7494983},
     *                         {10.4686648,43.7494112}
     *                     }
     *                 }
     *             }
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
}
