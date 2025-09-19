<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;              // <-- tambah ini
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
     use HasApiTokens, Notifiable, HasUuids;    // <-- dan aktifkan trait-nya

    protected $fillable = ['email','password','full_name','status'];
    protected $hidden = ['password'];
}
