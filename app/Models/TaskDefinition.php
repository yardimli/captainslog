<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskDefinition extends Model
{
    private const LEGACY_COLORS = [
        'indigo' => '#4f46e5',
        'emerald' => '#059669',
        'amber' => '#d97706',
        'rose' => '#e11d48',
        'sky' => '#0284c7',
    ];

    protected $fillable = ['user_id', 'name', 'color', 'is_sticky', 'options', 'is_active'];

    protected $casts = ['is_sticky' => 'boolean', 'is_active' => 'boolean', 'options' => 'array'];

    public function getColorHexAttribute(): string
    {
        $color = strtolower((string) $this->color);

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : (self::LEGACY_COLORS[$color] ?? self::LEGACY_COLORS['indigo']);
    }

    public function getButtonTextColorAttribute(): string
    {
        [$red, $green, $blue] = sscanf($this->color_hex, '#%02x%02x%02x');
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance > 155 ? '#0f172a' : '#ffffff';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TaskEvent::class);
    }
}
