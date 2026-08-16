<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        'is_guest',
        'guest_token_hash',
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
    ];

    public function dailyLogs()
    {
        return $this->hasMany(DailyLog::class);
    }

    public function taskDefinitions()
    {
        return $this->hasMany(TaskDefinition::class);
    }
}
