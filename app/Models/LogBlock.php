<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LogBlock extends Model
{
    public const DEFAULT_EMOJIS = [
        'event' => '✅',
        'text' => '📝',
        'media' => '📎',
        'generated_image' => '🎨',
        'chat_user' => '💬',
        'chat_assistant' => '🤖',
        'sensor_github' => '💻',
        'sensor_browser' => '🌐',
    ];

    protected $fillable = ['daily_log_id', 'type', 'emoji', 'content', 'metadata', 'position', 'occurred_at', 'is_hidden'];

    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime', 'is_hidden' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (LogBlock $block) {
            $block->emoji = filled($block->emoji) ? $block->emoji : self::defaultEmojiForType($block->type);
        });
    }

    public static function defaultEmojiForType(string $type): string
    {
        return self::DEFAULT_EMOJIS[$type] ?? self::DEFAULT_EMOJIS['text'];
    }

    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function taskEvent(): HasOne
    {
        return $this->hasOne(TaskEvent::class);
    }

    public function browsingActivities(): HasMany
    {
        return $this->hasMany(BrowsingActivity::class);
    }
}
