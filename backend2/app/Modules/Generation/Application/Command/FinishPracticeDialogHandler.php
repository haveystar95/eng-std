<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\DialogFinishView;
use App\Modules\Generation\Application\Dto\DialogSummaryBrief;
use App\Modules\Generation\Application\Port\DialogSummarizerPort;
use App\Modules\Generation\Application\Service\DialogCoverage;
use App\Modules\Generation\Application\Service\PracticeCostEstimator;
use App\Modules\Generation\Domain\Exception\PracticeDialogNotFound;
use App\Modules\Generation\Domain\Repository\PracticeDialogMessageRepository;
use App\Modules\Generation\Domain\Repository\PracticeDialogRepository;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * Finishes a dialog: records the estimated realtime spend, then asks the summariser for a short
 * native-language recap (what went well, one or two main mistakes) and reports how many target
 * words were used. Idempotent — a repeat finish keeps the recorded spend and re-derives the recap.
 */
final readonly class FinishPracticeDialogHandler
{
    public function __construct(
        private PracticeDialogRepository $dialogs,
        private PracticeDialogMessageRepository $messages,
        private DialogCoverage $coverage,
        private DialogSummarizerPort $summarizer,
        private PracticeCostEstimator $estimator,
        private Clock $clock,
    ) {}

    public function __invoke(FinishPracticeDialog $command): DialogFinishView
    {
        $dialog = $this->dialogs->findById($command->dialogId);
        if ($dialog === null || ! $dialog->userId()->equals($command->userId)) {
            throw PracticeDialogNotFound::make();
        }

        $lesson = $dialog->lesson();
        $lines = $this->messages->forDialog($command->dialogId);

        // Close it (idempotent) with the estimated spend for the audio time actually used.
        $cost = $this->estimator->estimate($lesson, $lines, $dialog->billableSeconds($this->clock->now()));
        $dialog->finish($cost->tokensIn, $cost->tokensOut, $cost->costUsd);
        $this->dialogs->save($dialog);

        $targetWords = $this->targetWords($lesson);
        $used = 0;
        foreach ($this->coverage->evaluate($targetWords, $lines) as $word) {
            if ($word->used) {
                $used++;
            }
        }

        $summary = $this->summarizer->summarize(new DialogSummaryBrief(
            nativeLang: is_string($lesson['native'] ?? null) ? $lesson['native'] : '',
            targetLang: is_string($lesson['target'] ?? null) ? $lesson['target'] : '',
            topic: is_string($lesson['topic'] ?? null) ? $lesson['topic'] : '',
            lines: $lines,
        ))->summary;

        return new DialogFinishView(
            summary: $summary,
            wordsUsed: $used,
            wordsTotal: count($targetWords),
        );
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
