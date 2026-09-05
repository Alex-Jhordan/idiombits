<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[RouteKey('ulid')]
#[Fillable(['ulid', 'name', 'email', 'password', 'active_hours_start', 'active_hours_end', 'quiz_preferred_time'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function phrases(): HasMany
    {
        return $this->hasMany(Phrase::class);
    }

    public function dailyQuizSessions(): HasMany
    {
        return $this->hasMany(DailyQuizSession::class);
    }

    public function quizLogs(): HasMany
    {
        return $this->hasMany(QuizLog::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
