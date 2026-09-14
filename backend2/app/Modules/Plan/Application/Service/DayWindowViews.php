<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\DayWindowView;
use App\Modules\Plan\Application\Dto\SceneView;
use App\Modules\Plan\Application\Dto\WindowDayView;
use App\Modules\Plan\Application\Dto\WindowGoalView;
use App\Modules\Plan\Application\Dto\WindowLineView;
use App\Modules\Plan\Application\Dto\WindowPairView;
use App\Modules\Plan\Application\Dto\WindowPhraseView;
use App\Modules\Plan\Application\Dto\WindowProgramView;
use App\Modules\Plan\Application\Dto\WindowStageView;
use App\Modules\Plan\Application\Dto\WindowSummaryView;
use App\Modules\Plan\Application\Dto\WindowWordView;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Domain\Assembly\CardPayloads;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\DayWindowStages;
use App\Modules\Plan\Domain\Service\ImageTones;
use App\Modules\Plan\Domain\Service\RouteStages;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\ProgramSummary;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Domain\ValueObject\UnitState;
use App\Modules\Plan\Domain\ValueObject\WindowStage;
use App\Modules\Plan\Domain\ValueObject\WindowStatus;

/**
 * THE DAY WINDOW, READ OFF THE DAY'S CARDS (DAY-UI-2).
 *
 * The cards are the day's truth — dealt ones for an opened day, the dealer's outline for a day not
 * opened yet — so the programme lists exactly what the day deals, returned units included, and a
 * unit's state is its cards'. Two more reads for any number of cards: the terms of the scenes the
 * cards touch (a word's photo and tone) and the spoken lines of those scenes (the phrases' and the
 * partner's voice). Stages, minutes, states and summaries are the Domain's rules
 * (`DayWindowStages`, `DayPace`, `UnitStates`, `ProgramSummary`); this class only puts them side by
 * side.
 */
final readonly class DayWindowViews
{
    public function __construct(
        private PlanTermRepository $terms,
        private LineAudioStore $audios,
        private LineSpeaker $speaker,
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
        $sceneIds = array_keys($sceneIds);
        $voice = $this->speaker->voiceKeyFor($plan->targetLang()->value);
        $audios = $voice === null || $sceneIds === [] ? [] : $this->audios->forScenes($sceneIds, $voice);
        $audioOf = static fn (string $sceneId, string $ref): ?string => ($audios[$sceneId.':'.$ref] ?? null)?->id;

        $sceneTones = [];
        foreach ($plan->scenes() as $planScene) {
            $sceneTones[$planScene->id()->value] = $planScene->image()?->tone;
        }

        [$words, $wordStates] = $this->words($plan, $cards, $states, $sceneTones, $sceneIds);
        [$phrases, $phraseStates] = $this->phrases($cards, $states, $audioOf);
        [$dialogue, $lineStates] = $this->dialogue($cards, $states, $audioOf);

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
        );
    }

    /**
     * @param  list<DayCard>  $cards
     * @param  array<string, UnitState>  $states
     * @param  array<string, string|null>  $sceneTones
     * @param  list<string>  $sceneIds
     * @return array{0: list<WindowWordView>, 1: list<UnitState>}
     */
    private function words(Plan $plan, array $cards, array $states, array $sceneTones, array $sceneIds): array
    {
        $termsById = [];
        $terms = $sceneIds === [] ? [] : $this->terms->forScenes(array_map(static fn (string $id): PlanSceneId => PlanSceneId::fromString($id), $sceneIds));
        foreach ($terms as $sceneTerms) {
            foreach ($sceneTerms as $term) {
                $termsById[$term->id()->value] = $term;
            }
        }

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
            /** @var PlanTerm|null $term */
            $term = is_string($termId) ? ($termsById[$termId] ?? null) : null;
            $photo = $term?->image();
            $state = $states[$key] ?? UnitState::Pending;
            $out[$key] = new WindowWordView(
                ref: $card->unitRef(),
                term: self::text($payload, 'text_target'),
                translation: self::text($payload, 'text_native'),
                image: $photo === null ? null : [...$photo->toArray(), 'tone' => $photo->tone],
                imageTone: ImageTones::first($term?->imageTone(), $sceneTones[$sceneId] ?? null, $plan->coverImage()?->tone),
                state: $state->value,
            );
            $unitStates[] = $state;
        }

        return [array_values($out), $unitStates];
    }

    /**
     * @param  list<DayCard>  $cards
     * @param  array<string, UnitState>  $states
     * @param  callable(string, string): ?string  $audioOf
     * @return array{0: list<WindowPhraseView>, 1: list<UnitState>}
     */
    private function phrases(array $cards, array $states, callable $audioOf): array
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
            $state = $states[$key] ?? UnitState::Pending;
            $out[$key] = new WindowPhraseView(
                ref: $card->unitRef(),
                text: self::text($payload, 'text_target'),
                translation: self::text($payload, 'text_native'),
                audioId: $audioOf($sceneId, $card->unitRef()),
                state: $state->value,
            );
            $unitStates[] = $state;
        }

        return [array_values($out), $unitStates];
    }

    /**
     * The dialogue in its own order: a scene day's dialogue read carries every exchange, the lines
     * of a day without one (a review, the rehearsal) come from its exchange cards, and an exchange
     * returned from yesterday is appended after today's. The learner's line takes its exchange's
     * state; an exchange with no practice card of its own is walked when the dialogue was read.
     *
     * @param  list<DayCard>  $cards
     * @param  array<string, UnitState>  $states
     * @param  callable(string, string): ?string  $audioOf
     * @return array{0: list<WindowPairView>, 1: list<UnitState>}
     */
    private function dialogue(array $cards, array $states, callable $audioOf): array
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
            $learner = null;
            if ($pair['learner'] !== null) {
                $state = $states[UnitStates::key($pair['scene'], UnitKind::Exchange, $ref)]
                    ?? (($read[$pair['scene']] ?? false) ? UnitState::Done : UnitState::Pending);
                $learner = new WindowLineView($pair['learner'][0], $pair['learner'][1], null, $state->value);
                $unitStates[] = $state;
            }
            $out[] = new WindowPairView(
                $pair['step'],
                $pair['partner'] === null ? null : new WindowLineView($pair['partner'][0], $pair['partner'][1], $audioOf($pair['scene'], $ref), null),
                $learner,
            );
        }

        return [$out, $unitStates];
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
