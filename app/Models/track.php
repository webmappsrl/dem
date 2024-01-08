<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Track extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'geometry',
        'tags',
        'distance',
        'source_id',
        'ele_min',
        'ele_max',
        'ele_from',
        'ele_to',
        'ascent',
        'descent',
        'duration_forward_hiking',
        'duration_backward_hiking',
        'round_trip',
    ];

    public function createTrackFromGeoJson($feature)
    {
        $geometry = DB::select("SELECT ST_AsText(ST_LineMerge(ST_GeomFromGeoJSON('" . json_encode($feature['geometry']) . "'))) As wkt")[0]->wkt;

        $track = Track::create([
            'name' => $feature['properties']['name'],
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
        return $track;
    }
}
