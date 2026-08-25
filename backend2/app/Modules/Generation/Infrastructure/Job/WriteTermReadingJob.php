<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Command\WriteTermReading;
use App\Modules\Generation\Application\Command\WriteTermReadingHandler;
use App\Modules\Generation\Domain\ValueObject\TermReadingOutcome;
use App\Modules\Shared\Domain\ValueObject\TermId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Buys one term's reading hint off the request thread. Best-effort, like the photo: the card was
 * built before this job ran and stays built whatever happens here.
 *
 * Retries cover the transient half — a timeout, a 429 — and the handler's own gate makes a retry
 * free once the hint is in the table. A REFUSED hint is not retried and must not be: the alphabet
 * gate threw away an answer the model already gave, and asking the same model the same question
 * again buys the same answer twice.
 */
final class WriteTermReadingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly string $termId,
        private readonly string $supportLang,
    ) {}

    public function handle(WriteTermReadingHandler $handler): void
    {
        $outcome = $handler(new WriteTermReading(TermId::fromString($this->termId), $this->supportLang));

        // The one outcome that cost money and produced nothing. Everything else is either the hint
        // being written or a check doing its job before the call, and neither is news.
        if ($outcome === TermReadingOutcome::Refused) {
            Log::info('Reading hint refused by the alphabet gate; the card keeps its content', [
                'term_id' => $this->termId,
                'support_lang' => $this->supportLang,
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('WriteTermReadingJob failed; the card lives without a reading hint', [
            'term_id' => $this->termId,
            'support_lang' => $this->supportLang,
            'error' => $e->getMessage(),
        ]);
    }
}
