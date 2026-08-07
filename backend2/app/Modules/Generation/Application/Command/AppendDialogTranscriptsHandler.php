<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\TargetWordView;
use App\Modules\Generation\Application\Service\DialogCoverage;
use App\Modules\Generation\Application\Service\ExpirePracticeDialog;
use App\Modules\Generation\Domain\Exception\PracticeDialogNotFound;
use App\Modules\Generation\Domain\Repository\PracticeDialogMessageRepository;
use App\Modules\Generation\Domain\Repository\PracticeDialogRepository;
use App\Modules\Generation\Domain\ValueObject\TranscriptLine;
use App\Modules\Generation\Domain\ValueObject\TranscriptRole;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * Stores a batch of transcript events (append-only, idempotent on (role, ts)) and returns the
 * up-to-date coverage of the dialog's target words. On access it also retires a session whose
 * token TTL has already lapsed. Pure practice — nothing here touches reviews or progress.
 */
final readonly class AppendDialogTranscriptsHandler
{
    public function __construct(
        private PracticeDialogRepository $dialogs,
        private PracticeDialogMessageRepository $messages,
        private DialogCoverage $coverage,
        private ExpirePracticeDialog $expire,
        private Clock $clock,
    ) {}

    /** @return list<TargetWordView> */
    public function __invoke(AppendDialogTranscripts $command): array
    {
        $dialog = $this->dialogs->findById($command->dialogId);
        if ($dialog === null || ! $dialog->userId()->equals($command->userId)) {
            throw PracticeDialogNotFound::make();
        }

        // On-access expiry: a token whose TTL lapsed retires the session (records estimated cost).
        $this->expire->ifStale($dialog, $this->clock->now());

        // The transcript records what was actually said; keep appending until the dialog is finished.
        if (! $dialog->status()->isFinished()) {
            $this->messages->append($command->dialogId, $this->toLines($command->events));
        }

        $lines = $this->messages->forDialog($command->dialogId);

        return $this->coverage->evaluate($this->targetWords($dialog->lesson()), $lines);
    }

    /**
     * @param  list<\App\Modules\Generation\Application\Dto\DialogTranscriptEvent>  $events
     * @return list<TranscriptLine>
     */
    private function toLines(array $events): array
    {
        $lines = [];
        foreach ($events as $event) {
            $role = TranscriptRole::tryFrom($event->role);
            if ($role === null || trim($event->text) === '') {
                continue; // unknown speaker or empty line — nothing to store
            }
            $lines[] = new TranscriptLine($role, $event->text, $event->ts);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $lesson
     * @return list<array{term_id: string, text: string, forms: list<string>}>
     */
    private function targetWords(array $lesson): array
    {
        /** @var list<array{term_id: string, text: string, forms: list<string>}> $words */
        $words = is_array($lesson['target_words'] ?? null) ? $lesson['target_words'] : [];

        return $words;
    }
}
