<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\TermExposure;
use App\Modules\Learning\Domain\Repository\TermExposureRepository;
use Illuminate\Support\Facades\DB;

final class EloquentTermExposureRepository implements TermExposureRepository
{
    public function record(TermExposure $exposure): bool
    {
        $inserted = DB::table('term_exposures')->insertOrIgnore([
            'user_id' => $exposure->userId->value,
            'term_id' => $exposure->termId->value,
            'session_id' => $exposure->sessionId?->value,
            // Device-stamped, like a review's answered_at — bound as an instant, not as whatever
            // wall clock the phone happened to be showing (see UtcInstant).
            'shown_at' => UtcInstant::bind($exposure->shownAt),
            'created_at' => now(),
        ]);

        if ($inserted === 1) {
            return true;
        }

        // ALREADY MET — and shown again, which is a fact about TODAY that the plan's ladder reads
        // ({@see TermExposureRepository::record()}). Moved forward only: a replayed batch carrying
        // an older showing must not drag the row backwards, so the row itself is the guard rather
        // than a read-then-write nothing serialises.
        DB::table('term_exposures')
            ->where('user_id', $exposure->userId->value)
            ->where('term_id', $exposure->termId->value)
            ->where('shown_at', '<', UtcInstant::bind($exposure->shownAt))
            ->update([
                'session_id' => $exposure->sessionId?->value,
                'shown_at' => UtcInstant::bind($exposure->shownAt),
            ]);

        return false;
    }
}
