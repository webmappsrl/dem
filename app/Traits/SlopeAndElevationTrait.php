<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

trait SlopeAndElevationTrait
{
    /**
     * Calculate the elevation of a point given its longitude and latitude.
     *
     * @param float $lng Longitude of the point.
     * @param float $lat Latitude of the point.
     * @return int|null Elevation of the point or null if not found.
     */
    public function calcPointElevation($lng, $lat)
    {
        $result = DB::table('dem')
            ->select(DB::raw("
                ST_Value(
                    rast,
                    ST_Transform(
                        ST_SetSRID(ST_MakePoint($lng, $lat), 4326),
                        3035
                    )
                ) AS ele
            "))
            ->whereRaw("
                ST_Intersects(
                    rast,
                    ST_Transform(
                        ST_SetSRID(ST_MakePoint($lng, $lat), 4326),
                        3035
                    )
                )
            ")
            ->first();

        return ($result && $result->ele) ? intval($result->ele) : null;
    }

    /**
     * Calculate the technical data for a given track.
     *
     * @param object $track The track object containing track data.
     * @return void
     */
    public function calcTrackTechData($track)
    {
        // Get tech params from config
        $simplify_preserve_topology_param = config('services.tech_params.simplify_preserve_topology');
        $sampling_step_param = config('services.tech_params.sampling_step');
        $smoothed_elevation_param = config('services.tech_params.smoothed_elevation');
        $round_trip_max_distance_param = config('services.tech_params.round_trip_max_distance');

        // Create simplified and transformed geometry. the bevel (smusso) value is set to 5.
        $SimplifyPreserveTopology = DB::select("SELECT ST_SimplifyPreserveTopology(ST_Transform('$track->geometry'::geometry, 3035), $simplify_preserve_topology_param) AS geom")[0]->geom;

        // Extracts individual points from simplified geometry
        $original_track_points = DB::select("SELECT (dp).path[1] AS index, (dp).geom AS geom FROM (SELECT (ST_DumpPoints(ST_Transform('$track->geometry'::geometry, 3035))) as dp) as Foo");
        $geojson_coordinates = $this->calcOriginalTrackElevations($original_track_points);

        // TODO: DELETE THIS Line because it's not being used
        // Extracts individual points from simplified geometry
        $track_points = DB::select("SELECT (dp).path[1] AS index, (dp).geom AS geom FROM (SELECT (ST_DumpPoints('$SimplifyPreserveTopology')) as dp) as Foo");

        //check if $simplyfiPreserveTopology is a multilinestring
        $geomType = DB::select("SELECT ST_GeometryType('$SimplifyPreserveTopology') AS geom_type")[0]->geom_type;
        if ($geomType != 'ST_LineString') {
            return ['error' => 'Invalid geometry for track with id: ' . $track->source_id];
        }
        // Creates a linestring geometry with points resampled every 12.5 meters
        $resampled_line = DB::select("SELECT ST_LineFromMultiPoint(ST_LineInterpolatePoints('$SimplifyPreserveTopology', $sampling_step_param/ST_Length('$SimplifyPreserveTopology'))) AS geom")[0]->geom;

        // Extracts individual points from resampled geometry
        $resampled_line_points = DB::select("SELECT (dp).path[1] AS index, (dp).geom AS geom FROM (SELECT (ST_DumpPoints('$resampled_line')) as dp) as Foo");

        // Calcola Elevation di ogni punto
        foreach ($resampled_line_points as $point) {
            $point_geom = DB::select("SELECT ST_Transform('$point->geom'::geometry,4326) AS geom")[0]->geom;
            $coordinates = DB::select("SELECT ST_X('$point_geom') as x,ST_Y('$point_geom') AS y")[0];
            $point->ele = $this->calcPointElevation($coordinates->x, $coordinates->y);
        }

        // Aggiunge smoothed_ele con i valori di elevazione smoothati. Il parametro per la media è settato a 5 punti prima e 5 punti dopo
        $smoothed_line_points = $this->calcSmoothedElevation($resampled_line_points, $smoothed_elevation_param);

        // Calculate Ascent and Descent
        $ascent = 0;
        $descent = 0;
        $this->calcAscentDescent($smoothed_line_points, $ascent, $descent);

        // Calculate ele_min and ele_max from the Original Track geometry
        $ele_min = null;
        $ele_max = null;
        $this->calcEleMinEleMax($original_track_points, $ele_max, $ele_min);

        // Calculate Distance from the Original Track geometry
        $distance = intval(DB::select("SELECT ST_Length(ST_Transform('$track->geometry'::geometry, 3035)) AS distance")[0]->distance) / 1000;

        // Calculate Round Trip
        $round_trip = DB::select("SELECT ST_Distance(ST_StartPoint('$resampled_line'), ST_EndPoint('$resampled_line')) < $round_trip_max_distance_param AS round_trip")[0]->round_trip;

        // Calculate Duration and set it to the nearest 15 minutes
        $duration_forward_hiking = ceil($this->calcDuration($distance, $ascent, 'hiking'));
        $duration_backward_hiking = ceil($this->calcDuration($distance, $descent, 'hiking'));
        $duration_forward_bike = ceil($this->calcDuration($distance, $ascent, 'bike'));
        $duration_backward_bike = ceil($this->calcDuration($distance, $descent, 'bike'));


        $geojson = [];
        $geojson['type'] = 'Feature';
        $geojson['properties'] = [
            'ele_min' => $ele_min,
            'ele_max' => $ele_max,
            'ele_from' => intval($smoothed_line_points[0]->smoothed_ele),
            'ele_to' => intval($smoothed_line_points[count($smoothed_line_points) - 1]->smoothed_ele),
            'ascent' => intval($ascent),
            'descent' => intval($descent),
            'distance' => round($distance, 1),
            'duration_forward_hiking' => $duration_forward_hiking,
            'duration_backward_hiking' => $duration_backward_hiking,
            'duration_forward_bike' => $duration_forward_bike,
            'duration_backward_bike' => $duration_backward_bike,
            'round_trip' => $round_trip,
        ];

        $geojson['geometry'] = [
            'type' => 'LineString',
            'coordinates' => $geojson_coordinates
        ];

        return $geojson;
    }

    /**
     * Calculate the smoothed elevation for a given set of data.
     *
     * @param array $data The data set containing elevation points.
     * @param int $smoothed_elevation_param The parameter for smoothing elevation (default is 5).
     * @return array The smoothed elevation data.
     */
    public function calcSmoothedElevation($data, $smoothed_elevation_param = 5)
    {
        $result = [];

        // Iterate through each data point
        for ($i = 0; $i < count($data); $i++) {
            $startIndex = max(0, $i - $smoothed_elevation_param);
            $endIndex = min(count($data) - 1, $i + $smoothed_elevation_param);

            // Calculate the average elevation for the current index
            $sum = 0;
            $count = 0;
            for ($j = $startIndex; $j <= $endIndex; $j++) {
                $sum += $data[$j]->ele;
                $count++;
            }

            $averageElevation = ($count > 0) ? ($sum / $count) : null;

            // Create a new stdClass object for the result
            $resultObj = new stdClass();
            $resultObj->index = $data[$i]->index;
            $resultObj->ele = $data[$i]->ele;
            $resultObj->geom = $data[$i]->geom;
            $resultObj->smoothed_ele = $averageElevation;
            // Store the result
            $result[] = $resultObj;
        }

        return $result;
    }

    public function calcAscentDescent($data, &$ascent, &$descent)
    {
        // Iterate through each data point
        for ($i = 0; $i < count($data) - 1; $i++) {
            $currentEle = $data[$i]->smoothed_ele;
            $nextEle = $data[$i + 1]->smoothed_ele;

            if ($currentEle && $nextEle) {
                $diff = $nextEle - $currentEle;
                if ($diff > 0) {
                    $ascent += $diff;
                } else {
                    $descent += abs($diff);
                }
            }
        }
    }

    public function calcEleMinEleMax($data, &$ele_max, &$ele_min)
    {
        // Iterate through each data point
        for ($i = 0; $i < count($data) - 1; $i++) {
            if ($data[$i]->ele) {
                if (!$ele_min || $data[$i]->ele < $ele_min) {
                    $ele_min = intval($data[$i]->ele);
                }
                if (!$ele_max || $data[$i]->ele > $ele_max) {
                    $ele_max = intval($data[$i]->ele);
                }
            }
        }
    }

    public function calcDuration($distance, $height, $type)
    {
        $avarage_hiking_speed_param = config('services.tech_params.avarage_hiking_speed');
        $avarage_biking_speed_param = config('services.tech_params.avarage_biking_speed');

        if ($type == 'hiking') {
            return intval((($distance + $height / 100) / $avarage_hiking_speed_param) * 60);
        } elseif ($type == 'bike') {
            return intval((($distance + $height * 3 / 100) / $avarage_biking_speed_param) * 60);
        }
    }

    public function calcOriginalTrackElevations($original_track_points)
    {
        $geojson_coordinates = [];
        // Calcola Elevation di ogni punto
        foreach ($original_track_points as $point) {
            $point_geom = DB::select("SELECT ST_Transform('$point->geom'::geometry,4326) AS geom")[0]->geom;
            $coordinates = DB::select("SELECT ST_X('$point_geom') as x,ST_Y('$point_geom') AS y")[0];
            $point->ele = $this->calcPointElevation($coordinates->x, $coordinates->y);
            $geojson_coordinates[] = [$coordinates->x, $coordinates->y, $point->ele];
        }
        return $geojson_coordinates;
    }

    /**
     * Calculate travel time matrix between points on a track (optimized for CAI signage)
     *
     * @param object $track The track object containing track data.
     * @param array $userPoints Array of user points with id, lat, lng, name
     * @return array Matrix with points and travel times between all pairs
     */
    public function calcTrackMatrix($track, $userPoints)
    {
        // Get tech params
        $simplify_preserve_topology_param = config('services.tech_params.simplify_preserve_topology');
        $sampling_step_param = config('services.tech_params.sampling_step');
        $smoothed_elevation_param = config('services.tech_params.smoothed_elevation');

        // FASE 1: Pre-calcolo track completa
        $SimplifyPreserveTopology = DB::select("SELECT ST_SimplifyPreserveTopology(ST_Transform('$track->geometry'::geometry, 3035), $simplify_preserve_topology_param) AS geom")[0]->geom;

        // Prova a unire le linee dopo la semplificazione (potrebbero essere state separate)
        $mergedGeometry = DB::select("SELECT ST_LineMerge('$SimplifyPreserveTopology'::geometry) AS geom")[0]->geom;

        $geomType = DB::select("SELECT ST_GeometryType('$mergedGeometry') AS geom_type")[0]->geom_type;

        // Se rimane un MultiLineString, convertilo in LineString unendo tutte le linee
        if ($geomType == 'ST_MultiLineString') {
            // Estrai tutte le LineString e uniscile in una singola LineString
            $mergedGeometry = DB::select("
                SELECT ST_MakeLine(geom) as geom
                FROM (
                    SELECT (ST_Dump('$mergedGeometry'::geometry)).geom as geom
                    ORDER BY (ST_Dump('$mergedGeometry'::geometry)).path
                ) AS lines
            ")[0]->geom;
            $geomType = 'ST_LineString';
        }

        if ($geomType != 'ST_LineString') {
            return ['error' => 'Invalid geometry type: track must be a single connected LineString'];
        }

        // Usa la geometria unita per i calcoli successivi
        $SimplifyPreserveTopology = $mergedGeometry;

        $resampled_line = DB::select("SELECT ST_LineFromMultiPoint(ST_LineInterpolatePoints('$SimplifyPreserveTopology', $sampling_step_param/ST_Length('$SimplifyPreserveTopology'))) AS geom")[0]->geom;
        $resampled_line_points = DB::select("SELECT (dp).path[1] AS index, (dp).geom AS geom FROM (SELECT (ST_DumpPoints('$resampled_line')) as dp) as Foo");

        // Calcola elevazioni per tutti i punti
        foreach ($resampled_line_points as $point) {
            $point_geom = DB::select("SELECT ST_Transform('$point->geom'::geometry,4326) AS geom")[0]->geom;
            $coordinates = DB::select("SELECT ST_X('$point_geom') as x,ST_Y('$point_geom') AS y")[0];
            $point->ele = $this->calcPointElevation($coordinates->x, $coordinates->y);
        }

        $smoothed_line_points = $this->calcSmoothedElevation($resampled_line_points, $smoothed_elevation_param);

        // FASE 2: Calcola posizioni e ordina punti lungo la track
        $track_geom_3035 = DB::select("SELECT ST_Transform('$track->geometry'::geometry, 3035) AS geom")[0]->geom;
        $pointsWithPosition = [];

        foreach ($userPoints as $point) {
            $point_geom = DB::select("SELECT ST_Transform(ST_SetSRID(ST_MakePoint({$point['lng']}, {$point['lat']}), 4326), 3035) AS geom")[0]->geom;
            $closest_point = DB::select("SELECT ST_ClosestPoint('$track_geom_3035'::geometry, '$point_geom'::geometry) AS geom")[0]->geom;
            $fraction = DB::select("SELECT ST_LineLocatePoint('$track_geom_3035'::geometry, '$closest_point'::geometry) AS fraction")[0]->fraction;

            // Calcola elevazione
            $elevation = $this->calcPointElevation($point['lng'], $point['lat']);

            $pointsWithPosition[] = [
                'id' => $point['id'],
                'name' => $point['name'] ?? $point['id'],
                'lat' => $point['lat'],
                'lng' => $point['lng'],
                'elevation' => $elevation,
                'position_on_track' => floatval($fraction),
            ];
        }

        // Ordina per posizione sulla track
        usort($pointsWithPosition, function ($a, $b) {
            return $a['position_on_track'] <=> $b['position_on_track'];
        });

        // Aggiungi is_start e is_end
        for ($i = 0; $i < count($pointsWithPosition); $i++) {
            $pointsWithPosition[$i]['is_start'] = ($i === 0);
            $pointsWithPosition[$i]['is_end'] = ($i === count($pointsWithPosition) - 1);
        }

        // FASE 3: Calcola solo segmenti consecutivi
        $segments = [];
        for ($i = 0; $i < count($pointsWithPosition) - 1; $i++) {
            $from = $pointsWithPosition[$i];
            $to = $pointsWithPosition[$i + 1];

            $fraction_from = $from['position_on_track'];
            $fraction_to = $to['position_on_track'];

            // Estrai segmento
            $segment_geom = DB::select("SELECT ST_LineSubstring('$track_geom_3035'::geometry, $fraction_from, $fraction_to) AS geom")[0]->geom;
            $distance = floatval(DB::select("SELECT ST_Length('$segment_geom'::geometry) AS distance")[0]->distance);

            // Trova punti resampled nel segmento
            $start_idx = intval($fraction_from * (count($smoothed_line_points) - 1));
            $end_idx = intval($fraction_to * (count($smoothed_line_points) - 1));
            $segment_points = array_slice($smoothed_line_points, $start_idx, $end_idx - $start_idx + 1);

            // Calcola ascent/descent
            $ascent = 0;
            $descent = 0;
            if (count($segment_points) >= 2) {
                $this->calcAscentDescent($segment_points, $ascent, $descent);
            }

            $segments[] = [
                'from' => $from['id'],
                'to' => $to['id'],
                'distance' => round($distance),
                'ascent' => intval($ascent),
                'descent' => intval($descent),
                'elevation_from' => $from['elevation'],
                'elevation_to' => $to['elevation'],
                'time_hiking' => ceil($this->calcDuration($distance / 1000, $ascent, 'hiking')),
                'time_bike' => ceil($this->calcDuration($distance / 1000, $ascent, 'bike')),
            ];
        }

        // FASE 4: Costruisci matrice completa per composizione
        $matrix = [];
        foreach ($pointsWithPosition as $i => $point_i) {
            $matrix[$point_i['id']] = [];

            foreach ($pointsWithPosition as $j => $point_j) {
                if ($i === $j) {
                    $matrix[$point_i['id']][$point_j['id']] = null;
                    continue;
                }

                $forward = $i < $j;
                $start_idx = $forward ? $i : $j;
                $end_idx = $forward ? $j : $i;

                // Somma segmenti consecutivi
                $total_distance = 0;
                $total_ascent = 0;
                $total_descent = 0;
                $elevation_from = $pointsWithPosition[$start_idx]['elevation'];
                $elevation_to = $pointsWithPosition[$end_idx]['elevation'];

                for ($k = $start_idx; $k < $end_idx; $k++) {
                    $total_distance += $segments[$k]['distance'];
                    if ($forward) {
                        $total_ascent += $segments[$k]['ascent'];
                        $total_descent += $segments[$k]['descent'];
                    } else {
                        // Inverti per direzione opposta
                        $total_ascent += $segments[$k]['descent'];
                        $total_descent += $segments[$k]['ascent'];
                    }
                }

                if (!$forward) {
                    // Inverti elevazioni
                    $temp = $elevation_from;
                    $elevation_from = $elevation_to;
                    $elevation_to = $temp;
                }

                $matrix[$point_i['id']][$point_j['id']] = [
                    'distance' => $total_distance,
                    'time_hiking' => ceil($this->calcDuration($total_distance / 1000, $total_ascent, 'hiking')),
                    'time_bike' => ceil($this->calcDuration($total_distance / 1000, $total_ascent, 'bike')),
                    'ascent' => intval($total_ascent),
                    'descent' => intval($total_descent),
                    'elevation_from' => $elevation_from,
                    'elevation_to' => $elevation_to,
                ];
            }
        }

        return [
            'points' => $pointsWithPosition,
            'matrix' => $matrix,
        ];
    }
}
