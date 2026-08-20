<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * The RAW content of one term, as the projection read it out of Postgres — before anything is
 * decided about it.
 *
 * Deliberately un-judged: whether these facts add up to a playable card is Learning's question, and
 * it is asked once, through
 * {@see \App\Modules\Learning\Application\Service\ContentRequirementsResolver}. This object exists so
 * the SQL side and the judgement side cannot quietly grow two different ideas of what «дистрактор»
 * or «пример» means — the projection selects, the resolver rules.
 *
 * `example*` is the PINNED example (lowest id) and nothing else: that is the only sentence the card
 * and the client ever show, and the only one distractors hang off.
 */
final readonly class ContentTermFacts
{
    /**
     * @param  list<string>  $distractorSpans  `error_span` of every distractor row on the pinned
     *                                         example, VERBATIM and unfiltered — the «годный» count
     *                                         is derived by Learning, not here
     * @param  list<string>  $collectionIds    live collections holding this term
     */
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        public string $type,
        public ?string $exampleId,
        public ?string $exampleSentence,
        public ?string $exampleTranslation,
        public array $distractorSpans,
        public int $variantCount,
        public ?string $enrichmentVersion,
        public array $collectionIds,
    ) {}
}
