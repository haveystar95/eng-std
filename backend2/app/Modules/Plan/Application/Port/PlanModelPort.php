<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;

/**
 * The model calls of the plan, behind one seam: which vendor, which model, which prompt file, which schema and
 * what it cost are Infrastructure's business. A transport or vendor failure throws; a reply that decodes as JSON
 * comes back whatever its content — the checks judge it.
 */
interface PlanModelPort
{
    public function buildPlan(PlanRequest $request): ModelReply;

    public function buildLesson(LessonRequest $request): ModelReply;

    /**
     * P2R: one card of a written lesson, repaired — `{card}` in that card's shape; an exchange may come with
     * `frame_update`. Asked by the lesson build for a fatal card and by the `plan:repair-card` command.
     */
    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply;

    /** The seam judge: `{verdicts: [{id, reads}]}` — does each native sentence of the day read. Once a day. */
    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply;

    /**
     * The slot judge (`slot_judge.v2`, наряд SESSION-1a, разд. 4): `{accepted, slot_value, reason_native}` — what the
     * learner put into the slot and whether it answers. ONE
     * attempt within the judge's own timeout, synchronously inside the learner's request; a silence throws.
     */
    public function judgeSlot(SlotJudgeRequest $request): ModelReply;

    /**
     * ONE MOVE OF THE CONVERSATION AGENT (`conversation_agent.v1`, наряд CONV-1): the role's reply in
     * both languages, what it judged about the learner's move, the checkpoint it closed, the hint to
     * offer next and whether the talk is over. Synchronous, inside the learner's request, ONE attempt
     * — a retry would only lengthen a wait the learner is sitting through; a silence throws.
     */
    public function conversationTurn(ConversationAgentRequest $request): ModelReply;

    /** The versions stamped on every plan and lesson — read from the prompt files' names. */
    public function planPromptVersion(): string;

    public function lessonPromptVersion(): string;

    public function repairPromptVersion(): string;

    public function judgePromptVersion(): string;

    /** The slot judge's version — what its counters (`judge.unavailable`) are kept under. */
    public function slotJudgePromptVersion(): string;

    /** The agent's version — stamped on every turn it writes. */
    public function conversationPromptVersion(): string;
}
