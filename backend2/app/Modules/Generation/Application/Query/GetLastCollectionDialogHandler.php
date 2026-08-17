<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Generation\Application\Dto\LastCollectionDialogView;
use App\Modules\Generation\Application\Service\DialogCoverage;
use App\Modules\Generation\Domain\Repository\PracticeDialogMessageRepository;
use App\Modules\Generation\Domain\Repository\PracticeDialogRepository;

/**
 * Reads the result of a collection's most recent concluded (finished|expired) dialog. Word counts
 * are derived from the stored transcript at read time (no denormalised columns). Null → the client
 * shows no result yet (404 at the edge). Owner-scoped by the repository.
 */
final readonly class GetLastCollectionDialogHandler
{
    public function __construct(
        private PracticeDialogRepository $dialogs,
        private PracticeDialogMessageRepository $messages,
        private DialogCoverage $coverage,
    ) {}

    public function __invoke(GetLastCollectionDialog $query): ?LastCollectionDialogView
    {
        $dialog = $this->dialogs->lastConcludedForCollection($query->userId, $query->collectionId);
        if ($dialog === null) {
            return null;
        }

        $lesson = $dialog->lesson();
        /** @var list<array{term_id: string, text: string, forms: list<string>}> $targetWords */
        $targetWords = is_array($lesson['target_words'] ?? null) ? $lesson['target_words'] : [];

        $used = 0;
        foreach ($this->coverage->evaluate($targetWords, $this->messages->forDialog($dialog->id())) as $word) {
            if ($word->used) {
                $used++;
            }
        }

        return new LastCollectionDialogView(
            finishedAt: $dialog->finishedAt(),
            wordsUsed: $used,
            wordsTotal: count($targetWords),
            summary: $dialog->summary(),
        );
    }
}
