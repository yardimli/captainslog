<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notebook extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'notebook_stack_id', 'name', 'description', 'color', 'is_default', 'is_pinned', 'position'];

    protected $casts = ['is_default' => 'boolean', 'is_pinned' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stack(): BelongsTo
    {
        return $this->belongsTo(NotebookStack::class, 'notebook_stack_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function shares(): MorphMany
    {
        return $this->morphMany(NoteShare::class, 'shareable');
    }
}
