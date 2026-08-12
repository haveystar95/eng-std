<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Admin\Application\Port\AdminAuditRecorder;
use App\Modules\Collections\Application\Port\CollectionCurator;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\CurateTerm;
use App\Modules\Vocabulary\Application\Command\CurateTermHandler;
use App\Modules\Vocabulary\Application\Command\RetireTerm;
use App\Modules\Vocabulary\Application\Command\RetireTermHandler;

/**
 * Every content mutation the panel can make, each paired with its audit row in the same call — the
 * same shape as the tier change: a write cannot land without a trace.
 *
 * Admin owns none of this data, so each act goes through the owning module's Application (never its
 * tables): Vocabulary curates terms, Collections curates collections. The audit line records the
 * BEFORE state where a before exists, because "who changed this translation and what did it say"
 * is the question you actually ask three weeks later.
 *
 * Returns false when the target isn't there, so the controller answers 404 rather than reporting
 * a save that never happened.
 */
final readonly class CurateContentHandler
{
    public function __construct(
        private CurateTermHandler $curateTerm,
        private RetireTermHandler $retireTerm,
        private CollectionCurator $collections,
        private AdminAuditRecorder $audit,
    ) {}

    public function __invoke(CurateContent $command): bool
    {
        $applied = match ($command->action) {
            CurateContent::EDIT_TERM => $this->editTerm($command),
            CurateContent::RETIRE_TERM => ($this->retireTerm)(new RetireTerm(TermId::fromString((string) $command->termId))),
            CurateContent::EDIT_COLLECTION => $this->collections->updateDetails(
                CollectionId::fromString((string) $command->collectionId),
                $this->str($command, 'title'),
                $this->str($command, 'description'),
            ),
            CurateContent::ADD_TERM_TO_COLLECTION => $this->collections->addTerm(
                CollectionId::fromString((string) $command->collectionId),
                TermId::fromString((string) $command->termId),
            ),
            CurateContent::REMOVE_TERM_FROM_COLLECTION => $this->collections->removeTerm(
                CollectionId::fromString((string) $command->collectionId),
                TermId::fromString((string) $command->termId),
            ),
            CurateContent::DELETE_COLLECTION => $this->collections->purge(
                CollectionId::fromString((string) $command->collectionId),
            ),
            default => false,
        };

        if (! $applied) {
            return false;
        }

        $this->audit->record(
            $command->adminId,
            $command->action,
            // Content is global — it belongs to no single user, so the audit row's target_user_id
            // stays null and the subject lives in the context.
            null,
            array_filter([
                'term_id' => $command->termId,
                'collection_id' => $command->collectionId,
            ] + $command->fields, static fn (mixed $v): bool => $v !== null),
        );

        return true;
    }

    private function editTerm(CurateContent $command): bool
    {
        return ($this->curateTerm)(new CurateTerm(
            termId: TermId::fromString((string) $command->termId),
            text: $this->str($command, 'text'),
            translation: $this->str($command, 'translation'),
            ipa: $this->str($command, 'ipa'),
            exampleId: $this->str($command, 'example_id'),
            exampleSentence: $this->str($command, 'example_sentence'),
            exampleTranslation: $this->str($command, 'example_translation'),
        ));
    }

    private function str(CurateContent $command, string $key): ?string
    {
        $value = $command->fields[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
