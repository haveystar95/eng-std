<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Dto\Inspection\InspectedRejection;
use App\Modules\Plan\Application\Dto\Inspection\InspectedTalk;
use App\Modules\Plan\Application\Dto\Inspection\InspectedTurn;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\FrameState;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE PLAN'S TALKS, TURN BY TURN (наряд ADM-1, «Разговоры»): every talk (the day's, the rehearsal's, «Ещё раз»), its
 * constructions — said, said almost or not (what the judge of the move wrote on the learner's turns, `phrases_used` /
 * `phrases_almost`, наряд FIX-4 §2 — the same journal the talk's summary reads), the frames of its scenes said beside the
 * targets («ещё вспомнил»), and the transcript: who, what, the sound, what recognition heard, the scene each line was
 * said in and which of them greets or says goodbye (§4), which construction the role's line opened, what the server
 * refused of the role on that line — each attempt of the model with its call and why (§§3, 6), the verdict, the delay and
 * the money of each turn; how it ended, a limit named «лимит» whatever `ended_reason` says.
 * The role's voice is checked against the voice of the scene the line was said in — the line's own scene since наряд
 * FIX-4; an older line has none, and its scene is the checkpoint current at that turn, as the move then picked it.
 */
final readonly class TalkReport
{
    public const ENDINGS = [
        'natural' => 'прощание',
        'limit' => 'лимит ходов, минут или денег',
        'declined' => 'ученик отказался',
        'replayed' => 'прерван «Ещё раз»',
    ];

    /** A talk the turns ended before its scenes were walked — `natural` by its reason, a limit by what happened. */
    public const ENDED_BY_TURNS = 'лимит ходов — роль попрощалась';

    public function __construct(
        private ConversationMaterial $material,
        private LineSpeaker $speaker,
        private VoiceTable $voices,
        private InspectionCanon $canon,
    ) {}

    /** @return list<array<string, mixed>> */
    public function of(PlanInspectionData $data, ?int $number): array
    {
        $out = [];
        foreach ($data->talks() as $talk) {
            if ($number !== null && $talk->dayNumber !== $number) {
                continue;
            }
            $out[] = $this->talk($data, $talk);
        }

        return $out;
    }

    /**
     * Each role line with a sound: its turn, the voice it was said in, and the voice its scene's cast gives the role.
     *
     * @return list<array{turn: int, voice: string, expected: string|null}>
     */
    public function voicedRoleLines(PlanInspectionData $data, InspectedTalk $talk): array
    {
        $material = $this->materialOf($data, $talk);
        $out = [];
        foreach ($this->expectedVoices($data, $talk, $material) as $index => $expected) {
            foreach ($talk->turns as $turn) {
                if ($turn->index === $index && $turn->audioVoiceKey !== null) {
                    $out[] = ['turn' => $index, 'voice' => $turn->audioVoiceKey, 'expected' => $expected];
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function talk(PlanInspectionData $data, InspectedTalk $talk): array
    {
        $material = $this->materialOf($data, $talk);
        $lang = $data->plan->targetLang()->value;
        $identities = $this->voices->identities($lang);
        $expected = $this->expectedVoices($data, $talk, $material);
        $byLimit = $talk->endedByLimit();

        return [
            'id' => $talk->id,
            'day' => $talk->dayNumber,
            'type' => $talk->type,
            'state' => $talk->state,
            'ended_reason' => $talk->endedReason,
            'ended_by_limit' => $byLimit,
            'ended_label' => self::endedLabel($talk->endedReason, $byLimit),
            'started_at' => $talk->startedAt->format(DATE_ATOM),
            'ended_at' => $talk->endedAt?->format(DATE_ATOM),
            'turn_limit' => $talk->turnLimit,
            'hints_enabled' => $talk->hintsEnabled,
            'cost_usd' => (float) $talk->costUsd,
            'scenes' => array_map(static fn ($c): array => [
                'scene_id' => $c->sceneId,
                'title_native' => $c->titleNative,
                'role_native' => $c->roleNative,
                'partner_gender' => $c->partnerGender->value,
                'done' => in_array($c->sceneId, $talk->checkpointsDone, true),
            ], $material->checkpoints),
            'checkpoints_done' => $talk->checkpointsDone,
            'openers_checked' => $talk->startedAt >= $this->canon->openersSince,
            'targets' => $this->targets($talk, $material),
            'extra_said' => $this->extraSaid($talk, $material),
            'rejections' => count($talk->rejections),
            'turns' => array_map(fn (InspectedTurn $turn): array => [
                'index' => $turn->index,
                'kind' => $turn->kind,
                'speaker' => $turn->speaker,
                'scene_id' => $turn->sceneId,
                'scene_event' => $turn->sceneEvent,
                'text_target' => $turn->textTarget,
                'text_native' => $turn->textNative,
                'heard' => $turn->speaker === Speaker::Learner->value ? $turn->textTarget : null,
                'audio_id' => $turn->hasAudio ? $turn->id : null,
                'audio' => $turn->hasAudio ? [
                    'voice' => VoiceLine::voice($turn->audioVoiceKey, $identities),
                    'expected_voice' => VoiceLine::voice($expected[$turn->index] ?? null, $identities),
                    'duration_ms' => $turn->audioDurationMs,
                    'characters' => $turn->audioCharacters,
                    'credits' => $turn->audioCredits,
                    'cost_usd' => $turn->audioCostUsd,
                ] : null,
                'understood' => $turn->understood,
                'off_topic' => $turn->offTopic,
                'phrases_used' => $turn->phrasesUsed,
                'phrases_almost' => $turn->phrasesAlmost,
                'extra_said' => array_values(array_filter($turn->phrasesUsed, static fn (string $id): bool => ! $material->isTarget($id))),
                'opens_target' => $turn->opensTarget,
                'checkpoint_done' => $turn->checkpointDone,
                'hint_native' => $turn->hintNative,
                'rejections' => array_values(array_map(static fn (InspectedRejection $r): array => [
                    'attempt' => $r->attempt,
                    'kind' => $r->kind,
                    'reason' => $r->reason,
                    'model_call_id' => $r->modelCallId,
                    'detail' => $r->detail,
                ], array_filter($talk->rejections, static fn (InspectedRejection $r): bool => $r->turnIndex === $turn->index))),
                'model' => $turn->model,
                'prompt_version' => $turn->promptVersion,
                'tokens_in' => $turn->tokensIn,
                'tokens_out' => $turn->tokensOut,
                'model_cost_usd' => (float) $turn->modelCostUsd,
                'speech_cost_usd' => (float) $turn->speechCostUsd,
                'cost_usd' => (float) $turn->costUsd,
                'latency_ms' => $turn->latencyMs,
                'model_latency_ms' => $turn->modelLatencyMs,
                'speech_latency_ms' => $turn->speechLatencyMs,
                'created_at' => $turn->createdAt->format(DATE_ATOM),
            ], $talk->turns),
            'not_stored' => ['сырой текст распознавания и его альтернативы — сервер получает только итоговую строку heard'],
        ];
    }

    private static function endedLabel(?string $reason, bool $byLimit): ?string
    {
        if ($reason === null) {
            return null;
        }
        if ($byLimit && $reason === 'natural') {
            return self::ENDED_BY_TURNS;
        }

        return self::ENDINGS[$reason] ?? $reason;
    }

    /**
     * The talk's targets as the journal has them: said on the first turn that said it, else said almost on the first that
     * did, else none; its short id (what the role named it by — the journal of refusals quotes it); the turn that opened it.
     *
     * @return list<array<string, mixed>>
     */
    private function targets(InspectedTalk $talk, ConversationMaterialView $material): array
    {
        return array_map(static function (ConversationPhrase $target) use ($talk, $material): array {
            $saidOn = null;
            $almostOn = null;
            foreach ($talk->turns as $turn) {
                if ($saidOn === null && in_array($target->id(), $turn->phrasesUsed, true)) {
                    $saidOn = $turn->index;
                }
                if ($almostOn === null && in_array($target->id(), $turn->phrasesAlmost, true)) {
                    $almostOn = $turn->index;
                }
            }
            $state = $saidOn !== null ? FrameState::Said : ($almostOn !== null ? FrameState::Almost : FrameState::None);

            return [
                'scene_id' => $target->sceneId,
                'ref' => $target->ref,
                'short_id' => $material->shortId($target),
                'frame_target' => $target->frameTarget,
                'frame_native' => $target->frameNative,
                'example_target' => $target->exampleTarget,
                'line_target' => $target->lineTarget,
                'status' => $state->value,
                'said_turn' => $saidOn,
                'almost_turn' => $almostOn,
                'opened_on_turn' => self::openedOn($talk, $target),
            ];
        }, $material->targets);
    }

    /**
     * «Ещё вспомнил»: the frames of the talk's scenes the judge heard said that are no target, in the order they were first
     * said.
     *
     * @return list<array{id: string, frame_target: string|null, said_turn: int}>
     */
    private function extraSaid(InspectedTalk $talk, ConversationMaterialView $material): array
    {
        $out = [];
        foreach ($talk->turns as $turn) {
            foreach ($turn->phrasesUsed as $id) {
                if (! isset($out[$id]) && ! $material->isTarget($id)) {
                    $out[$id] = ['id' => $id, 'frame_target' => $material->phrase($id)?->frameTarget, 'said_turn' => $turn->index];
                }
            }
        }

        return array_values($out);
    }

    private static function openedOn(InspectedTalk $talk, ConversationPhrase $target): ?int
    {
        foreach ($talk->turns as $turn) {
            if ($turn->opensTarget !== null && in_array($turn->opensTarget, [$target->id(), $target->ref], true)) {
                return $turn->index;
            }
        }

        return null;
    }

    /**
     * The voice key each role line should have been said in — the partner's voice of the scene it was said in: its own
     * scene when the line keeps it (наряд FIX-4 §4), else the scene current at that turn.
     *
     * @return array<int, string|null>
     */
    private function expectedVoices(PlanInspectionData $data, InspectedTalk $talk, ConversationMaterialView $material): array
    {
        $lang = $data->plan->targetLang()->value;
        $done = [];
        $out = [];
        foreach ($talk->turns as $turn) {
            if ($turn->speaker === Speaker::Partner->value) {
                // An older line: the talk's current scene as `Conversation::currentCheckpoint()` read it — the first of
                // ITS scenes not walked — and that scene's checkpoint as the move picked it: none left means the LAST one
                // ({@see ConversationMaterialView::checkpoint()}), never the first.
                $current = $turn->sceneId;
                if ($current === null) {
                    foreach ($talk->sceneIds as $sceneId) {
                        if (! in_array($sceneId, $done, true)) {
                            $current = $sceneId;
                            break;
                        }
                    }
                }
                $gender = $material->checkpoint($current)->partnerGender ?? VoiceGender::Female;
                $out[$turn->index] = $this->speaker->voiceKeyFor($lang, Speaker::Partner, $gender);
            }
            if ($turn->checkpointDone !== null) {
                $done[] = $turn->checkpointDone;
            }
        }

        return $out;
    }

    private function materialOf(PlanInspectionData $data, InspectedTalk $talk): ConversationMaterialView
    {
        $day = $data->dayByNumber($talk->dayNumber);

        return $day === null ? new ConversationMaterialView([], []) : $this->material->for($data->plan, $day);
    }
}
