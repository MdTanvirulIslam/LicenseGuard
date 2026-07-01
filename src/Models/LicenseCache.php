<?php

namespace Vendor\LicenseGuard\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseCache extends Model
{
    protected $table = 'license_cache';

    protected $fillable = [
        'domain',
        'token',
        'signature',
        'status',
        'is_local',
        'last_checked_at',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'signature' => 'encrypted',
        'is_local' => 'boolean',
        'last_checked_at' => 'datetime',
    ];
}
