<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\IntroducedTermsReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class EloquentIntroducedTermsReader implements IntroducedTermsReader
{
    public function countForDay(UserId $userId, DateTimeImmutable $day): int
    {
        // daily_user_stats is keyed by UTC date, matching the stats projector.
        $date = $day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');

        return (int) DB::table('daily_user_stats')
            ->where('user_id', $userId->value)
            ->where('date', $date)
            ->value('new_terms_count');
    }
}
