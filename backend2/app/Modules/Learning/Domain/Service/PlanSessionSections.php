<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * THE FIVE PARTS OF A SITTING, named once — канон §11 made mechanical.
 *
 * «Разогрев → слова/связки → „Тебе скажут“ → „Ты ответишь“ → „Ты спросишь“» is a fixed, server-side
 * order, and this is the vocabulary it is stated in. Two readers need exactly the same answer and
 * for different reasons, which is why the grouping is a class rather than a `match` inside either:
 *
 *   the RUNNING ORDER ({@see PlanDayOrder}) sorts the day's cards into these parts;
 *   the SITTING CUTTER ({@see PlanSittings}) is only ever allowed to cut BETWEEN them.
 *
 * `words` and `chunks` are one part on purpose (канон §2 puts them under a single heading, «слова и
 * связки»), so a seam between them would announce a change the learner cannot see. `numbers` is
 * absent because no session deals a number yet (NUM-1) and a part with no cards is not a part.
 *
 * The CAPTIONS are not here and must not be: the wording is the client's, in two languages, exactly
 * as it is for `origin` and `speaker` on the task envelope. What the server owns is the order and
 * the boundaries.
 */
final class PlanSessionSections
{
    /** The rescue kit plus yesterday's misses — before the day, every day (канон §5). */
    public const WARMUP = 'warmup';

    /** «Слова и связки»: the pieces the scene's lines are built from. */
    public const WORDS = 'words';

    /** «Тебе скажут»: the interlocutor's turns. */
    public const HEAR = 'hear';

    /** «Ты ответишь». */
    public const SAY = 'say';

    /** «Ты спросишь». */
    public const ASK = 'ask';

    /** «Повторение · из прошлых дней» — this plan's earlier material, after the day. */
    public const REVIEW = 'review';

    /**
     * A card of a day written before shelves existed: one undivided block, which is what those days
     * have always been drawn as.
     */
    public const DAY = 'day';

    /** The parts of the day's own material, in the order канон §11 fixes them. */
    /** @var list<string> */
    public const DAY_ORDER = [self::WORDS, self::HEAR, self::SAY, self::ASK, self::DAY];

    /** The whole sitting, in order: the warm-up, the day, the revision. */
    /** @var list<string> */
    public const ORDER = [self::WARMUP, ...self::DAY_ORDER, self::REVIEW];

    /**
     * Which part a card belongs to, from the shelf it stands on.
     *
     * A shelf this build has never heard of falls through to {@see DAY} — the undivided block —
     * rather than becoming a part of its own: an unnamed part is a seam with no caption, and a seam
     * the learner cannot read is worse than no seam.
     */
    public static function ofShelf(?string $shelf): string
    {
        return match ($shelf) {
            'words', 'chunks' => self::WORDS,
            'hear' => self::HEAR,
            'say' => self::SAY,
            'ask' => self::ASK,
            default => self::DAY,
        };
    }

    /** Where this part stands in the running order; an unknown one sorts with the day's block. */
    public static function rankOf(string $section): int
    {
        $rank = array_search($section, self::ORDER, true);

        return $rank === false ? array_search(self::DAY, self::ORDER, true) : $rank;
    }
}
