<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\Inspection\JournalClientCall;
use App\Modules\Plan\Application\Dto\Inspection\JournalModelCall;
use App\Modules\Plan\Application\Dto\Inspection\JournalSpeechCall;
use DateTimeImmutable;

/**
 * WHAT WAS CALLED ON A PLAN'S BEHALF (наряд ADM-1) — the model journal and the request log, read for the admin's plan page.
 * Both tables are Observability's; the adapter reads them through that module's Application port and never writes.
 */
interface PlanCallJournal
{
    /**
     * The model calls started inside the window for these purposes, oldest first, each with its request-log row when one
     * answers it exactly.
     *
     * @param  list<string>  $purposes
     * @return list<JournalModelCall>
     */
    public function modelCalls(DateTimeImmutable $from, DateTimeImmutable $to, array $purposes): array;

    /** @return list<JournalSpeechCall> */
    public function speechCalls(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * The client's calls whose path starts with one of the prefixes or is one of the exact paths, newest first, paged
     * back from `$before`.
     *
     * @param  list<string>  $pathPrefixes
     * @param  list<string>  $exactPaths
     * @param  array{0: DateTimeImmutable, 1: string}|null  $before
     * @return list<JournalClientCall>
     */
    public function clientCalls(array $pathPrefixes, array $exactPaths, ?array $before, int $limit): array;

    /**
     * @param  list<string>  $pathPrefixes
     * @param  list<string>  $exactPaths
     */
    public function clientCallCount(array $pathPrefixes, array $exactPaths): int;

    /**
     * When the client first received each of these documents (a 200 on GET of the exact path).
     *
     * @param  list<string>  $paths
     * @return array<string, DateTimeImmutable>
     */
    public function firstServed(array $paths): array;
}
