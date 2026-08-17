<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskEvent extends Model
{
    protected $fillable = ['daily_log_id', 'task_definition_id', 'log_block_id', 'task_name', 'selected_value', 'occurred_at', 'latitude', 'longitude', 'location_accuracy'];

    protected $casts = ['occurred_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float', 'location_accuracy' => 'float'];

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TaskDefinition::class, 'task_definition_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(LogBlock::class, 'log_block_id');
    }
}
