<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteSpaceMember extends Model
{
    protected $fillable = ['note_space_id', 'user_id', 'permission', 'invited_by_user_id', 'accepted_at'];

    protected $casts = ['accepted_at' => 'datetime'];

    public function space(): BelongsTo
    {
        return $this->belongsTo(NoteSpace::class, 'note_space_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
