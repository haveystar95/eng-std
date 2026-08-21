<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

use App\Modules\Generation\Domain\Service\EnrichmentValidator;

/**
 * WHICH check of {@see \App\Modules\Generation\Domain\Service\EnrichmentValidator} decided a
 * distractor's fate — one case per `continue` in its distractor loop, in the order they run.
 *
 * The validator itself does not need this: it returns what survived, and the run reports a scrap
 * RATE, which is the number that matters when twenty terms go through at once. What has never been
 * answerable is «почему выбросило вот эту строку», and the answer was previously obtainable only by
 * reading the loop with the row in hand. That is a question a person asks about ONE row, in a
 * sandbox, while deciding whether the prompt or the model is at fault — so the enum lives here and
 * the validator merely names the case it took ({@see DistractorGateLog}); no decision is changed by
 * anybody recording it.
 *
 * The Russian wording is Domain's, next to the rule it describes, for the same reason
 * {@see \App\Modules\Learning\Domain\Service\ModePassport::reasonFor()} keeps its wording in Domain:
 * the sentence IS the rule stated in words, and a panel that re-phrased it would drift from the gate.
 */
enum DistractorGate: string
{
    /** The whole term has no pinned example: nothing to hang a distractor on, so every row is scrap. */
    case NoExample = 'no_example';

    /** Empty sentence, or one that normalises to nothing at all. */
    case EmptySentence = 'empty_sentence';

    /**
     * The sentence was already seen — a row stored against this example, a sentence a proofreader
     * or the audit suppressed, a twin from a sibling term, or an earlier row of this very batch.
     * The validator cannot tell those apart (they arrive as one list); the caller that BUILT the list
     * can, and labels it.
     */
    case Duplicate = 'duplicate';

    /** Our own grader would accept this «wrong» sentence — it is the answer, not a distractor. */
    case EqualsAcceptedAnswer = 'equals_accepted_answer';

    /** The same sentence was proposed as a correct variant AND as a distractor. One claim is false. */
    case VariantConflict = 'variant_conflict';

    /** `error_type` is not one of the six the schema allows. */
    case UnknownErrorType = 'unknown_error_type';

    /** The span is empty, or does not occur in the sentence it claims to be a fragment of. */
    case SpanNotFound = 'span_not_found';

    /** The span sits inside the term's own accepted wording — marking the right answer as a mistake. */
    case SpanInsideAcceptedForm = 'span_inside_accepted_form';

    case EmptyCorrection = 'empty_correction';

    /** The correction swallowed the sentence's final «.»/«?» — substituting it back doubles it up. */
    case CorrectionCarriesSentenceEnd = 'correction_carries_sentence_end';

    /** The «wrong» sentence IS the pinned example. */
    case EqualsExample = 'equals_example';

    /** span → correction changes nothing: the card underlines a fragment and offers it back. */
    case NoOpCorrection = 'no_op_correction';

    /** The circular check: applying the row's own repair does not give back the example. */
    case RepairDoesNotMatchExample = 'repair_does_not_match_example';

    /** Survived every check. */
    case Kept = 'kept';

    /**
     * Never examined: the example already had {@see EnrichmentValidator::MAX_DISTRACTORS} keepers, so
     * the loop stopped. Reported rather than hidden — «выбросило» and «не дошли руки» are different
     * facts about a row, and a sandbox that showed the second as the first would send someone
     * rewriting a prompt that was fine.
     */
    case CapReached = 'cap_reached';

    /** One sentence a person reads. Russian, like every operator-facing string in this codebase. */
    public function reason(): string
    {
        return match ($this) {
            self::NoExample => 'у термина нет закреплённого примера — дистрактору не на чём держаться.',
            self::EmptySentence => 'пустое предложение (или оно нормализуется в пустоту).',
            self::Duplicate => 'такое предложение уже есть — в базе, среди подавленных или выше в этом же списке.',
            self::EqualsAcceptedAnswer => 'наш же грейдер засчитал бы это как верный ответ — это не дистрактор.',
            self::VariantConflict => 'то же предложение предложено и как верный вариант, и как дистрактор.',
            self::UnknownErrorType => 'error_type не из шести разрешённых значений.',
            self::SpanNotFound => 'error_span пуст или не встречается в своём же предложении — подчёркивать нечего.',
            self::SpanInsideAcceptedForm => 'error_span попал внутрь собственной формулировки термина — это разметка верного ответа как ошибки.',
            self::EmptyCorrection => 'пустое correction.',
            self::CorrectionCarriesSentenceEnd => 'correction утащил финальный знак предложения — подстановка удвоит его.',
            self::EqualsExample => 'дистрактор совпадает с эталонным примером.',
            self::NoOpCorrection => 'correction ничего не исправляет: span и correction совпадают.',
            self::RepairDoesNotMatchExample => 'круговая проверка не сошлась: замена span→correction не даёт эталонный пример.',
            self::Kept => 'прошёл все проверки.',
            self::CapReached => 'лимит ' . EnrichmentValidator::MAX_DISTRACTORS . ' дистрактора на пример уже набран — строку не рассматривали.',
        };
    }

    public function isKept(): bool
    {
        return $this === self::Kept;
    }
}
