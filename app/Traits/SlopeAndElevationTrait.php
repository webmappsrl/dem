<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait SlopeAndElevationTrait
{
    public function calcPointElevation($lat, $lng)
    {
        $result = DB::table('o_4_mptdem')
            ->select(DB::raw("
                ST_Value(
                    rast,
                    ST_Transform(
                        ST_SetSRID(ST_MakePoint($lng, $lat), 4326),
                        3035
                    )
                ) AS elevation
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

        return $result->elevation ?? null;
    }
}
