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
}
