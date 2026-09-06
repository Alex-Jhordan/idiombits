<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DailyQuizSessionStatus;
use App\Enums\PhraseStatus;
use App\Enums\QuizLogInteractionType;
use App\Enums\QuizLogType;
use App\Http\Controllers\Controller;
use App\Models\DailyQuizSession;
use App\Models\Phrase;
use App\Models\QuizLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuizController extends Controller
{
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'quiz_session_ulid' => ['nullable', 'string', 'size:26'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.phrase_ulid' => [
                'required',
                'string',
                Rule::exists('phrases', 'ulid')->where('user_id', $user->id),
            ],
            'answers.*.is_success' => ['required', 'boolean'],
            'answers.*.quiz_type' => ['required', Rule::enum(QuizLogType::class)],
            'answers.*.interaction_type' => ['required', Rule::enum(QuizLogInteractionType::class)],
        ]);

        $results = [];
        $quizSession = null;

        DB::transaction(function () use ($user, $validated, &$quizSession, &$results): void {
            $quizSession = DailyQuizSession::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'ulid' => $validated['quiz_session_ulid'] ?? null,
                ],
                [
                    'scheduled_for' => $user->quiz_preferred_time ?? now()->format('H:i:s'),
                    'expires_at' => now()->setTimeFromTimeString($user->active_hours_end ?? '23:59:59'),
                    'status' => DailyQuizSessionStatus::Pending,
                ]
            );

            foreach ($validated['answers'] as $answer) {
                $phrase = Phrase::query()
                    ->where('user_id', $user->id)
                    ->where('ulid', $answer['phrase_ulid'])
                    ->firstOrFail();

                $isSuccess = (bool) $answer['is_success'];

                $quizLog = QuizLog::query()->create([
                    'quiz_session_id' => $quizSession->id,
                    'user_id' => $user->id,
                    'phrase_id' => $phrase->id,
                    'is_success' => $isSuccess,
                    'quiz_type' => $answer['quiz_type'],
                    'interaction_type' => $answer['interaction_type'],
                ]);

                $newStreak = $isSuccess ? ($phrase->success_streak + 1) : 0;
                $phrase->success_streak = $newStreak;

                if ($isSuccess) {
                    if ($newStreak >= 3) {
                        $phrase->status = PhraseStatus::Learned;
                        $phrase->learned_at = now();
                    } else {
                        $phrase->status = PhraseStatus::InProgress;
                    }
                } else {
                    $phrase->status = PhraseStatus::ReLearning;
                    $phrase->learned_at = null;
                }

                $phrase->save();

                $results[] = [
                    'phrase_ulid' => $phrase->ulid,
                    'status' => $phrase->status->value,
                    'success_streak' => $phrase->success_streak,
                    'quiz_log_id' => $quizLog->id,
                ];
            }

            $quizSession->status = DailyQuizSessionStatus::Completed;
            $quizSession->save();
        });

        return response()->json([
            'session_ulid' => $quizSession->ulid,
            'status' => $quizSession->status->value,
            'results' => $results,
        ]);
    }
}
