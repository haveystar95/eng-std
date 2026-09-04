<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\PlanDayCard;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;

/**
 * A2, second half — the order a day's material is INTRODUCED in, and канон §11 made mechanical.
 *
 * FOUR BLOCKS, ALWAYS THE SAME FOUR, AND THE LEVEL DOES NOT MOVE THEM:
 *
 *   1. «слова и связки» — the pieces, then the connectors, which are pieces of a bigger shape
 *   2. «Тебе скажут»    — the interlocutor's turns, understood and never said
 *   3. «Ты ответишь»    — the replies the learner says, assembled out of 1
 *   4. «Ты спросишь»    — the questions that buy time and detail
 *
 * ## The role line used to be LAST, and канон §11 moved it to third
 *
 * The old order put the interlocutor's line at the end, on the argument that it is the one card the
 * learner never produces and so belongs «after the ones that are being learned». The canon's order
 * is the SCENE's: you hear what is said to you, and then you answer it. Reading the doctor's
 * question after having already practised every reply to it is a rehearsal run backwards — and
 * since SIT-1 it is also mechanically wrong, because the situational card of «Ты ответишь» shows
 * that very line as the position the learner is answering from
 * ({@see \App\Modules\Learning\Domain\Service\SituationalPrompt}).
 *
 * «Ты ответишь» and «Ты спросишь» are told apart for the first time here, for the same reason: they
 * are two sections of the sitting with two captions, and `kind` calls them both `line`.
 *
 * Within a block, easy first ({@see \App\Modules\Shared\Domain\Service\DifficultyScorer}). A day
 * opening on its hardest sentence is a day the learner bounces off.
 *
 * ## The level used to decide, and the live day is why it no longer does
 *
 * The old rule was two blocks and an inversion: below `conversational` the words came first, from
 * `conversational` up the replies did, because «the reply is the useful unit and the words inside
 * it are recognised on the way past». The owner's plan on 01.09 was `conversational`, so day 1
 * opened on a fifteen-word reply — and the words inside it were NOT recognised on the way past,
 * because there is no card that does that. Recognition is a card of its own, dealt when that word's
 * own ladder gets there, which on that day was after every reply had been dealt.
 *
 * So the inversion was describing a lesson the machine does not run. The pieces before the sentence
 * they build is the order the material actually has, at every level; what a `fluent` learner gets
 * out of the same day is a shorter checklist per card, not a different running order. `PlanLevel`
 * still decides plenty — how many options a choice card carries, which trainers are open — and it
 * decides nothing here.
 *
 * The role line last is the second half of the same sentence: it is the one card of a day the
 * learner will never produce ({@see \App\Modules\Learning\Application\Service\PlanStandings::PRODUCTION_MODES}),
 * so it is the one card whose place in the day is «after the ones that are being learned».
 *
 * Pure. It orders ids and nothing else, so it can be tested against the sandbox's real days without
 * a database — and so that when the session assembler picks this up (1b) it is picking up a
 * decision that has already been made and checked, not making a second one.
 */
final class PlanDayOrder
{
    /**
     * THE BLOCKS OF THE INTRODUCTION, and they are this class's own vocabulary.
     *
     * They used to be {@see PlanSessionSections}'s `HEAR`/`SAY`/`ASK`, back when each shelf was a
     * SECTION of the sitting with a caption of its own. DAY-2 folded the three into one part —
     * «знакомство с репликами сцены» — because on the day a scene arrives, meeting its lines is one
     * thing that happens (канон §10). The ORDER inside that part is unchanged and still канон §11's:
     * you hear what will be said to you, then what you answer, then what you ask.
     *
     * So the two vocabularies came apart: a SECTION is what the learner is told they are doing, and
     * these are the buckets that decide which card comes next inside one of them.
     */
    private const BLOCK_HEAR = 'hear';

    private const BLOCK_SAY = 'say';

    private const BLOCK_ASK = 'ask';

    /** A card of a day written before shelves existed — one undivided block, as it always was. */
    private const BLOCK_REST = 'rest';

    /**
     * @param  list<PlanDayCard>  $cards
     * @param  PlanLevel  $level  read no more: kept on the signature because the caller has it and
     *         because «the level does not decide the running order» is a statement worth being able
     *         to see at the call site.
     * @return list<string>  term ids, in the order the day introduces them
     */
    public function order(array $cards, PlanLevel $level): array
    {
        // The parts of the day, in канон §11's order — and the `words` block keeps its own internal
        // order (word before chunk, DECISIONS 217), which is why it is two buckets and one section.
        $blocks = [
            PlanStageLadder::KIND_WORD => [],
            PlanStageLadder::KIND_CHUNK => [],
            self::BLOCK_HEAR => [],
            self::BLOCK_SAY => [],
            self::BLOCK_ASK => [],
        ];

        foreach ($cards as $card) {
            $blocks[$this->blockOf($card)][] = $card;
        }

        $ordered = [];
        foreach ($blocks as $block) {
            $this->sortByDifficulty($block);
            $ordered = [...$ordered, ...$block];
        }

        return array_map(static fn (PlanDayCard $c): string => $c->termId, $ordered);
    }

    /**
     * Which of the four blocks this card belongs to.
     *
     * A kind this build does not know rides with the WORDS, for the same reason
     * {@see PlanStageLadder::normalizeKind()} gives it the word's ladder: it is the conservative
     * answer, and every term written before plans existed has no kind at all.
     */
    private function blockOf(PlanDayCard $card): string
    {
        // THE SHELF DECIDES, and it decides first: it is the fact the day was written with, and the
        // only one that can tell «Ты ответишь» from «Ты спросишь».
        $block = match ($card->shelf) {
            self::BLOCK_HEAR => self::BLOCK_HEAR,
            self::BLOCK_SAY => self::BLOCK_SAY,
            self::BLOCK_ASK => self::BLOCK_ASK,
            'words', 'chunks' => self::BLOCK_REST,
            // NO SHELF — a day written before v0.4, or a term re-used from outside a plan. The old
            // two-and-two reading is still the best available: a line the interlocutor says is
            // recognised and everything else is a piece.
            default => $card->isLine()
                ? ($card->isRoleLine ? self::BLOCK_HEAR : self::BLOCK_SAY)
                : self::BLOCK_REST,
        };

        if ($block !== self::BLOCK_REST) {
            return $block;
        }

        return $card->kind === PlanStageLadder::KIND_CHUNK
            ? PlanStageLadder::KIND_CHUNK
            : PlanStageLadder::KIND_WORD;
    }

    /**
     * Easiest first, and an UNSCORED card sorts as if it were easy.
     *
     * Not «last» and not «hardest»: a null score means nobody has judged this term, which is the
     * state of every term written before plans existed. Sinking them to the bottom would put a
     * whole re-used vocabulary at the end of the day for a reason that is about our data and not
     * about the language. Ties keep their incoming order (`usort` is stable in PHP 8), which is the
     * model's own order — the one it wrote the day in.
     *
     * @param  list<PlanDayCard>  $cards
     */
    private function sortByDifficulty(array &$cards): void
    {
        usort(
            $cards,
            static fn (PlanDayCard $a, PlanDayCard $b): int => ($a->difficultyScore ?? 0) <=> ($b->difficultyScore ?? 0),
        );
    }
}
