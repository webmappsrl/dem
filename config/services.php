<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'tech_params' => [
        'simplify_preserve_topology' => env('SIMPLIFY_PRESERVE_TOPOLOGY', 5),
        'sampling_step' => env('SAMPLING_STEP', 12.5),
        'smoothed_elevation' => env('SMOOTHED_ELEVATION', 5),
        'round_trip_max_distance' => env('ROUND_TRIP_MAX_DISTANCE', 250),
        'avarage_hiking_speed' => env('AVARAGE_HIKING_SPEED', 3.5),
        'avarage_biking_speed' => env('AVARAGE_BIKING_SPEED', 10.5),
    ],

];
