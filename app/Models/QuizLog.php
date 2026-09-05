<?php

namespace App\Models;

use App\Enums\QuizLogInteractionType;
use App\Enums\QuizLogType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizLog extends Model
{
    protected $fillable = [
        'quiz_session_id',
        'user_id',
        'phrase_id',
        'is_success',
        'quiz_type',
        'interaction_type',
    ];

    protected function casts(): array
    {
        return [
            'is_success' => 'boolean',
            'quiz_type' => QuizLogType::class,
            'interaction_type' => QuizLogInteractionType::class,
        ];
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(DailyQuizSession::class, 'quiz_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function phrase(): BelongsTo
    {
        return $this->belongsTo(Phrase::class);
    }
}