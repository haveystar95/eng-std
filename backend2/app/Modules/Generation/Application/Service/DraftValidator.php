<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Domain\Exception\InvalidGeneratedDraft;

/**
 * Never trust the model. Drop empty/out-of-level/duplicate items, cap the size, infer the
 * word/phrase type when the model omits it, and reject the whole draft if too little
 * survives (a truncated response means max_tokens was hit — better to fail than ship junk).
 */
final class DraftValidator
{
    private const MIN_ITEMS = 8;
    private const MAX_ITEMS = 25;
    private const CEFR_ORDER = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6];

    public function validate(GeneratedCollectionDraft $draft, GenerationBrief $brief): GeneratedCollectionDraft
    {
        [$min, $max] = $this->levelRange($brief->levels);

        $seen = [];
        $clean = [];
        foreach ($draft->items as $item) {
            $text = trim($item->text);
            $translation = trim($item->translation);
            if ($text === '' || $translation === '') {
                continue;
            }

            $cefr = $this->cefr($item->cefr);
            if ($min !== null && $max !== null && $cefr !== null && isset(self::CEFR_ORDER[$cefr])) {
                $rank = self::CEFR_ORDER[$cefr];
                if ($rank < $min || $rank > $max) {
                    continue;
                }
            }

            $key = mb_strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $clean[] = new GeneratedItem(
                text: $text,
                type: $this->type($item->type, $text),
                translation: $translation,
                example: $this->nullableText($item->example),
                cefr: $cefr,
                transcription: $this->nullableText($item->transcription),
                exampleTranslation: $this->nullableText($item->exampleTranslation),
            );
        }

        if (count($clean) < self::MIN_ITEMS) {
            throw InvalidGeneratedDraft::because('only ' . count($clean) . ' usable items after validation');
        }

        // Honour the requested count: trim any over-generation down to exactly what the user
        // asked for (bounded by the hard ceiling). Under-generation is kept as-is.
        $target = max(self::MIN_ITEMS, min(self::MAX_ITEMS, $brief->size));
        if (count($clean) > $target) {
            $clean = array_slice($clean, 0, $target);
        }

        return new GeneratedCollectionDraft(
            title: trim($draft->title) !== '' ? trim($draft->title) : 'New collection',
            description: $this->nullableText($draft->description),
            items: $clean,
            model: $draft->model,
            tokensIn: $draft->tokensIn,
            tokensOut: $draft->tokensOut,
        );
    }

    private function type(string $type, string $text): string
    {
        if ($type === 'word' || $type === 'phrase') {
            return $type;
        }

        return str_contains($text, ' ') ? 'phrase' : 'word';
    }

    private function cefr(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $upper = strtoupper(trim($value));

        return $upper !== '' ? $upper : null;
    }

    private function nullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  list<string>  $levels
     * @return array{0: int|null, 1: int|null}
     */
    private function levelRange(array $levels): array
    {
        $ranks = [];
        foreach ($levels as $level) {
            $upper = strtoupper($level);
            if (isset(self::CEFR_ORDER[$upper])) {
                $ranks[] = self::CEFR_ORDER[$upper];
            }
        }

        return $ranks === [] ? [null, null] : [min($ranks), max($ranks)];
    }
}
