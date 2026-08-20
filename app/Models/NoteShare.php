<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NoteShare extends Model
{
    protected $fillable = ['owner_user_id', 'shareable_type', 'shareable_id', 'recipient_user_id', 'recipient_email', 'permission', 'invitation_token_hash', 'accepted_at', 'expires_at'];

    protected $hidden = ['invitation_token_hash'];

    protected $casts = ['accepted_at' => 'datetime', 'expires_at' => 'datetime'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }
}
