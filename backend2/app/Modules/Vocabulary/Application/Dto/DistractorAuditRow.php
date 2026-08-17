<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * One stored distractor next to the example it claims to be a broken version of — everything an audit
 * needs to re-ask the questions the validator asks at write time, about content written before the
 * validator asked them.
 *
 * `termText` travels with the row because every edit a review makes is addressed by term TEXT, not by
 * id: ids are regenerated when the database is rebuilt, the text is the decision.
 */
final readonly class DistractorAuditRow
{
    public function __construct(
        public string $termId,
        public string $termText,
        public string $exampleId,
        public string $exampleSentence,
        public string $sentence,
        public string $errorType,
        public string $errorSpan,
        public string $correction,
        public string $generatorVersion,
    ) {}
}
