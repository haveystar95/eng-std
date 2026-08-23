<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\ValueObject\SessionOutcome;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
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
            // Client-supplied, so it carries the device's offset (see UtcInstant).
            'started_at' => UtcInstant::bind($session->startedAt()),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function complete(
        StudySessionId $id,
        UserId $userId,
        DateTimeImmutable $endedAt,
        SessionOutcome $outcome,
    ): bool {
        // One conditional statement carries all three rules: it is this user's session, it is still
        // open, and whoever gets there first is the one that closes it. Reading then writing would
        // let a re-sent completion overwrite the real finishing time with the retry's.
        $updated = DB::table('study_sessions')
            ->where('id', $id->value)
            ->where('user_id', $userId->value)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => UtcInstant::bind($endedAt),
                'stats' => json_encode($outcome->toArray()),
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }
}
