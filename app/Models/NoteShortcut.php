<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NoteShortcut extends Model
{
    protected $fillable = ['user_id', 'shortcutable_type', 'shortcutable_id', 'position'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shortcutable(): MorphTo
    {
        return $this->morphTo();
    }
}
