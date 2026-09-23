<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/**
 * A stretch of time in which something of a plan called the model: the plan's own build, a scene's lesson build, a talk.
 * `model_calls` names no plan (наряд ADM-1), so a call is read as this plan's only by falling inside one of these — and
 * `others` counts the windows of OTHER plans overlapping this one: when it is not 0 a call inside may be theirs.
 */
final readonly class BuildWindow
{
    public const PLAN = 'plan';

    public const SCENE = 'scene';

    public const TALK = 'talk';

    /** @param list<string> $purposes the journal purposes this window's calls are made under */
    public function __construct(
        public string $kind,
        public string $subjectId,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public array $purposes,
        public int $others = 0,
    ) {}

    public function holds(DateTimeImmutable $at): bool
    {
        return $at >= $this->from && $at <= $this->to;
    }

    public function withOthers(int $others): self
    {
        return new self($this->kind, $this->subjectId, $this->from, $this->to, $this->purposes, $others);
    }
}
