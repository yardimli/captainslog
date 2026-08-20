<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteLogLink extends Model
{
    protected $fillable = ['note_id', 'daily_log_id', 'log_block_id', 'occurred_at', 'label'];

    protected $casts = ['occurred_at' => 'datetime'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
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
