<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Dto\Inspection\BuildWindow;
use App\Modules\Plan\Application\Dto\Inspection\InspectedAudio;
use App\Modules\Plan\Application\Dto\Inspection\InspectedCard;
use App\Modules\Plan\Application\Dto\Inspection\InspectedPassage;
use App\Modules\Plan\Application\Dto\Inspection\InspectedPlan;
use App\Modules\Plan\Application\Dto\Inspection\InspectedScene;
use App\Modules\Plan\Application\Dto\Inspection\InspectedTalk;
use App\Modules\Plan\Application\Dto\Inspection\InspectedTurn;
use App\Modules\Plan\Application\Port\PlanInspectionReader;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The plan's rows for the admin's page (наряд ADM-1). The code lookup rides `plans_code_idx` (the expression
 * `substr(id, 5, 6)`, migration `2026_09_23_140100`); every other read is by the plan, its days or its scenes on the
 * indexes the tables were created with.
 */
final class EloquentPlanInspectionReader implements PlanInspectionReader
{
    public function idsByCode(string $code): array
    {
        return array_values(array_map(
            static fn (mixed $id): string => (string) $id,
            DB::table('plans')->whereRaw('substr(id, 5, 6) = ?', [strtoupper($code)])->orderBy('id')->pluck('id')->all(),
        ));
    }

    public function idsOf(string $userId): array
    {
        return array_values(array_map(
            static fn (mixed $id): string => (string) $id,
            DB::table('plans')->where('user_id', $userId)->orderByDesc('created_at')->orderByDesc('id')->pluck('id')->all(),
        ));
    }

    public function plan(string $planId): ?InspectedPlan
    {
        $row = DB::table('plans')->where('id', $planId)->first();
        if ($row === null) {
            return null;
        }
        $r = (array) $row;

        return new InspectedPlan(
            id: (string) $r['id'],
            userId: (string) $r['user_id'],
            status: (string) $r['status'],
            promptVersion: self::str($r['prompt_version_plan']),
            buildVersion: self::str($r['build_version']),
            model: self::str($r['model_plan']),
            costUsd: self::str($r['cost_usd_plan']),
            latencyMs: self::int($r['latency_ms_plan']),
            attempts: self::int($r['attempts_plan']),
            checks: self::list($r['checks_json']),
            unclearReason: self::str($r['unclear_reason']),
            failReason: self::str($r['fail_reason']),
            buildStartedAt: self::at($r['build_started_at']),
            createdAt: self::at($r['created_at']) ?? new DateTimeImmutable('@0'),
            updatedAt: self::at($r['updated_at']),
            coverImageUrl: self::str($r['cover_image_url']),
        );
    }

    public function scenes(string $planId): array
    {
        $out = [];
        foreach (DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->get() as $row) {
            $r = (array) $row;
            $out[] = new InspectedScene(
                id: (string) $r['id'],
                order: (int) $r['order'],
                kind: (string) $r['kind'],
                priority: (int) $r['priority'],
                titleNative: (string) $r['title_native'],
                titleTarget: (string) $r['title_target'],
                learnerRoleNative: (string) $r['learner_role_native'],
                learnerRoleTarget: (string) $r['learner_role_target'],
                partnerRoleNative: (string) $r['partner_role_native'],
                partnerRoleTarget: (string) $r['partner_role_target'],
                lessonStatus: (string) $r['lesson_status'],
                promptVersion: self::str($r['prompt_version_lesson']),
                buildVersion: self::str($r['build_version']),
                model: self::str($r['model_lesson']),
                costUsd: self::str($r['cost_usd_lesson']),
                latencyMs: self::int($r['latency_ms_lesson']),
                attempts: self::int($r['attempts_lesson']),
                findings: self::list($r['checks_json']),
                failReason: self::str($r['fail_reason']),
                buildStartedAt: self::at($r['build_started_at']),
                generatedAt: self::at($r['generated_at']),
                updatedAt: self::at($r['updated_at']),
                imageUrl: self::str($r['image_url']),
                imageAuthor: self::str($r['image_author']),
                imageTone: self::str($r['image_tone']),
                partnerVoiceGender: self::str($r['partner_voice_gender']),
            );
        }

        return $out;
    }

    public function cards(array $dayIds): array
    {
        if ($dayIds === []) {
            return [];
        }
        $out = [];
        $rows = DB::table('day_cards')->whereIn('day_id', $dayIds)->orderBy('day_id')->orderBy('stage')->orderBy('position')->get();
        foreach ($rows as $row) {
            $r = (array) $row;
            $response = self::json($r['response']);
            $out[] = new InspectedCard(
                id: (string) $r['id'],
                dayId: (string) $r['day_id'],
                stage: (string) $r['stage'],
                position: (int) $r['position'],
                kind: (string) $r['kind'],
                payload: self::json($r['payload']) ?? [],
                source: (string) $r['source'],
                sourceDayId: self::str($r['source_day_id']),
                unitKind: (string) $r['unit_kind'],
                unitRef: (string) $r['unit_ref'],
                retryOf: self::str($r['retry_of']),
                result: self::str($r['result']),
                attempts: (int) $r['attempts'],
                answeredAt: self::at($r['answered_at']),
                returns: (bool) $r['returns'],
                response: $response,
            );
        }

        return $out;
    }

    public function audios(array $sceneIds): array
    {
        if ($sceneIds === []) {
            return [];
        }
        $out = [];
        $rows = DB::table('plan_line_audios')->whereIn('scene_id', $sceneIds)->orderBy('scene_id')->orderBy('line_ref')->get();
        foreach ($rows as $row) {
            $r = (array) $row;
            $out[] = new InspectedAudio(
                id: (string) $r['id'],
                sceneId: (string) $r['scene_id'],
                lineRef: (string) $r['line_ref'],
                voiceKey: (string) $r['voice_key'],
                format: (string) $r['format'],
                bytes: (int) $r['bytes'],
                durationMs: self::int($r['duration_ms']),
                characters: self::int($r['characters']),
                credits: self::int($r['credits']),
                costUsd: self::str($r['cost_usd']),
                requestId: self::str($r['request_id']),
                createdAt: self::at($r['created_at']),
            );
        }

        return $out;
    }

    public function talks(string $planId): array
    {
        $talks = DB::table('conversations')->where('plan_id', $planId)->orderBy('started_at')->orderBy('id')->get();
        if ($talks->isEmpty()) {
            return [];
        }
        $turns = [];
        $rows = DB::table('conversation_turns')->whereIn('conversation_id', $talks->pluck('id')->all())->orderBy('conversation_id')->orderBy('turn_index')->get();
        foreach ($rows as $row) {
            $r = (array) $row;
            $turns[(string) $r['conversation_id']][] = new InspectedTurn(
                id: (string) $r['id'],
                index: (int) $r['turn_index'],
                kind: (string) $r['kind'],
                speaker: (string) $r['speaker'],
                textTarget: self::str($r['text_target']),
                textNative: self::str($r['text_native']),
                hasAudio: $r['audio_path'] !== null,
                audioDurationMs: self::int($r['audio_duration_ms']),
                audioVoiceKey: self::str($r['audio_voice_key']),
                audioCharacters: self::int($r['audio_characters']),
                audioCredits: self::int($r['audio_credits']),
                audioCostUsd: self::str($r['audio_cost_usd']),
                understood: $r['understood'] === null ? null : (bool) $r['understood'],
                phrasesUsed: self::strings($r['phrases_used']),
                offTopic: $r['off_topic'] === null ? null : (bool) $r['off_topic'],
                checkpointDone: self::str($r['checkpoint_done']),
                hintNative: self::str($r['hint_native']),
                model: self::str($r['model']),
                promptVersion: self::str($r['prompt_version']),
                tokensIn: self::int($r['tokens_in']),
                tokensOut: self::int($r['tokens_out']),
                modelCostUsd: (string) $r['model_cost_usd'],
                speechCostUsd: (string) $r['speech_cost_usd'],
                costUsd: (string) $r['cost_usd'],
                modelLatencyMs: self::int($r['model_latency_ms']),
                speechLatencyMs: self::int($r['speech_latency_ms']),
                latencyMs: self::int($r['latency_ms']),
                opensTarget: self::str($r['opens_target']),
                createdAt: self::at($r['created_at']) ?? new DateTimeImmutable('@0'),
            );
        }

        $out = [];
        foreach ($talks as $row) {
            $r = (array) $row;
            $out[] = new InspectedTalk(
                id: (string) $r['id'],
                dayId: (string) $r['day_id'],
                dayNumber: (int) $r['day_number'],
                type: (string) $r['type'],
                state: (string) $r['state'],
                sceneIds: self::strings($r['scene_ids']),
                checkpointsDone: self::strings($r['checkpoints_done']),
                turnLimit: (int) $r['turn_limit'],
                hintsEnabled: (bool) $r['hints_enabled'],
                costUsd: (string) $r['cost_usd'],
                endedReason: self::str($r['ended_reason']),
                startedAt: self::at($r['started_at']) ?? new DateTimeImmutable('@0'),
                endedAt: self::at($r['ended_at']),
                turns: $turns[(string) $r['id']] ?? [],
            );
        }

        return $out;
    }

    public function passages(string $planId): array
    {
        $out = [];
        foreach (DB::table('plan_stage_passages')->where('plan_id', $planId)->orderBy('passed_at')->get() as $row) {
            $r = (array) $row;
            $out[] = new InspectedPassage((string) $r['day_id'], (string) $r['stage'], self::str($r['conversation_id']), self::at($r['passed_at']) ?? new DateTimeImmutable('@0'));
        }

        return $out;
    }

    public function withOthers(string $planId, array $windows): array
    {
        if ($windows === []) {
            return [];
        }
        $from = min(array_map(static fn (BuildWindow $w): DateTimeImmutable => $w->from, $windows))->format(DATE_ATOM);
        $to = max(array_map(static fn (BuildWindow $w): DateTimeImmutable => $w->to, $windows))->format(DATE_ATOM);

        // Every other plan's window that touches the span of this plan's windows, ended by the same rule as this plan's
        // own ({@see \App\Modules\Plan\Application\Inspection\CallAttribution}): a plan build at its `plan_ready` line (a
        // failed or unclear one at the row's last change, one still building — now), a scene lesson at its `day_ready`
        // line (a failed one at the row's last change, one being written — now), a talk at its end (an open one — now).
        // A build whose end is unknown has no window here either: a row's last change can be days after its build.
        $spans = DB::select(<<<'SQL'
            SELECT f, t FROM (
                SELECT COALESCE(p.build_started_at, p.created_at) AS f,
                       COALESCE((SELECT MIN(e.occurred_at) FROM plan_events e WHERE e.plan_id = p.id AND e.kind = 'plan_ready'),
                                CASE WHEN p.status IN ('failed', 'unclear') THEN p.updated_at WHEN p.status = 'building' THEN now() END) AS t
                  FROM plans p
                 WHERE p.id <> ?
                UNION ALL
                SELECT s.build_started_at,
                       COALESCE((SELECT MIN(e.occurred_at) FROM plan_events e WHERE e.plan_id = s.plan_id AND e.kind = 'day_ready' AND e.payload->>'scene_id' = s.id),
                                CASE WHEN s.lesson_status = 'failed' THEN s.updated_at WHEN s.lesson_status IN ('building', 'illustrating') THEN now() END)
                  FROM plan_scenes s
                 WHERE s.plan_id <> ? AND s.build_started_at IS NOT NULL
                UNION ALL
                SELECT c.started_at, COALESCE(c.ended_at, now())
                  FROM conversations c
                 WHERE c.plan_id <> ?
            ) w
            WHERE t IS NOT NULL AND f <= ? AND t >= ?
            SQL, [$planId, $planId, $planId, $to, $from]);

        $others = array_map(static function (object $row): array {
            $r = (array) $row;

            return [new DateTimeImmutable((string) $r['f']), new DateTimeImmutable((string) $r['t'])];
        }, $spans);

        return array_map(static fn (BuildWindow $w): BuildWindow => $w->withOthers(count(array_filter(
            $others,
            static fn (array $o): bool => $o[0] <= $w->to && $o[1] >= $w->from,
        ))), $windows);
    }

    private static function str(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function int(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function at(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable((string) $value);
    }

    /** @return array<string, mixed>|null */
    private static function json(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : null;
    }

    /** @return list<array<string, mixed>> */
    private static function list(mixed $value): array
    {
        $decoded = self::json($value) ?? [];

        return array_values(array_filter($decoded, 'is_array'));
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        return array_values(array_map(static fn (mixed $v): string => (string) $v, array_filter(self::json($value) ?? [], 'is_scalar')));
    }
}
