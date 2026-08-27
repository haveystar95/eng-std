<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Dto\StoreCatalogueItem;
use App\Modules\Collections\Application\Port\StoreCatalogueReader;
use App\Modules\Collections\Application\Port\UserCollectionsReader;
use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Collections\Application\Service\DefaultCollectionPair;
use App\Modules\Learning\Application\Dto\HomeContinueView;
use App\Modules\Learning\Application\Dto\HomeDayAwardView;
use App\Modules\Learning\Application\Dto\HomeEdgeTermView;
use App\Modules\Learning\Application\Dto\HomeHardTermView;
use App\Modules\Learning\Application\Dto\HomeInWorkView;
use App\Modules\Learning\Application\Dto\HomePlanView;
use App\Modules\Learning\Application\Dto\HomeSessionView;
use App\Modules\Learning\Application\Dto\HomeState;
use App\Modules\Learning\Application\Dto\HomeStoreItemView;
use App\Modules\Learning\Application\Dto\HomeStoreView;
use App\Modules\Learning\Application\Dto\ScheduledTermFact;
use App\Modules\Learning\Application\Dto\TermErrorFact;
use App\Modules\Learning\Application\Dto\TermPromotionFact;
use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\HomePlanReader;
use App\Modules\Learning\Application\Port\IntroducedTermsReader;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\ModeAdmissionReader;
use App\Modules\Learning\Application\Port\TriagedTermsReader;
use App\Modules\Learning\Application\Service\CardLanguageResolver;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
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

    /**
     * How many ready-made decks the home screen's shop window holds (кадры 19-2, 19-3).
     *
     * Six for a strip that shows three and a half at a time: the half-deck at the edge is what says
     * «здесь есть ещё», and a strip that ends exactly at the fold reads as the whole catalogue. The
     * older `topics` preview still publishes three of them — {@see \App\Modules\Collections\Infrastructure\Eloquent\EloquentStoreCatalogueReader}.
     */
    private const STORE_SAMPLE = 6;

    /** How far back «за неделю» looks. Seven days, which is what the word means. */
    private const LEARNED_WINDOW_DAYS = 7;

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
        /**
         * The two halves of «is the intro trainer dealt at all», read exactly as
         * {@see \App\Modules\Learning\Application\Command\BuildStudySessionHandler} reads them.
         *
         * They are here for ONE number: a first meeting is a three-card chain with the intro on and
         * a two-card one with it off, and the day card promises how long the session takes. Asking
         * a different question than the builder asks is how the promise and the session would come
         * to disagree the day somebody switched the trainer off.
         */
        private EnabledModesReader $enabledModes,
        private ModeAdmissionReader $admission,
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
        // THE DAY IN CARDS, which is the unit the session counts and the unit a minute is spent in.
        // Owed words bring the rest of their chain; a word met today brings its whole one.
        $cards = $this->home->owedCardCount($user, $now) + $new * $this->firstMeetingCards($user);
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

        // «Завтра выпадет N слов» — tomorrow SPECIFICALLY, which is a narrower question than
        // `nextReview` and gets its own count rather than a comparison of dates on the client.
        $dueTomorrow = $this->home->dueTomorrowCount($user, $dayEnd, $tz);
        $promoted = $this->home->promotionsToday($user, $now, $tz);
        $best = $this->bestPromotion($promoted);
        $learnedWeek = $this->home->graduatedSince(
            $user,
            $todayStart->modify('-' . (self::LEARNED_WINDOW_DAYS - 1) . ' days'),
        );

        $content = $this->contentFor($user, [
            ...array_map(static fn (ScheduledTermFact $f): string => $f->termId, $edge),
            ...array_map(static fn (TermErrorFact $f): string => $f->termId, $hardest),
            ...($best === null ? [] : [$best->termId]),
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
                cards: $cards,
                // Cards × seconds-per-card. Words × seconds-per-card was two units multiplied
                // together, and it under-sold every day that had a new word in it.
                estimatedMinutes: $cards > 0 ? max(1, (int) round($cards * $seconds / 60)) : null,
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
            store: new HomeStoreView(
                $store->count,
                $store->topics,
                array_map(static fn (StoreCatalogueItem $i): HomeStoreItemView => new HomeStoreItemView(
                    id: $i->id,
                    title: $i->title,
                    itemsCount: $i->itemsCount,
                    imageUrl: $i->imageUrl,
                    level: $i->level,
                ), $store->items),
            ),
            // Null, not 0, all three times. The row, the reward line and the middle number of the
            // statistics tile are each a block the design does not draw on a day that has nothing to
            // put in it, and only a null can say that.
            edgeTomorrow: $dueTomorrow > 0 ? $dueTomorrow : null,
            dayAward: $this->dayAward($promoted, $best, $content),
            learnedWeek: $learnedWeek > 0 ? $learnedWeek : null,
        );
    }

    /**
     * The day's best promotion — the word that got FURTHEST, ties broken by term id.
     *
     * Furthest rather than first, because the line is the day's one piece of good news and a word
     * reaching «написание» is a better sentence than a word reaching «узнавание». The tiebreak is
     * there so two runs of the same query name the same word: an example that changes on every
     * refresh reads as a bug even when both answers are true.
     *
     * @param  list<TermPromotionFact>  $promoted
     */
    private function bestPromotion(array $promoted): ?TermPromotionFact
    {
        $best = null;
        foreach ($promoted as $fact) {
            if ($best === null
                || $fact->toStep > $best->toStep
                || ($fact->toStep === $best->toStep && $fact->termId < $best->termId)) {
                $best = $fact;
            }
        }

        return $best;
    }

    /**
     * «+5 слов продвинулись · reluctant дошло до „написание"», or nothing at all.
     *
     * Nothing at all in two cases, and they are the same case: the day promoted no word, or it
     * promoted words whose content will not render. A count with no example would be «+5 слов
     * продвинулись ·» trailing into nothing, which is worse than the silence.
     *
     * @param  list<TermPromotionFact>  $promoted
     * @param  array<string, TermContentView>  $content
     */
    private function dayAward(array $promoted, ?TermPromotionFact $best, array $content): ?HomeDayAwardView
    {
        if ($best === null) {
            return null;
        }

        $view = $content[$best->termId] ?? null;
        if ($view === null) {
            return null;
        }

        return new HomeDayAwardView(count($promoted), $best->termId, $view->text, $best->toStep);
    }

    /**
     * How many cards ONE never-met word costs today: the whole chain from the rung it starts on.
     *
     * With the intro trainer on that is rung 0 and three cards; with it off a first meeting starts
     * at recognition and it is two. The condition is the session builder's own, term for term —
     * a plan that answered this question its own way would price a day the trainer does not deal.
     */
    private function firstMeetingCards(UserId $user): int
    {
        $introDealt = $this->enabledModes->forUser($user)->has(ExerciseMode::Intro)
            && $this->admission->matrixFor($user)->allows(ExerciseMode::Intro, LearningLadder::STEP_INTRO);

        return LearningLadder::chainLength(
            $introDealt ? LearningLadder::STEP_INTRO : LearningLadder::STEP_RECOGNITION_FORWARD,
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
