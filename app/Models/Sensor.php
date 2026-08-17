<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sensor extends Model
{
    public const GITHUB = 'github';

    public const BROWSER = 'browser';

    protected $fillable = ['user_id', 'type', 'username', 'token', 'pairing_key_hash', 'enabled', 'settings', 'last_checked_at', 'last_error'];

    protected $hidden = ['token', 'pairing_key_hash'];

    protected $casts = [
        'token' => 'encrypted',
        'enabled' => 'boolean',
        'settings' => 'array',
        'last_checked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function daySyncs(): HasMany
    {
        return $this->hasMany(SensorDaySync::class);
    }
}
