<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckAnswerRequest;
use App\Models\AiJob;
use App\Models\Word;
use App\Services\Ai\AiProvider;
use Illuminate\Http\JsonResponse;

class AiController extends Controller
{
    public function __construct(private readonly AiProvider $ai) {}

    public function jobStatus(AiJob $aiJob): JsonResponse
    {
        $this->authorize('view', $aiJob);

        return response()->json([
            'status' => $aiJob->status,
            'collection_id' => $aiJob->collection_id,
            'error' => $aiJob->error,
        ]);
    }

    public function check(CheckAnswerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $word = Word::findOrFail($data['word_id']);
        $this->authorize('review', $word);

        $result = $this->ai->checkAnswer(
            term: $word->term,
            expected: $word->translation,
            userAnswer: $data['user_answer'],
            mode: $data['mode'] ?? 'translation',
        );

        return response()->json([
            'correct' => (bool) ($result['correct'] ?? false),
            'score' => (int) ($result['score'] ?? 0),
            'feedback' => (string) ($result['feedback'] ?? ''),
            'corrected' => $result['corrected'] ?? null,
        ]);
    }
}
