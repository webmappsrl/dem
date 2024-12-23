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
     *             example={
     *                 "type": "Feature",
     *                 "properties": {
     *                     "id": 1
     *                 },
     *                 "geometry": {
     *                     "type": "LineString",
     *                     "coordinates": [
     *                         [10.5158256, 43.7889613],
     *                         [10.5156075, 43.7886481],
     *                         [10.5155837, 43.7885842],
     *                         [10.51563, 43.78856],
     *                         [10.51576, 43.78852],
     *                         [10.5156411, 43.7881923],
     *                         [10.5157253, 43.7881353],
     *                         [10.515915, 43.7880872],
     *                         [10.5158963, 43.7879696],
     *                         [10.5158353, 43.7878844],
     *                         [10.5157631, 43.7877896],
     *                         [10.515636, 43.787774],
     *                         [10.515334, 43.78753],
     *                         [10.51523, 43.7874],
     *                         [10.514838, 43.786734],
     *                         [10.51455, 43.786413],
     *                         [10.514479, 43.786251],
     *                         [10.514458, 43.786083],
     *                         [10.514471, 43.785787],
     *                         [10.514533, 43.785514],
     *                         [10.514695, 43.785046],
     *                         [10.514691, 43.7847],
     *                         [10.514712, 43.784396],
     *                         [10.514742, 43.784275],
     *                         [10.514937, 43.783833],
     *                         [10.515052, 43.783505],
     *                         [10.515146, 43.783321],
     *                         [10.515149, 43.783215],
     *                         [10.515101, 43.782904],
     *                         [10.515032, 43.782659],
     *                         [10.514994, 43.782366],
     *                         [10.515027, 43.78201],
     *                         [10.515002, 43.781715],
     *                         [10.515013, 43.780893],
     *                         [10.515056, 43.780636],
     *                         [10.515148, 43.780433],
     *                         [10.515202, 43.780232],
     *                         [10.515215, 43.779855],
     *                         [10.515177, 43.779382],
     *                         [10.515193, 43.779195],
     *                         [10.515241, 43.779084],
     *                         [10.51535, 43.778979],
     *                         [10.51547, 43.778916],
     *                         [10.516022, 43.778706],
     *                         [10.516281, 43.778658],
     *                         [10.516564, 43.778652],
     *                         [10.517076, 43.778607],
     *                         [10.517721, 43.778681],
     *                         [10.518814, 43.779105],
     *                         [10.519153, 43.779312],
     *                         [10.519354, 43.779399],
     *                         [10.519625, 43.779581],
     *                         [10.520339, 43.779882],
     *                         [10.520604, 43.780014],
     *                         [10.521134, 43.780184],
     *                         [10.522348, 43.780425],
     *                         [10.523021, 43.780529],
     *                         [10.523165, 43.780579],
     *                         [10.523437, 43.780718],
     *                         [10.523511, 43.780765],
     *                         [10.523633, 43.780888],
     *                         [10.52377, 43.780958],
     *                         [10.524044, 43.781015],
     *                         [10.5242493, 43.781004],
     *                         [10.5241331, 43.7811323],
     *                         [10.5239522, 43.7812037],
     *                         [10.523632, 43.7812898],
     *                         [10.5235858, 43.7812687],
     *                         [10.52347, 43.7812437],
     *                         [10.5234139, 43.7811894],
     *                         [10.5233078, 43.78114],
     *                         [10.5231484, 43.7810631],
     *                         [10.5230319, 43.7810389],
     *                         [10.5229698, 43.7810183],
     *                         [10.52283, 43.7810164],
     *                         [10.5227136, 43.781037],
     *                         [10.5226463, 43.7810631],
     *                         [10.5226139, 43.781095],
     *                         [10.5226387, 43.7811309],
     *                         [10.5226955, 43.78119],
     *                         [10.522688, 43.7812277],
     *                         [10.5226764, 43.7812632],
     *                         [10.5226735, 43.7812817],
     *                         [10.5226091, 43.7812997],
     *                         [10.522545, 43.7812944],
     *                         [10.5224425, 43.7813288],
     *                         [10.5223419, 43.7813367],
     *                         [10.5222778, 43.781301],
     *                         [10.5222138, 43.7812892],
     *                         [10.5221387, 43.7813341],
     *                         [10.522093, 43.7813764],
     *                         [10.5220216, 43.7813605],
     *                         [10.5219466, 43.7813962],
     *                         [10.5219136, 43.7814213],
     *                         [10.5218196, 43.7813953],
     *                         [10.5217849, 43.7813565],
     *                         [10.5217805, 43.7813235],
     *                         [10.52169, 43.7812833],
     *                         [10.5216162, 43.7813298],
     *                         [10.5216337, 43.7813942],
     *                         [10.5216186, 43.7814351],
     *                         [10.5216025, 43.7814583],
     *                         [10.5215549, 43.7814979],
     *                         [10.5215183, 43.781486],
     *                         [10.5215439, 43.7814477],
     *                         [10.5215403, 43.78142],
     *                         [10.5215128, 43.7814081],
     *                         [10.5214634, 43.7814134],
     *                         [10.5213551, 43.7814934],
     *                         [10.5212749, 43.7815812],
     *                         [10.521204, 43.7815601],
     *                         [10.5211503, 43.7815048],
     *                         [10.5211227, 43.7814711],
     *                         [10.5210652, 43.7814255],
     *                         [10.5210052, 43.7814749],
     *                         [10.5209825, 43.7815537],
     *                         [10.5209215, 43.7816501],
     *                         [10.5207295, 43.7817688],
     *                         [10.5205315, 43.7817708],
     *                         [10.5204748, 43.7817714],
     *                         [10.5202529, 43.7817991],
     *                         [10.5198075, 43.7817228],
     *                         [10.5197709, 43.7816494],
     *                         [10.5199539, 43.7816054],
     *                         [10.5200385, 43.7815655],
     *                         [10.5200485, 43.7815158],
     *                         [10.5198355, 43.7814051],
     *                         [10.5197113, 43.7813341]
     *                     ]
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
     *                     "id": 1
     *                 },
     *                 "geometry": {
     *                     "type": "LineString",
     *                     "coordinates": [
     *                         [10.5158256, 43.7889613],
     *                         [10.5156075, 43.7886481],
     *                         [10.5155837, 43.7885842],
     *                         [10.51563, 43.78856],
     *                         [10.51576, 43.78852],
     *                         [10.5156411, 43.7881923],
     *                         [10.5157253, 43.7881353],
     *                         [10.515915, 43.7880872],
     *                         [10.5158963, 43.7879696],
     *                         [10.5158353, 43.7878844],
     *                         [10.5157631, 43.7877896],
     *                         [10.515636, 43.787774],
     *                         [10.515334, 43.78753],
     *                         [10.51523, 43.7874],
     *                         [10.514838, 43.786734],
     *                         [10.51455, 43.786413],
     *                         [10.514479, 43.786251],
     *                         [10.514458, 43.786083],
     *                         [10.514471, 43.785787],
     *                         [10.514533, 43.785514],
     *                         [10.514695, 43.785046],
     *                         [10.514691, 43.7847],
     *                         [10.514712, 43.784396],
     *                         [10.514742, 43.784275],
     *                         [10.514937, 43.783833],
     *                         [10.515052, 43.783505],
     *                         [10.515146, 43.783321],
     *                         [10.515149, 43.783215],
     *                         [10.515101, 43.782904],
     *                         [10.515032, 43.782659],
     *                         [10.514994, 43.782366],
     *                         [10.515027, 43.78201],
     *                         [10.515002, 43.781715],
     *                         [10.515013, 43.780893],
     *                         [10.515056, 43.780636],
     *                         [10.515148, 43.780433],
     *                         [10.515202, 43.780232],
     *                         [10.515215, 43.779855],
     *                         [10.515177, 43.779382],
     *                         [10.515193, 43.779195],
     *                         [10.515241, 43.779084],
     *                         [10.51535, 43.778979],
     *                         [10.51547, 43.778916],
     *                         [10.516022, 43.778706],
     *                         [10.516281, 43.778658],
     *                         [10.516564, 43.778652],
     *                         [10.517076, 43.778607],
     *                         [10.517721, 43.778681],
     *                         [10.518814, 43.779105],
     *                         [10.519153, 43.779312],
     *                         [10.519354, 43.779399],
     *                         [10.519625, 43.779581],
     *                         [10.520339, 43.779882],
     *                         [10.520604, 43.780014],
     *                         [10.521134, 43.780184],
     *                         [10.522348, 43.780425],
     *                         [10.523021, 43.780529],
     *                         [10.523165, 43.780579],
     *                         [10.523437, 43.780718],
     *                         [10.523511, 43.780765],
     *                         [10.523633, 43.780888],
     *                         [10.52377, 43.780958],
     *                         [10.524044, 43.781015],
     *                         [10.5242493, 43.781004],
     *                         [10.5241331, 43.7811323],
     *                         [10.5239522, 43.7812037],
     *                         [10.523632, 43.7812898],
     *                         [10.5235858, 43.7812687],
     *                         [10.52347, 43.7812437],
     *                         [10.5234139, 43.7811894],
     *                         [10.5233078, 43.78114],
     *                         [10.5231484, 43.7810631],
     *                         [10.5230319, 43.7810389],
     *                         [10.5229698, 43.7810183],
     *                         [10.52283, 43.7810164],
     *                         [10.5227136, 43.781037],
     *                         [10.5226463, 43.7810631],
     *                         [10.5226139, 43.781095],
     *                         [10.5226387, 43.7811309],
     *                         [10.5226955, 43.78119],
     *                         [10.522688, 43.7812277],
     *                         [10.5226764, 43.7812632],
     *                         [10.5226735, 43.7812817],
     *                         [10.5226091, 43.7812997],
     *                         [10.522545, 43.7812944],
     *                         [10.5224425, 43.7813288],
     *                         [10.5223419, 43.7813367],
     *                         [10.5222778, 43.781301],
     *                         [10.5222138, 43.7812892],
     *                         [10.5221387, 43.7813341],
     *                         [10.522093, 43.7813764],
     *                         [10.5220216, 43.7813605],
     *                         [10.5219466, 43.7813962],
     *                         [10.5219136, 43.7814213],
     *                         [10.5218196, 43.7813953],
     *                         [10.5217849, 43.7813565],
     *                         [10.5217805, 43.7813235],
     *                         [10.52169, 43.7812833],
     *                         [10.5216162, 43.7813298],
     *                         [10.5216337, 43.7813942],
     *                         [10.5216186, 43.7814351],
     *                         [10.5216025, 43.7814583],
     *                         [10.5215549, 43.7814979],
     *                         [10.5215183, 43.781486],
     *                         [10.5215439, 43.7814477],
     *                         [10.5215403, 43.78142],
     *                         [10.5215128, 43.7814081],
     *                         [10.5214634, 43.7814134],
     *                         [10.5213551, 43.7814934],
     *                         [10.5212749, 43.7815812],
     *                         [10.521204, 43.7815601],
     *                         [10.5211503, 43.7815048],
     *                         [10.5211227, 43.7814711],
     *                         [10.5210652, 43.7814255],
     *                         [10.5210052, 43.7814749],
     *                         [10.5209825, 43.7815537],
     *                         [10.5209215, 43.7816501],
     *                         [10.5207295, 43.7817688],
     *                         [10.5205315, 43.7817708],
     *                         [10.5204748, 43.7817714],
     *                         [10.5202529, 43.7817991],
     *                         [10.5198075, 43.7817228],
     *                         [10.5197709, 43.7816494],
     *                         [10.5199539, 43.7816054],
     *                         [10.5200385, 43.7815655],
     *                         [10.5200485, 43.7815158],
     *                         [10.5198355, 43.7814051],
     *                         [10.5197113, 43.7813341]
     *                     ]
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

        if(isset($geojson['error'])){

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