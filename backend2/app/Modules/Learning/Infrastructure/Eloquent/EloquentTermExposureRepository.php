<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\TermExposure;
use App\Modules\Learning\Domain\Repository\TermExposureRepository;
use Illuminate\Support\Facades\DB;

final class EloquentTermExposureRepository implements TermExposureRepository
{
    public function insertIgnore(TermExposure $exposure): bool
    {
        // ON CONFLICT DO NOTHING against the (user_id, term_id) primary key. A re-uploaded intro
        // therefore keeps the FIRST `shown_at` — the moment the learner actually met the word —
        // rather than the moment the retry happened.
        $inserted = DB::table('term_exposures')->insertOrIgnore([
            'user_id' => $exposure->userId->value,
            'term_id' => $exposure->termId->value,
            'session_id' => $exposure->sessionId?->value,
            // Device-stamped, like a review's answered_at — bound as an instant, not as whatever
            // wall clock the phone happened to be showing (see UtcInstant).
            'shown_at' => UtcInstant::bind($exposure->shownAt),
            'created_at' => now(),
        ]);

        return $inserted === 1;
    }
}
