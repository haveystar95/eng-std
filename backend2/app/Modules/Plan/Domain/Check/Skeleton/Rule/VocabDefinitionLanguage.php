<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\Language\TextLanguage;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `vocab.definition_language` — a warning (VOCABULARY: «definition_target — short, in TARGET_LANGUAGE»; a rule beside the
 * order's list, наряд GEN-4: the repair prompt `lesson_card_repair.v1.5` names the code — it keeps the word and writes the
 * definition anew). A word's definition is not in the target language: fewer than half of its letters are the target's, or
 * in the target's own letters it holds more of the words only a neighbour language uses often than of the words only the
 * target does ({@see TextLanguage}, the reading of наряд LANG-1b §4). A definition of no frequent word at all says nothing
 * and is let be.
 */
final class VocabDefinitionLanguage implements SkeletonRule
{
    public const CODE = 'vocab.definition_language';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return false;
    }

    public function findings(Skeleton $skeleton, SkeletonContext $context): array
    {
        if (! $context->target->has('script_letters') || ! $context->target->has('common_words')) {
            return [];
        }
        $out = [];
        foreach ($skeleton->vocabulary as $item) {
            $definition = trim($item->definitionTarget);
            if ($definition === '') {
                continue;
            }
            if (TextLanguage::outOfScript($definition, $context->target) === true) {
                $out[] = new LessonViolation(self::CODE, $item->id, "the definition «{$definition}» of «{$item->termTarget}» is not written in the letters of the target language ({$context->target->code})");

                continue;
            }
            foreach (TextLanguage::tellingWords($definition, $context->target) as $row) {
                if ($row['theirs'] > $row['mine']) {
                    $out[] = new LessonViolation(self::CODE, $item->id, "the definition «{$definition}» of «{$item->termTarget}» reads as {$row['code']}, not {$context->target->code} (".implode(', ', $row['their_words']).')');
                    break;
                }
            }
        }

        return $out;
    }
}
