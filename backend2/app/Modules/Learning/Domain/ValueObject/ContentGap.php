<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * WHY a trainer's card cannot be built from a term's content — the machine-readable half of a
 * {@see ContentStatus} that is not `ok`.
 *
 * A closed set on purpose: a back-office screen groups by it («сколько терминов упирается в
 * отсутствие примера»), and free text would rot the moment two call sites phrased the same gap
 * differently. Each case is one clause of {@see TermPlayability::supports()} — nothing here is a
 * new rule, it is the existing gate read backwards.
 */
enum ContentGap: string
{
    /**
     * word_bank: the answer yields ONE chip, and a one-chip card asks nothing. A single word is
     * assembled from its letters now (BUGFIX-2 Ч.2б D2), so this is reached only by a one-letter
     * answer — the name is kept because the wire value is stored and read by the admin panel.
     */
    case SingleWord = 'single_word';

    /** No pinned example at all — cured by regenerating the example, never by the станок. */
    case NoExample = 'no_example';

    /** cloze: the example exists but does not contain the answer, so there is nothing to blank. */
    case ExampleLacksTerm = 'example_lacks_term';

    /** The example tokenizes to the term itself — scrambling or dictating it repeats another mode. */
    case ExampleIsTerm = 'example_is_term';

    /** scramble/pick_correct: the PROMPT is the example's translation, and there isn't one. */
    case NoExampleTranslation = 'no_example_translation';

    /** Below the mode's sentence-length floor — too little sentence to assemble or hear. */
    case ExampleTooShort = 'example_too_short';

    /** Above the mode's sentence-length ceiling — a chore rather than a drill. */
    case ExampleTooLong = 'example_too_long';

    /** pick_correct: fewer span-distinct distractors than the card's floor. THE станок's job. */
    case TooFewDistractors = 'too_few_distractors';

    /**
     * description_match: the term has no description in the language being learned, and the
     * description IS the card's question. Cured by the станок / a search lookup, never by content
     * the term already has — which is why the showcase, written before descriptions existed, is
     * blocked on this and is deliberately not being backfilled.
     */
    case NoDescription = 'no_description';

    /** Not a gap in the term: the wrong options come from other words in the pool. */
    case OptionsFromPool = 'options_from_pool';
}
