<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton;

use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Lesson\VocabularyItem;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * THE WORDS OF THE DAY ONLY ONE CARD SAYS (наряд GEN-4c) — the vocabulary items a frame (with any of its fillers) or a partner
 * line says and no other frame and no other line does ({@see TermForms}, as `vocab.not_found` reads them). A repair that
 * rewrites the card without one of them leaves a word of the day said nowhere — `vocab.not_found`, fatal — and is thrown
 * away: the e2e of GEN-4c answered «Does this job include ___?» with «The main duties are prep, cooking, and keeping the
 * kitchen clean.», the only line that said «duties» and «keep clean», and its repair to «Yes. Training is provided during the
 * first week.» was refused; in the recorded skeletons of GEN-4 and GEN-4b a third of the lines sent to a repair for a yes or a
 * no or a filler named were such lines. The repair of such a card is told which words to keep.
 */
final class CarriedWords
{
    /** @return list<VocabularyItem> */
    public static function of(Skeleton $skeleton, LessonCard $card, ?LanguageWords $words): array
    {
        if ($card->kind !== LessonCard::FRAME && $card->kind !== LessonCard::PARTNER_LINE) {
            return [];
        }
        $out = [];
        foreach ($skeleton->vocabulary as $item) {
            // Where the word is said: by the card itself, and by every other frame and line.
            $saidBy = [];
            foreach ($skeleton->frames as $frame) {
                $texts = [$frame->phrase->frameTarget, ...array_map(static fn ($f): string => FrameText::fill($frame->phrase->frameTarget, $f->target), $frame->phrase->fillers())];
                if (array_filter($texts, static fn (string $t): bool => TermForms::in($item->termTarget, $t, $words)) !== []) {
                    $saidBy[] = $frame->id();
                }
            }
            foreach ($skeleton->partnerLines as $line) {
                if (TermForms::in($item->termTarget, $line->textTarget, $words)) {
                    $saidBy[] = $line->id;
                }
            }
            if ($saidBy === [$card->id]) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
