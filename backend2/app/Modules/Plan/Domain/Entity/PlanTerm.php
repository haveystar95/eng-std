<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Lesson\VocabularyItem;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\WordUsage;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\TermKind;

/**
 * A word, a chunk or a phrase of a scene as ONE unit — what the window lists, the program names
 * and the collection receives when the day is closed. Written once from the SERVED lesson; its `ref`
 * (`v3`, `p1`) is how the cards point at it.
 *
 * A phrase is a frame (`lesson_day.v4.5`): the unit keeps the frame itself — both renderings, the
 * reading, the kind and the slot with its fillers — and its text is the frame said with the filler of
 * its first dialogue line, which is what the phrase cards, the voice and the collection use. A word
 * keeps where the lesson says it (`used_in`).
 */
final class PlanTerm
{
    /**
     * @param  list<string>  $simplifiedVariants
     * @param  list<string>  $usedIn
     */
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
        private ?string $missingImageTone = null,
        private readonly ?Phrase $frame = null,
        private readonly array $usedIn = [],
    ) {}

    /**
     * The units of a served lesson, words and chunks first, phrases after.
     *
     * A word's example is the line of the day it is said in ({@see WordUsage}). A phrase's text is its
     * frame with the filler of its first dialogue line — or, for a frame no line says, its first
     * in-dialogue filler, then its first filler; the translation and the reading are put together the
     * same way; the line lends the phrase its speaking key, variants and example — and its closing mark
     * to a frame written without one (доработка GEN-2b: «I'd like a ___, please» said «…, please.»).
     *
     * @param  callable(): PlanTermId  $ids
     * @return list<self>
     */
    public static function fromLesson(PlanSceneId $sceneId, Lesson $lesson, callable $ids): array
    {
        $out = [];
        $position = 0;

        foreach ($lesson->vocabulary as $item) {
            $usage = WordUsage::of($lesson, $item->id, $item->termTarget);
            $out[] = new self(
                $ids(), $sceneId, $item->kind === VocabularyItem::KIND_CHUNK ? TermKind::Chunk : TermKind::Word,
                $item->id, $position++, $item->termTarget, $item->translationNative,
                self::orNull($item->pronunciationNative), self::orNull($item->definitionTarget),
                $usage['text'] ?? null, $usage['translation'] ?? null, null, [], $item->imagePrompt, null,
                null, null, $item->usedIn,
            );
        }

        foreach ($lesson->phrases as $phrase) {
            $line = $lesson->linesOf($phrase->id)[0]['message'] ?? null;
            [$text, $native, $reading] = self::said($phrase, $line);
            $out[] = new self(
                $ids(), $sceneId, TermKind::Phrase, $phrase->id, $position++, $text, $native,
                self::orNull($reading), null, $line?->textTarget, $line?->textNative,
                $line?->speakingKey, $line === null ? [] : $line->simplifiedVariants, null, null, null, $phrase,
            );
        }

        return $out;
    }

    /**
     * The frame said with its dialogue filler: [text, translation, reading]. A text or a translation whose frame ends
     * with no mark takes the mark its line ends with.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private static function said(Phrase $phrase, ?Message $line): array
    {
        [$text, $native, $reading] = self::filled($phrase, $line);
        if ($line === null) {
            return [$text, $native, $reading];
        }

        return [FrameText::withEndMarkOf($text, $line->textTarget), FrameText::withEndMarkOf($native, $line->textNative), $reading];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private static function filled(Phrase $phrase, ?Message $line): array
    {
        if (! FrameText::hasSlot($phrase->frameTarget)) {
            return [trim($phrase->frameTarget), trim($phrase->frameNative), trim($phrase->pronunciationNative)];
        }
        $filler = $phrase->filler($line?->filler);
        if ($filler === null) {
            foreach ($phrase->fillers() as $candidate) {
                if ($candidate->inDialogue) {
                    $filler = $candidate;
                    break;
                }
            }
            $filler ??= $phrase->fillers()[0] ?? null;
        }
        if ($filler === null) {
            return $line === null
                ? [trim($phrase->frameTarget), trim($phrase->frameNative), '']
                : [$line->textTarget, $line->textNative, $line->pronunciationNative ?? ''];
        }

        return [
            FrameText::fill($phrase->frameTarget, $filler->target),
            FrameText::fill($phrase->frameNative, $filler->native),
            FrameText::fill($phrase->pronunciationNative, $filler->pronunciationNative),
        ];
    }

    /**
     * @param  list<string>  $simplifiedVariants
     * @param  string|null  $missingImageTone  the slot's tone when the whole search ladder found no photo
     * @param  Phrase|null  $frame  the frame of a phrase; null for a word or a chunk
     * @param  list<string>  $usedIn  where the lesson says a word or a chunk
     */
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
        ?string $missingImageTone = null,
        ?Phrase $frame = null,
        array $usedIn = [],
    ): self {
        return new self(
            $id, $sceneId, $kind, $ref, $position, $textTarget, $textNative, $pronunciationNative, $definitionTarget,
            $exampleTarget, $exampleNative, $speakingKey, $simplifiedVariants, $imagePrompt, $image,
            $image === null ? Image::normalTone($missingImageTone) : null, $frame, $usedIn,
        );
    }

    public function attachImage(Image $image): void
    {
        $this->image ??= $image;
        $this->missingImageTone = null;
    }

    /**
     * The search ladder ran and found nothing: the card keeps no photo and is painted with [$tone].
     * The tone is also the mark that the question was asked — the photo job does not ask it again
     * after every lesson of the plan (only `plan:images-backfill` retries it).
     */
    public function markImageMissing(string $tone): void
    {
        if ($this->image === null) {
            $this->missingImageTone = Image::normalTone($tone);
        }
    }

    /**
     * A word or a chunk with no photo that the ladder has not been asked about yet. A phrase has no
     * photo by design — the day window shows phrases as lines, not cards.
     */
    public function needsImage(): bool
    {
        return $this->kind !== TermKind::Phrase && $this->image === null && $this->missingImageTone === null;
    }

    /** The tone of the card's photo slot: the photo's own, or the one it was painted with for lack of one. */
    public function imageTone(): ?string
    {
        return $this->image->tone ?? $this->missingImageTone;
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

    /** The frame of a phrase — the pattern, both renderings, the reading, the kind and the slot; null for a word or a chunk. */
    public function frame(): ?Phrase
    {
        return $this->frame;
    }

    /** @return list<string> where the lesson says a word or a chunk: frame ids and partner lines (`p3`, `A3`) */
    public function usedIn(): array
    {
        return $this->usedIn;
    }
}
