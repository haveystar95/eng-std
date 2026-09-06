<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * What a plan day knows about a term that nothing else asks: what the expression DOES in a day
 * («line», «word» or «chunk»), the frame it stands in, whose turn it is, and how much machinery
 * it carries.
 *
 * Written for EVERY term of a day, including one the store already had from a collection — such a
 * term has never been asked any of these questions, and `false, null, null, null` on it is absence
 * rather than a considered answer. Overwriting absence with knowledge is the one case where a
 * write-once rule would be wrong.
 *
 * `kind` and `frame` are what the SESSION reads: the stage checklist a card is dealt depends on
 * the first ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}), and the gap of a
 * cloze card is cut from the second. A term re-imported by a later plan day gets the later day's
 * frame, which is correct — the frame belongs to the day being taught.
 *
 * `filler` is the third of that set and arrived with v0.3: the string that stands in the frame's
 * hole, which is what the cloze gap blanks. Stored rather than re-derived from `text` minus
 * `frame`, because that derivation is right nine times and quietly wrong the tenth.
 *
 * `shelf` and `tier` arrived with v0.4 and are the pair the whole day contract turns on. The shelf
 * is «Тебе скажут» / «Ты ответишь» / «Ты спросишь» / слова / связки / цифры — the caption the card
 * is dealt under and the thing `kind` alone cannot say, since «say» and «ask» are both `line`s the
 * learner speaks and «hear» is a `line` they never will. The tier is DERIVED from the shelf by the
 * server ({@see \App\Modules\Generation\Domain\ValueObject\PlanShelf::tier()}) and stored
 * beside it, because it decides which ladder the card climbs and a card whose tier had to be
 * recomputed by every reader would eventually be recomputed differently by one of them.
 *
 * `skillRef` is «почему я это учу», mechanically (канон §8): the id of the scene skill this card
 * serves. `numberValue` is the digits a `numbers` card is graded on — the one field that never
 * appears on the screen, which is why nothing but a gate could notice it being wrong.
 *
 * `speakingKey` is a DECISION rather than a copy: which string a spoken line
 * is graded on ({@see \App\Modules\Generation\Domain\Service\PlanSpeakingKey}). Usually the
 * filler; on a formula it may be another card of the same day; sometimes nothing, which means «say
 * the whole line». Only the code that writes the day can see all three, so it is stored here rather
 * than worked out again by the grader.
 */
interface TermPlanFactsWriter
{
    /** @param list<string>|null $speakingKeys */
    public function write(
        TermId $termId,
        bool $isLine,
        ?int $difficultyScore,
        ?string $kind = null,
        ?string $frame = null,
        ?string $speaker = null,
        ?string $filler = null,
        ?string $speakingKey = null,
        ?string $shelf = null,
        ?string $tier = null,
        ?string $skillRef = null,
        ?string $numberValue = null,
        /**
         * WHAT ELSE COUNTS WHEN THE LINE IS SPOKEN — 1–2 simpler forms beside `speakingKey`
         * (наряд GEN-1, P2 v0.7). Null on everything that is not a spoken plan line.
         *
         * @var list<string>|null
         */
        ?array $speakingKeys = null,
    ): void;
}
