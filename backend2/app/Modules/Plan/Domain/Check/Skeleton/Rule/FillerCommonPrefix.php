<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\Words;

/**
 * `filler.common_prefix` — a warning (FRAMES, «where the slot cuts»: words every filler would start with belong to the frame
 * — «Candidez pentru postul de ___» + «vânzător», never «Candidez pentru ___» + «postul de vânzător»). Every filler of one
 * slot opens with the same CONTENT word, in either language — that word is the frame's. An article or a preposition every
 * filler opens with is no finding: the prompt puts them into the fillers («an engineer», «в магазине»).
 */
final class FillerCommonPrefix implements SkeletonRule
{
    public const CODE = 'filler.common_prefix';

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
        $out = [];
        foreach ($skeleton->frames as $frame) {
            $fillers = $frame->phrase->fillers();
            if (count($fillers) < 2) {
                continue;
            }
            foreach ([
                'target' => [array_map(static fn ($f): string => $f->target, $fillers), $context->targetReading('function_words')],
                'native' => [array_map(static fn ($f): string => $f->native, $fillers), $context->nativeReading('function_words')],
            ] as $side => [$values, $words]) {
                $common = $words === null ? null : self::commonContentStart($values, $words);
                if ($common !== null) {
                    $out[] = new LessonViolation(self::CODE, $frame->id(), "every {$side} filler starts with «{$common}» — the word is the frame's");
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * The first content word every value opens with — past the function words they all open with alike — or null.
     *
     * @param  list<string>  $values
     */
    private static function commonContentStart(array $values, LanguageWords $words): ?string
    {
        $tokens = array_map(Words::tokens(...), $values);
        $longest = $tokens === [] ? 0 : max(array_map('count', $tokens));
        for ($i = 0; $i < $longest; $i++) {
            $at = array_values(array_unique(array_map(static fn (array $t): string => $t[$i] ?? '', $tokens)));
            if (count($at) !== 1 || $at[0] === '') {
                return null;
            }
            if (! $words->isFunction($at[0])) {
                // A value that is nothing but this word is the word itself, not a value opening with it.
                return array_filter($tokens, static fn (array $t): bool => count($t) <= $i + 1) === [] ? $at[0] : null;
            }
        }

        return null;
    }
}
