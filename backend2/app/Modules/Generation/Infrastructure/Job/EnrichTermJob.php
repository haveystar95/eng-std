<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Command\EnrichTerm;
use App\Modules\Generation\Application\Command\EnrichTermHandler;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Enriches a bare user term off the request thread. Best-effort, like image attach: a terminal
 * failure just leaves the term as the user typed it (no translation/photo) — the app still works.
 * Retries cover transient LLM / image-search failures; the handler's eligibility gate keeps a retry
 * from re-spending on the model after a successful enrichment.
 */
final class EnrichTermJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly string $termId,
        private readonly string $translationLang,
    ) {}

    public function handle(EnrichTermHandler $handler): void
    {
        $handler(new EnrichTerm(
            TermId::fromString($this->termId),
            new LanguageCode($this->translationLang),
        ));
    }

    public function failed(Throwable $e): void
    {
        Log::warning('EnrichTermJob failed; term stays as entered', [
            'term_id' => $this->termId,
            'error' => $e->getMessage(),
        ]);
    }
}
