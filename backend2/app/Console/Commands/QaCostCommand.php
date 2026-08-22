<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Admin\Application\Port\AdminCostReader;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * What one account has spent on paid APIs over a window — the number a QA run is budgeted against.
 *
 * There is no new accounting here and there must not be: the money is already recorded, ledger by
 * ledger, and the admin panel reports it. This command is a terminal view of the same numbers, for
 * the two moments a panel is the wrong tool — before a run («how much have I got left today») and
 * straight after it («what did that scenario cost»).
 *
 * ── Where each line comes from ──────────────────────────────────────────────────────────────────
 *
 * generation / practice / example_regen come from {@see AdminCostReader::userBreakdownSince} — the
 * SAME call the panel's user page makes, so a disagreement between this and the panel is a bug in
 * one of them rather than two opinions.
 *
 * search is read here, directly from `search_lookups`, because the user breakdown does not carry
 * it: that ledger was added after the breakdown was written, and it is the one QA spends on most —
 * a scenario is mostly searching for words. It is labelled as its own source rather than folded in
 * silently, and the gap is filed as a finding rather than fixed here.
 *
 * instant is reported in CHARACTERS, not dollars, and not per user at all: `instant_translations`
 * is a global translation cache with a character meter and no user column, so the honest answer to
 * «what did this user's instant translations cost» is «the cache does not know». Characters in the
 * window are still worth seeing — they are what a DeepL bill is computed from.
 */
final class QaCostCommand extends Command
{
    protected $signature = 'qa:cost
        {user : the account — email or ULID (any account, not only QA ones)}
        {--period=week : day|week|month|all}';

    protected $description = 'Report one account\'s paid-API spend over a window (day|week|month|all)';

    public function __construct(private readonly AdminCostReader $costs)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $since = $this->since();
        if ($since === false) {
            return self::FAILURE;
        }

        $needle = trim((string) $this->argument('user'));
        $user = DB::table('users')->where('email', $needle)->orWhere('id', $needle)->first();
        if ($user === null) {
            $this->error("No user matches «{$needle}».");

            return self::FAILURE;
        }

        $userId = (string) $user->id;
        $breakdown = $this->costs->userBreakdownSince($userId, $since);
        $search = $this->searchSpend($userId, $since);

        $period = (string) $this->option('period');
        $window = $since === null ? 'all time' : 'since ' . $since->format('Y-m-d H:i');
        $this->line("Spend for {$user->email} — period={$period} ({$window})");
        $this->newLine();

        $this->table(
            ['purpose', 'calls', 'tokens in', 'tokens out', 'USD', 'source'],
            [
                ['generation', $breakdown->generation->count, $breakdown->generation->tokensIn, $breakdown->generation->tokensOut, $this->usd($breakdown->generation->costUsd), 'generation_requests'],
                ['practice', $breakdown->practice->count, $breakdown->practice->tokensIn, $breakdown->practice->tokensOut, $this->usd($breakdown->practice->costUsd), 'practice_dialogs'],
                ['example_regen', $breakdown->exampleRegen->count, $breakdown->exampleRegen->tokensIn, $breakdown->exampleRegen->tokensOut, $this->usd($breakdown->exampleRegen->costUsd), 'example_regenerations'],
                ['search', $search['calls'], $search['tokens_in'], $search['tokens_out'], $this->usd($search['cost']), 'search_lookups (NOT in the panel breakdown)'],
            ],
        );

        $total = round($breakdown->totalUsd + $search['cost'], 6);
        $this->line('  TOTAL  ' . $this->usd($total));
        $this->line('  (panel user breakdown would say ' . $this->usd($breakdown->totalUsd) . ' — it omits search)');

        $chars = $this->instantCharacters($since);
        $this->newLine();
        $this->line("  instant translations in this window, fleet-wide: {$chars} characters (no user column, no cost column)");
        $this->line('  Enrichment is fleet-only by design — its ledger has no user_id, so it is not attributable here.');

        return self::SUCCESS;
    }

    /** The window start, null for «all time», or false after refusing an unknown period. */
    private function since(): DateTimeImmutable|null|false
    {
        return match ((string) $this->option('period')) {
            'day' => new DateTimeImmutable('-1 day'),
            'week' => new DateTimeImmutable('-7 days'),
            'month' => new DateTimeImmutable('-30 days'),
            'all' => null,
            default => $this->refusePeriod(),
        };
    }

    private function refusePeriod(): false
    {
        $this->error('--period must be one of day|week|month|all.');

        return false;
    }

    /**
     * The search ledger for this user over the window.
     *
     * `search_lookups.user_id` is the PAYER — the account whose lookup first paid for the entry;
     * a later hit on the same query is served from the row and costs nobody anything. So this is
     * «what this account paid», which is the question a budget asks.
     *
     * @return array{calls: int, tokens_in: int, tokens_out: int, cost: float}
     */
    private function searchSpend(string $userId, ?DateTimeImmutable $since): array
    {
        $row = DB::table('search_lookups')
            ->where('user_id', $userId)
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(tokens_in),0) AS ti, COALESCE(SUM(tokens_out),0) AS tout, COALESCE(SUM(cost_usd),0) AS c')
            ->first();

        return [
            'calls' => (int) ($row->n ?? 0),
            'tokens_in' => (int) ($row->ti ?? 0),
            'tokens_out' => (int) ($row->tout ?? 0),
            'cost' => round((float) ($row->c ?? 0), 6),
        ];
    }

    private function instantCharacters(?DateTimeImmutable $since): int
    {
        return (int) DB::table('instant_translations')
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->sum('characters');
    }

    private function usd(float $amount): string
    {
        return '$' . number_format($amount, 4, '.', '');
    }
}
