<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardingHouse extends Model
{
    protected $fillable = ['name','address','latitude','longitude','price_month'];

    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'price_month' => 'integer',
    ];

    /**
     * Get the images for the boarding house.
     */
    public function images(): HasMany
    {
        return $this->hasMany(BoardingHouseImage::class)->ordered();
    }

    /**
     * Get the primary image for the boarding house.
     */
    public function primaryImage()
    {
        return $this->hasOne(BoardingHouseImage::class)->primary();
    }
}
