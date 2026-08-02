<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Port\SyncCursorReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sync cursor for offline clients. Returns the greatest client_seq the server holds per
 * append-only log, so a client seeds its monotonic counter from here on login — a reinstall
 * (counter lost) or a second device then can't emit sequences that lose to stored rows.
 * (Delta sync, GET /sync?since=, will grow alongside this.)
 */
final class SyncController
{
    public function __construct(private readonly SyncCursorReader $cursor) {}

    public function cursor(Request $request): JsonResponse
    {
        $view = $this->cursor->cursorFor(
            UserId::fromString((string) $request->user()?->getAuthIdentifier()),
        );

        return response()->json(['data' => [
            'max_triage_seq' => $view->maxTriageSeq,
            'max_review_seq' => $view->maxReviewSeq,
        ]]);
    }
}
