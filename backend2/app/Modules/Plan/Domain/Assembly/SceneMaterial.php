<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
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
 * learner's own for what they read (listening, the native value of a number) — and the fillers whose native sentence
 * the seam judge said does not read (SESSION-1e), which no card shows.
 *
 * A language with no pack is `LanguagePack::none()`: every key is absent, and a card whose rule needs one is simply
 * not dealt — the assembler never borrows another language's words.
 */
final readonly class SceneMaterial
{
    /** @var array<string, PlanTerm> by ref */
    private array $byRef;

    /** @var array<string, true> by the filler's address (`p3.f2`) */
    private array $unreadable;

    /**
     * @param  list<PlanTerm>  $terms
     * @param  list<string>  $unreadable  the addresses (`p3.f2`) of the scene's `filler.native_seam` findings
     */
    public function __construct(
        public PlanSceneId $sceneId,
        public Lesson $lesson,
        public array $terms,
        public LanguagePack $target,
        public LanguagePack $native,
        array $unreadable = [],
    ) {
        $byRef = [];
        foreach ($terms as $term) {
            $byRef[$term->ref()] = $term;
        }
        $this->byRef = $byRef;
        $this->unreadable = array_fill_keys($unreadable, true);
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
     * Is the filler at `$index` of a frame one no card shows (SESSION-1e)? The seam judge said its native sentence does
     * not read («Это у него уже уже три дня» — a `filler.native_seam` finding at `p2.f1`), and the dialogue does not say
     * it: a filler the dialogue says — marked `in_dialogue`, or the one the phrase itself is said with — stays as it is,
     * the day is built on it. The frame is the scene's phrase term, else the lesson's own.
     */
    public function hides(string $phraseRef, int $index): bool
    {
        if (! isset($this->unreadable[SpokenLines::fillerRef($phraseRef, $index)])) {
            return false;
        }
        $term = $this->phraseTerm($phraseRef);
        $filler = ($term?->frame() ?? $this->lesson->phrase($phraseRef))?->fillers()[$index] ?? null;

        return $filler !== null && ! $filler->inDialogue && ($term === null || $this->saidIndex($term) !== $index);
    }

    /**
     * Does a line ask — its text ends with a mark the target pack's `sentence_ends` calls a question (SESSION-1d)? A
     * target without that key asks nothing: the rules that read the form of a line do not tell one form from another.
     */
    public function asks(string $text): bool
    {
        return $this->target->has('sentence_ends') && (new LanguageWords($this->target))->terminalKind($text) === 'question';
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
     * The other exchanges of the visit, the FARTHEST from `$step` first — between two as far, the lower step: where a
     * card looks for a wrong option that is surely wrong here because it belongs elsewhere (`dialogue_partner`'s fourth,
     * `phrase_combine`'s wrong frames).
     *
     * @return list<Exchange>
     */
    public function farthestFrom(int $step): array
    {
        $others = array_values(array_filter($this->lesson->exchanges, static fn (Exchange $other): bool => $other->step !== $step));
        usort($others, static fn (Exchange $a, Exchange $b): int => [abs($b->step - $step), $a->step] <=> [abs($a->step - $step), $b->step]);

        return $others;
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
