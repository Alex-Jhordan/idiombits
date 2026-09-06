<?php

namespace App\Jobs;

use App\Enums\PhraseStatus;
use App\Models\Phrase;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPhraseWithGemini implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function __construct(public Phrase $phrase)
    {
    }

    public function handle(GeminiService $geminiService): void
    {
        $enrichment = $geminiService->enrichPhrase(
            $this->phrase->original_text,
            $this->phrase->tag,
        );

        DB::transaction(function () use ($enrichment): void {
            $this->phrase->phrasePayload()->updateOrCreate(
                [],
                [
                    'idiomatic_translation' => $enrichment['idiomatic_translation'],
                    'payload_data' => Arr::except($enrichment, ['idiomatic_translation']),
                ],
            );

            $activePhraseCount = Phrase::query()
                ->where('user_id', $this->phrase->user_id)
                ->where('status', PhraseStatus::InProgress)
                ->lockForUpdate()
                ->count();

            if ($activePhraseCount < 5) {
                $this->phrase->forceFill([
                    'status' => PhraseStatus::InProgress,
                    'queue_position' => null,
                ])->save();

                return;
            }

            $nextQueuePosition = (int) Phrase::query()
                ->where('user_id', $this->phrase->user_id)
                ->where('status', PhraseStatus::Queued)
                ->max('queue_position') + 1;

            $this->phrase->forceFill([
                'status' => PhraseStatus::Queued,
                'queue_position' => $nextQueuePosition,
            ])->save();
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Phrase enrichment failed after all retries.', [
            'phrase_ulid' => $this->phrase->ulid,
            'phrase_id' => $this->phrase->getKey(),
            'exception' => $exception,
        ]);

        $this->phrase->forceFill([
            'status' => PhraseStatus::Captured,
            'queue_position' => null,
        ])->save();
    }
}