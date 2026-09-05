<?php

namespace App\Models;

use App\Enums\DailyQuizSessionStatus;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[RouteKey('ulid')]
class DailyQuizSession extends Model
{
    use HasUlids;

    protected $fillable = [
        'ulid',
        'user_id',
        'scheduled_for',
        'expires_at',
        'status',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'expires_at' => 'datetime',
            'status' => DailyQuizSessionStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quizLogs(): HasMany
    {
        return $this->hasMany(QuizLog::class, 'quiz_session_id');
    }
}