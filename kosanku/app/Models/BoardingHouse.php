<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BoardingHouse extends Model
{
    protected $fillable = ['name','address','latitude','longitude','price_month'];

    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'price_month' => 'integer',
    ];
}
