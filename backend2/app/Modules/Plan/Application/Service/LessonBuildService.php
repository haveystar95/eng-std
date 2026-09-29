<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairOutcome;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\LessonSeamVerdict;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\BuildVersion;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueCheck;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\CarriedWords;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\AskReplies;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Lesson\LessonAssembler;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\OptionShuffle;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use Closure;
use Throwable;

/**
 * THE DAY, BUILT IN TWO STAGES (наряд GEN-4, `docs/plan-v2.md` §2) — the conveyor of one scene's lesson:
 *
 *   skeleton → SkeletonCheck → seam judge → repair of the skeleton → dialogue → DialogueCheck → shuffle of the options →
 *   repair of the dialogue → the lesson assembled.
 *
 *  - THE SKELETON (`lesson_skeleton.v1.1`) turns the scene's survival set into frames, partner lines and words. {@see SkeletonCheck}
 *    reads it; a FATAL finding asks the skeleton once more with the findings quoted (`PREVIOUS_ATTEMPT_REJECTED_FOR`), an
 *    answer off the schema the same — one repeat a stage, no more; still fatal, the day fails with its codes.
 *  - THE SEAM JUDGE reads the skeleton's native frames said with their fillers, before the dialogue exists — and in the same
 *    call the partner's replies to the learner's questions, whether one names a filler of its question (наряд GEN-4c).
 *  - THE SKELETON'S REPAIRS: the cards its warnings — and the seam judge's findings — stand at, at most {@see REPAIR_CARDS},
 *    taken in the repairs' order ({@see LessonCodes::REPAIR_ORDER}: a letter of another writing, a placeholder word, a yes or
 *    no, a reply naming a filler, then the rest) and repaired frames first, then lines, then words
 *    ({@see LessonCard::SKELETON_KINDS}); each repair is checked again and kept only if it brings no fatal finding. What a
 *    repair changed that the judge reads — a frame, a reply, a question whose fillers changed — the seam judge reads once
 *    more, in one call.
 *  - THE DIALOGUE (`lesson_dialogue.v1.1`) puts the repaired skeleton into DIALOGUE_COUNT exchanges; {@see DialogueCheck}, one
 *    repeat for a fatal finding, as the skeleton.
 *  - THE SHUFFLE: the server puts the right option of every check and every listening question where the scene's seed says
 *    ({@see OptionShuffle}) — the model's index says only which option is right.
 *  - THE DIALOGUE'S REPAIRS — exchanges, checks, listening questions — as the skeleton's.
 *  - THE LESSON is assembled from the two ({@see LessonAssembler}) in the shape every reader of a scene deals from, spoken in
 *    the roles the plan gives.
 *
 * A call that got no answer at all (a timeout) is not retried here or anywhere (наряд GEN-3): the day fails, the learner
 * asks again. Every finding is counted by its code under the version of the prompt it was found in (`gated` when it asked
 * its stage again, `failed` when it failed the day); the warnings left are stored with the lesson.
 */
final readonly class LessonBuildService
{
    /** How many times a stage is asked: once, and once more for a fatal finding or an answer off the schema. */
    public const STAGE_ATTEMPTS = 2;

    /** How many cards of a stage are sent to a repair, at most, each once — four of the skeleton, four of the dialogue (наряд GEN-4c; two before it). */
    public const REPAIR_CARDS = 4;

    public function __construct(
        private PlanModelPort $model,
        private LessonParser $parser,
        private SkeletonCheck $skeletons,
        private DialogueCheck $dialogues,
        private LessonContexts $contexts,
        private LessonSeamJudge $seams,
        private LessonCardRepairer $repairer,
        private CheckCounters $counters,
        private BuildVersion $build,
    ) {}

    public function build(LessonRequest $request): LessonBuildOutcome
    {
        $log = new LessonBuildLog;
        $bill = new LessonBill;

        $skeletonContext = $this->contexts->skeleton($request);
        [$skeleton, $found, $failed] = $this->skeletonStage($request, $skeletonContext, $log, $bill);
        if ($skeleton === null) {
            return LessonBuildOutcome::failed((string) $failed, $this->call($bill), self::rows($found), $log);
        }

        $seams = $this->judge($skeleton, null, null, $request, $log, $bill);
        [$skeleton, $found, $seams] = $this->repairSkeleton($skeleton, $found, $seams, $request, $skeletonContext, $log, $bill);

        $dialogueContext = $this->contexts->dialogue($request, $skeleton);
        [$dialogue, $spoken, $failed] = $this->dialogueStage(new DialogueRequest($request, $skeleton), $dialogueContext, $log, $bill);
        if ($dialogue === null) {
            return LessonBuildOutcome::failed((string) $failed, $this->call($bill), self::rows([...$found, ...$seams, ...$spoken]), $log);
        }
        $dialogue = OptionShuffle::of($dialogue, $request->sceneId);
        [$dialogue, $spoken] = $this->repairDialogue($skeleton, $dialogue, $spoken, $request, $dialogueContext, $log, $bill);

        $lesson = LessonAssembler::assemble($skeleton, $dialogue)->withRoles($request->roles);

        return LessonBuildOutcome::ok($lesson, $skeleton, $this->call($bill), self::rows([...$found, ...$seams, ...$spoken]), $log);
    }

    /**
     * The skeleton, asked once and once more for a fatal finding.
     *
     * @return array{0: Skeleton|null, 1: list<LessonViolation>, 2: string|null}
     */
    private function skeletonStage(LessonRequest $request, SkeletonContext $context, LessonBuildLog $log, LessonBill $bill): array
    {
        $violations = [];
        $found = [];
        $failed = null;
        for ($attempt = 1; $attempt <= self::STAGE_ATTEMPTS; $attempt++) {
            $reply = $this->ask(fn (): ModelReply => $this->model->buildSkeleton($request->withViolations($violations)));
            $bill->stage($reply);
            $log->call('skeleton', $attempt, $reply);
            try {
                $skeleton = $this->parser->forNative($request->nativeLangCode)->skeleton($reply->payload);
            } catch (ModelAnswerOffSchema $e) {
                $log->attempt('skeleton', $attempt, [], [], $e->getMessage());
                [$violations, $found, $failed] = [[$e->getMessage()], [], $e->getMessage()];

                continue;
            }
            $found = $this->skeletons->run($skeleton, $context);
            [$violations, $failed] = $this->counted($reply->promptVersion, $found, 'skeleton', $attempt, $log);
            if ($failed === null) {
                return [$skeleton, $found, null];
            }
        }
        $this->counters->recordCodes($this->model->skeletonPromptVersion(), self::codes(self::fatalOf($found)), CheckAction::Failed);

        return [null, $found, $failed];
    }

    /**
     * The dialogue, asked once and once more for a fatal finding.
     *
     * @return array{0: Dialogue|null, 1: list<LessonViolation>, 2: string|null}
     */
    private function dialogueStage(DialogueRequest $request, DialogueContext $context, LessonBuildLog $log, LessonBill $bill): array
    {
        $violations = [];
        $found = [];
        $failed = null;
        for ($attempt = 1; $attempt <= self::STAGE_ATTEMPTS; $attempt++) {
            $reply = $this->ask(fn (): ModelReply => $this->model->buildDialogue($request->withViolations($violations)));
            $bill->stage($reply);
            $log->call('dialogue', $attempt, $reply);
            try {
                $dialogue = $this->parser->forNative($request->lesson->nativeLangCode)->dialogue($reply->payload);
            } catch (ModelAnswerOffSchema $e) {
                $log->attempt('dialogue', $attempt, [], [], $e->getMessage());
                [$violations, $found, $failed] = [[$e->getMessage()], [], $e->getMessage()];

                continue;
            }
            $found = $this->dialogues->run($dialogue, $context);
            [$violations, $failed] = $this->counted($reply->promptVersion, $found, 'dialogue', $attempt, $log);
            if ($failed === null) {
                return [$dialogue, $found, null];
            }
        }
        $this->counters->recordCodes($this->model->dialoguePromptVersion(), self::codes(self::fatalOf($found)), CheckAction::Failed);

        return [null, $found, $failed];
    }

    /**
     * A stage's answer counted: every code found, the fatal ones `gated` too. Null when nothing fatal was found; else the
     * findings to quote on the repeat and why the stage fails if the repeat finds them too.
     *
     * @param  list<LessonViolation>  $found
     * @return array{0: list<string>, 1: string|null}
     */
    private function counted(string $version, array $found, string $stage, int $attempt, LessonBuildLog $log): array
    {
        $this->counters->recordCodes($version, self::codes($found));
        $fatal = self::fatalOf($found);
        $log->attempt($stage, $attempt, $found, $fatal);
        if ($fatal === []) {
            return [[], null];
        }
        $this->counters->recordCodes($version, self::codes($fatal), CheckAction::Gated);

        return [
            array_map(static fn (LessonViolation $v): string => "{$v->code} · {$v->address}: {$v->detail}", $fatal),
            LessonCodes::failReason($fatal),
        ];
    }

    /**
     * The skeleton's warnings sent to repairs — the seam judge's among them — and what the judge reads that a repair changed
     * read again: a frame's native seams, and a reply to a question of the learner's — its partner line rewritten, or its
     * question's fillers (наряд GEN-4c).
     *
     * @param  list<LessonViolation>  $found
     * @param  list<LessonViolation>  $seams
     * @return array{0: Skeleton, 1: list<LessonViolation>, 2: list<LessonViolation>}
     */
    private function repairSkeleton(Skeleton $skeleton, array $found, array $seams, LessonRequest $request, SkeletonContext $context, LessonBuildLog $log, LessonBill $bill): array
    {
        $frames = [];
        $lines = [];
        $words = $context->targetReading('function_words', 'word_forms');
        foreach (self::cards([...$found, ...$seams], LessonCard::SKELETON_KINDS) as $card) {
            $sent = self::at($card, [...$found, ...$seams]);
            if ($sent === []) {
                // An earlier repair took the card's findings away (the e2e of GEN-4c paid for a word sent with none).
                continue;
            }
            $outcome = $this->repairer->repair($skeleton, null, $card, [...$sent, ...self::carried($skeleton, $card, $words)], $request);
            $bill->repair($outcome);
            $after = $outcome->skeleton === null ? null : $this->skeletons->run($outcome->skeleton, $context);
            if (! $this->kept($outcome, $after, 'skeleton', $card, $sent, $log) || $outcome->skeleton === null || $after === null) {
                continue;
            }
            $skeleton = $outcome->skeleton;
            $found = $after;
            if ($card->kind === LessonCard::FRAME) {
                $frames[] = $card->id;
            } elseif ($card->kind === LessonCard::PARTNER_LINE) {
                $lines[] = $card->id;
            }
        }
        foreach ($skeleton->repliesToAsks() as [$line, $frame]) {
            if (in_array($frame->id(), $frames, true)) {
                $lines[] = $line->id;
            }
        }
        $lines = array_values(array_unique($lines));
        if ($frames !== [] || $lines !== []) {
            // What the judge found at a card it reads again is its answer of then: the answer of now replaces it.
            $seams = array_values(array_filter($seams, static function (LessonViolation $v) use ($frames, $lines): bool {
                $card = LessonCard::at($v->address);

                return $card === null || ! in_array($card->id, [...$frames, ...$lines], true);
            }));
            $again = $this->judge($skeleton, $frames, $lines, $request, $log, $bill);
            $seams = [...$seams, ...$again];
            foreach ([...$frames, ...$lines] as $address) {
                $card = LessonCard::at($address);
                $left = $card === null ? [] : self::codes(self::at($card, [...$found, ...$seams]));
                $log->helped($address, array_intersect($left, self::sentCodes($log, $address)) === [], $left);
            }
        }

        return [$skeleton, $found, $seams];
    }

    /**
     * The dialogue's warnings sent to repairs.
     *
     * @param  list<LessonViolation>  $found
     * @return array{0: Dialogue, 1: list<LessonViolation>}
     */
    private function repairDialogue(Skeleton $skeleton, Dialogue $dialogue, array $found, LessonRequest $request, DialogueContext $context, LessonBuildLog $log, LessonBill $bill): array
    {
        foreach (self::cards($found, LessonCard::DIALOGUE_KINDS) as $card) {
            $sent = self::at($card, $found);
            if ($sent === []) {
                continue;
            }
            $outcome = $this->repairer->repair($skeleton, $dialogue, $card, $sent, $request);
            $bill->repair($outcome);
            $after = $outcome->dialogue === null ? null : $this->dialogues->run($outcome->dialogue, $context);
            if (! $this->kept($outcome, $after, 'dialogue', $card, $sent, $log) || $outcome->dialogue === null || $after === null) {
                continue;
            }
            $dialogue = $outcome->dialogue;
            $found = $after;
        }

        return [$dialogue, $found];
    }

    /**
     * Is a repair kept? It came back as a card, and its stage checked again has no fatal finding — and no finding of a code the
     * repairs take first ({@see LessonCodes::BUDGETED}) at its card that the card was not sent with (наряд GEN-4c). Written
     * down either way, with whether it helped: the codes it was sent for are gone from its card (a frame's seams and a
     * reply's naming are known only after the judge reads it again).
     *
     * @param  list<LessonViolation>|null  $after
     * @param  list<LessonViolation>  $sent
     */
    private function kept(LessonCardRepairOutcome $outcome, ?array $after, string $stage, LessonCard $card, array $sent, LessonBuildLog $log): bool
    {
        $sentFor = self::codes($sent);
        if ($outcome->status !== LessonCardRepairOutcome::REPAIRED || $after === null) {
            $log->repair($stage, $card->address, $card->kind, $sentFor, $outcome->status, false, [], $sentFor, false, $outcome->note);

            return false;
        }
        $broke = self::codes(self::fatalOf($after));
        if ($broke !== []) {
            $log->repair($stage, $card->address, $card->kind, $sentFor, $outcome->status, false, $broke, $sentFor, false, 'the repair brings a fatal finding');

            return false;
        }
        // A finding the repairs take first that the card did not have (наряд GEN-4c): the e2e of GEN-4c sent «a veni», a
        // stop word, to a repair, and the repair wrote «casier» — a word of a placeholder. The card stays as it was.
        $brought = array_values(array_diff(array_intersect(self::codes(self::at($card, $after)), LessonCodes::BUDGETED), $sentFor));
        if ($brought !== []) {
            $log->repair($stage, $card->address, $card->kind, $sentFor, $outcome->status, false, $brought, $sentFor, false, 'the repair brings a finding the repairs take first');

            return false;
        }
        $left = self::codes(self::at($card, $after));
        $waiting = ($card->kind === LessonCard::FRAME && in_array(LessonCodes::FILLER_NATIVE_SEAM, $sentFor, true))
            || ($card->kind === LessonCard::PARTNER_LINE && in_array(LessonCodes::NAMES_FILLER_MEANING, $sentFor, true));
        $log->repair($stage, $card->address, $card->kind, $sentFor, $outcome->status, true, [], $left, $waiting ? null : array_intersect($left, $sentFor) === []);

        return true;
    }

    /**
     * The seam judge over some frames and some replies of the skeleton — every one of them when null: what does not read, as
     * findings at the fillers; a reply that names a filler of its question (наряд GEN-4c), at its partner line.
     *
     * @param  list<string>|null  $frames  the frames whose native seams to read
     * @param  list<string>|null  $lines  the partner lines whose replies to read
     * @return list<LessonViolation>
     */
    private function judge(Skeleton $skeleton, ?array $frames, ?array $lines, LessonRequest $request, LessonBuildLog $log, LessonBill $bill): array
    {
        $phrases = array_values(array_filter($skeleton->phrases(), static fn (Phrase $p): bool => $frames === null || in_array($p->id, $frames, true)));
        $replies = AskReplies::of($skeleton, $lines);
        $verdict = $this->seams->judge($phrases, $request->nativeLanguage, $replies, $request->targetLanguage);
        $bill->judge($verdict);
        $version = $this->model->skeletonPromptVersion();
        $this->counters->recordCodes($version, self::codes($verdict->violations));
        if ($verdict->status === LessonSeamVerdict::UNAVAILABLE) {
            $this->counters->recordCodes($version, [LessonCodes::JUDGE_UNAVAILABLE]);
        }
        $log->judgement(
            array_map(static fn (Phrase $p): string => $p->id, $phrases),
            $verdict->items,
            $verdict->judged,
            $verdict->status,
            array_values(array_map(
                static fn (LessonViolation $v): string => $v->address,
                array_filter($verdict->violations, static fn (LessonViolation $v): bool => $v->code === LessonCodes::FILLER_NATIVE_SEAM),
            )),
            array_map(static fn (array $reply): string => $reply['id'], $replies),
            $verdict->naming,
        );

        return $verdict->violations;
    }

    /** @param Closure(): ModelReply $call */
    private function ask(Closure $call): ModelReply
    {
        try {
            return $call();
        } catch (PlanModelUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            throw PlanModelUnavailable::because($e->getMessage());
        }
    }

    private function call(LessonBill $bill): ModelCall
    {
        return new ModelCall($this->model->lessonPromptVersion(), $this->build->current(), $bill->models(), $bill->costUsd, $bill->latencyMs, $bill->stageCalls);
    }

    /**
     * The findings that ask a stage once more: the fatal ones, and those of a budgeted code the stage's repairs cannot take
     * ({@see LessonCodes::overBudget()}).
     *
     * @param  list<LessonViolation>  $found
     * @return list<LessonViolation>
     */
    private static function fatalOf(array $found): array
    {
        return [...LessonCodes::fatalOf($found), ...LessonCodes::overBudget($found, self::REPAIR_CARDS)];
    }

    /**
     * The cards the non-fatal findings stand at, each once, at most {@see REPAIR_CARDS} — TAKEN in the repairs' order (наряд
     * GEN-4c §4, {@see LessonCodes::REPAIR_ORDER}: a card goes by the first of its findings there — a letter of another
     * writing, a placeholder word, a yes or no, a reply the judge finds naming a filler — then by its stage's kinds, then by
     * address) and REPAIRED in the order of their stage's kinds, then by address: what the others are built on goes first
     * ({@see LessonCard::SKELETON_KINDS}). The e2e of GEN-4c repaired a placeholder word first, into a word of a line that named
     * a filler, and the line's own repair was then refused — it took that word away (`vocab.not_found`). A finding about a
     * stage as a whole stands at no card.
     *
     * @param  list<LessonViolation>  $findings
     * @param  list<string>  $kinds
     * @return list<LessonCard>
     */
    private static function cards(array $findings, array $kinds): array
    {
        $cards = [];
        $rank = [];
        foreach ($findings as $finding) {
            $card = LessonCodes::isFatal($finding->code) ? null : LessonCard::at($finding->address);
            if ($card !== null && in_array($card->kind, $kinds, true)) {
                $cards[$card->address] = $card;
                $rank[$card->address] = min($rank[$card->address] ?? PHP_INT_MAX, LessonCodes::repairRank($finding->code));
            }
        }
        $byKind = static fn (LessonCard $a, LessonCard $b): int => array_search($a->kind, $kinds, true) <=> array_search($b->kind, $kinds, true)
            ?: strnatcmp($a->address, $b->address);
        $cards = array_values($cards);
        usort($cards, static fn (LessonCard $a, LessonCard $b): int => $rank[$a->address] <=> $rank[$b->address] ?: $byKind($a, $b));
        $taken = array_slice($cards, 0, self::REPAIR_CARDS);
        usort($taken, $byKind);

        return $taken;
    }

    /**
     * The note a repair of a frame or a partner line is sent with beside its findings (наряд GEN-4c): the words of the day only
     * this card says ({@see CarriedWords}) — keep them. None when there are none.
     *
     * @return list<LessonViolation>
     */
    private static function carried(Skeleton $skeleton, LessonCard $card, ?LanguageWords $words): array
    {
        $items = CarriedWords::of($skeleton, $card, $words);
        if ($items === []) {
            return [];
        }
        $named = implode(', ', array_map(static fn ($v): string => "«{$v->termTarget}» ({$v->id})", $items));

        return [new LessonViolation(LessonCodes::REPAIR_NOTE_CARRIED, $card->address, "not a finding: this card alone says the words of the day {$named} — keep each of them in the card you write, or the day loses it")];
    }

    /**
     * @param  list<LessonViolation>  $findings
     * @return list<LessonViolation>
     */
    private static function at(LessonCard $card, array $findings): array
    {
        return array_values(array_filter($findings, static fn (LessonViolation $v): bool => $card->covers($v)));
    }

    /** @return list<string> the codes a frame was sent to its repair for */
    private static function sentCodes(LessonBuildLog $log, string $address): array
    {
        foreach ($log->repairs as $repair) {
            if ($repair['address'] === $address && $repair['kept']) {
                return $repair['sent_for'];
            }
        }

        return [];
    }

    /**
     * @param  list<LessonViolation>  $violations
     * @return list<string>
     */
    private static function codes(array $violations): array
    {
        return array_map(static fn (LessonViolation $v): string => $v->code, $violations);
    }

    /**
     * @param  list<LessonViolation>  $violations
     * @return list<array{code: string, address: string, detail: string}>
     */
    private static function rows(array $violations): array
    {
        return array_map(static fn (LessonViolation $v): array => $v->toArray(), $violations);
    }
}
