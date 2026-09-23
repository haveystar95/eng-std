<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\JournalClientCall;
use App\Modules\Plan\Application\Port\PlanCallJournal;
use App\Modules\Plan\Domain\Entity\PlanDay;
use DateTimeImmutable;

/**
 * ONE JOURNAL OF EVERYTHING CALLED FOR THE PLAN (наряд ADM-1, «Вызовы API»), newest first, paged by a cursor: the model
 * calls read as the plan's ({@see CallAttribution}), every voice purchase (a scene's line, a talk's line), every slot judge,
 * and the client's calls to the plan's routes. Filters: a day, a source. Failures and `lost` are flagged `is_error`.
 */
final readonly class CallJournalReport
{
    public const SOURCES = ['model', 'voice', 'judge', 'client'];

    public function __construct(private PlanCallJournal $journal) {}

    /**
     * @param  list<AttributedCall>  $calls
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function of(PlanInspectionData $data, ?int $number, ?string $source, ?string $cursor, int $limit, array $calls): array
    {
        $before = self::decode($cursor);
        $rows = $source === 'client' ? [] : $this->stored($data, $calls);
        $rows = array_values(array_filter($rows, static fn (array $r): bool => ($number === null || $r['day'] === $number)
            && ($source === null || $r['source'] === $source)));
        $storedTotal = count($rows);
        if ($before !== null) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => [$r['_at'], $r['id']] < [$before[0], $before[1]]));
        }

        [$prefixes, $exact] = $source === null || $source === 'client' ? $this->paths($data, $number) : [[], []];
        $client = $prefixes === [] && $exact === [] ? [] : $this->journal->clientCalls($prefixes, $exact, $before, $limit);
        foreach ($client as $call) {
            $rows[] = $this->client($data, $call);
        }
        usort($rows, static fn (array $a, array $b): int => [$b['_at'], $b['id']] <=> [$a['_at'], $a['id']]);
        $page = array_slice($rows, 0, $limit);
        $more = count($rows) > $limit;
        $last = $page === [] ? null : $page[count($page) - 1];

        return [
            'data' => array_map(static function (array $r): array {
                unset($r['_at']);

                return $r;
            }, $page),
            'meta' => [
                'total' => $storedTotal + ($prefixes === [] && $exact === [] ? 0 : $this->journal->clientCallCount($prefixes, $exact)),
                'per_page' => $limit,
                'next_cursor' => $more && $last !== null ? self::encode($last['_at'], $last['id']) : null,
                'not_stored' => [
                    'вызовы модели не ссылаются на план — показаны вызовы, начатые в окне сборки плана, сцены или разговора; window.others > 0 — окно пересекается с чужими сборками',
                    'обращения клиента за звуком и фото (api/v1/plans/audio/…, api/v1/plans/images/…) адреса плана не несут и в журнал плана не попадают',
                    'поиск фото (Pexels) в логе без ссылки на план',
                ],
            ],
        ];
    }

    /**
     * @param  list<AttributedCall>  $calls
     * @return list<array<string, mixed>>
     */
    private function stored(PlanInspectionData $data, array $calls): array
    {
        $rows = [];
        foreach ($calls as $call) {
            $c = $call->call;
            $rows[] = self::row($c->id, 'model', $c->startedAt, $call->day, $c->purpose ?? 'model', $c->status, in_array($c->status, ['failed', 'lost', 'started'], true), [
                'model' => $c->answeredModel ?? $c->model,
                'duration_ms' => $c->latencyMs,
                'tokens_in' => $c->tokensIn,
                'cached_tokens' => $c->cachedTokens,
                'tokens_out' => $c->tokensOut,
                'cost_usd' => $c->costUsd === null ? null : (float) $c->costUsd,
                'http_status' => $c->httpStatus,
                'error' => $c->error,
                'log_id' => $c->logId,
                'certain' => $call->certain(),
                'window' => $call->toArray()['window'],
            ]);
        }
        foreach ($data->audios() as $audio) {
            $parts = explode(':', $audio->voiceKey);
            $rows[] = self::row($audio->id, 'voice', $audio->createdAt ?? $data->row->createdAt, $data->dayOfScene($audio->sceneId), 'voice', 'bought', false, [
                'line_ref' => $audio->lineRef,
                'model' => $parts[1] ?? null,
                'voice_id' => $parts[2] ?? null,
                'characters' => $audio->characters,
                'credits' => $audio->credits,
                'cost_usd' => $audio->costUsd === null ? null : (float) $audio->costUsd,
                'bytes' => $audio->bytes,
                'request_id' => $audio->requestId,
            ]);
        }
        foreach ($data->talks() as $talk) {
            foreach ($talk->turns as $turn) {
                if (! $turn->hasAudio) {
                    continue;
                }
                $parts = explode(':', (string) $turn->audioVoiceKey);
                $rows[] = self::row($turn->id, 'voice', $turn->createdAt, $talk->dayNumber, 'conversation', 'bought', false, [
                    'conversation_id' => $talk->id,
                    'turn' => $turn->index,
                    'model' => $parts[1] ?? null,
                    'voice_id' => $parts[2] ?? null,
                    'characters' => $turn->audioCharacters,
                    'credits' => $turn->audioCredits,
                    'cost_usd' => $turn->audioCostUsd === null ? null : (float) $turn->audioCostUsd,
                    'duration_ms' => $turn->speechLatencyMs,
                ]);
            }
        }
        foreach ($data->cards() as $card) {
            $judge = $card->response['judge'] ?? null;
            if (! is_array($judge) || $card->answeredAt === null) {
                continue;
            }
            $by = (string) ($judge['by'] ?? '');
            $rows[] = self::row($card->id, 'judge', $card->answeredAt, $data->dayNumberOf($card->dayId), 'slot_judge', $by, $by === 'unavailable', [
                'card_kind' => $card->kind,
                'accepted' => $judge['accepted'] ?? null,
                'model' => $judge['model'] ?? null,
                'prompt_version' => $judge['prompt_version'] ?? null,
                'duration_ms' => $judge['latency_ms'] ?? null,
                'tokens_in' => $judge['tokens_in'] ?? null,
                'tokens_out' => $judge['tokens_out'] ?? null,
                'cost_usd' => isset($judge['cost_usd']) ? (float) $judge['cost_usd'] : null,
                'at_note' => 'время — ответ карточки (answered_at); своего времени у вызова судьи нет',
            ]);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function client(PlanInspectionData $data, JournalClientCall $call): array
    {
        $day = null;
        if (preg_match('#/days/(\d+)#', $call->path, $m) === 1) {
            $day = (int) $m[1];
        } elseif (preg_match('#/conversation/([0-9A-Z]{26})#', $call->path, $m) === 1) {
            foreach ($data->talks() as $talk) {
                if ($talk->id === $m[1]) {
                    $day = $talk->dayNumber;
                }
            }
        }

        return self::row($call->id, 'client', $call->occurredAt, $day, $call->method.' '.$call->path, (string) ($call->status ?? '—'), $call->status === null || $call->status >= 400, [
            'method' => $call->method,
            'path' => $call->path,
            'http_status' => $call->status,
            'duration_ms' => $call->durationMs,
            'request_bytes' => $call->requestBytes,
            'response_bytes' => $call->responseBytes,
            'user_agent' => $call->userAgent,
            'error' => $call->error,
            'log_id' => $call->id,
        ]);
    }

    /**
     * The paths of the plan's client calls — prefixes and exact paths: the whole plan, or one day (the day's document, its
     * routes below it and its talks'). A day's own document is matched exactly, so day 1 does not take day 10's calls.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function paths(PlanInspectionData $data, ?int $number): array
    {
        $base = 'api/v1/plans/'.$data->id();
        if ($number === null) {
            return [[$base], []];
        }
        $prefixes = [$base.'/days/'.$number.'/'];
        foreach ($data->talksOfDay($number) as $talk) {
            $prefixes[] = $base.'/conversation/'.$talk->id;
        }

        return [$prefixes, [$base.'/days/'.$number]];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function row(string $id, string $source, DateTimeImmutable $at, ?int $day, string $stage, string $status, bool $isError, array $extra): array
    {
        return ['id' => $id, 'source' => $source, 'at' => $at->format(DATE_ATOM), '_at' => $at, 'day' => $day, 'stage' => $stage, 'status' => $status, 'is_error' => $isError, ...$extra];
    }

    private static function encode(DateTimeImmutable $at, string $id): string
    {
        return rtrim(strtr(base64_encode($at->format(DATE_ATOM).'|'.$id), '+/', '-_'), '=');
    }

    /** @return array{0: DateTimeImmutable, 1: string}|null */
    private static function decode(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        $parts = $raw === false ? [] : explode('|', $raw, 2);
        if (count($parts) !== 2) {
            return null;
        }
        try {
            return [new DateTimeImmutable($parts[0]), $parts[1]];
        } catch (\Exception) {
            return null;
        }
    }
}
