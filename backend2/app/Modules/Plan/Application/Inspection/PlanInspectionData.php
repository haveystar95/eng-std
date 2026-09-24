<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\InspectedAudio;
use App\Modules\Plan\Application\Dto\Inspection\InspectedCard;
use App\Modules\Plan\Application\Dto\Inspection\InspectedPassage;
use App\Modules\Plan\Application\Dto\Inspection\InspectedPlan;
use App\Modules\Plan\Application\Dto\Inspection\InspectedScene;
use App\Modules\Plan\Application\Dto\Inspection\InspectedTalk;
use App\Modules\Plan\Application\Port\PlanInspectionReader;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanEventRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use DateTimeImmutable;

/**
 * ONE PLAN, READ FOR ITS ADMIN PAGE (наряд ADM-1): the aggregate (its statuses, days and served lessons), the rows it does not
 * carry out, and the learner's day and gender — each further table read once, when a section first asks for it.
 */
final class PlanInspectionData
{
    /** @var list<InspectedCard>|null */
    private ?array $cards = null;

    /** @var list<InspectedAudio>|null */
    private ?array $audios = null;

    /** @var list<InspectedTalk>|null */
    private ?array $talks = null;

    /** @var list<InspectedPassage>|null */
    private ?array $passages = null;

    /** @var list<PlanEvent>|null */
    private ?array $events = null;

    /** @var array<string, list<PlanTerm>>|null */
    private ?array $terms = null;

    /** @param list<InspectedScene> $scenes */
    public function __construct(
        public readonly Plan $plan,
        public readonly InspectedPlan $row,
        public readonly array $scenes,
        public readonly DateTimeImmutable $today,
        /** The learner's gender as the profile says it; null — not said (the voice then takes the default). */
        public readonly ?VoiceGender $profileGender,
        private readonly PlanInspectionReader $reader,
        private readonly PlanTermRepository $termRepository,
        private readonly PlanEventRepository $eventRepository,
    ) {}

    public function id(): string
    {
        return $this->plan->id()->value;
    }

    public static function codeOf(string $planId): string
    {
        return substr($planId, 4, 6);
    }

    public function code(): string
    {
        return self::codeOf($this->id());
    }

    /** @return list<PlanDay> */
    public function days(): array
    {
        return $this->plan->days();
    }

    /** @return list<PlanDay> the days of the filter: one day, or all of them */
    public function daysOf(?int $number): array
    {
        return array_values(array_filter($this->days(), static fn (PlanDay $d): bool => $number === null || $d->number() === $number));
    }

    public function dayByNumber(int $number): ?PlanDay
    {
        foreach ($this->days() as $day) {
            if ($day->number() === $number) {
                return $day;
            }
        }

        return null;
    }

    public function dayNumberOf(string $dayId): ?int
    {
        foreach ($this->days() as $day) {
            if ($day->id()->value === $dayId) {
                return $day->number();
            }
        }

        return null;
    }

    /** The scene day a scene is taught on (a scene has one scene day; reviews and the rehearsal have no scene of their own). */
    public function dayOfScene(string $sceneId): ?int
    {
        foreach ($this->days() as $day) {
            if ($day->type() === DayType::Scene && $day->sceneId()?->value === $sceneId) {
                return $day->number();
            }
        }

        return null;
    }

    public function sceneRow(?string $sceneId): ?InspectedScene
    {
        foreach ($this->scenes as $scene) {
            if ($scene->id === $sceneId) {
                return $scene;
            }
        }

        return null;
    }

    public function scene(?string $sceneId): ?PlanScene
    {
        if ($sceneId === null) {
            return null;
        }
        foreach ($this->plan->scenes() as $scene) {
            if ($scene->id()->value === $sceneId) {
                return $scene;
            }
        }

        return null;
    }

    /** @return list<InspectedCard> */
    public function cards(): array
    {
        return $this->cards ??= $this->reader->cards(array_map(static fn (PlanDay $d): string => $d->id()->value, $this->days()));
    }

    /** @return list<InspectedCard> */
    public function cardsOfDay(PlanDay $day): array
    {
        return array_values(array_filter($this->cards(), static fn (InspectedCard $c): bool => $c->dayId === $day->id()->value));
    }

    /** @return list<InspectedAudio> */
    public function audios(): array
    {
        return $this->audios ??= $this->reader->audios(array_map(static fn (InspectedScene $s): string => $s->id, $this->scenes));
    }

    /** @return list<InspectedAudio> */
    public function audiosOfScene(string $sceneId): array
    {
        return array_values(array_filter($this->audios(), static fn (InspectedAudio $a): bool => $a->sceneId === $sceneId));
    }

    /** @return list<InspectedTalk> */
    public function talks(): array
    {
        return $this->talks ??= $this->reader->talks($this->id());
    }

    /** @return list<InspectedTalk> */
    public function talksOfDay(int $number): array
    {
        return array_values(array_filter($this->talks(), static fn (InspectedTalk $t): bool => $t->dayNumber === $number));
    }

    /** @return list<InspectedPassage> */
    public function passages(): array
    {
        return $this->passages ??= $this->reader->passages($this->id());
    }

    /** @return list<PlanEvent> */
    public function events(): array
    {
        return $this->events ??= $this->eventRepository->forPlan($this->plan->id());
    }

    public function firstEvent(PlanEventKind $kind, ?int $day = null): ?PlanEvent
    {
        foreach ($this->events() as $event) {
            if ($event->kind === $kind && ($day === null || $event->dayNumber === $day)) {
                return $event;
            }
        }

        return null;
    }

    /** @return list<PlanTerm> */
    public function termsOf(string $sceneId): array
    {
        $this->terms ??= $this->loadTerms();

        return $this->terms[$sceneId] ?? [];
    }

    /** @return array<string, list<PlanTerm>> */
    private function loadTerms(): array
    {
        return $this->termRepository->forScenes(array_map(static fn (InspectedScene $s): PlanSceneId => new PlanSceneId($s->id), $this->scenes));
    }
}
