<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly string $model;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $model = null,
    )
    {
        $this->apiKey  = $apiKey  ?? config('services.gemini.key', '');
        $this->baseUrl = $baseUrl ?? config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->model   = $model   ?? config('services.gemini.model', 'gemini-3-flash-preview');
    }

    /**
     * @return array<string, mixed>
     */
    public function enrichPhrase(string $text, ?string $tag = null): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('The Gemini API key is not configured.');
        }

        $prompt = 'Enrich the following phrase for an English language learner. Return only the requested JSON object.';

        if ($tag !== null && $tag !== '') {
            $prompt .= " The learner's tag is: {$tag}.";
        }

        $prompt .= "\nPhrase: {$text}";

        $response = Http::acceptJson()
            ->withQueryParameters(['key' => $this->apiKey])
            ->post("{$this->baseUrl}/models/{$this->model}:generateContent", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'source_language' => ['type' => 'STRING'],
                            'original_text' => ['type' => 'STRING'],
                            'idiomatic_translation' => ['type' => 'STRING'],
                            'time_variations' => [
                                'type' => 'ARRAY',
                                'items' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'tense' => ['type' => 'STRING'],
                                        'sentence' => ['type' => 'STRING'],
                                        'translation' => ['type' => 'STRING'],
                                    ],
                                    'required' => ['tense', 'sentence', 'translation'],
                                ],
                            ],
                            'complementary_phrase' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'phrase' => ['type' => 'STRING'],
                                    'translation' => ['type' => 'STRING'],
                                ],
                                'required' => ['phrase', 'translation'],
                            ],
                        ],
                        'required' => [
                            'source_language',
                            'original_text',
                            'idiomatic_translation',
                            'time_variations',
                            'complementary_phrase',
                        ],
                    ],
                ],
            ]);

        $payload = $response->throw()->json();
        $content = $payload['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        $result = json_decode($content, true);

        if (! is_array($result)) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return $result;
    }
}