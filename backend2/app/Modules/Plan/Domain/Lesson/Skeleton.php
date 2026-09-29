<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE SKELETON OF A DAY (`lesson_skeleton.v1.1`, наряд GEN-4) — the first of the day's two stages: the frames the learner
 * practises (one per item of the scene's `must_say`), the lines the partner says (one per item of `must_understand`), the
 * vocabulary of both, the day's title and description, the learner's role and the partner's gender. No dialogue: the second
 * stage ({@see Dialogue}) puts these frames and lines into a conversation and may add nothing of its own.
 *
 * Immutable. Kept beside the scene's lesson (`plan_scenes.skeleton_json`): a repair of the dialogue reads it, the lesson is
 * assembled from it ({@see LessonAssembler}). Parsed by {@see LessonParser::skeleton()}.
 */
final readonly class Skeleton
{
    /**
     * @param  list<SkeletonFrame>  $frames
     * @param  list<PartnerLine>  $partnerLines
     * @param  list<VocabularyItem>  $vocabulary  `used_in` names frames (`p3`) and partner lines (`a4`)
     */
    public function __construct(
        public string $titleTarget,
        public string $titleNative,
        public string $descriptionTarget,
        public string $descriptionNative,
        public string $learnerRoleTarget,
        public string $learnerRoleNative,
        public ?VoiceGender $roleGender,
        public array $frames,
        public array $partnerLines,
        public array $vocabulary,
    ) {}

    public function frame(string $id): ?SkeletonFrame
    {
        foreach ($this->frames as $frame) {
            if ($frame->id() === $id) {
                return $frame;
            }
        }

        return null;
    }

    public function partnerLine(string $id): ?PartnerLine
    {
        foreach ($this->partnerLines as $line) {
            if ($line->id === $id) {
                return $line;
            }
        }

        return null;
    }

    public function vocabularyItem(string $id): ?VocabularyItem
    {
        foreach ($this->vocabulary as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    /** @return list<Phrase> the frames as the lesson knows them */
    public function phrases(): array
    {
        return array_map(static fn (SkeletonFrame $f): Phrase => $f->phrase, $this->frames);
    }

    /** The frame that serves the `must_say` item `$number`, or null. */
    public function frameOfItem(int $number): ?SkeletonFrame
    {
        foreach ($this->frames as $frame) {
            if (in_array($number, $frame->mustSay, true)) {
                return $frame;
            }
        }

        return null;
    }

    /**
     * Does some partner line name this frame in `pairs_with` — by any of its `must_say` numbers?
     */
    public function isPaired(SkeletonFrame $frame): bool
    {
        foreach ($this->partnerLines as $line) {
            if (array_intersect($line->pairsWith, $frame->mustSay) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * THE PARTNER'S REPLIES TO THE LEARNER'S QUESTIONS (наряд GEN-4c): every statement of the partner with an `ask` frame it
     * pairs with — the learner asks with the frame, A answers with the line. Each pair once, in the order of the lines.
     *
     * @return list<array{0: PartnerLine, 1: SkeletonFrame}>
     */
    public function repliesToAsks(): array
    {
        $out = [];
        foreach ($this->partnerLines as $line) {
            if ($line->isQuestion()) {
                continue;
            }
            $seen = [];
            foreach ($line->pairsWith as $number) {
                $frame = $this->frameOfItem($number);
                if ($frame !== null && $frame->phrase->kind === ExchangeKind::Ask && ! isset($seen[$frame->id()])) {
                    $seen[$frame->id()] = true;
                    $out[] = [$line, $frame];
                }
            }
        }

        return $out;
    }

    /**
     * DIALOGUE_COUNT (наряд GEN-4, 3.5): an exchange for every partner line, one for every frame no partner line pairs with
     * (its A line the dialogue writes itself), and one for the rescue.
     */
    public function dialogueCount(): int
    {
        $unpaired = count(array_filter($this->frames, fn (SkeletonFrame $f): bool => ! $this->isPaired($f)));

        return count($this->partnerLines) + $unpaired + 1;
    }

    /** @param list<SkeletonFrame> $frames */
    public function withFrames(array $frames): self
    {
        return new self(
            $this->titleTarget, $this->titleNative, $this->descriptionTarget, $this->descriptionNative, $this->learnerRoleTarget,
            $this->learnerRoleNative, $this->roleGender, $frames, $this->partnerLines, $this->vocabulary,
        );
    }

    /** @param list<PartnerLine> $lines */
    public function withPartnerLines(array $lines): self
    {
        return new self(
            $this->titleTarget, $this->titleNative, $this->descriptionTarget, $this->descriptionNative, $this->learnerRoleTarget,
            $this->learnerRoleNative, $this->roleGender, $this->frames, $lines, $this->vocabulary,
        );
    }

    /** @param list<VocabularyItem> $vocabulary */
    public function withVocabulary(array $vocabulary): self
    {
        return new self(
            $this->titleTarget, $this->titleNative, $this->descriptionTarget, $this->descriptionNative, $this->learnerRoleTarget,
            $this->learnerRoleNative, $this->roleGender, $this->frames, $this->partnerLines, $vocabulary,
        );
    }

    /**
     * The shape of the prompt's OUTPUT SCHEMA, keys in its order — what `plan_scenes.skeleton_json` holds, and what the
     * dialogue and a repair read as SKELETON.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'topic' => [
                'title_target' => $this->titleTarget,
                'title_native' => $this->titleNative,
                'description_target' => $this->descriptionTarget,
                'description_native' => $this->descriptionNative,
            ],
            'learner_role' => [
                'role_target' => $this->learnerRoleTarget,
                'role_native' => $this->learnerRoleNative,
            ],
            'role_gender' => $this->roleGender?->value,
            'phrases' => array_map(static fn (SkeletonFrame $f): array => $f->toArray(), $this->frames),
            'partner_lines' => array_map(static fn (PartnerLine $l): array => $l->toArray(), $this->partnerLines),
            'vocabulary' => array_map(static fn (VocabularyItem $v): array => $v->toArray(), $this->vocabulary),
        ];
    }
}
