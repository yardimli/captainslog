<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteSpaceItem extends Model
{
    protected $fillable = ['note_space_id', 'note_id', 'notebook_id', 'position'];

    public function space(): BelongsTo
    {
        return $this->belongsTo(NoteSpace::class, 'note_space_id');
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }
}
