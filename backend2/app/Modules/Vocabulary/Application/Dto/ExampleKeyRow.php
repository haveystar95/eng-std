<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * One (example sentence, its translation) pair — the OTHER string the learner is asked to reproduce.
 *
 * The term is not the only key in a card. An example is shown, spoken and answered exactly like the
 * term, so a sentence translation that drops the addressee makes the same answer unanswerable: «Tell
 * us about a challenge you faced and how you overcame it» → «Расскажите о вызове, с которым вы
 * столкнулись, и как вы его преодолели» loses «нам» just as the term did, and the audit that only
 * read terms could not see it.
 *
 * The term text rides along because a proof-reader needs to know which card the sentence belongs to.
 */
final readonly class ExampleKeyRow
{
    public function __construct(
        public string $termId,
        public string $termText,
        public string $exampleId,
        public string $sentence,
        public string $translation,
    ) {}
}
