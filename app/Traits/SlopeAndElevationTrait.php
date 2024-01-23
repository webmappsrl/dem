<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use stdClass;

trait SlopeAndElevationTrait
{
    public function calcPointElevation($lng, $lat)
    {
        $result = DB::table('o_4_dem')
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

        // Calculate Duration
        $duration_forward_hiking = $this->calcDuration($distance, $ascent, 'hiking');
        $duration_backward_hiking = $this->calcDuration($distance, $descent, 'hiking');
        $duration_forward_bike = $this->calcDuration($distance, $ascent, 'bike');
        $duration_backward_bike = $this->calcDuration($distance, $descent, 'bike');


        $geojson = [];
        $geojson['type'] = 'Feature';
        $geojson['properties'] = [
            'ele_min' => $ele_min,
            'ele_max' => $ele_max,
            'ele_from' => intval($smoothed_line_points[0]->smoothed_ele),
            'ele_to' => intval($smoothed_line_points[count($smoothed_line_points) - 1]->smoothed_ele),
            'ascent' => intval($ascent),
            'descent' => intval($descent),
            'distance' => $distance,
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
}
