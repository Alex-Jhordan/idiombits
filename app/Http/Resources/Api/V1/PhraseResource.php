<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhraseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'original_text' => $this->original_text,
            'source_language' => $this->source_language,
            'status' => $this->status->value,
            'tag' => $this->tag,
            'queue_position' => $this->queue_position,
            'success_streak' => $this->success_streak,
            'learned_at' => $this->learned_at?->toISOString(),
            'phrase_payload' => $this->whenLoaded('phrasePayload', function (): array {
                return [
                    'idiomatic_translation' => $this->phrasePayload?->idiomatic_translation,
                    'payload_data' => $this->phrasePayload?->payload_data ?? [],
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
