<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * ЗВУК ≠ ТЕКСТ. A line must sound as it reads: the sound a card's line plays is the file of the ref its stub names, so the
 * lesson's text at that ref must be the text the card shows; and the file bought for a line must have been bought for the
 * line's text as it stands now (the vendor was sent that text). Either differs — the learner hears one thing and reads
 * another. Whitespace aside, the texts must be equal — except where the card hides a part of the line on purpose: a gap
 * `___` on the card (the word to find, the slot to fill) stands for whatever the sound says there.
 */
final class SoundTextMismatch implements PlanCheck
{
    public const CODE = 'sound_text_mismatch';

    /** The gap a card shows in place of what the learner is to find or say. */
    private const GAP = '___';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->cardSounds as $card) {
            if ($card->lessonText !== null && self::shows($card->cardText, $card->lessonText)) {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::ERROR, $card->day, 'card', $card->cardId,
                $card->lessonText === null
                    ? "Карточка {$card->kind}: звук ссылается на {$card->audioRef}, а такой строки в уроке нет"
                    : "Карточка {$card->kind}: звук {$card->audioRef} — «{$card->lessonText}», на карточке — «{$card->cardText}»",
                ['path' => $card->path, 'audio_ref' => $card->audioRef, 'card_text' => $card->cardText, 'sound_text' => $card->lessonText],
            );
        }
        foreach ($facts->lines as $line) {
            if ($line->voicedText === null || self::same($line->voicedText, $line->text)) {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::ERROR, $line->firstDay(), 'line', $line->ref,
                "Строка {$line->ref}: озвучено «{$line->voicedText}», в уроке — «{$line->text}»",
                ['scene_id' => $line->sceneId, 'voiced_text' => $line->voicedText, 'text' => $line->text],
            );
        }

        return $out;
    }

    private static function same(string $a, string $b): bool
    {
        return self::norm($a) === self::norm($b);
    }

    /** The card's text is the sound's, a gap `___` on the card standing for any stretch of it. */
    private static function shows(string $card, string $sound): bool
    {
        $card = self::norm($card);
        if (! str_contains($card, self::GAP)) {
            return $card === self::norm($sound);
        }
        $pattern = implode('.+?', array_map(static fn (string $part): string => preg_quote($part, '/'), explode(self::GAP, $card)));

        return preg_match('/^'.$pattern.'$/u', self::norm($sound)) === 1;
    }

    private static function norm(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
