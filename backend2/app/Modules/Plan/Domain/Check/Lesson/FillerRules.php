<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE FILLERS (`lesson_day.v4.5`, FILLERS, «where the slot cuts»): two or three per slot; every one of them makes a
 * sentence when put into the frame; `in_dialogue` marks exactly the fillers the dialogue says.
 *
 * «Grammatical» is checked by the assembly only mechanically (`filler.ungrammatical`, fatal) — a filler with its
 * own full stop or its own slot, a word doubled at the seam, and, by the target's pack, an article after an
 * article, «a» before a vowel or «an» before a consonant at the seam, a whole sentence where the frame already has
 * its verb («My biggest strength is ___» + «I am patient»). Three warnings of v4.5 beside it: a filler that is a
 * clause, not a value («if the fever returns»); an article that stays in the frame though it changes with the
 * filler («I work as an ___»), or an article of the filler that does not fit its noun («a engineer»). Whether the
 * native sentence reads is the seam judge's, not a code's. Everything else is read by a human: the day's export
 * prints every frame with every filler put in.
 */
final class FillerRules implements LessonRule
{
    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $mechanics = $context->reads(LessonCodes::FILLER_UNGRAMMATICAL, LanguageSide::Target, 'articles', 'article_sound', 'clause', 'seam_repeatable_words');
        $clauses = $context->reads(LessonCodes::FILLER_IS_CLAUSE, LanguageSide::Target, 'clause');
        $articles = $context->reads(LessonCodes::FILLER_ARTICLE_SEAM, LanguageSide::Target, 'article_sound');
        $words = $context->targetWords();

        $out = [];
        foreach ($answer->phrases as $phrase) {
            $marked = FrameText::hasSlot($phrase->frameTarget);
            if ($marked && $phrase->slot === null) {
                $out[] = new LessonViolation(LessonCodes::FILLER_COUNT, $phrase->id, "«{$phrase->frameTarget}» has a slot but no fillers");
            } elseif (! $marked && $phrase->slot !== null) {
                $out[] = new LessonViolation(LessonCodes::FILLER_COUNT, $phrase->id, "«{$phrase->frameTarget}» has fillers but no ___");
            } elseif ($phrase->slot !== null) {
                $count = count($phrase->slot->fillers);
                if ($count < 2 || $count > 3) {
                    $out[] = new LessonViolation(LessonCodes::FILLER_COUNT, $phrase->id, "{$count} fillers (2–3)");
                }
            }

            if ($marked) {
                $before = Words::tokens((preg_split(FrameText::SLOT_PATTERN, $phrase->frameTarget, 2) ?: [''])[0]);
                $left = $before === [] ? null : $before[count($before) - 1];
                if ($articles && $left !== null && $words->isSoundArticle($left)) {
                    $out[] = new LessonViolation(LessonCodes::FILLER_ARTICLE_SEAM, $phrase->id, "«{$phrase->frameTarget}»: «{$left}» stands before the slot — the article changes with the filler and goes with it");
                }

                foreach ($phrase->fillers() as $index => $filler) {
                    $address = $phrase->id.'.f'.($index + 1);
                    foreach (self::seams($phrase->frameTarget, $filler->target, $mechanics ? $words : null) as $problem) {
                        $out[] = new LessonViolation(
                            LessonCodes::FILLER_UNGRAMMATICAL,
                            $address,
                            '«'.FrameText::fill($phrase->frameTarget, $filler->target)."»: {$problem}",
                        );
                    }

                    $clause = $clauses ? $words->clause($filler->target) : null;
                    // A whole sentence after the frame's own words is the fatal code's already — one breach, one finding.
                    if ($clause !== null && ! ($clause === LanguageWords::SENTENCE && $before !== [])) {
                        $out[] = new LessonViolation(LessonCodes::FILLER_IS_CLAUSE, $address, "«{$filler->target}» is a clause, not a value — the frame should carry the clause and the slot the value");
                    }

                    $own = Words::surface($filler->target);
                    if ($articles && count($own) >= 2 && $words->isSoundArticle($own[0]) && ($problem = $words->articleMismatch($own[0], $own[1])) !== null) {
                        $out[] = new LessonViolation(LessonCodes::FILLER_ARTICLE_SEAM, $address, "«{$filler->target}»: {$problem}");
                    }
                }
            }

            $out = [...$out, ...self::marks($answer, $phrase)];
        }

        return $out;
    }

    /**
     * What the assembly of one filler into its frame shows on its face. Without the target's words (no pack) only
     * what needs no language: punctuation, a slot, a doubled word.
     *
     * @return list<string>
     */
    public static function seams(string $frame, string $filler, ?LanguageWords $words): array
    {
        $problems = [];
        $filler = trim($filler);
        if (preg_match('/[.?!,;:]$/u', $filler) === 1) {
            $problems[] = 'the filler carries its own punctuation';
        }
        if (FrameText::hasSlot($filler)) {
            $problems[] = 'the filler holds a slot of its own';
        }

        $parts = preg_split(FrameText::SLOT_PATTERN, $frame, 2);
        $before = Words::tokens($parts[0] ?? '');
        $after = Words::tokens($parts[1] ?? '');
        $own = Words::tokens($filler);
        if ($own === []) {
            return [...$problems, 'the filler is empty'];
        }
        $left = $before === [] ? null : $before[count($before) - 1];
        $right = $after[0] ?? null;

        // A particle before a preposition of the same spelling is English («move in in June»): the target's pack names
        // the words a seam may repeat; without the pack every doubled word counts.
        $doubled = ($left !== null && $left === $own[0] && ! $words?->isSeamRepeatable($left))
            || ($right !== null && $right === $own[count($own) - 1] && ! $words?->isSeamRepeatable($right));
        if ($doubled) {
            $problems[] = 'a word is doubled at the seam';
        }
        if ($words === null) {
            return $problems;
        }
        if ($left !== null && $words->isArticle($left) && $words->isArticle($own[0])) {
            $problems[] = "«{$left} {$own[0]}» — an article after an article";
        }
        if ($left !== null && ($mismatch = $words->articleMismatch($left, Words::surface($filler)[0] ?? $own[0])) !== null) {
            $problems[] = $mismatch;
        }
        if ($before !== [] && $words->clause($filler) === LanguageWords::SENTENCE) {
            $problems[] = 'the filler is a whole clause, and the frame already has its verb';
        }

        return $problems;
    }

    /** @return list<LessonViolation> */
    private static function marks(Lesson $answer, Phrase $phrase): array
    {
        if ($phrase->slot === null) {
            return [];
        }
        $out = [];
        // Which fillers the dialogue says, and where first. The same filler said twice is `exchange.repeats`.
        $said = [];
        foreach ($answer->linesOf($phrase->id) as $use) {
            $message = $use['message'];
            $step = $use['exchange']->step;
            $filler = $phrase->filler($message->filler);
            if ($filler === null) {
                $out[] = new LessonViolation(
                    LessonCodes::FILLER_ONE_IN_DIALOGUE,
                    LessonViolation::learner($step),
                    '«'.($message->filler ?? 'null')."» is not one of {$phrase->id}'s fillers",
                );

                continue;
            }
            foreach ($phrase->slot->fillers as $position => $candidate) {
                if ($candidate === $filler) {
                    $said[$position] ??= $step;
                    break;
                }
            }
        }

        foreach ($phrase->slot->fillers as $index => $filler) {
            $address = $phrase->id.'.f'.($index + 1);
            if ($filler->inDialogue && ! isset($said[$index])) {
                $out[] = new LessonViolation(LessonCodes::FILLER_ONE_IN_DIALOGUE, $address, "«{$filler->target}» is marked in_dialogue, but no line says it");
            } elseif (! $filler->inDialogue && isset($said[$index])) {
                $out[] = new LessonViolation(LessonCodes::FILLER_ONE_IN_DIALOGUE, $address, "«{$filler->target}» is said in exchange {$said[$index]}, but not marked in_dialogue");
            }
        }

        return $out;
    }
}
