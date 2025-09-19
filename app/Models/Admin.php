<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Admin extends Authenticatable
{
    use HasApiTokens, Notifiable, HasUuids;

    protected $fillable = ['email','password','full_name','birth_date','image','gender','status'];
    protected $hidden = ['password'];

    /**
     * Get the table associated with the model.
     */
    protected $table = 'admins';

    /**
     * Relationship with admin email OTPs
     */
    public function emailOtps()
    {
        return $this->hasMany(AdminEmailOtp::class);
    }
}