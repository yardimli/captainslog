<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteUserSetting extends Model
{
    protected $fillable = ['user_id', 'default_notebook_id', 'default_list_view', 'default_sort', 'default_sort_direction', 'editor_preferences', 'sidebar_preferences'];

    protected $casts = ['editor_preferences' => 'array', 'sidebar_preferences' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultNotebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class, 'default_notebook_id');
    }
}
