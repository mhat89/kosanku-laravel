<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EmailOtp extends Model
{
    use HasUuids;

    protected $fillable = ['user_id','code_hash','expires_at','consumed_at'];
    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
