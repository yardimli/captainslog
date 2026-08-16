<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LogBlock extends Model
{
    protected $fillable = ['daily_log_id', 'type', 'content', 'metadata', 'position', 'occurred_at', 'is_hidden'];

    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime', 'is_hidden' => 'boolean'];

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function taskEvent(): HasOne
    {
        return $this->hasOne(TaskEvent::class);
    }
}
