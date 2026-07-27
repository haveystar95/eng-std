<?php

namespace App\Http\Controllers\Api;

use App\Actions\GradeReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerReviewRequest;
use App\Http\Resources\ReviewCardResource;
use App\Http\Resources\ReviewStateResource;
use App\Models\Word;
use App\Services\DueReviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function due(Request $request, DueReviews $due): AnonymousResourceCollection
    {
        $cards = $due->forUser(
            user: $request->user(),
            collectionId: $request->integer('collection_id') ?: null,
            shuffle: $request->boolean('shuffle'),
            limit: (int) $request->integer('limit', 40),
        );

        return ReviewCardResource::collection($cards);
    }

    public function answer(AnswerReviewRequest $request, Word $word, GradeReview $action): JsonResponse
    {
        $this->authorize('review', $word);

        $state = $action->handle($request->user(), $word, $request->validated()['rating']);

        return response()->json([
            'next_due_at' => $state->due_at,
            'state' => (new ReviewStateResource($state))->resolve(),
        ]);
    }
}
