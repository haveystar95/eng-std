<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Command\SubmitReviews;
use App\Modules\Learning\Application\Command\SubmitReviewsHandler;
use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Learning\Presentation\Http\Request\SubmitReviewsRequest;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** Uploads a batch of raw answers (an offline session's worth); the server grades them. Idempotent by id. */
final class ReviewController
{
    public function __construct(private readonly SubmitReviewsHandler $submit) {}

    public function batch(SubmitReviewsRequest $request): JsonResponse
    {
        $actor = UserId::fromString((string) $request->user()?->getAuthIdentifier());

        $reviews = [];
        foreach ((array) $request->validated('reviews') as $row) {
            if (! is_array($row)) {
                continue;
            }
            $reviews[] = new ReviewInput(
                reviewId: ReviewId::fromString((string) $row['id']),
                termId: TermId::fromString((string) $row['term_id']),
                exerciseMode: ExerciseMode::from((string) $row['exercise_mode']),
                response: (string) ($row['response'] ?? ''),
                answeredAt: new DateTimeImmutable((string) $row['answered_at']),
                clientSeq: (int) $row['client_seq'],
                usedHint: (bool) ($row['used_hint'] ?? false),
                isPractice: (bool) ($row['is_practice'] ?? false),
                latencyMs: isset($row['latency_ms']) ? (int) $row['latency_ms'] : null,
                sessionId: isset($row['session_id']) ? StudySessionId::fromString((string) $row['session_id']) : null,
            );
        }

        $result = ($this->submit)(new SubmitReviews($actor, $reviews));

        return response()->json(['data' => [
            'accepted' => $result->accepted,
            'duplicates' => $result->duplicates,
            'unknown' => $result->unknown,
        ]]);
    }
}
