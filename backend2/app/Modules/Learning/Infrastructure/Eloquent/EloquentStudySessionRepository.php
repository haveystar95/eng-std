<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Shared\Domain\ValueObject\TermId;
use Illuminate\Support\Facades\DB;

final class EloquentStudySessionRepository implements StudySessionRepository
{
    public function save(StudySession $session): void
    {
        $now = now();

        // Idempotent on the client-supplied id, so a retried "build session" is a no-op.
        DB::table('study_sessions')->insertOrIgnore([
            'id' => $session->id()->value,
            'user_id' => $session->userId()->value,
            'collection_id' => $session->collectionId()?->value,
            'is_practice' => $session->isPractice(),
            'composition' => json_encode(array_map(static fn (TermId $id): string => $id->value, $session->composition())),
            'started_at' => $session->startedAt(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
