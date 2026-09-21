<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Console;

use App\Modules\Plan\Application\Service\ConversationPassing;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\ValueObject\Stage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * THE SIXTH STAGE, WRITTEN FOR THE TALKS THAT ENDED BEFORE ITS JOURNAL EXISTED (наряд CONV-2, п. 2).
 *
 * The journal of walked stages (`plan_stage_passages`) is written when a talk comes to an end of its own; the talks
 * that ended before the table was there have no row, and without one a day whose talk is over reads «идёт» — and a
 * day where «Ещё раз» was started after it cannot be closed at all. This command reads the journal of TALKS and writes
 * the missing passages: for every such day, the FIRST talk that ended of its own (natural, limit, declined — never a
 * replayed one). Idempotent: a day that has a passage is not touched, so a second run writes nothing. Prints what it
 * wrote, day by day, and «было / стало»; `--dry` writes nothing.
 *
 * Writes `plan_stage_passages` only — take the database backup first, as for any write to the dev database. Never an
 * UPDATE by hand instead of it: the rule «the first natural end walks the stage» lives here and in
 * {@see ConversationPassing}, and nowhere else.
 */
final class PlanReconcileTalksCommand extends Command
{
    protected $signature = 'plan:reconcile-talks {--dry : say what would be written, write nothing}';

    protected $description = 'Write the sixth-stage passages of days whose talk ended before plan_stage_passages existed';

    public function handle(ConversationRepository $conversations, StagePassageRepository $passages, ConversationPassing $passing): int
    {
        $dry = $this->option('dry') === true;
        $talks = $conversations->walkedWithoutPassage();

        $days = [];
        foreach ($talks as $talk) {
            $days[$talk->dayId()->value] ??= $talk;
        }
        $before = count($days);

        $written = 0;
        foreach ($days as $talk) {
            $day = DB::table('plan_days')->where('id', $talk->dayId()->value)->first(['number', 'status']);
            $line = sprintf(
                'план %s · день %d (%s) · разговор %s · %s · %s',
                $talk->planId()->value,
                $talk->dayNumber(),
                is_object($day) ? (string) $day->status : '?',
                $talk->id()->value,
                $talk->endedReason()->value ?? '?',
                $talk->endedAt()?->format(DATE_ATOM) ?? '?',
            );
            if (! $dry) {
                $passing->mark($talk);
                $written += $passages->of($talk->dayId(), Stage::Conversation)?->conversationId?->equals($talk->id()) === true ? 1 : 0;
            }
            $this->line(($dry ? '[--dry] ' : '').$line);
        }

        $after = $dry ? $before : count(array_unique(array_map(
            static fn ($talk): string => $talk->dayId()->value,
            $conversations->walkedWithoutPassage(),
        )));
        $this->info("дней с оконченным разговором и без прохождения шестого этапа: было {$before} / стало {$after}");
        if (! $dry) {
            $this->info("записано прохождений: {$written}");
        }

        return self::SUCCESS;
    }
}
