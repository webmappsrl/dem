<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CalculatePointElevationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/elevation/{lng}/{lat}",
     *     summary="Get Elevation",
     *     description="Retrieve the elevation of a point based on provided longitude and latitude.",
     *     operationId="getElevation",
     *     tags={"Elevation"},
     *     @OA\Parameter(
     *         name="lng",
     *         in="path",
     *         required=true,
     *         description="Longitude of the point",
     *         @OA\Schema(type="number", format="float"),
     *     ),
     *     @OA\Parameter(
     *         name="lat",
     *         in="path",
     *         required=true,
     *         description="Latitude of the point",
     *         @OA\Schema(type="number", format="float"),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="elevation", type="number", format="float", example=0),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve elevation"),
     *         ),
     *     ),
     * )
     */
    public function getElevation($lng, $lat)
    {
        $elevation = 0;
        return response()->json(['elevation' => $elevation]);
    }
}
