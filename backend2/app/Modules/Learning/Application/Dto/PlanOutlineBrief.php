<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * Everything P1 is told, in primitives.
 *
 * Neither `days` nor `minutes_per_day` is here any more, and their absence is the point of v0.2.
 * P1 used to be handed the day count and asked to fill it, which made the server's later
 * arithmetic a measurement of its own input — «иду к врачу через 30 дней» came back as
 * twenty-nine days of teaching and no gate could see it. The model is now asked only what it is
 * good at, and every number that touches the calendar is computed from the answer by
 * {@see \App\Modules\Learning\Domain\Service\PlanScheduler}.
 */
final readonly class PlanOutlineBrief
{
    public function __construct(
        // The plan and its owner: P1 is a PAID call and its ledger row has to say whose it was and
        // which plan it belongs to. A brief that carried only the content would make the writer
        // re-derive both, which is how a row ends up attached to the wrong plan.
        public string $planId,
        public string $userId,
        public string $goalText,
        public string $supportLang,
        public string $targetLang,
        public string $level,
        /**
         * The listening step's answers, or NULL — «шаг пропущен», which P1 handles by its own text.
         *
         * The whole record and not just its verdict: `{{diagnostics}}` is prose the model reads to
         * shape the scenes, and «не понял две реплики из трёх» tells it far less than the two lines
         * themselves. In primitives, like everything else here — the value object lives in
         * Learning's Domain and Generation has no business importing it; what crosses the boundary
         * is the shape {@see \App\Modules\Learning\Domain\ValueObject\ListeningDiagnostics::toArray()}
         * writes, plus the verdict it derives, so the two cannot disagree on the way over.
         *
         * @var array{lines: list<array{text: string, translation: string, place: string, understood: bool}>}|null
         */
        public ?array $diagnostics = null,
        /** `understanding` | `speaking` | `''` — the verdict of the step above, empty when skipped. */
        public string $balance = '',
    ) {}
}
