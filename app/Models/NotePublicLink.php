<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotePublicLink extends Model
{
    protected $fillable = ['note_id', 'created_by_user_id', 'token_hash', 'permission', 'expires_at', 'last_accessed_at'];

    protected $hidden = ['token_hash'];

    protected $casts = ['expires_at' => 'datetime', 'last_accessed_at' => 'datetime'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
