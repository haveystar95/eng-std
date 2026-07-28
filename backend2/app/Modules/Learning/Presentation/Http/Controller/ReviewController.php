<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Command\SubmitReviews;
use App\Modules\Learning\Application\Command\SubmitReviewsHandler;
use App\Modules\Learning\Application\Dto\ReviewInput;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Learning\Presentation\Http\Request\SubmitReviewsRequest;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

/** Uploads a batch of graded answers (an offline session's worth). Idempotent by review id. */
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
                grade: Grade::from((string) $row['grade']),
                answeredAt: new DateTimeImmutable((string) $row['answered_at']),
                latencyMs: isset($row['latency_ms']) ? (int) $row['latency_ms'] : null,
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
