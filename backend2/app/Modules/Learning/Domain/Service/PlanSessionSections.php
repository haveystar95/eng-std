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

    /**
     * ЗНАКОМСТВО С РЕПЛИКАМИ СЦЕНЫ — stage A of `hear`, `say` and `ask`, one part (наряд DAY-2).
     *
     * It used to be three: {@see HEAR}, {@see SAY}, {@see ASK}, one seam each. Канон §10 puts them
     * back together — «слова и связки → знакомство с репликами сцены → диалог сцены» — because on
     * the day a scene is introduced the three shelves are ONE thing that happens: you meet the lines
     * of this conversation. Telling the learner they have crossed from «Тебе скажут» into «Ты
     * ответишь» is a fact about our storage, not about their evening.
     */
    public const DIALOGUE_INTRO = 'dialogue_intro';

    /**
     * ДИАЛОГ СЦЕНЫ — stage B of those same three shelves, played as one conversation.
     *
     * The whole of DAY-2 Ч.3: the exchange is the unit, not the card
     * (`docs/plan-dialogue.md` §1), so the cards of a scene's stage B are ONE part of the sitting,
     * ordered by that scene's own chain ({@see PlanDialogueChain}) and drawn as a dialogue. This is
     * what took the place of the three separately-captioned situational cards.
     *
     * A присест is only ever cut BETWEEN parts ({@see PlanSittings}), which is what makes «рез не
     * режет обмен пополам» a property of the layout rather than a rule somebody has to remember.
     */
    public const DIALOGUE = 'dialogue';

    /** «Цифры на слух» — канон §6. No session deals one yet (NUM-1); the code exists so it can. */
    public const NUMBERS = 'numbers';

    /** «Прогон сцены» — the final day's run-through, and later SCENE-RUN. */
    public const REHEARSAL = 'rehearsal';

    /** «Повторение · из прошлых дней» — this plan's earlier material, after the day. */
    public const REVIEW = 'review';

    /**
     * A card of a day written before shelves existed: one undivided block, which is what those days
     * have always been drawn as.
     */
    public const DAY = 'day';

    /** The parts of a scene's own material, in the order канон §10 fixes them. */
    /** @var list<string> */
    public const DAY_ORDER = [
        self::WORDS,
        self::DIALOGUE_INTRO,
        self::DIALOGUE,
        self::NUMBERS,
        self::REHEARSAL,
        self::DAY,
    ];

    /** The whole sitting, in order: the warm-up, the day, the revision. */
    /** @var list<string> */
    public const ORDER = [self::WARMUP, ...self::DAY_ORDER, self::REVIEW];

    /**
     * WHICH PART A CARD BELONGS TO — its shelf, and the rung it is being dealt at.
     *
     * The rung reaches this decision since DAY-2 and it has to: the three line shelves are ONE part
     * on the day they are met («знакомство») and ONE part on the day they are spoken («диалог»), and
     * the only thing that tells those apart is the stage. Everything else answers on the shelf
     * alone, exactly as it did.
     *
     * A shelf this build has never heard of falls through to {@see DAY} — the undivided block —
     * rather than becoming a part of its own: an unnamed part is a seam with no caption, and a seam
     * the learner cannot read is worse than no seam.
     *
     * @param  string|null  $stage  `a` | `b` | `c`, or null when the sitting has no stages (the
     *                              warm-up's light touch, the final day's run-through)
     */
    public static function ofShelf(?string $shelf, ?string $stage = null): string
    {
        return match ($shelf) {
            'words', 'chunks' => self::WORDS,
            'numbers' => self::NUMBERS,
            // Stage A is the introduction; anything past it is the conversation. A card with NO
            // stage on a line shelf is a run-through card, and a run-through is not a dialogue —
            // it belongs to the part that says so.
            'hear', 'say', 'ask' => match ($stage) {
                'a' => self::DIALOGUE_INTRO,
                'b', 'c' => self::DIALOGUE,
                default => self::REHEARSAL,
            },
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
