<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * Everything the assembler needs from one scene: its SERVED lesson, the terms written from it, and the packs of the
 * plan's two languages (наряд SESSION-1a) — the target's for what the learner says and hears (articles, numbers), the
 * learner's own for what they read (listening, the native value of a number).
 *
 * A language with no pack is `LanguagePack::none()`: every key is absent, and a card whose rule needs one is simply
 * not dealt — the assembler never borrows another language's words.
 */
final readonly class SceneMaterial
{
    /** @var array<string, PlanTerm> by ref */
    private array $byRef;

    /** @param list<PlanTerm> $terms */
    public function __construct(
        public PlanSceneId $sceneId,
        public Lesson $lesson,
        public array $terms,
        public LanguagePack $target,
        public LanguagePack $native,
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
        return self::inPositionOrder(array_filter($this->terms, static fn (PlanTerm $t): bool => $t->kind() !== TermKind::Phrase));
    }

    /** @return list<PlanTerm> the phrases that carry their frame, in position order — the frames of the day */
    public function phrases(): array
    {
        return self::inPositionOrder(array_filter(
            $this->terms,
            static fn (PlanTerm $t): bool => $t->kind() === TermKind::Phrase && $t->frame() !== null,
        ));
    }

    public function exchange(int $step): ?Exchange
    {
        return $this->lesson->exchange($step);
    }

    /** The phrase term a learner line stands on (its `phrase_id`), or null — a rescue line, a frame with no term. */
    public function phraseTerm(?string $phraseId): ?PlanTerm
    {
        if ($phraseId === null) {
            return null;
        }
        $term = $this->term($phraseId);

        return $term !== null && $term->kind() === TermKind::Phrase ? $term : null;
    }

    /**
     * Which filler the phrase itself is said with — the index whose sound is the phrase's own file
     * ({@see SpokenLines::fillers()}, `voicedAs`): the frame with the filler of its first dialogue line. Null for a
     * frame without a slot, or a phrase no filler makes.
     */
    public function saidIndex(PlanTerm $phrase): ?int
    {
        foreach (SpokenLines::fillers($phrase) as $filler) {
            if ($filler['voicedAs'] === $phrase->ref()) {
                return $filler['index'];
            }
        }

        return null;
    }

    /**
     * The partner's line of every exchange that has one, in the order of the visit.
     *
     * @return list<array{step: int, message: Message}>
     */
    public function partnerLines(): array
    {
        $out = [];
        foreach ($this->lesson->exchanges as $exchange) {
            $partner = $exchange->partner();
            if ($partner !== null) {
                $out[] = ['step' => $exchange->step, 'message' => $partner];
            }
        }

        return $out;
    }

    /**
     * The seed of a shuffle or a rotation in this scene: the scene and the card's own address, so a day dealt again
     * deals the same card and two scenes do not rotate in step.
     */
    public function seed(string $suffix): string
    {
        return $this->sceneId->value.':'.$suffix;
    }

    /**
     * @param  array<array-key, PlanTerm>  $terms
     * @return list<PlanTerm>
     */
    private static function inPositionOrder(array $terms): array
    {
        $terms = array_values($terms);
        usort($terms, static fn (PlanTerm $a, PlanTerm $b): int => $a->position() <=> $b->position());

        return $terms;
    }
}
