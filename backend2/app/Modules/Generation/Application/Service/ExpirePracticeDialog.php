<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Domain\Entity\PracticeDialog;
use App\Modules\Generation\Domain\Repository\PracticeDialogMessageRepository;
use App\Modules\Generation\Domain\Repository\PracticeDialogRepository;
use DateTimeImmutable;

/**
 * Retires an active dialog whose realtime token TTL has lapsed, recording its estimated spend. Used
 * both on access (a transcript/finish call finds the session already over) and by the background
 * sweep. Idempotent: a dialog that isn't a stale-active one is left untouched.
 */
final readonly class ExpirePracticeDialog
{
    public function __construct(
        private PracticeDialogRepository $dialogs,
        private PracticeDialogMessageRepository $messages,
        private PracticeCostEstimator $estimator,
    ) {}

    public function ifStale(PracticeDialog $dialog, DateTimeImmutable $now): void
    {
        if (! $dialog->isExpiredAt($now)) {
            return;
        }

        $lines = $this->messages->forDialog($dialog->id());
        $cost = $this->estimator->estimate(
            $dialog->lesson(),
            $lines,
            $dialog->billableSeconds($dialog->expiresAt()),
        );

        $dialog->expire($cost->tokensIn, $cost->tokensOut, $cost->costUsd, $now);
        $this->dialogs->save($dialog);
    }
}
