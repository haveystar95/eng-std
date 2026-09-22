<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Dto\DayWindowView;
use App\Modules\Plan\Application\Dto\SceneView;
use App\Modules\Plan\Application\Dto\WindowDayView;
use App\Modules\Plan\Application\Dto\WindowFrameView;
use App\Modules\Plan\Application\Dto\WindowGoalView;
use App\Modules\Plan\Application\Dto\WindowLineView;
use App\Modules\Plan\Application\Dto\WindowListeningView;
use App\Modules\Plan\Application\Dto\WindowPairView;
use App\Modules\Plan\Application\Dto\WindowPhraseView;
use App\Modules\Plan\Application\Dto\WindowProgramView;
use App\Modules\Plan\Application\Dto\WindowSourceView;
use App\Modules\Plan\Application\Dto\WindowStageView;
use App\Modules\Plan\Application\Dto\WindowSummaryView;
use App\Modules\Plan\Application\Dto\WindowUsageView;
use App\Modules\Plan\Application\Dto\WindowWordView;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Exception\SceneNotFound;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Service\ConversationOutcomes;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\Service\DayBudget;
use App\Modules\Plan\Domain\Service\DayHighlights;
use App\Modules\Plan\Domain\Service\DayStages;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\ValueObject\ConversationOutcome;
use App\Modules\Plan\Domain\Service\DayWindowStages;
use App\Modules\Plan\Domain\Service\ImageTones;
use App\Modules\Plan\Domain\Service\ReturnDay;
use App\Modules\Plan\Domain\Service\RouteStages;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\Service\WordUsage;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\ProgramSummary;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\TalkStage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Domain\ValueObject\UnitState;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Plan\Domain\ValueObject\WindowStage;
use App\Modules\Plan\Domain\ValueObject\WindowStatus;

/**
 * THE DAY WINDOW, READ OFF THE DAY'S CARDS (DAY-UI-2, DAY-UI-3; наряд SESSION-1a).
 *
 * The cards are the day's truth — dealt ones for an opened day, the dealer's outline for a day not
 * opened yet — so the programme lists exactly what the day deals, returned units included, and a
 * unit's state is its cards'. What a unit IS — a word's or a phrase's texts, a line of the dialogue —
 * is read off the scene, not off a card's payload: the terms by scene and ref, the dialogue of the
 * day's own scene exchange by exchange from its served lesson (a card of the registry carries only
 * what its trainer shows). Two more reads for any number of cards: the terms of the scenes the
 * cards touch (a word's photo and tone, how it reads, what it means) and the spoken files of those
 * scenes — every line of the dialogue, every phrase and word, each in its speaker's voice (DAY-UI-3).
 * Stages, minutes, states and summaries are the Domain's rules (`DayWindowStages`, `DayPace`,
 * `UnitStates`, `ProgramSummary`); the line a word is said in is `WordUsage`'s. This class only puts
 * them side by side.
 */
final readonly class DayWindowViews
{
    public function __construct(
        private PlanTermRepository $terms,
        private SceneVoices $voices,
        private PlanPaces $paces,
        private DayBudget $budget,
        private ConversationMaterial $material,
        private ConversationRules $rules,
        private VoiceCasts $voiceCasts,
        private ConversationViews $talks,
    ) {}

    /**
     * @param  list<DayCard>  $cards
     * @param  TalkStage|null  $talkStage  where the day's sixth stage stands (наряд CONV-2, п. 2) — null: nothing of it yet
     * @param  Conversation|null  $walked  the talk that walked the stage — the day's result, what «Что было хорошо» reads
     * @param  Conversation|null  $latest  the day's latest talk, its lines in hand — what the talk row's `targets` tick
     * @param  bool  $talkAgain  may the walked talk be held again today — the learner's replays of the day not spent (наряд FIX-3 §8)
     */
    public function of(
        Plan $plan,
        PlanDay $day,
        DayStatus $effective,
        bool $building,
        ?SceneView $scene,
        array $cards,
        ?TalkStage $talkStage = null,
        ?Conversation $walked = null,
        ?Conversation $latest = null,
        bool $talkAgain = false,
    ): DayWindowView {
        $status = WindowStatus::of($effective, $plan->status(), $day->number(), $building);
        $pace = $this->paces->for($plan);
        $talks = DayStages::walksConversation($day, $this->rules->enabled);
        // One formula for «сколько идёт день»: the cards' minutes plus the talk's own budget, which
        // is not the cards' and never stood under their ceiling ({@see DayBudget}).
        $talkMinutes = $this->budget->talkMinutes($day->type(), $talks);
        $material = $talks ? $this->material->for($plan, $day) : null;
        $stages = DayWindowStages::of(
            $cards, RouteStages::dealtBy($day->type()), $status, $pace,
            $talks, $talkStage, $talkMinutes,
            $material === null ? null : ['title' => $material->titleNative, 'scenes' => count($material->checkpoints)],
            $talkAgain,
        );
        $states = UnitStates::of($cards);
        $ownScene = $plan->sceneOf($day);

        $sceneIds = [];
        foreach ($cards as $card) {
            $sceneId = UnitStates::sceneOf($card);
            if ($sceneId !== '') {
                $sceneIds[$sceneId] = true;
            }
        }
        if ($ownScene !== null && $cards !== []) {
            $sceneIds[$ownScene->id()->value] = true;
        }
        $sceneIds = array_map('strval', array_keys($sceneIds));
        $casts = [];
        $sceneTones = [];
        $learner = $this->voiceCasts->learnerOf($plan->userId());
        foreach ($plan->scenes() as $planScene) {
            $sceneTones[$planScene->id()->value] = $planScene->image()?->tone;
            if (in_array($planScene->id()->value, $sceneIds, true)) {
                $casts[$planScene->id()->value] = VoiceCast::ofScene($planScene, $learner);
            }
        }
        $audio = $this->voices->index($plan->targetLang()->value, $casts);

        /** @var array<string, array<string, PlanTerm>> $termsByRef scene id → ref → term */
        $termsByRef = [];
        $known = array_values(array_filter($sceneIds, static fn (string $id): bool => isset($casts[$id])));
        $terms = $known === [] ? [] : $this->terms->forScenes(array_map(static fn (string $id): PlanSceneId => PlanSceneId::fromString($id), $known));
        foreach ($terms as $sceneId => $sceneTerms) {
            foreach ($sceneTerms as $term) {
                $termsByRef[(string) $sceneId][$term->ref()] = $term;
            }
        }

        // Where every item of the tabs is from (наряд FIX-3 §9): the scene it belongs to, named as the plan names it, with
        // the day of the route it stands on.
        $scenesOf = self::sceneNames($plan);
        [$words, $wordStates] = $this->words($plan, $day, $cards, $states, $sceneTones, $termsByRef, $audio, $scenesOf);
        [$phrases, $phraseStates] = $this->phrases($cards, $states, $termsByRef, $audio, $scenesOf);
        [$dialogue, $lineStates] = $this->dialogue($plan, $cards === [] ? null : $ownScene, $cards, $states, $audio, $scenesOf);

        return new DayWindowView(
            day: new WindowDayView(
                index: $day->number(),
                type: $day->type()->value,
                scene: $scene,
                imageTone: ImageTones::first($ownScene?->image()?->tone, $plan->coverImage()?->tone),
                status: $status->value,
                // The talk answers no card, so its minutes are added on top of what the cards cost —
                // and only while its stage is still ahead (наряд CONV-1; «walked» — наряд CONV-2).
                minutesEstimate: self::plusTalk(
                    DayWindowStages::minutesEstimate($cards, $status, $pace),
                    $talkStage === TalkStage::Passed ? 0 : $talkMinutes,
                ),
                minutesSpent: $status === WindowStatus::Passed ? $day->metrics()->minutesSpent : null,
                goals: array_map(
                    static fn (string $goal): WindowGoalView => new WindowGoalView($goal, $status === WindowStatus::Passed),
                    $ownScene?->goalsNative() ?? [],
                ),
            ),
            stages: array_map(fn (WindowStage $s): WindowStageView => new WindowStageView(
                $s->stage->value, $s->state->value, $s->doneCount, $s->total, $s->minutesLeft, $s->share, $s->talkTitle, $s->scenes, $s->minutes,
                // The talk's row carries the targets of its talk (наряд BACK-TAILS-2 §4, по вопросу клиента 1c): the very
                // list `POST …/conversation` starts the talk with — one selector of the day — ticked by the day's latest talk.
                $s->stage === Stage::Conversation && $material !== null ? $this->talks->targets($material, $latest) : null,
                $s->again,
                $s->summary,
            ), $stages),
            dayProgress: DayWindowStages::progress($stages),
            program: new WindowProgramView(
                $words, self::summary($wordStates, $words),
                $phrases, self::summary($phraseStates, $phrases),
                $dialogue, self::summary($lineStates, $dialogue),
            ),
            allowedAction: $status->action()?->value,
            listening: self::listening($ownScene?->lesson()),
            // «Что было хорошо» (кадр 30-7) is shown when the last stage is walked, before «Закрыть день» — not only
            // on a day already closed (наряд CONV-2, п. 9): the first pass through a day used to see it empty.
            highlights: $status === WindowStatus::Passed || DayWindowStages::allWalked($stages)
                ? DayHighlights::of($cards, $this->outcome($walked, $material), new NativeStrings($plan->nativeLang()->value, $learner))
                : [],
            sources: self::sources($plan, $day),
        );
    }

    /**
     * THE SCENES A DAY IS MADE OF (наряд BACK-TAILS-2 §4, кадры 37-1, 37-2): the rehearsal — every content scene of the
     * plan; a review — the scenes of the two scene days it repeats, the ones whose returns it takes; a scene day — its own
     * scene. Each named as the plan names it and with the day it stands on, in the plan's order of scenes — the order the
     * rehearsal's «Вспомнить» and its talk walk them in, and in a plan the generator built the order of their days.
     *
     * A scene with no day of its own — never in a plan the generator built, where every scene has its day, but a stand
     * put together by hand has one — is still a scene the rehearsal is made of: it is named in its place, with no day.
     *
     * @return list<WindowSourceView>
     */
    private static function sources(Plan $plan, PlanDay $day): array
    {
        $names = self::sceneNames($plan);
        $scenes = match ($day->type()) {
            DayType::Scene => [$plan->sceneOf($day)],
            DayType::Review => array_map(static fn (PlanDay $d): ?PlanScene => $plan->sceneOf($d), $plan->sceneDaysBefore($day->number(), 2)),
            DayType::Rehearsal => $plan->scenes(),
        };
        $scenes = array_values(array_filter($scenes, static fn (?PlanScene $s): bool => $s !== null));
        usort($scenes, static fn (PlanScene $a, PlanScene $b): int => $a->order() <=> $b->order());

        return array_map(static fn (PlanScene $s): WindowSourceView => $names[$s->id()->value], $scenes);
    }

    /**
     * Every scene of the plan as a day names it: its id, its name as the plan gives it, and the first day of the route it
     * stands on — null for a scene with no day of its own (a stand built by hand).
     *
     * @return array<string, WindowSourceView> by scene id
     */
    private static function sceneNames(Plan $plan): array
    {
        $dayOf = [];
        foreach ($plan->days() as $sceneDay) {
            $id = $sceneDay->type() === DayType::Scene ? $sceneDay->sceneId() : null;
            if ($id !== null) {
                $dayOf[$id->value] = min($dayOf[$id->value] ?? PHP_INT_MAX, $sceneDay->number());
            }
        }
        $out = [];
        foreach ($plan->scenes() as $scene) {
            $out[$scene->id()->value] = new WindowSourceView($scene->id()->value, $scene->titleNative(), $dayOf[$scene->id()->value] ?? null);
        }

        return $out;
    }

    /** `own` or `returned` — where a card, and the item of a tab it stands for, is from (наряд FIX-3 §9). */
    private static function sourceOf(?DayCard $card): string
    {
        return $card?->source() === CardSource::Returned ? WindowSourceView::RETURNED : WindowSourceView::OWN;
    }

    /** The minutes a day still asks for, with the talk's own on top; null stays null — a passed day asks for none. */
    private static function plusTalk(?int $minutes, int $talkMinutes): ?int
    {
        return $minutes === null ? null : $minutes + $talkMinutes;
    }

    /**
     * The summary of the talk that walked the day's sixth stage, for «Что было хорошо» — over the talk's targets, the
     * list the learner was shown. No such talk (none yet, or a day without one) — no lines about it.
     */
    private function outcome(?Conversation $walked, ?ConversationMaterialView $material): ?ConversationOutcome
    {
        if ($walked === null || $material === null || ! $walked->isEnded()) {
            return null;
        }

        return ConversationOutcomes::of($walked, $material->targets);
    }

    /**
     * The questions about the day's own visit, the right option marked where the served lesson put it.
     *
     * @return list<WindowListeningView>
     */
    private static function listening(?Lesson $lesson): array
    {
        $out = [];
        foreach ($lesson->listening ?? [] as $question) {
            $options = [];
            foreach ($question->optionsNative as $index => $option) {
                $options[] = ['text' => $option, 'correct' => $index === $question->correctOptionIndex];
            }
            $out[] = new WindowListeningView($question->textNative, $options, $question->explanationNative);
        }

        return $out;
    }

    /**
     * The frame behind a phrase, each filler with the voice of the frame said with it (TTS-2): its own file, or the
     * phrase's when the phrase already is the frame said with that filler.
     */
    private static function frame(?PlanTerm $term, string $sceneId, SceneAudioIndex $audio): ?WindowFrameView
    {
        $frame = $term?->frame();
        if ($term === null || $frame === null) {
            return null;
        }
        $voiced = [];
        foreach (SpokenLines::fillers($term) as $filler) {
            $voiced[$filler['index']] = $audio->idOf($sceneId, $filler['voicedAs']);
        }

        return new WindowFrameView(
            target: $frame->frameTarget,
            native: $frame->frameNative,
            pronunciation: $frame->pronunciationNative,
            kind: $frame->kind->value,
            slot: $frame->slot === null ? null : [
                'hint' => $frame->slot->hintNative,
                'fillers' => array_map(static fn (Filler $f, int $i): array => [
                    'target' => $f->target,
                    'native' => $f->native,
                    'pronunciation' => $f->pronunciationNative,
                    'in_dialogue' => $f->inDialogue,
                    'audio_id' => $voiced[$i] ?? null,
                ], $frame->slot->fillers, array_keys($frame->slot->fillers)),
            ],
        );
    }

    /**
     * The words and chunks the day deals, in the order their first card comes, each read off its term (by scene and
     * ref) — never off a card's payload, which carries only what its trainer shows.
     *
     * @param  list<DayCard>  $cards
     * @param  array<string, UnitState>  $states
     * @param  array<string, string|null>  $sceneTones
     * @param  array<string, array<string, PlanTerm>>  $termsByRef
     * @param  array<string, WindowSourceView>  $scenesOf
     * @return array{0: list<WindowWordView>, 1: list<UnitState>}
     */
    private function words(Plan $plan, PlanDay $day, array $cards, array $states, array $sceneTones, array $termsByRef, SceneAudioIndex $audio, array $scenesOf): array
    {
        $returnsDay = ReturnDay::of($plan, $day);
        $out = [];
        $unitStates = [];
        foreach ($cards as $card) {
            if ($card->unitKind() !== UnitKind::Word) {
                continue;
            }
            $sceneId = UnitStates::sceneOf($card);
            $key = UnitStates::key($sceneId, UnitKind::Word, $card->unitRef());
            if (isset($out[$key])) {
                continue;
            }
            $term = $termsByRef[$sceneId][$card->unitRef()] ?? null;
            $photo = $term?->image();
            $state = $states[$key] ?? UnitState::Pending;
            $text = $term?->textTarget() ?? '';
            $out[$key] = new WindowWordView(
                ref: $card->unitRef(),
                term: $text,
                translation: $term?->textNative() ?? '',
                image: $photo === null ? null : [...$photo->toArray(), 'tone' => $photo->tone],
                imageTone: ImageTones::first($term?->imageTone(), $sceneTones[$sceneId] ?? null, $plan->coverImage()?->tone),
                state: $state->value,
                pronunciation: $term?->pronunciationNative(),
                definition: $term?->definitionTarget(),
                audioId: $audio->idOf($sceneId, $card->unitRef()),
                usage: $this->usage($plan, $sceneId, $card->unitRef(), $text, $audio),
                returnsDay: $state === UnitState::ReturnsTomorrow ? $returnsDay : null,
                usedIn: $term?->usedIn() ?? [],
                source: self::sourceOf($card),
                scene: $scenesOf[$sceneId] ?? null,
            );
            $unitStates[] = $state;
        }

        return [array_values($out), $unitStates];
    }

    /** The line of the day the word is said in, with its voice — or null when the dialogue never says it. */
    private function usage(Plan $plan, string $sceneId, string $ref, string $term, SceneAudioIndex $audio): ?WindowUsageView
    {
        try {
            $lesson = $sceneId === '' ? null : $plan->scene(PlanSceneId::fromString($sceneId))->lesson();
        } catch (SceneNotFound) {
            $lesson = null;
        }
        $line = $lesson === null ? null : WordUsage::of($lesson, $ref, $term);
        if ($line === null) {
            return null;
        }

        return new WindowUsageView(
            text: $line['text'],
            translation: $line['translation'],
            offset: $line['offset'],
            length: $line['length'],
            audioId: $audio->idOf($sceneId, $line['speaker'] === Speaker::Partner ? SpokenLines::partnerRef($line['step']) : SpokenLines::learnerRef($line['step'])),
        );
    }

    /**
     * The phrases the day deals, in the order their first card comes, each read off its term (by scene and ref): the
     * frame said with the dialogue's filler, its reading, its voice, and the frame itself.
     *
     * @param  list<DayCard>  $cards
     * @param  array<string, UnitState>  $states
     * @param  array<string, array<string, PlanTerm>>  $termsByRef
     * @param  array<string, WindowSourceView>  $scenesOf
     * @return array{0: list<WindowPhraseView>, 1: list<UnitState>}
     */
    private function phrases(array $cards, array $states, array $termsByRef, SceneAudioIndex $audio, array $scenesOf): array
    {
        $out = [];
        $unitStates = [];
        foreach ($cards as $card) {
            if ($card->unitKind() !== UnitKind::Phrase) {
                continue;
            }
            $sceneId = UnitStates::sceneOf($card);
            $key = UnitStates::key($sceneId, UnitKind::Phrase, $card->unitRef());
            if (isset($out[$key])) {
                continue;
            }
            $term = $termsByRef[$sceneId][$card->unitRef()] ?? null;
            $state = $states[$key] ?? UnitState::Pending;
            $out[$key] = new WindowPhraseView(
                ref: $card->unitRef(),
                text: $term?->textTarget() ?? '',
                translation: $term?->textNative() ?? '',
                state: $state->value,
                pronunciation: $term?->pronunciationNative(),
                audioId: $audio->idOf($sceneId, $card->unitRef()),
                frame: self::frame($term, $sceneId, $audio),
                source: self::sourceOf($card),
                scene: $scenesOf[$sceneId] ?? null,
            );
            $unitStates[] = $state;
        }

        return [array_values($out), $unitStates];
    }

    /**
     * THE DIALOGUE TAB (наряд SESSION-1a, разд. 5 «Window»): every exchange of the day's OWN scene, in the order of its
     * served lesson — an exchange the day deals no card on is still a line of the visit — and after them the exchanges
     * of other scenes the day's cards are about (a return, a review, the rehearsal), in the order their first card
     * comes. Texts, the kind, the frame and filler of the learner's line are the lesson's; both lines carry their
     * voice, each in its speaker's.
     *
     * The learner's line takes its exchange's state: over the exchange's cards ({@see UnitStates}) when it has any;
     * an exchange without cards is walked once every card of the dialogue stage is answered — the stage is where the
     * visit is walked through — and pending until then.
     *
     * @param  PlanScene|null  $own  the day's own scene — null for a review or the rehearsal, and for a day with no card
     * @param  list<DayCard>  $cards
     * @param  array<string, UnitState>  $states
     * @param  array<string, WindowSourceView>  $scenesOf
     * @return array{0: list<WindowPairView>, 1: list<UnitState>}
     */
    private function dialogue(Plan $plan, ?PlanScene $own, array $cards, array $states, SceneAudioIndex $audio, array $scenesOf): array
    {
        $ownId = $own?->id()->value;
        $withCards = [];
        /** @var array<string, array{scene: string, step: int, card: DayCard}> $others */
        $others = [];
        $dialogueCards = 0;
        $dialogueAnswered = 0;
        foreach ($cards as $card) {
            if ($card->stage() === Stage::Dialogue) {
                $dialogueCards++;
                $dialogueAnswered += $card->isAnswered() ? 1 : 0;
            }
            $step = SpokenLines::stepOfRef($card->unitRef());
            if ($card->unitKind() !== UnitKind::Exchange || $step === null) {
                continue;
            }
            $sceneId = UnitStates::sceneOf($card);
            $withCards[$sceneId.':'.$step] = true;
            if ($sceneId !== $ownId) {
                $others[$sceneId.':'.$step] ??= ['scene' => $sceneId, 'step' => $step, 'card' => $card];
            }
        }
        $walked = $dialogueCards > 0 && $dialogueAnswered === $dialogueCards ? UnitState::Done : UnitState::Pending;

        $out = [];
        $unitStates = [];
        $seen = [];
        foreach ($own?->lesson()->exchanges ?? [] as $exchange) {
            if ($ownId === null || isset($seen[$exchange->step])) {
                continue;
            }
            $seen[$exchange->step] = true;
            $state = isset($withCards[$ownId.':'.$exchange->step])
                ? ($states[UnitStates::key($ownId, UnitKind::Exchange, SpokenLines::exchangeRef($exchange->step))] ?? UnitState::Pending)
                : $walked;
            [$pair, $learnerState] = self::pair($ownId, $exchange->step, $exchange, null, $state, $audio, $scenesOf[$ownId] ?? null);
            $out[] = $pair;
            if ($learnerState !== null) {
                $unitStates[] = $learnerState;
            }
        }
        foreach ($others as $other) {
            $state = $states[UnitStates::key($other['scene'], UnitKind::Exchange, SpokenLines::exchangeRef($other['step']))] ?? UnitState::Pending;
            [$pair, $learnerState] = self::pair(
                $other['scene'], $other['step'], self::exchangeOf($plan, $other['scene'], $other['step']), $other['card'], $state, $audio,
                $scenesOf[$other['scene']] ?? null,
            );
            $out[] = $pair;
            if ($learnerState !== null) {
                $unitStates[] = $learnerState;
            }
        }

        return [$out, $unitStates];
    }

    /**
     * One exchange of the tab, its texts from the lesson — or, when the scene's lesson is not there to read, from the
     * lines the exchange's card carries.
     *
     * @return array{0: WindowPairView, 1: UnitState|null} the pair, and the state of its learner's line when it has one
     */
    private static function pair(string $sceneId, int $step, ?Exchange $exchange, ?DayCard $card, UnitState $state, SceneAudioIndex $audio, ?WindowSourceView $scene): array
    {
        $partner = $exchange !== null ? self::messageLine($exchange->partner()) : self::payloadLine($card?->payload()['partner_line'] ?? null);
        $learner = $exchange !== null ? self::messageLine($exchange->learner()) : self::learnerLineOf($card);
        $said = $exchange?->learner();

        return [
            new WindowPairView(
                $step,
                $partner === null ? null : new WindowLineView(
                    $partner[0], $partner[1], $audio->idOf($sceneId, SpokenLines::partnerRef($step)), null,
                ),
                $learner === null ? null : new WindowLineView(
                    $learner[0], $learner[1], $audio->idOf($sceneId, SpokenLines::learnerRef($step)), $state->value,
                    $said?->phraseId, $said?->filler,
                ),
                $exchange?->kind->value,
                self::sourceOf($card),
                $scene,
            ),
            $learner === null ? null : $state,
        ];
    }

    /** The exchange of a scene's served lesson, or null when the scene or its lesson is not there. */
    private static function exchangeOf(Plan $plan, string $sceneId, int $step): ?Exchange
    {
        try {
            return $sceneId === '' ? null : $plan->scene(PlanSceneId::fromString($sceneId))->lesson()?->exchange($step);
        } catch (SceneNotFound) {
            return null;
        }
    }

    /** @return array{0: string, 1: string}|null */
    private static function messageLine(?Message $message): ?array
    {
        if ($message === null || trim($message->textTarget) === '') {
            return null;
        }

        return [trim($message->textTarget), $message->textNative];
    }

    /**
     * The learner's line as an exchange card carries it: the own line of an answer, an ask or a speak card, the rescue
     * line of a rescue. The cards about the partner's line alone carry none.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function learnerLineOf(?DayCard $card): ?array
    {
        $payload = $card?->payload() ?? [];
        foreach (['own_line', 'rescue_line'] as $key) {
            if (is_array($payload[$key] ?? null)) {
                return self::payloadLine($payload[$key]);
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string}|null */
    private static function payloadLine(mixed $source): ?array
    {
        if (! is_array($source)) {
            return null;
        }
        $value = is_string($source['text_target'] ?? null) ? trim($source['text_target']) : '';

        return $value === '' ? null : [$value, is_string($source['text_native'] ?? null) ? $source['text_native'] : ''];
    }

    /**
     * @param  list<UnitState>  $states
     * @param  list<WindowWordView|WindowPhraseView|WindowPairView>  $items  the tab's items — how many of them came back
     */
    private static function summary(array $states, array $items): WindowSummaryView
    {
        $returned = count(array_filter($items, static fn (WindowWordView|WindowPhraseView|WindowPairView $i): bool => $i->source === WindowSourceView::RETURNED));
        $summary = ProgramSummary::of($states, $returned);

        return new WindowSummaryView($summary->total, $summary->done, $summary->returns);
    }
}
