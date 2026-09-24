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
        private ServedSounds $served,
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
            new DayCostOverCanon($this->canon->dayUsd, $this->canon->repairShare, $this->canon->warnRatio, $this->canon->errorRatio),
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
        $served = $this->served->of($data);
        $facts = $this->facts($data, $calls, $served);
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
            // Grey marks, not findings: what a check was not able to look at on this plan.
            'notes' => array_values(array_map(fn (TalkFact $t): array => [
                'check' => TalkWithoutOpeners::CODE,
                'day' => $t->day,
                'place_kind' => 'talk',
                'place' => $t->id,
                'message' => 'Разговор дня '.$t->day.' начат до '.$this->canon->openersSince->format('d.m').' — открытия конструкций не проверялись',
            ], array_filter($facts->talks, static fn (TalkFact $t): bool => ($number === null || $t->day === $number) && $t->roleLines > 0 && ! $t->openersRecorded))),
            'not_checked' => [
                ...($served['unavailable'] === null ? [] : ['звук ≠ текст по ответу клиенту: '.$served['unavailable']]),
                'причина «телефонного голоса» (402 вендора, предохранитель, кап) не хранится — видно только, что файла нет',
                'вызовы модели — по окнам сборки; вызов в окне, которое пересекается с чужими сборками, может быть не этого плана',
                'доля P2R — только у дней, чьи вызовы в журнале однозначно этого плана',
                'звук ≠ текст по тексту вендора — только у файлов, чей вызов в логе найден однозначно (голос, размер, минута)',
            ],
        ];
    }

    /**
     * @param  list<AttributedCall>  $calls
     * @param  array{sounds: list<ServedSound>, unavailable: string|null}  $served
     */
    private function facts(PlanInspectionData $data, array $calls, array $served): PlanFacts
    {
        $days = [];
        $lines = [];
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
            }
        }

        $cardSounds = array_map(static fn (ServedSound $s): CardSoundFact => new CardSoundFact(
            $s->day, $s->answer, $s->place, $s->kind, $s->path, $s->audioId, $s->fileRef, $s->text, $s->fileText, $s->fragment,
        ), $served['sounds']);

        $talks = array_map(fn ($talk): TalkFact => new TalkFact(
            id: $talk->id,
            day: $talk->dayNumber,
            type: $talk->type,
            ended: $talk->state === 'ended',
            endedReason: $talk->endedReason,
            roleLines: count(array_filter($talk->turns, static fn ($t): bool => $t->kind === 'agent')),
            openers: count(array_filter($talk->turns, static fn ($t): bool => $t->kind === 'agent' && $t->opensTarget !== null)),
            voicedLines: $this->talks->voicedRoleLines($data, $talk),
            openersRecorded: $talk->startedAt >= $this->canon->openersSince,
            endedByLimit: $talk->endedByLimit(),
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

}
