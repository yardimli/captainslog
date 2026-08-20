<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NoteSpace extends Model
{
    use SoftDeletes;

    protected $fillable = ['owner_user_id', 'name', 'description', 'color', 'visibility'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(NoteSpaceMember::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(NoteSpaceItem::class)->orderBy('position');
    }
}
