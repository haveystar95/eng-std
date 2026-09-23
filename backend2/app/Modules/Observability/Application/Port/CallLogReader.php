<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Port;

use App\Modules\Observability\Application\Dto\InboundCallRecord;
use App\Modules\Observability\Application\Dto\ModelCallRecord;
use App\Modules\Observability\Application\Dto\SpeechCallRecord;
use DateTimeImmutable;

/**
 * READING BACK WHAT WAS CALLED (наряд ADM-1) — the model call journal and the request log, for a report that looks at one
 * learner's plan. Read-only; the module still writes both tables alone.
 *
 * Neither table names a plan: `model_calls` carries no subject at all and the request log only a path (inbound) or a
 * purpose (outbound). What CAN be said exactly is said here — a model call and its outbound log row are the same call when
 * the vendor's usage in the row equals the journal's tokens, inside the call's own window; a client call belongs to a plan
 * when its path does. Attributing a call to a plan by time is the caller's reading, and the caller says so.
 */
interface CallLogReader
{
    /**
     * The journal's calls started inside `[$from, $to]` for these purposes, oldest first.
     *
     * @param  list<string>  $purposes
     * @return list<ModelCallRecord>
     */
    public function modelCalls(DateTimeImmutable $from, DateTimeImmutable $to, array $purposes): array;

    /**
     * The same calls, each with the id of its outbound request-log row — the one row whose vendor usage (tokens in and out)
     * equals the journal's, logged between the call's start and a few seconds after its finish. A call with no usage, or
     * whose usage no row (or more than one row) answers, keeps a null id: the page says «н/д», never a neighbour's body.
     *
     * @param  list<ModelCallRecord>  $calls
     * @return list<ModelCallRecord>
     */
    public function withLogIds(array $calls): array;

    /**
     * The outbound text-to-speech calls logged inside `[$from, $to]`, oldest first.
     *
     * @return list<SpeechCallRecord>
     */
    public function speechCalls(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Inbound calls whose path starts with one of the prefixes or equals one of the exact paths, newest first; `$before`
     * pages back — `[occurred_at, id]` of the last row seen.
     *
     * @param  list<string>  $pathPrefixes
     * @param  list<string>  $exactPaths
     * @param  array{0: DateTimeImmutable, 1: string}|null  $before
     * @return list<InboundCallRecord>
     */
    public function inbound(array $pathPrefixes, array $exactPaths, ?array $before, int $limit): array;

    /**
     * @param  list<string>  $pathPrefixes
     * @param  list<string>  $exactPaths
     */
    public function inboundCount(array $pathPrefixes, array $exactPaths): int;

    /**
     * The first successful GET of each path (exact), keyed by path — when a client first received that document.
     *
     * @param  list<string>  $paths
     * @return array<string, DateTimeImmutable>
     */
    public function firstServed(array $paths): array;
}
