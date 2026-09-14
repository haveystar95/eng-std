<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

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
use App\Modules\Plan\Application\Dto\WindowStageView;
use App\Modules\Plan\Application\Dto\WindowSummaryView;
use App\Modules\Plan\Application\Dto\WindowUsageView;
use App\Modules\Plan\Application\Dto\WindowWordView;
use App\Modules\Plan\Domain\Assembly\CardPayloads;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Exception\SceneNotFound;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\DayWindowStages;
use App\Modules\Plan\Domain\Service\ImageTones;
use App\Modules\Plan\Domain\Service\RouteStages;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\Service\WordUsage;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\ProgramSummary;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Domain\ValueObject\UnitState;
use App\Modules\Plan\Domain\ValueObject\VoiceCast;
use App\Modules\Plan\Domain\ValueObject\WindowStage;
use App\Modules\Plan\Domain\ValueObject\WindowStatus;

/**
 * THE DAY WINDOW, READ OFF THE DAY'S CARDS (DAY-UI-2, DAY-UI-3).
 *
 * The cards are the day's truth — dealt ones for an opened day, the dealer's outline for a day not
 * opened yet — so the programme lists exactly what the day deals, returned units included, and a
 * unit's state is its cards'. Two more reads for any number of cards: the terms of the scenes the
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
    ) {}

    /** @param list<DayCard> $cards */
    public function of(Plan $plan, PlanDay $day, DayStatus $effective, ?SceneView $scene, array $cards): DayWindowView
    {
        $status = WindowStatus::of($effective, $plan->status(), $day->number());
        $stages = DayWindowStages::of($cards, RouteStages::dealtBy($day->type()), $status);
        $states = UnitStates::of($cards);

        $sceneIds = [];
        foreach ($cards as $card) {
            $sceneId = UnitStates::sceneOf($card);
            if ($sceneId !== '') {
                $sceneIds[$sceneId] = true;
            }
        }
        $sceneIds = array_map('strval', array_keys($sceneIds));
        $casts = [];
        $sceneTones = [];
        foreach ($plan->scenes() as $planScene) {
            $sceneTones[$planScene->id()->value] = $planScene->image()?->tone;
            if (in_array($planScene->id()->value, $sceneIds, true)) {
                $casts[$planScene->id()->value] = VoiceCast::ofScene($planScene);
            }
        }
        $audio = $this->voices->index($plan->targetLang()->value, $casts);

        $termsById = [];
        $terms = $sceneIds === [] ? [] : $this->terms->forScenes(array_map(static fn (string $id): PlanSceneId => PlanSceneId::fromString($id), $sceneIds));
        foreach ($terms as $sceneTerms) {
            foreach ($sceneTerms as $term) {
                $termsById[$term->id()->value] = $term;
            }
        }

        [$words, $wordStates] = $this->words($plan, $day, $cards, $states, $sceneTones, $termsById, $audio);
        [$phrases, $phraseStates] = $this->phrases($cards, $states, $termsById, $audio);
        [$dialogue, $lineStates] = $this->dialogue($plan, $cards, $states, $audio);

        $ownScene = $plan->sceneOf($day);

        return new DayWindowView(
            day: new WindowDayView(
                index: $day->number(),
                type: $day->type()->value,
                scene: $scene,
                imageTone: ImageTones::first($ownScene?->image()?->tone, $plan->coverImage()?->tone),
                status: $status->value,
                minutesEstimate: DayWindowStages::minutesEstimate($cards, $status),
                minutesSpent: $status === WindowStatus::Passed ? $day->metrics()->minutesSpent : null,
                goals: array_map(
                    static fn (string $goal): WindowGoalView => new WindowGoalView($goal, $status === WindowStatus::Passed),
                    $ownScene?->goalsNative() ?? [],
                ),
            ),
            stages: array_map(static fn (WindowStage $s): WindowStageView => new WindowStageView(
                $s->stage->value, $s->state->value, $s->doneCount, $s->total, $s->minutesLeft, $s->share,
            ), $stages),
            dayProgress: DayWindowStages::progress($stages),
            program: new WindowProgramView(
                $words, self::summary($wordStates),
                $phrases, self::summary($phraseStates),
                $dialogue, self::summary($lineStates),
            ),
            allowedAction: $status->action(self::hasSpeak($cards))?->value,
            listening: self::listening($ownScene?->lesson()),
        );
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

    private static function frame(?Phrase $frame): ?WindowFrameView
    {
        if ($frame === null) {
            return null;
        }

        return new WindowFrameView(
            target: $frame->frameTarget,
            native: $frame->frameNative,
            pronunciation: $frame->pronunciationNative,
            kind: $frame->kind->value,
            slot: $frame->slot === null ? null : [
                'hint' => $frame->slot->hintNative,
                'fillers' => array_map(static fn (Filler $f): array => [
                    'target' => $f->target,
                    'native' => $f->native,
                    'pronunciation' => $f->pronunciationNative,
                    'in_dialogue' => $f->inDialogue,
                ], $frame->slot->fillers),
            ],
        );
    }

    /**
     * @param  list<DayCard>  $cards
     * @param  array<string, UnitState>  $states
     * @param  array<string, string|null>  $sceneTones
     * @param  array<string, PlanTerm>  $termsById
     * @return array{0: list<WindowWordView>, 1: list<UnitState>}
     */
    private function words(Plan $plan, PlanDay $day, array $cards, array $states, array $sceneTones, array $termsById, SceneAudioIndex $audio): array
    {
        $returnsDay = self::returnsDay($plan, $day);
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
            $payload = $card->payload();
            $termId = $payload['plan_term_id'] ?? null;
            $term = is_string($termId) ? ($termsById[$termId] ?? null) : null;
            $photo = $term?->image();
            $state = $states[$key] ?? UnitState::Pending;
            $text = self::text($payload, 'text_target');
            $out[$key] = new WindowWordView(
                ref: $card->unitRef(),
                term: $text,
                translation: self::text($payload, 'text_native'),
                image: $photo === null ? null : [...$photo->toArray(), 'tone' => $photo->tone],
                imageTone: ImageTones::first($term?->imageTone(), $sceneTones[$sceneId] ?? null, $plan->coverImage()?->tone),
                state: $state->value,
                pronunciation: $term?->pronunciationNative(),
                definition: $term?->definitionTarget(),
                audioId: $audio->idOf($sceneId, $card->unitRef()),
                usage: $this->usage($plan, $sceneId, $card->unitRef(), $text, $audio),
                returnsDay: $state === UnitState::ReturnsTomorrow ? $returnsDay : null,
                usedIn: $term?->usedIn() ?? [],
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
     * The day a unit failed twice today comes back on: the next day, when it is a scene or a review
     * day (they deal the returns of the scene days before them); none after the last scene day.
     */
    private static function returnsDay(Plan $plan, PlanDay $day): ?int
    {
        foreach ($plan->days() as $next) {
            if ($next->number() === $day->number() + 1) {
                return $next->type() === DayType::Rehearsal ? null : $next->number();
            }
        }

        return null;
    }

    /**
     * @param  list<DayCard>  $cards
     * @param  array<string, UnitState>  $states
     * @param  array<string, PlanTerm>  $termsById
     * @return array{0: list<WindowPhraseView>, 1: list<UnitState>}
     */
    private function phrases(array $cards, array $states, array $termsById, SceneAudioIndex $audio): array
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
            $payload = $card->payload();
            $termId = $payload['plan_term_id'] ?? null;
            $term = is_string($termId) ? ($termsById[$termId] ?? null) : null;
            $state = $states[$key] ?? UnitState::Pending;
            $out[$key] = new WindowPhraseView(
                ref: $card->unitRef(),
                text: self::text($payload, 'text_target'),
                translation: self::text($payload, 'text_native'),
                state: $state->value,
                pronunciation: $term?->pronunciationNative() ?? self::nullableText($payload, 'pronunciation_native'),
                audioId: $audio->idOf($sceneId, $card->unitRef()),
                frame: self::frame($term?->frame()),
            );
            $unitStates[] = $state;
        }

        return [array_values($out), $unitStates];
    }

    /**
     * The dialogue in its own order: a scene day's dialogue read carries every exchange, the lines
     * of a day without one (a review, the rehearsal) come from its exchange cards, and an exchange
     * returned from yesterday is appended after today's. The learner's line takes its exchange's
     * state; an exchange with no practice card of its own is walked when the dialogue was read. Both
     * lines carry their voice — each in its speaker's — and, from the scene's served lesson, the
     * exchange's kind and the frame and filler of the learner's line (GEN-2a, additive).
     *
     * @param  list<DayCard>  $cards
     * @param  array<string, UnitState>  $states
     * @return array{0: list<WindowPairView>, 1: list<UnitState>}
     */
    private function dialogue(Plan $plan, array $cards, array $states, SceneAudioIndex $audio): array
    {
        /** @var array<string, array{scene: string, step: int, partner: array{0: string, 1: string}|null, learner: array{0: string, 1: string}|null}> $pairs */
        $pairs = [];
        $read = [];
        foreach ($cards as $card) {
            if ($card->kind() !== CardKind::DialogueRead) {
                continue;
            }
            $sceneId = UnitStates::sceneOf($card);
            $read[$sceneId] = $card->isAnswered();
            $exchanges = $card->payload()['exchanges'] ?? [];
            foreach (is_array($exchanges) ? $exchanges : [] as $exchange) {
                if (! is_array($exchange) || ! is_int($exchange['step'] ?? null)) {
                    continue;
                }
                $partner = null;
                $learner = null;
                foreach (is_array($exchange['messages'] ?? null) ? $exchange['messages'] : [] as $message) {
                    if (! is_array($message)) {
                        continue;
                    }
                    if (($message['speaker'] ?? null) === Message::SPEAKER_LEARNER) {
                        $learner ??= self::line($message, 'text_target', 'text_native');
                    } else {
                        $partner ??= self::line($message, 'text_target', 'text_native');
                    }
                }
                self::addPair($pairs, $sceneId, $exchange['step'], $partner, $learner);
            }
        }
        foreach ($cards as $card) {
            $step = CardPayloads::stepOfRef($card->unitRef());
            if ($card->unitKind() !== UnitKind::Exchange || $card->kind() === CardKind::DialogueRead || $step === null) {
                continue;
            }
            $payload = $card->payload();
            $partner = is_array($payload['partner'] ?? null) ? self::line($payload['partner'], 'text_target', 'text_native') : null;
            self::addPair($pairs, UnitStates::sceneOf($card), $step, $partner, self::learnerLineOf($card));
        }

        $out = [];
        $unitStates = [];
        foreach ($pairs as $pair) {
            $ref = CardPayloads::exchangeRef($pair['step']);
            $exchange = self::exchangeOf($plan, $pair['scene'], $pair['step']);
            $said = $exchange?->learner();
            $learner = null;
            if ($pair['learner'] !== null) {
                $state = $states[UnitStates::key($pair['scene'], UnitKind::Exchange, $ref)]
                    ?? (($read[$pair['scene']] ?? false) ? UnitState::Done : UnitState::Pending);
                $learner = new WindowLineView(
                    $pair['learner'][0], $pair['learner'][1],
                    $audio->idOf($pair['scene'], SpokenLines::learnerRef($pair['step'])), $state->value,
                    $said?->phraseId, $said?->filler,
                );
                $unitStates[] = $state;
            }
            $out[] = new WindowPairView(
                $pair['step'],
                $pair['partner'] === null ? null : new WindowLineView(
                    $pair['partner'][0], $pair['partner'][1], $audio->idOf($pair['scene'], SpokenLines::partnerRef($pair['step'])), null,
                ),
                $learner,
                $exchange?->kind->value,
            );
        }

        return [$out, $unitStates];
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

    /**
     * @param  array<string, array{scene: string, step: int, partner: array{0: string, 1: string}|null, learner: array{0: string, 1: string}|null}>  $pairs
     * @param  array{0: string, 1: string}|null  $partner
     * @param  array{0: string, 1: string}|null  $learner
     */
    private static function addPair(array &$pairs, string $sceneId, int $step, ?array $partner, ?array $learner): void
    {
        $key = $sceneId.':'.$step;
        $pairs[$key] ??= ['scene' => $sceneId, 'step' => $step, 'partner' => null, 'learner' => null];
        $pairs[$key]['partner'] ??= $partner;
        $pairs[$key]['learner'] ??= $learner;
    }

    /**
     * The learner's line as an exchange card carries it: what is said aloud, what is assembled, the
     * right option of a choice. The comprehension cards are about the partner's line and carry none.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function learnerLineOf(DayCard $card): ?array
    {
        $payload = $card->payload();

        return match ($card->kind()) {
            CardKind::Speak => self::line($payload, 'expected', 'task_native'),
            CardKind::AnswerAssemble => self::line($payload, 'answer', 'prompt_native'),
            CardKind::AnswerChoose => self::chosenLine($payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string}|null
     */
    private static function chosenLine(array $payload): ?array
    {
        foreach (is_array($payload['options'] ?? null) ? $payload['options'] : [] as $option) {
            if (is_array($option) && ($option['correct'] ?? false) === true) {
                return self::line($option, 'text_target', 'text_native');
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $source
     * @return array{0: string, 1: string}|null
     */
    private static function line(array $source, string $text, string $translation): ?array
    {
        $value = is_string($source[$text] ?? null) ? trim($source[$text]) : '';

        return $value === '' ? null : [$value, is_string($source[$translation] ?? null) ? $source[$translation] : ''];
    }

    /** @param array<string, mixed> $payload */
    private static function text(array $payload, string $key): string
    {
        return is_string($payload[$key] ?? null) ? $payload[$key] : '';
    }

    /** @param array<string, mixed> $payload */
    private static function nullableText(array $payload, string $key): ?string
    {
        $value = is_string($payload[$key] ?? null) ? trim($payload[$key]) : '';

        return $value === '' ? null : $value;
    }

    /** @param list<UnitState> $states */
    private static function summary(array $states): WindowSummaryView
    {
        $summary = ProgramSummary::of($states);

        return new WindowSummaryView($summary->total, $summary->done, $summary->returns);
    }

    /** @param list<DayCard> $cards */
    private static function hasSpeak(array $cards): bool
    {
        foreach ($cards as $card) {
            if ($card->stage() === Stage::Speak) {
                return true;
            }
        }

        return false;
    }
}
