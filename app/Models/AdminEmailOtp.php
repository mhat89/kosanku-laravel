<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AdminEmailOtp extends Model
{
    use HasUuids;

    protected $table = 'admin_email_otps';

    protected $fillable = [
        'admin_id',
        'code_hash',
        'expires_at',
        'consumed_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /**
     * Relationship with admin
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired()
    {
        return $this->expires_at < now();
    }

    /**
     * Check if OTP is consumed
     */
    public function isConsumed()
    {
        return !is_null($this->consumed_at);
    }

    /**
     * Mark OTP as consumed
     */
    public function markAsConsumed()
    {
        $this->consumed_at = now();
        $this->save();
    }
}