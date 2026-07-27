<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use Illuminate\Support\Facades\DB;

final class EloquentStudySessionRepository implements StudySessionRepository
{
    public function save(StudySession $session): void
    {
        $now = now();

        // Idempotent on the client-supplied id, so a retried "start session" is a no-op.
        DB::table('study_sessions')->insertOrIgnore([
            'id' => $session->id()->value,
            'user_id' => $session->userId()->value,
            'collection_id' => $session->collectionId()?->value,
            'mode' => $session->mode()->value,
            'started_at' => $session->startedAt(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
