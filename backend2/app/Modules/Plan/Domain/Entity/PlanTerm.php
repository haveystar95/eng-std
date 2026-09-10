<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\VocabularyItem;
use App\Modules\Plan\Domain\Service\PhraseInMessage;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * A word, a chunk or a phrase of a scene as ONE unit — what the sheet lists, the program names
 * and the collection receives when the day is closed. Written once from the lesson; its `ref`
 * (`v3`, `p1`) is how the cards point at it.
 */
final class PlanTerm
{
    /** @param list<string> $simplifiedVariants */
    private function __construct(
        private readonly PlanTermId $id,
        private readonly PlanSceneId $sceneId,
        private readonly TermKind $kind,
        private readonly string $ref,
        private readonly int $position,
        private readonly string $textTarget,
        private readonly string $textNative,
        private readonly ?string $pronunciationNative,
        private readonly ?string $definitionTarget,
        private readonly ?string $exampleTarget,
        private readonly ?string $exampleNative,
        private readonly ?string $speakingKey,
        private readonly array $simplifiedVariants,
        private readonly ?string $imagePrompt,
        private ?Image $image,
    ) {}

    /**
     * The units of a lesson, words and chunks first, phrases after.
     *
     * A word's example is the dialogue message that names it; a phrase's example is the learner
     * message it stands in, which also lends the phrase its speaking key and variants.
     *
     * @param  callable(): PlanTermId  $ids
     * @return list<self>
     */
    public static function fromLesson(PlanSceneId $sceneId, Lesson $lesson, callable $ids): array
    {
        $out = [];
        $position = 0;

        foreach ($lesson->vocabulary as $item) {
            $example = self::messageNaming($lesson, $item);
            $out[] = new self(
                $ids(), $sceneId, $item->kind === VocabularyItem::KIND_CHUNK ? TermKind::Chunk : TermKind::Word,
                $item->id, $position++, $item->termTarget, $item->translationNative,
                self::orNull($item->pronunciationNative), self::orNull($item->definitionTarget),
                $example?->textTarget, $example?->textNative, null, [], $item->imagePrompt, null,
            );
        }

        foreach ($lesson->phrases as $phrase) {
            $spoken = self::messageSpeaking($lesson, $phrase->textTarget);
            $out[] = new self(
                $ids(), $sceneId, TermKind::Phrase, $phrase->id, $position++, $phrase->textTarget, $phrase->textNative,
                self::orNull($phrase->pronunciationNative), null, $spoken?->textTarget, $spoken?->textNative,
                $spoken?->speakingKey, $spoken->simplifiedVariants ?? [], null, null,
            );
        }

        return $out;
    }

    /** @param list<string> $simplifiedVariants */
    public static function reconstitute(
        PlanTermId $id,
        PlanSceneId $sceneId,
        TermKind $kind,
        string $ref,
        int $position,
        string $textTarget,
        string $textNative,
        ?string $pronunciationNative,
        ?string $definitionTarget,
        ?string $exampleTarget,
        ?string $exampleNative,
        ?string $speakingKey,
        array $simplifiedVariants,
        ?string $imagePrompt,
        ?Image $image,
    ): self {
        return new self(
            $id, $sceneId, $kind, $ref, $position, $textTarget, $textNative, $pronunciationNative, $definitionTarget,
            $exampleTarget, $exampleNative, $speakingKey, $simplifiedVariants, $imagePrompt, $image,
        );
    }

    public function attachImage(Image $image): void
    {
        $this->image ??= $image;
    }

    public function needsImage(): bool
    {
        return $this->image === null && $this->imagePrompt !== null && trim($this->imagePrompt) !== '';
    }

    private static function messageNaming(Lesson $lesson, VocabularyItem $item): ?Message
    {
        foreach ($lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                if (in_array($item->id, $message->vocabularyIds, true) && Words::containsTerm($item->termTarget, $message->textTarget)) {
                    return $message;
                }
            }
        }
        foreach ($lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                if (Words::containsTerm($item->termTarget, $message->textTarget)) {
                    return $message;
                }
            }
        }

        return null;
    }

    private static function messageSpeaking(Lesson $lesson, string $phrase): ?Message
    {
        foreach ($lesson->exchanges as $exchange) {
            $learner = $exchange->learner();
            if ($learner !== null && PhraseInMessage::matches($phrase, $learner->textTarget)) {
                return $learner;
            }
        }

        return null;
    }

    private static function orNull(string $value): ?string
    {
        return trim($value) === '' ? null : $value;
    }

    public function id(): PlanTermId
    {
        return $this->id;
    }

    public function sceneId(): PlanSceneId
    {
        return $this->sceneId;
    }

    public function kind(): TermKind
    {
        return $this->kind;
    }

    public function ref(): string
    {
        return $this->ref;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function textTarget(): string
    {
        return $this->textTarget;
    }

    public function textNative(): string
    {
        return $this->textNative;
    }

    public function pronunciationNative(): ?string
    {
        return $this->pronunciationNative;
    }

    public function definitionTarget(): ?string
    {
        return $this->definitionTarget;
    }

    public function exampleTarget(): ?string
    {
        return $this->exampleTarget;
    }

    public function exampleNative(): ?string
    {
        return $this->exampleNative;
    }

    public function speakingKey(): ?string
    {
        return $this->speakingKey;
    }

    /** @return list<string> */
    public function simplifiedVariants(): array
    {
        return $this->simplifiedVariants;
    }

    public function imagePrompt(): ?string
    {
        return $this->imagePrompt;
    }

    public function image(): ?Image
    {
        return $this->image;
    }
}
