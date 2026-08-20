<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteTask extends Model
{
    protected $fillable = ['user_id', 'note_id', 'title', 'is_completed', 'completed_at', 'position'];

    protected $casts = ['is_completed' => 'boolean', 'completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
