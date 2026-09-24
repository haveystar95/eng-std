<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\BuildWindow;
use App\Modules\Plan\Application\Dto\Inspection\InspectedTalk;
use App\Modules\Plan\Application\Port\PlanCallJournal;
use App\Modules\Plan\Application\Port\PlanInspectionReader;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * WHICH MODEL CALLS ARE THIS PLAN'S (наряд ADM-1). The journal names no plan, so a call is read as the plan's when it
 * started inside one of the plan's own windows under that window's purposes: the plan's build (`plan`), a scene's lesson
 * build (`lesson`, `repair`, `judge` — the seam judge), a talk (`conversation`). What this cannot know it says: every
 * window carries how many windows of OTHER plans overlap it (a call inside may be theirs), and only the LAST build of a
 * scene has a window — `build_started_at` is overwritten by a rebuild.
 */
final readonly class CallAttribution
{
    public function __construct(
        private PlanInspectionReader $reader,
        private PlanCallJournal $journal,
        private Clock $clock,
    ) {}

    /** @return list<AttributedCall> oldest first */
    public function of(PlanInspectionData $data): array
    {
        $out = [];
        $seen = [];
        foreach ($this->windows($data) as $window) {
            foreach ($this->journal->modelCalls($window->from, $window->to, $window->purposes) as $call) {
                if (isset($seen[$call->id])) {
                    $seen[$call->id]++;

                    continue;
                }
                $seen[$call->id] = 1;
                $out[] = [$call, $window];
            }
        }

        $calls = array_map(fn (array $pair): AttributedCall => new AttributedCall(
            call: $pair[0],
            window: $pair[1],
            day: $this->dayOf($data, $pair[1]),
            inOtherWindows: $seen[$pair[0]->id] - 1,
        ), $out);
        usort($calls, static fn (AttributedCall $a, AttributedCall $b): int => $a->call->startedAt <=> $b->call->startedAt ?: strcmp($a->call->id, $b->call->id));

        return $calls;
    }

    /** @return list<BuildWindow> */
    public function windows(PlanInspectionData $data): array
    {
        $windows = [];
        $row = $data->row;
        // The plan's build ends with its `plan_ready` line; a failed or unclear build at the row's last change, one still
        // building — now; otherwise its end is not known and it gets no window.
        $from = $row->buildStartedAt ?? $row->createdAt;
        $ready = $data->firstEvent(PlanEventKind::PlanReady);
        $to = $ready !== null ? $ready->occurredAt : match ($row->status) {
            'failed', 'unclear' => $row->updatedAt ?? $from,
            'building' => $this->clock->now(),
            default => null,
        };
        if ($to !== null) {
            $windows[] = new BuildWindow(BuildWindow::PLAN, $row->id, $from, max($from, $to), ['plan']);
        }

        foreach ($data->scenes as $scene) {
            if ($scene->buildStartedAt === null) {
                continue;
            }
            // The build ends when the scene went ready (`built_at`, наряд FIX-4 §6) — the lesson, its repairs, the seam judge
            // and the photos all done. NOT `generated_at`: it is stamped with the moment the build began. A failed build ends
            // with the row's last change; one still being written is open until now. A ready scene with no `built_at`
            // (built before the journal of events) has no known end — no window: its row's last change may be days later,
            // and the slot judges of its passage are journaled as `judge` too.
            $to = $scene->builtAt ?? match ($scene->lessonStatus) {
                'building', 'illustrating' => $this->clock->now(),
                'failed' => $scene->updatedAt ?? $scene->buildStartedAt,
                default => null,
            };
            if ($to === null) {
                continue;
            }
            $windows[] = new BuildWindow(BuildWindow::SCENE, $scene->id, $scene->buildStartedAt, max($scene->buildStartedAt, $to), ['lesson', 'repair', 'judge']);
        }

        foreach ($data->talks() as $talk) {
            $windows[] = new BuildWindow(BuildWindow::TALK, $talk->id, $talk->startedAt, $this->endOf($talk), ['conversation']);
        }

        return $this->reader->withOthers($data->id(), $windows);
    }

    private function endOf(InspectedTalk $talk): \DateTimeImmutable
    {
        $last = $talk->turns === [] ? $talk->startedAt : $talk->turns[count($talk->turns) - 1]->createdAt;

        return max($talk->startedAt, $talk->endedAt ?? $last, $last);
    }

    private function dayOf(PlanInspectionData $data, BuildWindow $window): ?int
    {
        return match ($window->kind) {
            BuildWindow::SCENE => $data->dayOfScene($window->subjectId),
            BuildWindow::TALK => $this->talkDay($data, $window->subjectId),
            default => null,
        };
    }

    private function talkDay(PlanInspectionData $data, string $talkId): ?int
    {
        foreach ($data->talks() as $talk) {
            if ($talk->id === $talkId) {
                return $talk->dayNumber;
            }
        }

        return null;
    }
}
