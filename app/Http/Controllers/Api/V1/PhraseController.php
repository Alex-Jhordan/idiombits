<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PhraseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PhraseResource;
use App\Jobs\ProcessPhraseWithGemini;
use App\Models\Phrase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PhraseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ulid' => ['nullable', 'string', 'max:26'],
            'original_text' => ['required', 'string', 'max:150'],
            'source_language' => ['required', 'string', 'max:5'],
            'tag' => ['nullable', 'string', 'max:50'],
        ]);

        $phrase = Phrase::create([
            'ulid' => $validated['ulid'] ?? null,
            'user_id' => $request->user()->id,
            'original_text' => $validated['original_text'],
            'source_language' => $validated['source_language'],
            'status' => PhraseStatus::Captured,
            'tag' => $validated['tag'] ?? null,
        ]);

        ProcessPhraseWithGemini::dispatch($phrase);

        return (new PhraseResource($phrase))
            ->response()
            ->setStatusCode(202);
    }

    public function active(Request $request): AnonymousResourceCollection
    {
        $phrases = Phrase::query()
            ->with('phrasePayload')
            ->where('user_id', $request->user()->id)
            ->where('status', PhraseStatus::InProgress)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return PhraseResource::collection($phrases);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phrases' => ['required', 'array', 'min:1'],
            'phrases.*.ulid' => ['required', 'string', 'size:26'],
            'phrases.*.original_text' => ['required', 'string', 'max:150'],
            'phrases.*.source_language' => ['required', 'string', 'max:5'],
            'phrases.*.status' => ['required', Rule::enum(PhraseStatus::class)],
            'phrases.*.tag' => ['nullable', 'string', 'max:50'],
        ]);

        $statusMap = [];

        DB::transaction(function () use ($request, $validated, &$statusMap): void {
            foreach ($validated['phrases'] as $remotePhrase) {
                $phrase = Phrase::updateOrCreate(
                    [
                        'user_id' => $request->user()->id,
                        'ulid' => $remotePhrase['ulid'],
                    ],
                    [
                        'original_text' => $remotePhrase['original_text'],
                        'source_language' => $remotePhrase['source_language'],
                        'status' => $remotePhrase['status'],
                        'tag' => $remotePhrase['tag'] ?? null,
                    ]
                );

                $statusMap[$remotePhrase['ulid']] =  $phrase->status->value;
            }
        });

        return response()->json([
            'synced' => true,
            'statuses' => $statusMap,
        ]);
    }
}
