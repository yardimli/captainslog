<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NoteTag extends Model
{
    protected $table = 'note_tags';

    protected $fillable = ['user_id', 'parent_id', 'name', 'color'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class, 'note_tag')->withPivot('created_at');
    }
}
