<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LongTextAttachment extends Model
{
    protected $fillable = ['user_id', 'daily_log_id', 'log_block_id', 'format', 'content'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    public function logBlock(): BelongsTo
    {
        return $this->belongsTo(LogBlock::class);
    }
}
