<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Command\BuildTermEnrichments;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One chunk of the enrichment станок. Chunked rather than one-job-per-term because the model call is
 * the cost and the queue overhead per term is not: a chunk amortises the dispatch while still being
 * small enough that a retry re-does little.
 *
 * Retries are safe by construction, not by luck: every finished term carries a version mark, so a
 * retry of a half-done chunk skips what already landed and re-pays only for what didn't. That is why
 * a transient API failure is allowed to throw all the way out of the handler.
 *
 * A terminal failure loses nothing permanent — the terms simply stay unmarked and the next run picks
 * them up. Content already written stays written; it is additive.
 */
final class EnrichTermsChunkJob implements ShouldQueue
{
    use Queueable;

    /** How many terms one job handles. One model call per term, so this is also the retry blast radius. */
    public const CHUNK_SIZE = 20;

    public int $tries = 3;

    /** Generous: CHUNK_SIZE sequential model calls, each up to 90s in the adapter. */
    public int $timeout = 1800;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /**
     * @param  list<string>  $termIds
     * @param  ?string  $collectionId  only for labelling the outbound log — a backfill run over the
     *                                 whole dictionary belongs to no collection and passes null.
     * @param  string  $translationLang  the deck's language: which of a term's translations is put in
     *                                   front of the model.
     * @param  bool  $ignoreVersionMark  a queued TOP-UP: these ids were chosen by coverage and must
     *                                   not be filtered by the journal on the worker — otherwise
     *                                   `--topup --queue` silently enriches nothing.
     */
    public function __construct(
        private readonly array $termIds,
        private readonly string $generatorVersion,
        private readonly ?string $collectionId = null,
        private readonly string $translationLang = 'ru',
        private readonly bool $ignoreVersionMark = false,
    ) {}

    public function handle(BuildTermEnrichmentsHandler $handler, OutboundCallContext $context): void
    {
        $metrics = $context->run(
            null,
            $this->collectionId,
            fn () => $handler(new BuildTermEnrichments(
                $this->termIds,
                $this->generatorVersion,
                $this->translationLang,
                $this->ignoreVersionMark,
            )),
        );

        // A chunk where EVERY term threw is the shape of "the provider is down", not "the content is
        // odd" — worth a loud line, because the per-term catch would otherwise swallow it entirely.
        if ($metrics->termsFailed > 0 && $metrics->termsFailed === $metrics->termsSeen) {
            Log::warning('Enrichment chunk failed on every term — check the provider', [
                'generator_version' => $this->generatorVersion,
                'terms' => $metrics->termsSeen,
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('EnrichTermsChunkJob exhausted its retries; terms stay unmarked for the next run', [
            'generator_version' => $this->generatorVersion,
            'terms' => count($this->termIds),
            'error' => $e->getMessage(),
        ]);
    }
}
