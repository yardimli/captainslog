<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'notebook_id', 'title', 'content', 'content_json', 'plain_text', 'excerpt', 'content_format',
        'source_type', 'source_url', 'latitude', 'longitude', 'place_name', 'color', 'is_pinned', 'is_archived',
        'is_template', 'last_viewed_at',
    ];

    protected $casts = [
        'content_json' => 'array', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_pinned' => 'boolean',
        'is_archived' => 'boolean', 'is_template' => 'boolean', 'last_viewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NoteVersion::class)->orderByDesc('version_number');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(NoteTask::class)->orderBy('position');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(NoteTag::class, 'note_tag')->withPivot('created_at');
    }

    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(NoteLink::class, 'source_note_id');
    }

    public function incomingLinks(): HasMany
    {
        return $this->hasMany(NoteLink::class, 'target_note_id');
    }

    public function logLinks(): HasMany
    {
        return $this->hasMany(NoteLogLink::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NoteAttachment::class)->orderBy('position');
    }

    public function publicLinks(): HasMany
    {
        return $this->hasMany(NotePublicLink::class);
    }

    public function shares(): MorphMany
    {
        return $this->morphMany(NoteShare::class, 'shareable');
    }
}
