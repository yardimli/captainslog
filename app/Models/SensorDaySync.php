<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorDaySync extends Model
{
    protected $fillable = ['sensor_id', 'log_date', 'status', 'item_count', 'metadata'];

    protected $casts = ['log_date' => 'date', 'item_count' => 'integer', 'metadata' => 'array'];

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
}
