<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanLineRepairRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;

/**
 * The model calls of the plan, behind one seam: which vendor, which model, which reasoning effort, which prompt file, which
 * schema and what it cost are Infrastructure's business — a model and an effort per PURPOSE (наряд GEN-4, `plan.model.purposes`).
 * A transport or vendor failure throws; a reply that decodes as JSON comes back whatever its content — the checks judge it.
 */
interface PlanModelPort
{
    public function buildPlan(PlanRequest $request): ModelReply;

    /** ONE screen line of the plan, shortened under its limit (`plan_line_repair.v1`, наряд GEN-4): `{line}`. */
    public function repairPlanLine(PlanLineRepairRequest $request): ModelReply;

    /** The day's first stage (`lesson_skeleton.v1`): frames, partner lines and vocabulary from the scene's survival set. */
    public function buildSkeleton(LessonRequest $request): ModelReply;

    /** The day's second stage (`lesson_dialogue.v1`): the skeleton's frames and lines put into DIALOGUE_COUNT exchanges. */
    public function buildDialogue(DialogueRequest $request): ModelReply;

    /**
     * The repair of one card of a day (`lesson_card_repair.v1.5`): `{card}` in the shape that card has in its stage — a
     * frame, a partner line or a word of the skeleton; an exchange, its check or a listening question of the dialogue.
     */
    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply;

    /** The seam judge: `{verdicts: [{id, reads}]}` — does each native sentence of the day's frames read. */
    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply;

    /**
     * The slot judge (`slot_judge.v3`, наряд SESSION-1a, разд. 4; v3 — наряд CONV-2): `{accepted, slot_value,
     * reason_native}` — in the mode the request names, whether the learner's answer says what the slot is about
     * (`answer`) or whether their own value is a value of the slot's kind (`own_value`). ONE
     * attempt within the judge's own timeout, synchronously inside the learner's request; a silence throws.
     */
    public function judgeSlot(SlotJudgeRequest $request): ModelReply;

    /**
     * ONE MOVE OF THE CONVERSATION AGENT (`conversation_agent.v3.4`, наряд CONV-1; v2 — CONV-2; v2.1 — BACK-TAILS-2 §9;
     * v3 — FIX-3 §7; v3.1 — FIX-4 §§3–4; v3.2 — FIX-4b §3; v3.3 — FIX-4c §6; v3.4 — ACC-1 §6): the role's reply in both languages, what it judged about the
     * learner's move, the target its line opens the door to and whether the talk is over — and the row of `model_calls` it
     * came from.
     * Synchronous, inside the learner's request, ONE attempt — a retry would only lengthen a wait the learner is sitting
     * through; a silence throws.
     */
    public function conversationTurn(ConversationAgentRequest $request): ModelReply;

    /** The versions stamped on every plan and lesson — read from the prompt files' names. */
    public function planPromptVersion(): string;

    public function skeletonPromptVersion(): string;

    public function dialoguePromptVersion(): string;

    /** The version a day's lesson is stamped with: its two stages', «lesson_skeleton.v1+lesson_dialogue.v1». */
    public function lessonPromptVersion(): string;

    public function repairPromptVersion(): string;

    public function judgePromptVersion(): string;

    /** The slot judge's version — what its counters (`judge.unavailable`) are kept under. */
    public function slotJudgePromptVersion(): string;

    /** The agent's version — stamped on every turn it writes. */
    public function conversationPromptVersion(): string;
}
