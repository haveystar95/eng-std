<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\FreshCore;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ReplaceTermCore;
use App\Modules\Vocabulary\Application\Command\ReplaceTermCoreHandler;

/**
 * Putting a freshly generated CORE onto a term that already exists — key, pronunciation, level, and
 * the one example — in the one order that is safe.
 *
 * Two paths do this and they must do it identically:
 *
 *  - the showcase regeneration, which buys a fresh core on purpose for content a defect sweep has
 *    judged bad;
 *  - the dedup merge inside a normal generation, which gets one for free: the model wrote a full
 *    core for a term it did not know was already in the store.
 *
 * The second was written after the first, and duplicating the pair of writes is exactly how the A1
 * repair would have been half-applied — one caller replacing the example through
 * {@see ExampleReplacement} and keeping the distractors that survive, the other reaching for
 * Vocabulary's replace command directly and taking every distractor down with the row. So the pair
 * lives here once.
 *
 * ## What it never touches
 *
 * `user_term_progress` and `reviews`. Rewriting the words is not a statement about who has learned
 * them, and the review log is append-only history. The term's `text` is not rewritten either — the
 * term IS the identity of the row.
 */
final readonly class CoreReplacement
{
    public function __construct(
        private ReplaceTermCoreHandler $cores,
        private ExampleReplacement $examples,
    ) {}

    /**
     * @param  string  $translationLang  the language the fresh key is written in
     * @param  string  $promptVersion  the prompt file that produced this core; it becomes the term's
     *         passport, so a later sweep can ask which prompt wrote the line it is reading
     * @param  string  $model  the model that answered
     * @return bool  false when there is no such live term — nothing was written
     */
    public function apply(
        TermId $termId,
        FreshCore $core,
        string $translationLang,
        string $promptVersion,
        string $model,
    ): bool {
        $replaced = ($this->cores)(new ReplaceTermCore(
            termId: $termId,
            translation: $core->translation,
            translationLang: $translationLang,
            promptVersion: $promptVersion,
            generationModel: $model,
            ipa: $core->ipa,
            cefr: $core->cefr,
            // The image query is deliberately left as it was: the term already carries a photo
            // fetched from it, and a fresh query would either be ignored (the photo is not
            // re-fetched) or, worse, describe a picture the card is not showing.
            imageApiPrompt: null,
        ));

        if (! $replaced) {
            return false;
        }

        if ($core->example !== null && trim($core->example) !== '') {
            // Through the repaired A1 path: the example row is updated IN PLACE, so the distractors
            // that still describe it survive, the ones the new sentence orphans are dropped, and the
            // term is re-opened for the станок only when something actually went.
            $this->examples->apply(
                $termId,
                $core->example,
                $core->exampleTranslation,
                // The example's gloss is written in the same language as the fresh key — one core,
                // one model call, one support language.
                $translationLang,
                $promptVersion,
                $model,
            );
        }

        return true;
    }
}
