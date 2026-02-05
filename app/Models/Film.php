<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Film extends Model
{
    protected $table = 'films';

    protected $fillable = [
        'filmId',
        'title',
        'description',
        'releaseYear',
        'length',
        'rating'
    ];

    public $timestamps = true;
}
