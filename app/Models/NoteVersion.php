<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['note_id', 'created_by_user_id', 'version_number', 'title', 'content', 'content_json', 'plain_text', 'content_format', 'color', 'change_source', 'change_summary', 'created_at'];

    protected $casts = ['content_json' => 'array', 'created_at' => 'datetime'];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
