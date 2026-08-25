<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * What happened to one term's reading hint — and, for the four outcomes that are not `Written`, WHY
 * nothing was written.
 *
 * A boolean would collapse the two that must never be confused when the bill is read: a term the
 * store already had a hint for cost nothing, and a term whose answer the gate refused cost a call.
 * The handler cannot say so itself — logging is not Application's to do — so it returns this and the
 * job that called it writes the line.
 */
enum TermReadingOutcome: string
{
    /** The hint was bought, passed the alphabet gate, and is in the table. */
    case Written = 'written';

    /** `GENERATION_WRITE_TRANSLITERATION` is off. Nothing was asked and nothing was spent. */
    case Disabled = 'disabled';

    /** This (term, support language) already had one — the term is global and someone got there first. */
    case AlreadyPresent = 'already_present';

    /**
     * The term is already written in the support language's own alphabet, so the prompt's own answer
     * here is an empty string. Refused BEFORE the call rather than after it: buying «» is still
     * buying, and on a same-script pair (en↔ro) it would be bought on every save forever.
     */
    case SameAlphabet = 'same_alphabet';

    /** The model answered, and the alphabet gate threw the answer away. The call is paid for. */
    case Refused = 'refused';
}
