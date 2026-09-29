<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

/**
 * ONE LINE OF THE PARTNER, AS THE SKELETON WRITES IT (`lesson_skeleton.v1.1`, PARTNER LINES; наряд GEN-4): what A says for one
 * item of the survival set's `must_understand` — the item's number, a question or a statement, the `must_say` numbers of the
 * frames it goes with, and its text in both languages. The dialogue puts it into an exchange character for character; the
 * lesson the learner gets carries only its text, as A's message of that exchange.
 */
final readonly class PartnerLine
{
    public const QUESTION = 'question';

    public const STATEMENT = 'statement';

    /** @param list<int> $pairsWith */
    public function __construct(
        public string $id,
        public int $mustUnderstand,
        public string $kind,
        public array $pairsWith,
        public string $textTarget,
        public string $textNative,
    ) {}

    public function isQuestion(): bool
    {
        return $this->kind === self::QUESTION;
    }

    /** The same line with its texts written anew — what a repair of the line brings back; id, item, kind and pairs stay. */
    public function withTexts(string $textTarget, string $textNative): self
    {
        return new self($this->id, $this->mustUnderstand, $this->kind, $this->pairsWith, $textTarget, $textNative);
    }

    /** @return array{id: string, must_understand: int, kind: string, pairs_with: list<int>, text_target: string, text_native: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'must_understand' => $this->mustUnderstand,
            'kind' => $this->kind,
            'pairs_with' => $this->pairsWith,
            'text_target' => $this->textTarget,
            'text_native' => $this->textNative,
        ];
    }
}
