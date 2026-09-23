<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * ЗВУК ≠ ТЕКСТ. A line must sound as it reads — as the CLIENT is given it: in the answer the phone gets (the day's cards,
 * «Вспомнить» among them, and the day), a line's sound id names a file, and that file's text must be the line's text;
 * and the file bought for a line must have been bought for the line's text as it stands now (the vendor was sent that
 * text). Compared without case and without the closing mark (наряд ADM-1, доработка: «p.m.» and «p.m..» are one line) —
 * and a gap `___` on a card (the word to find, the slot to fill) stands for whatever the sound says there. An id that
 * names no file of the plan is a sound that is not this plan's at all.
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
        foreach ($facts->cardSounds as $line) {
            if ($line->fileRef !== null && ($line->soundText === null || ($line->fragment
                ? self::holds($line->soundText, $line->cardText)
                : self::shows($line->cardText, $line->soundText)))) {
                continue;
            }
            $what = $line->kind === $line->answer ? 'Строка' : "Карточка {$line->kind}";
            $out[] = new PlanIssue(self::CODE, PlanIssue::ERROR, $line->day, $line->place === $line->answer ? 'day' : 'card', $line->place,
                $line->fileRef === null
                    ? "{$what}: звук {$line->audioId} — не файл этого плана, а строка — «{$line->cardText}»"
                    : "{$what}: звук играет «{$line->soundText}» ({$line->fileRef}), а строка — «{$line->cardText}»",
                ['answer' => $line->answer, 'path' => $line->path, 'audio_id' => $line->audioId, 'file_ref' => $line->fileRef, 'card_text' => $line->cardText, 'sound_text' => $line->soundText],
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

    /** An option's or a filler's sound is the phrase said with it: the sound holds the fragment, as whole words. */
    private static function holds(string $sound, string $fragment): bool
    {
        $fragment = self::norm($fragment);

        return $fragment !== '' && preg_match('/(^|\W)'.preg_quote($fragment, '/').'(\W|$)/u', self::norm($sound)) === 1;
    }

    /** Whitespace collapsed, case folded, the closing marks gone. */
    private static function norm(string $text): string
    {
        $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));

        return (string) preg_replace('/[\s.!?…;:,]+$/u', '', $text);
    }
}
