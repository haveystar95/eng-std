<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * ONE DAY OF THE STORY SO FAR (`lesson_day.v4.6`, EARLIER_DAYS; наряд GEN-3): a content day of the plan whose lesson is
 * written, as the next day's lesson reads it — the day's number, the scene's title in the target language, the partner's
 * role with the gender the day was spoken with, the dialogue line by line (who speaks, the target text only), the frames
 * in both languages and the words.
 *
 * Read off the SERVED lesson ({@see LessonAssembly}): the lines the learner heard and said, not the model's own fields.
 */
final readonly class EarlierDay
{
    /**
     * @param  list<array{speaker: string, text: string}>  $lines
     * @param  list<array{target: string, native: string}>  $frames
     * @param  list<string>  $words
     */
    public function __construct(
        public int $number,
        public string $titleTarget,
        public string $partnerRoleTarget,
        public VoiceGender $partnerGender,
        public array $lines,
        public array $frames,
        public array $words,
    ) {}

    public static function of(int $number, string $titleTarget, string $partnerRoleTarget, VoiceGender $partnerGender, Lesson $served): self
    {
        $lines = [];
        foreach ($served->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                $lines[] = ['speaker' => $message->speaker, 'text' => $message->textTarget];
            }
        }

        return new self(
            $number,
            $titleTarget,
            $partnerRoleTarget,
            $partnerGender,
            $lines,
            array_map(static fn (Phrase $p): array => ['target' => $p->frameTarget, 'native' => $p->frameNative], $served->phrases),
            array_map(static fn (VocabularyItem $v): string => $v->termTarget, $served->vocabulary),
        );
    }
}
