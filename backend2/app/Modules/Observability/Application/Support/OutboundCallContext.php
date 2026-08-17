<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Support;

use App\Modules\Observability\Application\Port\ApiLogWriter;
use Closure;
use Throwable;

/**
 * Ambient labelling for outbound HTTP calls: WHY the call is being made (`purpose`) and, when it is
 * known, WHICH collection it is spent on.
 *
 * The log row is written by an Http-client event listener, far away from the code that decided to
 * call OpenAI — so the label cannot be passed as an argument; it has to be ambient. A caller wraps
 * its work in {@see run()} and every outbound call inside that closure is tagged. Frames nest and
 * inherit: an enrichment job opens `('enrichment', $collectionId)`, the adapter inside it re-opens
 * `('enrichment', null)` and keeps the collection from the outer frame.
 *
 * Registered as a singleton. A queue worker runs one job at a time, and a web request one action,
 * so a single stack per process is exactly the scope we want; `run()` always pops, even on throw.
 */
final class OutboundCallContext
{
    /** @var list<array{purpose: ?string, collectionId: ?string, ids: list<string>}> */
    private array $frames = [];

    /**
     * Log ids recorded by the frame that closed most recently — see {@see attachCollection()}.
     *
     * @var list<string>
     */
    private array $lastIds = [];

    public function __construct(private readonly ApiLogWriter $logs) {}

    /**
     * Run $fn with every outbound call inside it labelled. Null purpose/collection inherit the
     * enclosing frame's.
     *
     * @template T
     * @param  Closure(): T  $fn
     * @return T
     */
    public function run(?string $purpose, ?string $collectionId, Closure $fn): mixed
    {
        $this->frames[] = [
            'purpose' => $purpose ?? $this->purpose(),
            'collectionId' => $collectionId ?? $this->collectionId(),
            'ids' => [],
        ];

        try {
            return $fn();
        } finally {
            // Always paired with the push above, so there is a frame to pop.
            $closed = array_pop($this->frames);
            $this->lastIds = $closed['ids'];
        }
    }

    public function purpose(): ?string
    {
        return $this->frames === [] ? null : $this->frames[count($this->frames) - 1]['purpose'];
    }

    public function collectionId(): ?string
    {
        return $this->frames === [] ? null : $this->frames[count($this->frames) - 1]['collectionId'];
    }

    /** The log writer reports back what it wrote, so a late-known collection can still be attached. */
    public function record(string $logId): void
    {
        if ($this->frames === []) {
            return;
        }
        $this->frames[count($this->frames) - 1]['ids'][] = $logId;
    }

    /**
     * Stamp the collection on rows written under the frame that just closed. Collection generation
     * needs this: the model call happens BEFORE the collection exists, so the id only becomes known
     * once the call it paid for is already logged. Best-effort, like every other write in this
     * module — a failure here must never fail the work that succeeded.
     */
    public function attachCollection(string $collectionId): void
    {
        if ($this->lastIds === []) {
            return;
        }

        try {
            $this->logs->linkCollection($this->lastIds, $collectionId);
        } catch (Throwable) {
            // Observability never breaks its caller.
        } finally {
            $this->lastIds = [];
        }
    }
}
