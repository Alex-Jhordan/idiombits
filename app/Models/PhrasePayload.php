<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhrasePayload extends Model
{
    protected $fillable = [
        'phrase_id',
        'idiomatic_translation',
        'payload_data',
    ];

    protected function casts(): array
    {
        return [
            'payload_data' => 'array',
        ];
    }

    public function phrase(): BelongsTo
    {
        return $this->belongsTo(Phrase::class);
    }
}