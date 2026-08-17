<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Console;

use App\Modules\Learning\Domain\Service\TriageVerificationPlanner;
use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Reports the outcome of resolved triage "known" verification checks — the one metric that says
 * whether the risk heuristic and the swipe wording are calibrated. Three buckets, not two:
 * `hard` is "didn't lie, but didn't know it cold" — many `hard` with few `again` means the
 * "I know" wording is too broad and the risk threshold should drop. Absolute counts are printed
 * alongside shares on purpose: the numbers are noise below a couple hundred checks (weeks of real
 * use), and thresholds must not be tuned on noise.
 */
final class VerificationStatsCommand extends Command
{
    protected $signature = 'learning:verification-stats';

    protected $description = 'Outcome shares of resolved triage "known" verification checks.';

    /** Below this the metric is noise — do not tune thresholds. */
    private const MIN_SAMPLES = 200;

    private const DEFAULT_LEVEL = CefrLevel::B1;

    public function __construct(private readonly TriageVerificationPlanner $planner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $reviews = DB::table('reviews')->where('is_verification', true)
            ->get(['user_id', 'term_id', 'grade', 'answered_at']);

        $total = $reviews->count();
        if ($total === 0) {
            $this->info('No "known" verification checks resolved yet.');

            return self::SUCCESS;
        }

        $termIds = $reviews->pluck('term_id')->unique()->values()->all();
        $userIds = $reviews->pluck('user_id')->unique()->values()->all();

        /** @var array<string, stdClass> $terms */
        $terms = DB::table('terms')->whereIn('id', $termIds)->get(['id', 'cefr', 'type'])->keyBy('id')->all();
        /** @var array<string, mixed> $levels */
        $levels = DB::table('profiles')->whereIn('user_id', $userIds)->pluck('cefr_level', 'user_id')->all();
        // Sorted newest-first, so the first match per (user, term) is the governing known verdict.
        /** @var list<stdClass> $knownTriages */
        $knownTriages = DB::table('term_triages')->where('verdict', 'known')->whereIn('term_id', $termIds)
            ->orderByDesc('decided_at')->get(['user_id', 'term_id', 'latency_ms', 'decided_at'])->all();

        $overall = $this->emptyTally();
        $risky = $this->emptyTally();
        $obvious = $this->emptyTally();

        foreach ($reviews as $review) {
            $bucket = $this->bucket((string) $review->grade);
            $overall[$bucket]++;
            if ($this->wasRisky($review, $terms, $levels, $knownTriages)) {
                $risky[$bucket]++;
            } else {
                $obvious[$bucket]++;
            }
        }

        $this->line('Resolved "known" verification checks — outcome shares:');
        $this->newLine();
        $this->printTally('all', $overall);
        $this->printTally('risky (7-day check)', $risky);
        $this->printTally('obvious (90-day check)', $obvious);

        if ($total < self::MIN_SAMPLES) {
            $this->newLine();
            $this->warn(sprintf(
                'Only %d checks (< %d) — this is noise. Do not move the 7/90-day or latency thresholds yet.',
                $total,
                self::MIN_SAMPLES,
            ));
        }

        return self::SUCCESS;
    }

    /** @return array{again: int, hard: int, ok: int} */
    private function emptyTally(): array
    {
        return ['again' => 0, 'hard' => 0, 'ok' => 0];
    }

    /** @return 'again'|'hard'|'ok' */
    private function bucket(string $grade): string
    {
        return match ($grade) {
            'again' => 'again',
            'hard' => 'hard',
            default => 'ok', // good | easy — knew it cold
        };
    }

    /**
     * @param  array<string, stdClass>  $terms
     * @param  array<string, mixed>  $levels
     * @param  list<stdClass>  $knownTriages
     */
    private function wasRisky(stdClass $review, array $terms, array $levels, array $knownTriages): bool
    {
        $term = $terms[(string) $review->term_id] ?? null;
        if ($term === null) {
            return false;
        }

        $latency = null;
        foreach ($knownTriages as $triage) {
            if ((string) $triage->user_id === (string) $review->user_id
                && (string) $triage->term_id === (string) $review->term_id
                && (string) $triage->decided_at <= (string) $review->answered_at
            ) {
                $latency = $triage->latency_ms !== null ? (int) $triage->latency_ms : null;
                break; // newest-first → first match is the governing verdict
            }
        }

        $level = CefrLevel::tryFromLabel(isset($levels[(string) $review->user_id]) ? (string) $levels[(string) $review->user_id] : null)
            ?? self::DEFAULT_LEVEL;

        return $this->planner->plan(
            CefrLevel::tryFromLabel($term->cefr !== null ? (string) $term->cefr : null),
            $level,
            $latency,
            // Phrase-like: anything that isn't a single word (mirrors Vocabulary's TermType::isPhraseLike;
            // kept inline rather than importing that Domain VO across the module boundary).
            (string) $term->type !== 'word',
        )->risky;
    }

    /** @param array{again: int, hard: int, ok: int} $tally */
    private function printTally(string $label, array $tally): void
    {
        $n = $tally['again'] + $tally['hard'] + $tally['ok'];
        if ($n === 0) {
            $this->line(sprintf('  %-24s n=0', $label));

            return;
        }

        $this->line(sprintf(
            '  %-24s n=%-4d  again %d (%s)   hard %d (%s)   good+easy %d (%s)',
            $label,
            $n,
            $tally['again'],
            $this->pct($tally['again'], $n),
            $tally['hard'],
            $this->pct($tally['hard'], $n),
            $tally['ok'],
            $this->pct($tally['ok'], $n),
        ));
    }

    private function pct(int $count, int $total): string
    {
        return sprintf('%.1f%%', 100 * $count / $total);
    }
}
