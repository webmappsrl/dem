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
     *     path="/api/v1/feature-collection/point-matrix",
     *     summary="Calculate distance and time matrix for Point features in a FeatureCollection",
     *     description="<p>Processes a GeoJSON FeatureCollection and calculates a distance/time matrix for each Point feature.</p><p><strong>Input:</strong> The API accepts a FeatureCollection containing Point features and LineString/MultiLineString features. MultiLineString features are automatically converted to LineString for processing.</p><p><strong>Processing:</strong> For each Point feature, it calculates distances, travel times, elevation changes, and other metrics to all other Point features along each LineString/MultiLineString feature.</p><p><strong>3D Coordinates:</strong> <em>All geometries in the response are enriched with 3D coordinates (Z elevation) calculated from the DEM.</em> Point features have coordinates <code>[lng, lat, elevation]</code>. LineString/MultiLineString features have all their coordinate points enriched with elevation <code>[lng, lat, elevation]</code>.</p><p><strong>Output:</strong> The response is a FeatureCollection where:</p><p><strong>For Point features:</strong> The API adds a <code>properties.dem</code> object containing:<ul><li><strong>id</strong> - Point identifier (copied from input)</li><li><strong>name</strong> - Point name (from input properties.name or properties.tooltip)</li><li><strong>elevation</strong> - Elevation in meters above sea level (calculated from DEM)</li><li><strong>matrix_row</strong> - Distance/time matrix organized by track ID, where each track contains matrix data for all target Point IDs with the following attributes:<br/>• <strong>distance</strong> - Distance along the LineString from this Point to the target Point, in meters<br/>• <strong>time_hiking</strong> - Estimated hiking time from this Point to the target Point, in seconds<br/>• <strong>time_bike</strong> - Estimated biking time from this Point to the target Point, in seconds<br/>• <strong>ascent</strong> - Total elevation gain (positive elevation change) from this Point to the target Point, in meters<br/>• <strong>descent</strong> - Total elevation loss (negative elevation change) from this Point to the target Point, in meters<br/>• <strong>elevation_from</strong> - Elevation at the source Point (this Point) in meters above sea level<br/>• <strong>elevation_to</strong> - Elevation at the target Point in meters above sea level</li></ul></p><p><strong>For LineString/MultiLineString features:</strong> The API adds a <code>properties.dem</code> object containing:<ul><li><strong>id</strong> - Track identifier (copied from input)</li><li><strong>points_order</strong> - Array of Point feature IDs ordered by their position along the track, from start to end</li></ul></p>",
     *     tags={"Point Matrix"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="GeoJSON FeatureCollection. Must contain at least one Point feature and one LineString or MultiLineString feature.",
     *         @OA\JsonContent(
     *             required={"type", "features"},
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="FeatureCollection",
     *                 description="Must be 'FeatureCollection'"
     *             ),
     *             @OA\Property(
     *                 property="features",
     *                 type="array",
     *                 description="Array of GeoJSON Feature objects. Each feature must have 'type': 'Feature', a 'geometry' object, and a 'properties' object.",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="type",
     *                         type="string",
     *                         example="Feature",
     *                         description="Must be 'Feature'"
     *                     ),
     *                     @OA\Property(
     *                         property="geometry",
     *                         type="object",
     *                         description="GeoJSON geometry object. For Point features: type 'Point' with coordinates [lng, lat]. For LineString features: type 'LineString' with array of coordinate pairs. For MultiLineString features: type 'MultiLineString' with array of LineString coordinate arrays."
     *                     ),
     *                     @OA\Property(
     *                         property="properties",
     *                         type="object",
     *                         required={"id"},
     *                         description="Feature properties. Must contain 'id' (required, string or number) for all features. For Point features: 'id' is used as identifier in the matrix_row. For LineString/MultiLineString features: 'id' is used as key in the matrix_row structure to identify which track the matrix data refers to."
     *                     )
     *                 )
     *             ),
     *             @OA\Examples(
     *                 example="feature_collection_example",
     *                 summary="Example FeatureCollection",
     *                 value={
     *                     "type": "FeatureCollection",
     *                     "features": {
     *                         {
     *                             "type": "Feature",
     *                             "geometry": {
     *                                 "type": "Point",
     *                                 "coordinates": {8.7907791, 46.0213254}
     *                             },
     *                             "properties": {
     *                                 "id": 4558
     *                             }
     *                         },
     *                         {
     *                             "type": "Feature",
     *                             "geometry": {
     *                                 "type": "Point",
     *                                 "coordinates": {8.789595, 46.0262182}
     *                             },
     *                             "properties": {
     *                                 "id": 4697
     *                             }
     *                         },
     *                         {
     *                             "type": "Feature",
     *                             "geometry": {
     *                                 "type": "MultiLineString",
     *                                 "coordinates": {
     *                                     {{8.790768804, 46.02130766}, {8.790814234, 46.02136516}, {8.791116, 46.0268621}}
     *                                 }
     *                             },
     *                             "properties": {
     *                                 "id": 848
     *                             }
     *                         }
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Returns a GeoJSON FeatureCollection with enriched Point features",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="FeatureCollection",
     *                 description="GeoJSON FeatureCollection type"
     *             ),
     *             @OA\Property(
     *                 property="features",
     *                 type="array",
     *                 description="<p>Array of GeoJSON Feature objects.</p><p>Contains:</p><ul><li>All <strong>Point features</strong> from input (enriched with matrix data in <code>properties.dem</code>)</li><li>All <strong>LineString/MultiLineString features</strong> from input (enriched with points ordering in <code>properties.dem</code>)</li></ul>",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="type",
     *                         type="string",
     *                         example="Feature",
     *                         description="GeoJSON Feature type"
     *                     ),
     *                     @OA\Property(
     *                         property="geometry",
     *                         type="object",
     *                         description="<p>GeoJSON geometry enriched with <strong>3D coordinates</strong>.</p><ul><li><strong>Point features:</strong> Coordinates are <code>[lng, lat, elevation]</code> where elevation is calculated from DEM</li><li><strong>LineString/MultiLineString features:</strong> All coordinate points are enriched with elevation <code>[lng, lat, elevation]</code></li></ul><p><em>Note:</em> If input coordinates already have a Z value of 0, it will be recalculated from DEM. Original 2D coordinates <code>[lng, lat]</code> are automatically converted to 3D <code>[lng, lat, elevation]</code>.</p>"
     *                     ),
     *                     @OA\Property(
     *                         property="properties",
     *                         type="object",
     *                         description="<p>Feature properties.</p><p><strong>IMPORTANT:</strong> All features (Point, LineString, MultiLineString) receive a <code>dem</code> object in their properties. This object contains different data depending on the feature type.</p><p>Original properties from input are preserved and merged with the <code>dem</code> object.</p>",
     *                         @OA\Property(
     *                             property="dem",
     *                             type="object",
     *                             description="<p><strong>DEM (Digital Elevation Model) calculated data.</strong></p><p>This object is <em>automatically added to ALL features</em> in the response. The structure differs based on feature type:</p><ul><li><strong>Point features:</strong> Contain matrix calculation data</li><li><strong>LineString/MultiLineString features:</strong> Contain points ordering information</li></ul>",
     *                             oneOf={
     *                                 @OA\Schema(
     *                                     type="object",
     *                                     description="<h4>What the API adds to Point features</h4><p>For each Point feature, the API automatically adds a <code>properties.dem</code> object with the following attributes:</p><ul><li><strong>id</strong> - Point identifier (copied from input properties.id)</li><li><strong>name</strong> - Point name (from input properties.name or properties.tooltip)</li><li><strong>elevation</strong> - Elevation in meters above sea level (calculated from DEM)</li><li><strong>matrix_row</strong> - Distance/time matrix organized by track ID, where each track contains matrix data for all target Point IDs with the following attributes:<br/>• <strong>distance</strong> - Distance along the LineString from this Point to the target Point, in meters<br/>• <strong>time_hiking</strong> - Estimated hiking time from this Point to the target Point, in seconds<br/>• <strong>time_bike</strong> - Estimated biking time from this Point to the target Point, in seconds<br/>• <strong>ascent</strong> - Total elevation gain (positive elevation change) from this Point to the target Point, in meters<br/>• <strong>descent</strong> - Total elevation loss (negative elevation change) from this Point to the target Point, in meters<br/>• <strong>elevation_from</strong> - Elevation at the source Point (this Point) in meters above sea level<br/>• <strong>elevation_to</strong> - Elevation at the target Point in meters above sea level</li></ul>",
     *                                     @OA\Property(property="id", type="string", description="Point identifier from properties.id (required in input)"),
     *                                     @OA\Property(property="name", type="string", description="Point name from properties.name or properties.tooltip"),
     *                                     @OA\Property(property="elevation", type="integer", description="Point elevation in meters above sea level"),
     *                                     @OA\Property(
     *                                         property="matrix_row",
     *                                         type="object",
     *                                         description="<p><strong>Distance and time matrix</strong></p><p>Organized by track (LineString/MultiLineString feature ID).</p><p>Structure:</p><ul><li>First level keys: <strong>track IDs</strong> (from LineString/MultiLineString <code>properties.id</code>)</li><li>Second level keys: <strong>target Point feature IDs</strong></li><li>Values: Matrix data objects containing the following attributes:<ul><li><strong>distance</strong> - Distance along the LineString from this Point to the target Point, in meters. This is the actual path distance along the track, not straight-line distance.</li><li><strong>time_hiking</strong> - Estimated hiking time from this Point to the target Point, in seconds. Calculated based on distance and elevation gain, rounded to nearest 15 minutes.</li><li><strong>time_bike</strong> - Estimated biking time from this Point to the target Point, in seconds. Calculated based on distance and elevation gain, rounded to nearest 15 minutes.</li><li><strong>ascent</strong> - Total elevation gain (positive elevation change) from this Point to the target Point, in meters. Sum of all uphill segments along the path.</li><li><strong>descent</strong> - Total elevation loss (negative elevation change) from this Point to the target Point, in meters. Sum of all downhill segments along the path.</li><li><strong>elevation_from</strong> - Elevation at the source Point (this Point) in meters above sea level. Same value as <code>properties.dem.elevation</code>.</li><li><strong>elevation_to</strong> - Elevation at the target Point in meters above sea level.</li></ul></li></ul><p><em>This structure allows you to query distances/times from this Point to any other Point, organized by which track the calculation was performed on.</em></p>",
     *                                         additionalProperties={
     *                                             "type": "object",
     *                                             "description": "Matrix data for this track. Keys are target Point feature IDs, values are matrix data objects.",
     *                                             "additionalProperties": {
     *                                                 "type": "object",
     *                                                 "properties": {
     *                                                     "distance": {"type": "number", "description": "Distance along the LineString in meters"},
     *                                                     "time_hiking": {"type": "integer", "description": "Estimated hiking time in seconds"},
     *                                                     "time_bike": {"type": "integer", "description": "Estimated biking time in seconds"},
     *                                                     "ascent": {"type": "integer", "description": "Total elevation gain in meters"},
     *                                                     "descent": {"type": "integer", "description": "Total elevation loss in meters"},
     *                                                     "elevation_from": {"type": "integer", "description": "Elevation at source Point in meters"},
     *                                                     "elevation_to": {"type": "integer", "description": "Elevation at target Point in meters"}
     *                                                 }
     *                                             }
     *                                         }
     *                                     )
     *                                 ),
     *                                 @OA\Schema(
     *                                     type="object",
     *                                     description="DEM data structure for LineString/MultiLineString features. This object is automatically added to properties.dem of every LineString/MultiLineString feature in the response.",
     *                                     @OA\Property(
                                         property="id",
                                         type="string",
                                         description="Track identifier copied from input properties.id (required in input). This is the same ID used as key in Point features' matrix_row structures."
                                     ),
     *                                     @OA\Property(
                                         property="points_order",
                                         type="array",
                                         @OA\Items(type="string"),
                                         description="Array of Point feature IDs ordered by their position along this track, from start to end. The first element in the array is the Point closest to the track start (lowest position_on_track value), and the last element is the Point closest to the track end (highest position_on_track value). This array allows you to determine which Point is the start point (first element) and which is the end point (last element) for this specific track. IMPORTANT: The same Point can appear in different positions in different tracks' points_order arrays, as its position depends on the track geometry."
                                     )
     *                                 )
     *                             }
     *                         )
     *                     )
     *                 )
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

        // Verifica che sia una FeatureCollection
        if (!isset($data['type']) || $data['type'] !== 'FeatureCollection' || !isset($data['features'])) {
            return response()->json(['error' => 'Input must be a GeoJSON FeatureCollection'], 400);
        }

        // Estrai track e points dalla FeatureCollection
        $tracksData = $this->extractTracksFromFeatureCollection($data);
        $pointsData = $this->extractPointsFromFeatureCollection($data);

        if (empty($tracksData['tracks'])) {
            return response()->json([
                'error' => 'No valid track geometry found in FeatureCollection',
                'details' => 'The FeatureCollection must contain at least one Feature with geometry type LineString or MultiLineString'
            ], 400);
        }

        if (empty($pointsData['points'])) {
            return response()->json(['error' => 'No valid points found in FeatureCollection'], 400);
        }

        // Verifica che tutti i Point features abbiano un id
        $missingIds = [];
        foreach ($pointsData['points'] as $point) {
            if (empty($point['id'])) {
                $missingIds[] = 'Point at coordinates [' . $point['lng'] . ', ' . $point['lat'] . ']';
            }
        }
        if (!empty($missingIds)) {
            return response()->json([
                'error' => 'Missing required id in Point features',
                'details' => 'All Point features must have properties.id. Missing in: ' . implode(', ', $missingIds)
            ], 400);
        }

        // Verifica che tutti i LineString/MultiLineString features abbiano un id
        $missingTrackIds = [];
        foreach ($tracksData['tracks'] as $track) {
            if (empty($track['id'])) {
                $missingTrackIds[] = 'LineString/MultiLineString feature';
            }
        }
        if (!empty($missingTrackIds)) {
            return response()->json([
                'error' => 'Missing required id in LineString/MultiLineString features',
                'details' => 'All LineString/MultiLineString features must have properties.id'
            ], 400);
        }

        // Processa ogni track separatamente
        $allMatrices = [];
        foreach ($tracksData['tracks'] as $trackInfo) {
            $trackId = $trackInfo['id'];
            $trackGeometry = $trackInfo['geometry'];

            // Stessa geometria di /api/v1/track (tech data): MultiLineString/LineString passata a PostGIS
            // così ST_LineMerge fa il merge per connettività e le distanze coincidono con tech data.
            $geometry = DB::select("SELECT ST_Force2D(ST_LineMerge(ST_GeomFromGeoJSON('" . json_encode($trackGeometry) . "'))) As wkt")[0]->wkt;

            $track = Track::create([
                'source_id' => 0,
                'geometry' => $geometry
            ]);

            $result = $this->calcTrackMatrix($track, $pointsData['points']);

            $track->delete();

            if (isset($result['error'])) {
                continue; // Salta questo track se c'è un errore
            }

            // Salva la matrice per questo track
            $allMatrices[$trackId] = $result;
        }

        if (empty($allMatrices)) {
            return response()->json(['error' => 'Failed to calculate matrix for any track'], 400);
        }

        // Costruisci la FeatureCollection di risposta
        $responseFeatures = $this->buildResponseFeatureCollection(
            $allMatrices,
            $pointsData['originalFeatures'],
            $tracksData['originalLineFeatures']
        );

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $responseFeatures
        ]);
    }

    /**
     * Estrae tutti i track (LineString/MultiLineString) da una FeatureCollection
     * Ogni track viene processato separatamente
     * 
     * @param array $featureCollection
     * @return array Array con 'tracks' (array di track con id) e 'originalLineFeatures' (features originali)
     */
    private function extractTracksFromFeatureCollection(array $featureCollection): array
    {
        $tracks = [];
        $originalLineFeatures = [];

        foreach ($featureCollection['features'] ?? [] as $feature) {
            // Salta le features con geometry vuota o senza type
            if (!isset($feature['geometry']) || !is_array($feature['geometry']) || empty($feature['geometry']) || !isset($feature['geometry']['type'])) {
                continue;
            }

            $geomType = $feature['geometry']['type'];

            // Raccogli tutte le LineString e MultiLineString
            if ($geomType === 'LineString' || $geomType === 'MultiLineString') {
                // Verifica che ci siano le coordinate
                if (!isset($feature['geometry']['coordinates']) || empty($feature['geometry']['coordinates'])) {
                    continue;
                }

                // Estrai l'ID dalla feature (properties.id è obbligatorio)
                $properties = $feature['properties'] ?? [];
                $trackId = $properties['id'] ?? null;

                // Salva il track con il suo ID
                $tracks[] = [
                    'id' => (string)$trackId,
                    'geometry' => $feature['geometry']
                ];

                // Salva la feature originale
                $originalLineFeatures[] = $feature;
            }
        }

        return [
            'tracks' => $tracks,
            'originalLineFeatures' => $originalLineFeatures
        ];
    }

    /**
     * Converte un MultiLineString in una singola LineString unendo tutte le linee
     * 
     * @param array $multiLineString GeoJSON MultiLineString geometry
     * @return array GeoJSON LineString geometry
     */
    private function convertMultiLineStringToLineString(array $multiLineString): array
    {
        if ($multiLineString['type'] !== 'MultiLineString' || empty($multiLineString['coordinates'])) {
            return $multiLineString;
        }

        $allCoordinates = [];
        foreach ($multiLineString['coordinates'] as $line) {
            if (empty($allCoordinates)) {
                $allCoordinates = $line;
            } else {
                // Controlla se l'ultimo punto coincide con il primo della nuova linea
                $lastPoint = end($allCoordinates);
                $firstPoint = $line[0];

                $pointsMatch = false;
                if (count($lastPoint) >= 2 && count($firstPoint) >= 2) {
                    $tolerance = 0.000001;
                    $pointsMatch = abs($lastPoint[0] - $firstPoint[0]) < $tolerance &&
                        abs($lastPoint[1] - $firstPoint[1]) < $tolerance;
                }

                if ($pointsMatch) {
                    $allCoordinates = array_merge($allCoordinates, array_slice($line, 1));
                } else {
                    $allCoordinates = array_merge($allCoordinates, $line);
                }
            }
        }

        return [
            'type' => 'LineString',
            'coordinates' => $allCoordinates
        ];
    }

    /**
     * Estrae i punti da una FeatureCollection
     * 
     * @param array $featureCollection
     * @return array Array con 'points' (formato per calcTrackMatrix) e 'originalFeatures' (features originali)
     */
    private function extractPointsFromFeatureCollection(array $featureCollection): array
    {
        $points = [];
        $originalFeatures = [];

        foreach ($featureCollection['features'] ?? [] as $feature) {
            if (!isset($feature['geometry']['type']) || $feature['geometry']['type'] !== 'Point') {
                continue;
            }

            $coordinates = $feature['geometry']['coordinates'];
            if (count($coordinates) < 2) {
                continue;
            }

            $lng = $coordinates[0];
            $lat = $coordinates[1];

            // Estrai id e name dalle properties
            // id è obbligatorio, name è opzionale
            $properties = $feature['properties'] ?? [];
            $id = $properties['id'] ?? null;
            $name = $properties['name'] ?? $properties['tooltip'] ?? $id ?? 'Point';

            // id è obbligatorio
            if (!$id) {
                continue; // Salta questo punto se non ha id (verrà validato dopo)
            }

            // Converti id in stringa (può essere numerico)
            $pointId = (string)$id;

            $points[] = [
                'id' => $pointId,
                'name' => (string)$name,
                'lat' => floatval($lat),
                'lng' => floatval($lng)
            ];

            // Salva la feature originale con il suo id per il mapping
            $originalFeatures[$pointId] = $feature;
        }

        return [
            'points' => $points,
            'originalFeatures' => $originalFeatures
        ];
    }

    /**
     * Costruisce la FeatureCollection di risposta con i dati della matrice nelle properties
     * Struttura: dem->matrix_row->{id_linestring}->{id_poles}
     * 
     * @param array $allMatrices Array di risultati da calcTrackMatrix, chiave = trackId
     * @param array $originalFeatures Features originali mappate per id
     * @param array $originalLineFeatures Features LineString/MultiLineString originali
     * @return array Array di features per la FeatureCollection
     */
    private function buildResponseFeatureCollection(array $allMatrices, array $originalFeatures, array $originalLineFeatures = []): array
    {
        $features = [];

        // Raccogli tutti i punti unici da tutte le matrici
        $allPoints = [];
        foreach ($allMatrices as $trackId => $result) {
            foreach ($result['points'] as $point) {
                $pointId = $point['id'];
                if (!isset($allPoints[$pointId])) {
                    $allPoints[$pointId] = $point;
                }
            }
        }

        // Costruisci la struttura matrix_row per ogni punto
        foreach ($allPoints as $pointId => $point) {
            $originalFeature = $originalFeatures[$pointId] ?? null;
            $properties = $originalFeature['properties'] ?? [];

            // Costruisci matrix_row organizzata per track: {id_linestring} -> {id_poles}
            $matrixRowByTrack = [];

            foreach ($allMatrices as $trackId => $result) {
                $matrix = $result['matrix'];

                // Verifica se questo punto è presente in questa matrice
                if (isset($matrix[$pointId])) {
                    $trackMatrixRow = [];
                    foreach ($matrix[$pointId] as $targetId => $matrixData) {
                        if ($matrixData !== null) {
                            $trackMatrixRow[$targetId] = $matrixData;
                        }
                    }

                    // Aggiungi solo se ci sono dati per questo track
                    if (!empty($trackMatrixRow)) {
                        $matrixRowByTrack[$trackId] = $trackMatrixRow;
                    }
                }
            }

            // Raccogli elevation (è la stessa per tutti i track)
            $elevation = null;

            foreach ($allMatrices as $trackId => $result) {
                foreach ($result['points'] as $p) {
                    if ($p['id'] === $pointId) {
                        // Prendi elevation dal primo track disponibile (è la stessa per tutti i track)
                        if ($elevation === null) {
                            $elevation = $p['elevation'];
                        }
                        // Prendi anche le coordinate dal primo punto trovato
                        if (!isset($pointCoordinates)) {
                            $pointCoordinates = [$p['lng'], $p['lat']];
                        }
                        break;
                    }
                }
            }

            $properties['dem'] = [
                'id' => $pointId,
                'name' => $point['name'] ?? $pointId,
                'elevation' => $elevation,
                'matrix_row' => $matrixRowByTrack
            ];

            // Allinea signage con i valori calcolati (distance, time_*, ascent, descent, elevation_*)
            // così la risposta non contiene distanze/tempi obsoleti dall'input.
            $properties = $this->syncSignageWithMatrixRow($properties, $matrixRowByTrack);

            // Calcola l'elevazione per il punto
            $pointLng = $pointCoordinates[0] ?? $point['lng'] ?? 0;
            $pointLat = $pointCoordinates[1] ?? $point['lat'] ?? 0;
            $pointElevation = $this->calcPointElevation($pointLng, $pointLat) ?? 0;

            // Crea la feature di risposta con coordinate 3D
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$pointLng, $pointLat, $pointElevation]
                ],
                'properties' => $properties
            ];
        }

        // Aggiungi le features LineString/MultiLineString originali con dem e 3D
        foreach ($originalLineFeatures as $lineFeature) {
            $trackId = $lineFeature['properties']['id'] ?? null;

            // Applica il 3D alla geometria (aggiungi elevazione a ogni punto)
            if (isset($lineFeature['geometry'])) {
                $lineFeature['geometry'] = $this->apply3DToGeometry($lineFeature['geometry']);
            }

            if ($trackId && isset($allMatrices[$trackId])) {
                $result = $allMatrices[$trackId];
                $points = $result['points'];

                // Ordina i punti per position_on_track
                usort($points, function ($a, $b) {
                    return $a['position_on_track'] <=> $b['position_on_track'];
                });

                // Estrai solo gli ID ordinati
                $pointsOrder = array_map(function ($p) {
                    return $p['id'];
                }, $points);

                // Aggiungi dem alle properties della LineString/MultiLineString
                $lineFeature['properties']['dem'] = [
                    'id' => $trackId,
                    'points_order' => $pointsOrder
                ];
            }

            $features[] = $lineFeature;
        }

        return $features;
    }

    /**
     * Sovrascrive in properties.signage i campi distance, time_hiking, time_bike, ascent, descent,
     * elevation_from, elevation_to con i valori calcolati da dem.matrix_row, per coerenza con la track.
     *
     * @param array $properties Properties della feature (può contenere signage)
     * @param array $matrixRowByTrack dem.matrix_row: [trackId => [targetId => [distance, time_hiking, ...]], ...]
     * @return array properties con signage aggiornato
     */
    private function syncSignageWithMatrixRow(array $properties, array $matrixRowByTrack): array
    {
        $signage = $properties['signage'] ?? null;
        if (!is_array($signage) || empty($matrixRowByTrack)) {
            return $properties;
        }

        foreach ($matrixRowByTrack as $trackId => $targets) {
            $trackIdStr = (string) $trackId;
            if (!isset($signage[$trackIdStr]['arrows']) || !is_array($signage[$trackIdStr]['arrows'])) {
                continue;
            }

            foreach ($signage[$trackIdStr]['arrows'] as $arrowIdx => $arrow) {
                if (!isset($arrow['rows']) || !is_array($arrow['rows'])) {
                    continue;
                }
                foreach ($arrow['rows'] as $rowIdx => $row) {
                    $targetId = isset($row['id']) ? (string) $row['id'] : null;
                    if (!$targetId || !isset($targets[$targetId])) {
                        continue;
                    }
                    $data = $targets[$targetId];
                    $signage[$trackIdStr]['arrows'][$arrowIdx]['rows'][$rowIdx]['distance'] = $data['distance'];
                    $signage[$trackIdStr]['arrows'][$arrowIdx]['rows'][$rowIdx]['time_hiking'] = $data['time_hiking'];
                    $signage[$trackIdStr]['arrows'][$arrowIdx]['rows'][$rowIdx]['time_bike'] = $data['time_bike'];
                    $signage[$trackIdStr]['arrows'][$arrowIdx]['rows'][$rowIdx]['ascent'] = $data['ascent'];
                    $signage[$trackIdStr]['arrows'][$arrowIdx]['rows'][$rowIdx]['descent'] = $data['descent'];
                    $signage[$trackIdStr]['arrows'][$arrowIdx]['rows'][$rowIdx]['elevation_from'] = $data['elevation_from'];
                    $signage[$trackIdStr]['arrows'][$arrowIdx]['rows'][$rowIdx]['elevation_to'] = $data['elevation_to'];
                }
            }
        }

        $properties['signage'] = $signage;
        return $properties;
    }

    /**
     * Applica il 3D a una geometria GeoJSON aggiungendo l'elevazione a ogni punto
     * 
     * @param array $geometry GeoJSON geometry (LineString o MultiLineString)
     * @return array GeoJSON geometry con coordinate 3D [lng, lat, elevation]
     */
    private function apply3DToGeometry(array $geometry): array
    {
        if (!isset($geometry['type']) || !isset($geometry['coordinates'])) {
            return $geometry;
        }

        $type = $geometry['type'];
        $coordinates = $geometry['coordinates'];

        if ($type === 'LineString') {
            // Per ogni coordinata [lng, lat] o [lng, lat, z], aggiungi/aggiorna l'elevazione
            $coordinates3D = [];
            foreach ($coordinates as $coord) {
                $lng = $coord[0];
                $lat = $coord[1];

                // Ricalcola sempre l'elevazione, anche se esiste già ed è 0
                $elevation = $this->calcPointElevation($lng, $lat) ?? 0;

                $coordinates3D[] = [$lng, $lat, $elevation];
            }

            return [
                'type' => 'LineString',
                'coordinates' => $coordinates3D
            ];
        } elseif ($type === 'MultiLineString') {
            // Per ogni LineString nel MultiLineString
            $coordinates3D = [];
            foreach ($coordinates as $lineString) {
                $lineString3D = [];
                foreach ($lineString as $coord) {
                    $lng = $coord[0];
                    $lat = $coord[1];

                    // Ricalcola sempre l'elevazione, anche se esiste già ed è 0
                    $elevation = $this->calcPointElevation($lng, $lat) ?? 0;

                    $lineString3D[] = [$lng, $lat, $elevation];
                }
                $coordinates3D[] = $lineString3D;
            }

            return [
                'type' => 'MultiLineString',
                'coordinates' => $coordinates3D
            ];
        }

        // Se non è LineString o MultiLineString, restituisci la geometria originale
        return $geometry;
    }
}
