<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\TranscriptLine;

/**
 * Everything the summariser needs: the language to write the recap in ({@see $nativeLang}), the
 * language being practised, the topic, and the transcript. The summary is written in the native
 * language so the learner reads their feedback without friction.
 */
final readonly class DialogSummaryBrief
{
    /**
     * @param  list<TranscriptLine>  $lines
     * @param  ?string  $collectionId  which deck this practice belonged to — carried purely so the
     *                                 adapter can label the outbound call, since the recap's own
     *                                 spend has no ledger of its own.
     */
    public function __construct(
        public string $nativeLang,
        public string $targetLang,
        public string $topic,
        public array $lines,
        public ?string $collectionId = null,
    ) {}
}
