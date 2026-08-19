<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'openrouter_api_key',
        'time_format',
        'week_starts_on',
        'default_chat_model',
        'is_guest',
        'guest_token_hash',
        'demo_seed_version',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'openrouter_api_key',
        'guest_token_hash',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'openrouter_api_key' => 'encrypted',
        'is_guest' => 'boolean',
        'week_starts_on' => 'integer',
        'demo_seed_version' => 'integer',
    ];

    public function dailyLogs()
    {
        return $this->hasMany(DailyLog::class);
    }

    public function taskDefinitions()
    {
        return $this->hasMany(TaskDefinition::class);
    }

    public function sensors()
    {
        return $this->hasMany(Sensor::class);
    }

    public function browsingActivities()
    {
        return $this->hasMany(BrowsingActivity::class);
    }

    public function kindleReadingProgress()
    {
        return $this->hasMany(KindleReadingProgress::class);
    }

    public function formatTime(DateTimeInterface $time): string
    {
        return $time->format($this->time_format === '12' ? 'g:i A' : 'H:i');
    }

    public function formatClock(string $time): string
    {
        if ($time === '24:00') {
            return $this->time_format === '12' ? '12:00 AM' : '24:00';
        }

        return Carbon::createFromFormat('H:i', $time)->format($this->time_format === '12' ? 'g:i A' : 'H:i');
    }
}
