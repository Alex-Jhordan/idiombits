<?php

namespace App\Models;

use App\Enums\PhraseStatus;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[RouteKey('ulid')]
class Phrase extends Model
{
    protected $fillable = [
        'ulid',
        'user_id',
        'original_text',
        'source_language',
        'status',
        'tag',
        'queue_position',
        'success_streak',
        'learned_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Phrase $phrase): void {
            $phrase->ulid ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PhraseStatus::class,
            'learned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function phrasePayload(): HasOne
    {
        return $this->hasOne(PhrasePayload::class);
    }

    public function quizLogs(): HasMany
    {
        return $this->hasMany(QuizLog::class);
    }
}