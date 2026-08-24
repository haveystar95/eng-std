<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * WHAT a generation call is asked to produce. Orthogonal to the prompt VERSION, which says which
 * edition of the rules applies: `v10` + `Terms` and `v10` + `Full` are the same rules asked for two
 * different products, and both must be recorded, because a defect rate is only comparable within a
 * shape.
 *
 * The three exist because the pipeline itself is the open question. Today a collection is built in
 * two steps — a list of terms, then the enrichment станок over each term — and `Full` is the one-shot
 * alternative: everything in a single call. They are measured against each other rather than argued
 * about.
 */
enum PromptShape: string
{
    /** A topic in, a list of terms with translations out. What production generation does today. */
    case Terms = 'terms';

    /** Existing terms in, full content for each out (translation, example, options). The станок's job. */
    case Enrich = 'enrich';

    /**
     * FINISHED cards in, exercise machinery out — wrong-answer options and accepted forms, nothing
     * else. The split that separates paying for content from paying for machinery: a core that has
     * been reviewed must not be re-generated to obtain the options around it.
     */
    case Mechanics = 'mechanics';

    /** A topic in, a finished collection out — terms AND their full content, in one call. */
    case Full = 'full';

    /**
     * FINISHED cards in, the machinery this app actually STORES out: the accepted forms of the term
     * (its answer key beyond `text`) and the wrong versions of its example sentence.
     *
     * Distinct from {@see Mechanics}, which produces wrong TRANSLATIONS to sit beside the right one.
     * That is a real product and it is not this one: the trainer builds its meaning options for free
     * out of the neighbouring terms in the same session, while the wrong-sentence options of
     * `pick_correct` can only come from a model — and nothing in this app can store a wrong
     * translation. A shape is what a call PRODUCES, so two different products are two cases, and
     * production asks for the one whose fields have somewhere to land.
     */
    case Machinery = 'machinery';

    /** Does this shape pick its own items from a topic, rather than being handed them? */
    public function selectsItems(): bool
    {
        return $this === self::Terms || $this === self::Full;
    }

    /** Does this shape produce a card's wrong-answer options — wrong TRANSLATIONS beside the right one? */
    public function hasOptions(): bool
    {
        return $this !== self::Terms && $this !== self::Machinery;
    }

    /** Does this shape produce wrong versions of the card's example sentence? */
    public function hasDistractors(): bool
    {
        return $this === self::Machinery;
    }

    /**
     * Does this shape produce the CORE of a card — the term's translation and example?
     *
     * False for {@see Mechanics}, and that is the whole point of it: the core it is handed has
     * already been paid for and reviewed, so the checks that judge a core (the key rules, the
     * example rule) do not apply to an answer that was forbidden to produce one.
     */
    public function producesCore(): bool
    {
        return $this !== self::Mechanics && $this !== self::Machinery;
    }

    /** Does this shape produce the accepted FORMS of a term (its answer key beyond `text`)? */
    public function hasForms(): bool
    {
        return $this === self::Mechanics || $this === self::Machinery;
    }

    /**
     * Does this shape produce near-SYNONYMS of the term — other words on the studied side that mean
     * nearly the same thing (`purpose` → `goal`, `aim`)?
     *
     * Only the machinery shape, and only from prompt v14. It is a different product from
     * {@see hasForms()} and the difference is what each one is allowed to do downstream: a form is
     * another spelling of the same word and widens the answer key everywhere, a synonym is another
     * word and widens it only on a card that asked what the term MEANS. Two products, two fields,
     * two tables — see the `term_synonyms` migration.
     */
    public function hasSynonyms(): bool
    {
        return $this === self::Machinery;
    }
}
