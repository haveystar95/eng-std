<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Vocabulary\Domain\Repository\TermRepository;
use App\Modules\Vocabulary\Domain\ValueObject\Translation;

final readonly class PinTermTranslationHandler
{
    public function __construct(private TermRepository $terms) {}

    public function __invoke(PinTermTranslation $command): void
    {
        if (trim($command->text) === '') {
            return;
        }

        $term = $this->terms->findById($command->termId);
        if ($term === null) {
            // The term vanished between the save and this call. Nothing to pin and nothing to
            // repair — a missing term is not a failed pin, and throwing here would turn a race into
            // a 500 on the one tap the learner cares about.
            return;
        }

        // No provenance: nobody generated this line, a person chose it. Leaving the stamp null is
        // what `prompt_version` NULL means everywhere else in the schema — «not written by a
        // prompt» — and inventing one would name a model that never saw this string.
        $term->pinTranslation(new Translation($command->lang, $command->text, true));
        $this->terms->save($term);
    }
}
