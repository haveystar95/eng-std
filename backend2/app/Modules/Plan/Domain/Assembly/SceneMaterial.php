<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/** Everything the assembler needs from one scene: its lesson and the terms written from it. */
final readonly class SceneMaterial
{
    /** @var array<string, PlanTerm> by ref */
    private array $byRef;

    /** @param list<PlanTerm> $terms */
    public function __construct(
        public PlanSceneId $sceneId,
        public Lesson $lesson,
        public array $terms,
    ) {
        $byRef = [];
        foreach ($terms as $term) {
            $byRef[$term->ref()] = $term;
        }
        $this->byRef = $byRef;
    }

    public function term(string $ref): ?PlanTerm
    {
        return $this->byRef[$ref] ?? null;
    }

    /** @return list<PlanTerm> words and chunks, in position order */
    public function vocabulary(): array
    {
        return array_values(array_filter($this->terms, static fn (PlanTerm $t): bool => $t->kind() !== TermKind::Phrase));
    }

    /** @return list<PlanTerm> */
    public function phrases(): array
    {
        return array_values(array_filter($this->terms, static fn (PlanTerm $t): bool => $t->kind() === TermKind::Phrase));
    }

    public function exchange(int $step): ?Exchange
    {
        return $this->lesson->exchange($step);
    }

    /**
     * The exchanges whose two messages are both there — what the listening and speaking stages deal.
     *
     * @return list<Exchange>
     */
    public function completeExchanges(): array
    {
        return array_values(array_filter(
            $this->lesson->exchanges,
            static fn (Exchange $e): bool => $e->partner() !== null && $e->learner() !== null,
        ));
    }
}
