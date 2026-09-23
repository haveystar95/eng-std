<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Inspection\CallFact;
use App\Modules\Plan\Domain\Inspection\CardSoundFact;
use App\Modules\Plan\Domain\Inspection\Check\BuildingOutOfLine;
use App\Modules\Plan\Domain\Inspection\Check\DayCostOverCanon;
use App\Modules\Plan\Domain\Inspection\Check\DayFailed;
use App\Modules\Plan\Domain\Inspection\Check\LineWithoutSound;
use App\Modules\Plan\Domain\Inspection\Check\LostModelCall;
use App\Modules\Plan\Domain\Inspection\Check\PassedWithoutSummary;
use App\Modules\Plan\Domain\Inspection\Check\ScheduledDayNotReady;
use App\Modules\Plan\Domain\Inspection\Check\SoundTextMismatch;
use App\Modules\Plan\Domain\Inspection\Check\TalkEndedByLimit;
use App\Modules\Plan\Domain\Inspection\Check\TalkWithoutOpeners;
use App\Modules\Plan\Domain\Inspection\Check\VoiceGenderMismatch;
use App\Modules\Plan\Domain\Inspection\DayFact;
use App\Modules\Plan\Domain\Inspection\LineFact;
use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;
use App\Modules\Plan\Domain\Inspection\TalkFact;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;

/**
 * «ЧТО НЕ ТАК» (наряд ADM-1): the plan's facts gathered once, every check run over them, the findings with the place
 * each points at (a day, a line, a card, a talk's turn, a model call). An empty list — the plan is healthy.
 */
final readonly class IssueReport
{
    public const TITLES = [
        SoundTextMismatch::CODE => 'Звук ≠ текст',
        VoiceGenderMismatch::CODE => 'Голос не по полу',
        LineWithoutSound::CODE => 'Строка без звука / телефонный голос',
        DayFailed::CODE => 'День failed',
        ScheduledDayNotReady::CODE => 'День открыт по расписанию, но не ready',
        BuildingOutOfLine::CODE => 'Сборка не у следующего в очереди',
        LostModelCall::CODE => 'Вызов модели lost',
        DayCostOverCanon::CODE => 'Цена дня выше канона',
        PassedWithoutSummary::CODE => 'День пройден без итога',
        TalkWithoutOpeners::CODE => 'Роль не открыла ни одной конструкции',
        TalkEndedByLimit::CODE => 'Разговор закончился по лимиту',
    ];

    public function __construct(
        private VoiceTable $voices,
        private TalkReport $talks,
        private DayMoney $money,
        private InspectionCanon $canon,
    ) {}

    /** @return list<PlanCheck> */
    public function checks(): array
    {
        return [
            new SoundTextMismatch,
            new VoiceGenderMismatch,
            new LineWithoutSound,
            new DayFailed,
            new ScheduledDayNotReady,
            new BuildingOutOfLine,
            new LostModelCall,
            new DayCostOverCanon($this->canon->dayUsd, $this->canon->repairShare),
            new PassedWithoutSummary,
            new TalkWithoutOpeners,
            new TalkEndedByLimit,
        ];
    }

    /**
     * @param  list<AttributedCall>  $calls
     * @return array<string, mixed>
     */
    public function of(PlanInspectionData $data, ?int $number, array $calls): array
    {
        $facts = $this->facts($data, $calls);
        $issues = [];
        $counts = [];
        foreach ($this->checks() as $check) {
            $found = array_values(array_filter($check->find($facts), static fn (PlanIssue $i): bool => $number === null || $i->day === $number));
            $counts[] = ['code' => $check->code(), 'title' => self::TITLES[$check->code()] ?? $check->code(), 'count' => count($found)];
            array_push($issues, ...$found);
        }
        usort($issues, static fn (PlanIssue $a, PlanIssue $b): int => [$a->severity !== PlanIssue::ERROR, $a->day ?? 0] <=> [$b->severity !== PlanIssue::ERROR, $b->day ?? 0]);

        return [
            'data' => array_map(static fn (PlanIssue $i): array => [
                'check' => $i->check,
                'title' => self::TITLES[$i->check] ?? $i->check,
                'severity' => $i->severity,
                'day' => $i->day,
                'place_kind' => $i->placeKind,
                'place' => $i->place,
                'message' => $i->message,
                'detail' => $i->detail,
            ], $issues),
            'checks' => $counts,
            'healthy' => $issues === [],
            'not_checked' => [
                'причина «телефонного голоса» (402 вендора, предохранитель, кап) не хранится — видно только, что файла нет',
                'вызовы модели — по окнам сборки; вызов в окне, которое пересекается с чужими сборками, может быть не этого плана',
                'доля P2R — только у дней, чьи вызовы в журнале однозначно этого плана',
                'звук ≠ текст по тексту вендора — только у файлов, чей вызов в логе найден однозначно (голос, размер, минута)',
            ],
        ];
    }

    /** @param list<AttributedCall> $calls */
    private function facts(PlanInspectionData $data, array $calls): PlanFacts
    {
        $days = [];
        $lines = [];
        $texts = [];
        $next = $data->plan->currentDay()?->number();
        foreach ($data->days() as $day) {
            $scene = $day->type() === DayType::Scene ? $data->scene($day->sceneId()?->value) : null;
            $row = $data->sceneRow($day->sceneId()?->value);
            $money = $this->money->of($data, $day, $calls);
            $days[] = new DayFact(
                number: $day->number(),
                type: $day->type()->value,
                status: $data->plan->effectiveDayStatus($day, $data->today)->value,
                scheduledOpen: $this->scheduledOpen($data, $day),
                nextInLine: $day->number() === $next,
                lessonStatus: $scene === null ? null : $row?->lessonStatus,
                failReason: $scene === null ? null : $row?->failReason,
                closed: $day->status() === DayStatus::Closed,
                hasPassedEvent: $data->firstEvent(PlanEventKind::DayPassed, $day->number()) !== null,
                generationUsd: $scene === null || $row?->costUsd === null ? null : (float) $row->costUsd,
                voiceUsd: (float) $money['voice']['cost_usd'],
                repairUsd: isset($money['generation']['repair_usd']) ? (float) $money['generation']['repair_usd'] : null,
            );
            if ($scene === null) {
                continue;
            }
            foreach ($this->voices->of($data, $scene) as $line) {
                $stored = [];
                foreach ([$line->audio, ...$line->others] as $audio) {
                    if ($audio !== null) {
                        $stored[$audio->voiceKey] = $line->identities[$audio->voiceKey] ?? null;
                    }
                }
                $lines[] = new LineFact($scene->id()->value, [$day->number()], $line->ref, $line->speaker, $line->text, $line->gender, $line->expectedVoice, $stored, $line->voicedText);
                $texts[$scene->id()->value][$line->ref] = $line->text;
            }
        }

        $cardSounds = [];
        foreach ($data->cards() as $card) {
            $number = $data->dayNumberOf($card->dayId);
            if ($number === null) {
                continue;
            }
            foreach (self::sounds($card->payload, is_string($card->payload['scene_id'] ?? null) ? $card->payload['scene_id'] : null, '') as [$path, $scene, $ref, $text]) {
                $cardSounds[] = new CardSoundFact($number, $card->id, $card->kind, $path, $ref, $text, $scene === null ? null : ($texts[$scene][$ref] ?? $this->textIn($data, $scene, $ref, $texts)));
            }
        }

        $talks = array_map(fn ($talk): TalkFact => new TalkFact(
            id: $talk->id,
            day: $talk->dayNumber,
            type: $talk->type,
            ended: $talk->state === 'ended',
            endedReason: $talk->endedReason,
            roleLines: count(array_filter($talk->turns, static fn ($t): bool => $t->kind === 'agent')),
            openers: count(array_filter($talk->turns, static fn ($t): bool => $t->kind === 'agent' && $t->opensTarget !== null)),
            voicedLines: $this->talks->voicedRoleLines($data, $talk),
        ), $data->talks());

        $callFacts = array_map(static fn (AttributedCall $c): CallFact => new CallFact($c->call->id, $c->day, $c->call->purpose, $c->call->status, $c->call->error), $calls);

        return new PlanFacts($days, $lines, $cardSounds, $talks, $callFacts);
    }

    /**
     * The calendar has come for the day and the day before it is closed — the day may be opened today by the schedule.
     */
    private function scheduledOpen(PlanInspectionData $data, PlanDay $day): bool
    {
        if (! $data->plan->status()->isLive() || $day->status() === DayStatus::Closed) {
            return false;
        }
        $previous = $day->number() > 1 ? $data->dayByNumber($day->number() - 1) : null;

        return ($previous === null || $previous->status() === DayStatus::Closed) && $day->isAvailableOn($data->today);
    }

    /**
     * The lesson's text at a ref of a scene no day of the filter holds (a returned card's scene).
     *
     * @param  array<string, array<string, string>>  $texts
     */
    private function textIn(PlanInspectionData $data, string $sceneId, string $ref, array &$texts): ?string
    {
        if (! isset($texts[$sceneId])) {
            $texts[$sceneId] = [];
            $scene = $data->scene($sceneId);
            if ($scene !== null) {
                foreach ($this->voices->of($data, $scene) as $line) {
                    $texts[$sceneId][$line->ref] = $line->text;
                }
            }
        }

        return $texts[$sceneId][$ref] ?? null;
    }

    /**
     * Every line of a payload shown next to its sound: a node with `text_target` and an `audio` stub naming a ref, with the
     * scene it stands in (the nearest `scene_id` above it).
     *
     * @param  array<mixed>  $node
     * @return list<array{0: string, 1: string|null, 2: string, 3: string}>
     */
    private static function sounds(array $node, ?string $scene, string $path): array
    {
        $scene = is_string($node['scene_id'] ?? null) ? $node['scene_id'] : $scene;
        $out = [];
        $audio = $node['audio'] ?? null;
        if (is_array($audio) && is_string($audio['ref'] ?? null) && is_string($node['text_target'] ?? null)) {
            $out[] = [$path === '' ? '.' : $path, $scene, $audio['ref'], $node['text_target']];
        }
        foreach ($node as $key => $child) {
            if (is_array($child) && $key !== 'audio') {
                array_push($out, ...self::sounds($child, $scene, ltrim($path.'.'.$key, '.')));
            }
        }

        return $out;
    }
}
