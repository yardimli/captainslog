<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskDefinition extends Model
{
    public const DEFAULT_EMOJI = '✅';

    private const LEGACY_COLORS = [
        'indigo' => '#4f46e5',
        'emerald' => '#059669',
        'amber' => '#d97706',
        'rose' => '#e11d48',
        'sky' => '#0284c7',
    ];

    protected $fillable = ['user_id', 'name', 'emoji', 'color', 'is_sticky', 'recurrence_type', 'recurrence_days', 'scheduled_times', 'options', 'is_active'];

    protected $casts = [
        'is_sticky' => 'boolean',
        'is_active' => 'boolean',
        'recurrence_days' => 'array',
        'scheduled_times' => 'array',
        'options' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (TaskDefinition $task) {
            $task->emoji = filled($task->emoji) ? $task->emoji : self::DEFAULT_EMOJI;
        });
    }

    public function occursOn(CarbonInterface $day): bool
    {
        return match ($this->recurrence_type ?: 'daily') {
            'weekly' => in_array($day->isoWeekday(), $this->recurrence_days ?? [], true),
            'monthly' => in_array($day->day, $this->recurrence_days ?? [], true),
            default => true,
        };
    }

    public function getScheduleSummaryAttribute(): string
    {
        $days = $this->recurrence_days ?? [];
        $recurrence = match ($this->recurrence_type ?: 'daily') {
            'weekly' => collect($days)->map(fn ($day) => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$day - 1] ?? null)->filter()->implode(', '),
            'monthly' => 'Monthly on '.collect($days)->implode(', '),
            default => 'Every day',
        };
        $times = collect($this->scheduled_times ?? [])->map(fn ($time) => auth()->user()?->formatClock($time) ?? $time)->implode(', ');

        return $times ? "$recurrence at $times" : $recurrence;
    }

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
