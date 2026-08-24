<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Vocabulary\Application\Port\TermCurator;

/**
 * Applies a curation edit. Returns false when the term (or the named example) isn't there, so the
 * caller can answer 404 instead of pretending something was saved.
 */
final readonly class CurateTermHandler
{
    public function __construct(private TermCurator $curator) {}

    public function __invoke(CurateTerm $command): bool
    {
        $ok = $this->curator->updateContent(
            $command->termId,
            $command->text,
            $command->translation,
            $command->ipa,
            $command->translationLang,
        );
        if (! $ok) {
            return false;
        }

        if ($command->touchesExample()) {
            return $this->curator->updateExample(
                $command->termId,
                (string) $command->exampleId,
                (string) $command->exampleSentence,
                $command->exampleTranslation,
                // The same learner language the term's own translation is edited in: the panel edits
                // ONE side of ONE pair at a time, and the example gloss is on that side.
                $command->translationLang,
            );
        }

        return true;
    }
}
