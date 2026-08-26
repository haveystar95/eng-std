<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\StoreCatalogueReader;
use App\Modules\Collections\Application\Port\UserCollectionsReader;
use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Collections\Application\Service\DefaultCollectionPair;
use App\Modules\Learning\Application\Dto\HomeContinueView;
use App\Modules\Learning\Application\Dto\HomeEdgeTermView;
use App\Modules\Learning\Application\Dto\HomeHardTermView;
use App\Modules\Learning\Application\Dto\HomeInWorkView;
use App\Modules\Learning\Application\Dto\HomePlanView;
use App\Modules\Learning\Application\Dto\HomeSessionView;
use App\Modules\Learning\Application\Dto\HomeState;
use App\Modules\Learning\Application\Dto\HomeStoreView;
use App\Modules\Learning\Application\Dto\ScheduledTermFact;
use App\Modules\Learning\Application\Dto\TermErrorFact;
use App\Modules\Learning\Application\Port\HomePlanReader;
use App\Modules\Learning\Application\Port\IntroducedTermsReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\TriagedTermsReader;
use App\Modules\Learning\Application\Service\CardLanguageResolver;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use App\Modules\Vocabulary\Application\Query\TermContentReader;
use DateTimeImmutable;
use DateTimeZone;

/**
 * THE HOME SCREEN'S DAY — assembled from the planner, not reconstructed beside it.
 *
 * Everything the card promises is counted over the same populations the trainer would deal from —
 * {@see HomePlanReader::owedCount()} mirrors the session builder's own predicate — so «повторить N»
 * is 0 exactly when a session would come back empty. Nothing here schedules, grades or introduces
 * anything: the query is a dry run, like the admin day-simulator.
 *
 * The numbers describe THE DAY, not one sitting. «Начать» deals the first twenty cards and the
 * learner comes back for the rest, so capping the card at a session's size makes it report the same
 * twenty after every run — and sit still while sixty repeats drain behind it.
 *
 * THE DAY IS THE POOL, and nothing else. A collection is a CATALOGUE of a topic; the pool is the
 * queue. Terms nobody has swiped yet are catalogue, so they are counted (`triage`) and OFFERED, but
 * they are not part of `total`, of the estimate, or of the composition on the card — otherwise
 * adding a twenty-word set would add twenty words to «сегодня», and a shelf of five sets would
 * announce a two-hundred-word day to someone who takes thirty. The learner decides what to study;
 * the app does not decide it for them by counting everything they own.
 *
 * The swipe pass is offered where it belongs: once the day's repeats are done («Разобрать ещё N из
 * „X"»), which is also the only moment it is the most useful thing on the screen.
 *
 * «Продолжить» rides beside it — one collection that was started and left. One, because a list of
 * them is a shelf, and the shelf already has a tab.
 *
 * The empty rule runs through the whole read model: a block with nothing in it comes back null or
 * empty, never as a zero. «0 слов» is not a sentence this screen says.
 */
final readonly class GetHomePlanHandler
{
    /** How far ahead «на грани забывания» looks. Beyond this a word is scheduled, not slipping. */
    private const EDGE_HORIZON_DAYS = 3;
    private const EDGE_LIMIT = 3;

    /** «Далось труднее всего» — a short list, or it is a report rather than a hint. */
    private const HARDEST_LIMIT = 3;

    /** Same cap the triage queue reads its candidates under, so the two agree about a huge folder. */
    private const TRIAGE_SCOPE_CAP = 500;

    /** Collections considered for the swipe count and «продолжить». Far above any real shelf. */
    private const COLLECTIONS_CAP = 200;

    /** How many recent answers the personal seconds-per-card is averaged over. */
    private const LATENCY_SAMPLE = 200;

    /**
     * Seconds per card for a learner who has not answered enough to have a pace of their own.
     *
     * Measured, not guessed: the winsorised (60 s ceiling) mean over all 919 non-practice answers in
     * the development database on 2026-08-26 was 7.97 s — median 3.8 s, p90 19.2 s, and one 420 s
     * outlier that is a phone left on a table rather than a card. Rounded to 8.
     */
    private const DEFAULT_CARD_SECONDS = 8;

    /**
     * …and seconds per SWIPE, which is a different act and measures like one: 3.0 s winsorised over
     * the 278 triage decisions in the same database (median 1.6 s).
     *
     * Priced at a card's rate instead, a 131-word swipe pass added seventeen minutes to a day that
     * actually holds seven — and «≈ N минут» is the element the product research called the most
     * useful on the screen, which makes overstating it the most expensive thing to get wrong.
     */
    private const DEFAULT_SWIPE_SECONDS = 3;

    /** Example topics on the first-day card — enough to make «17 тем» concrete, few enough to read. */
    private const STORE_SAMPLE = 3;

    public function __construct(
        private HomePlanReader $home,
        private LearnerProfileReader $profile,
        private IntroducedTermsReader $introduced,
        private UserCollectionsReader $collections,
        private UserCollectionTermsReader $collectionTerms,
        private TriagedTermsReader $triaged,
        private StoreCatalogueReader $store,
        private DefaultCollectionPair $defaultPair,
        private TermContentReader $content,
        private CardLanguageResolver $cardLanguages,
    ) {}

    public function __invoke(GetHomePlan $query): HomePlanView
    {
        $user = $query->userId;
        $now = $query->now;
        $tz = $this->profile->timezoneFor($user);
        $todayStart = $now->setTimezone($tz)->setTime(0, 0, 0);
        $dayEnd = $todayStart->modify('+1 day');

        // ── the study half of the day ───────────────────────────────────────────────────────────
        //
        // THE DAY, not one sitting. Both numbers are counted over the whole backlog: «Начать» deals
        // the first twenty and the learner comes back, so a card capped at twenty would report the
        // same twenty after every run and sit still while sixty repeats drained behind it. Which is
        // exactly what it did.
        $perDay = $this->profile->newTermsPerDay($user);
        $newRemaining = max(0, $perDay - $this->introduced->countForDay($user, $now));
        $repeat = $this->home->owedCount($user, $now);
        $waiting = $this->home->waitingInPool($user);
        $new = min($waiting, $newRemaining);

        // ── the swipe half, and the collection that was started and left ────────────────────────
        $summaries = $this->collections->forUser($user, null, self::COLLECTIONS_CAP)->items;
        $shelf = $this->shelf($user, $summaries);

        $triage = 0;
        $triageTarget = null;
        foreach ($shelf as $folder) {
            $triage += $folder['remaining'];
            if ($folder['remaining'] > 0 && ($triageTarget === null || $folder['remaining'] > $triageTarget['remaining'])) {
                $triageTarget = $folder;
            }
        }

        $total = $repeat + $new;
        $seconds = $this->home->averageCardSeconds($user, self::LATENCY_SAMPLE) ?? self::DEFAULT_CARD_SECONDS;
        $swipeSeconds = $this->home->averageSwipeSeconds($user, self::LATENCY_SAMPLE) ?? self::DEFAULT_SWIPE_SECONDS;

        // ── the schedule just ahead, and what today produced ────────────────────────────────────
        // The window is «the next N calendar days», so it ends at the start of the day after them.
        $edge = $this->home->edgeTerms(
            $user,
            $now,
            $todayStart->modify('+' . (self::EDGE_HORIZON_DAYS + 1) . ' days'),
            self::EDGE_LIMIT,
        );
        $hardest = $this->home->hardestToday($user, $now, $tz, self::HARDEST_LIMIT);
        $today = $this->home->todayAnswers($user, $now, $tz);
        $nextReview = $this->home->nextReview($user, $dayEnd, $tz);

        $content = $this->contentFor($user, [
            ...array_map(static fn (ScheduledTermFact $f): string => $f->termId, $edge),
            ...array_map(static fn (TermErrorFact $f): string => $f->termId, $hardest),
        ]);

        $poolSize = $this->home->poolSize($user);
        $langs = $this->defaultPair->forOwner($user);
        $store = $this->store->summaryFor($user, $langs->sourceLang, $langs->targetLang, self::STORE_SAMPLE);

        return new HomePlanView(
            state: $this->state($repeat, $today->answered, $poolSize, $summaries === []),
            session: new HomeSessionView(
                repeat: $repeat,
                new: $new,
                triage: $triage,
                total: $total,
                estimatedMinutes: $total > 0 ? max(1, (int) round($total * $seconds / 60)) : null,
                avgSecondsPerCard: $seconds,
                triageMinutes: $triage > 0 ? max(1, (int) round($triage * $swipeSeconds / 60)) : null,
                triageCollectionId: $triageTarget === null ? null : $triageTarget['id'],
                triageCollectionTitle: $triageTarget === null ? null : $triageTarget['title'],
            ),
            inWork: new HomeInWorkView(
                total: $poolSize,
                waiting: $waiting,
                perDay: $perDay,
                newRemaining: $newRemaining,
                daysUntilQueue: $this->daysUntilQueue($waiting, $newRemaining, $perDay),
            ),
            edge: $this->edgeViews($edge, $content, $tz, $todayStart),
            today: $today->answered > 0 ? $today : null,
            nextReview: $nextReview,
            hardest: $this->hardestViews($hardest, $content),
            unfinished: $this->unfinished($user, $shelf, $tz, $todayStart),
            store: new HomeStoreView($store->count, $store->topics),
        );
    }

    /**
     * Which screen this is.
     *
     * `$owed` is REPEATS — the only thing the day asks for. Neither new words nor swipes are in it,
     * and for the same reason: both are OFFERS. A queue of untaught words is not a session (кадр
     * 17d — «Всё повторено» over one button that takes them), and a shelf of unsorted collections is
     * not a session either, or every set the learner adds would announce itself as work they owe.
     *
     * The order matters twice more. `done` comes before `idle`, so leftovers a closed day did not
     * touch read as «сверх плана» rather than reopening it. And `empty` is last and narrow — no
     * shelf, no pool — because a learner who finished everything is not a new user, and showing
     * them the welcome card would be the rudest possible reading of a good day.
     */
    private function state(int $owed, int $answeredToday, int $poolSize, bool $shelfIsEmpty): HomeState
    {
        if ($owed > 0) {
            return HomeState::Plan;
        }
        if ($answeredToday > 0) {
            return HomeState::Done;
        }
        if ($shelfIsEmpty && $poolSize === 0) {
            return HomeState::Empty;
        }

        return HomeState::Idle;
    }

    /**
     * The learner's collections, each with how much of it has been dealt with. «Done» is a term that
     * carries a verdict OR a progress row — the same two exclusions the triage queue applies, so the
     * remainder here is exactly the deck that queue would serve.
     *
     * @param  list<\App\Modules\Collections\Application\Dto\CollectionSummaryView>  $summaries
     * @return list<array{id: string, title: string, terms: list<string>, total: int, done: int, remaining: int}>
     */
    private function shelf(UserId $user, array $summaries): array
    {
        if ($summaries === []) {
            return [];
        }

        $byCollection = $this->collectionTerms->termIdsByCollection($user);
        $all = [];
        foreach ($byCollection as $terms) {
            foreach ($terms as $termId) {
                $all[$termId] = true;
            }
        }
        $all = array_slice(array_keys($all), 0, self::TRIAGE_SCOPE_CAP);

        $studied = $this->home->progressTermIds($user, $all);
        $swiped = $this->triaged->triagedTermIds($user, $all);

        $shelf = [];
        foreach ($summaries as $summary) {
            $terms = $byCollection[$summary->id] ?? [];
            if ($terms === []) {
                continue;
            }

            $done = 0;
            foreach ($terms as $termId) {
                if (isset($studied[$termId]) || isset($swiped[$termId])) {
                    $done++;
                }
            }

            $shelf[] = [
                'id' => $summary->id,
                'title' => $summary->title,
                'terms' => $terms,
                'total' => count($terms),
                'done' => $done,
                'remaining' => count($terms) - $done,
            ];
        }

        return $shelf;
    }

    /**
     * «Продолжить „Ветклинику“» — the collection the learner started, left unfinished, and touched
     * most recently. Most recent rather than oldest: the thing almost finished yesterday is the thing
     * that might be finished today, and a folder abandoned in March is a decision, not a loose end.
     *
     * @param  list<array{id: string, title: string, terms: list<string>, total: int, done: int, remaining: int}>  $shelf
     */
    private function unfinished(UserId $user, array $shelf, DateTimeZone $tz, DateTimeImmutable $todayStart): ?HomeContinueView
    {
        $best = null;
        $bestAt = null;
        foreach ($shelf as $folder) {
            if ($folder['remaining'] <= 0 || $folder['done'] <= 0) {
                continue; // untouched is not abandoned, and finished is not unfinished
            }

            $at = $this->home->lastTouchedAt($user, $folder['terms']);
            if ($best === null || ($at !== null && ($bestAt === null || $at > $bestAt))) {
                $best = $folder;
                $bestAt = $at;
            }
        }

        if ($best === null) {
            return null;
        }

        return new HomeContinueView(
            collectionId: $best['id'],
            title: $best['title'],
            done: $best['done'],
            total: $best['total'],
            remaining: $best['remaining'],
            abandonedDays: $bestAt === null
                ? null
                : (int) $todayStart->diff($bestAt->setTimezone($tz)->setTime(0, 0, 0))->days,
        );
    }

    /**
     * When the last waiting word gets its first card. Today's leftover quota goes first, then whole
     * days of `perDay`. Null when nothing waits, and null when the learner takes no new words at all
     * — «никогда» is not a number of days, and 0 would read as «сегодня».
     */
    private function daysUntilQueue(int $waiting, int $newRemaining, int $perDay): ?int
    {
        if ($waiting <= 0) {
            return null;
        }
        if ($waiting <= $newRemaining) {
            return 1;
        }
        if ($perDay <= 0) {
            return null;
        }

        return 1 + (int) ceil(($waiting - $newRemaining) / $perDay);
    }

    /**
     * @param  list<ScheduledTermFact>  $facts
     * @param  array<string, TermContentView>  $content
     * @return list<HomeEdgeTermView>
     */
    private function edgeViews(array $facts, array $content, DateTimeZone $tz, DateTimeImmutable $todayStart): array
    {
        $views = [];
        foreach ($facts as $fact) {
            $view = $content[$fact->termId] ?? null;
            if ($view === null) {
                continue; // nothing renderable without content — the session skips such a term too
            }

            $dueLocal = $fact->dueAt->setTimezone($tz);
            // Whole calendar days from today, so «завтра» is the next date and not «через 18 часов».
            $views[] = new HomeEdgeTermView(
                termId: $fact->termId,
                text: $view->text,
                translation: $view->translation,
                dueOn: $dueLocal->format('Y-m-d'),
                inDays: max(1, (int) $todayStart->diff($dueLocal->setTime(0, 0, 0))->days),
            );
        }

        return $views;
    }

    /**
     * @param  list<TermErrorFact>  $facts
     * @param  array<string, TermContentView>  $content
     * @return list<HomeHardTermView>
     */
    private function hardestViews(array $facts, array $content): array
    {
        $views = [];
        foreach ($facts as $fact) {
            $view = $content[$fact->termId] ?? null;
            if ($view !== null) {
                $views[] = new HomeHardTermView($fact->termId, $view->text, $view->translation, $fact->errors);
            }
        }

        return $views;
    }

    /**
     * @param  list<string>  $termIds
     * @return array<string, TermContentView>
     */
    private function contentFor(UserId $user, array $termIds): array
    {
        $unique = array_values(array_unique($termIds));
        if ($unique === []) {
            return [];
        }

        $ids = array_map(TermId::fromString(...), $unique);

        // The home mixes words from several folders, so each term answers for itself — the same
        // per-term read the pool session and the sync feed use.
        return $this->content->byIds($ids, $this->cardLanguages->forTerms($user, $ids));
    }
}
