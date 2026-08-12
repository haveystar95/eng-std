<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Dto\RepairedTranslation;
use App\Modules\Generation\Application\Dto\ScreenedItems;
use App\Modules\Generation\Application\Dto\TranslationRepairBrief;
use App\Modules\Generation\Application\Port\TranslationRepairPort;
use App\Modules\Generation\Domain\ValueObject\RejectedItem;
use App\Modules\Shared\Domain\Service\LanguagePurity;
use Throwable;

/**
 * The barrier between the model and the database: an item whose field is written in the wrong
 * language is not written, full stop.
 *
 * This exists because the enrichment станок could not be it. The станок reports AFTER the write, on
 * its own job, over a subset of terms — by the time it found «на одній хвилі» the row had been on a
 * phone for six days, and of the 89 poisoned example translations it had ever looked at two
 * (docs/ua-audit.md). A finding is a request for a human; a barrier is a guarantee.
 *
 * Two different refusals, because they have different repairs:
 *
 *  - a LEARNER-language field (`translation`, `example_translation`) in the wrong language is a
 *    translation the model can be asked to do again. The English side is correct and stays byte for
 *    byte the same — which is also what keeps any distractors built on that sentence valid. Up to
 *    {@see MAX_ATTEMPTS} re-asks, then the item is dropped;
 *  - a TARGET-language field (`text`, `example`) in the wrong language is dropped immediately. No
 *    number of re-translations fixes an English sentence with Cyrillic in it, and spending model
 *    calls to discover that twice would be paying for a known answer.
 *
 * Nothing here is a judgement call: {@see LanguagePurity} is a character scan, the same one the
 * станок reports with. What it cannot see (Ukrainian spelled in shared letters) it still cannot see
 * here — the barrier is a floor, not a ceiling, and the prompt's own self-check is what covers the
 * rest.
 */
final readonly class LanguageBarrier
{
    /**
     * How many times one item may be re-translated before it is dropped.
     *
     * Two, not one and not five. One is not a retry policy — a single unlucky sample would throw
     * away an item the model can render perfectly well on the next try. Beyond two the evidence has
     * stopped changing: a model that has answered in Ukrainian twice in a row for the same term is
     * not going to be talked out of it by a third identical request, and every further call is paid
     * for out of the user's generation, while they wait.
     */
    public const MAX_ATTEMPTS = 2;

    /** Fields written in the learner's language — repairable. */
    private const LEARNER_FIELDS = ['translation', 'example_translation'];

    /** Fields written in the language being learned — not repairable by a re-translation. */
    private const TARGET_FIELDS = ['text', 'example'];

    public function __construct(
        private TranslationRepairPort $repairer,
        private LanguagePurity $purity = new LanguagePurity(),
    ) {}

    /**
     * @param  list<GeneratedItem>  $items
     */
    public function screen(array $items, GenerationBrief $brief): ScreenedItems
    {
        $kept = [];
        $rejections = [];
        $repairs = [];

        foreach ($items as $item) {
            $fatal = $this->offence($item, self::TARGET_FIELDS, $brief->targetLang->value);
            if ($fatal !== null) {
                $rejections[] = new RejectedItem(
                    $item->text,
                    $fatal['field'],
                    $this->reason($fatal, $brief->targetLang->value)
                        . ' Починка перевода такое не лечит — термин отброшен без попыток.',
                    0,
                );

                continue;
            }

            $offence = $this->offence($item, self::LEARNER_FIELDS, $brief->sourceLang->value);
            if ($offence === null) {
                $kept[] = $item;

                continue;
            }

            [$item, $offence, $attempts, $spent] = $this->repair($item, $offence, $brief);
            foreach ($spent as $repair) {
                $repairs[] = $repair;
            }

            if ($offence === null) {
                $kept[] = $item;

                continue;
            }

            $rejections[] = new RejectedItem(
                $item->text,
                $offence['field'],
                $this->reason($offence, $brief->sourceLang->value)
                    . " Перевод запрошен заново {$attempts} раз(а) — тот же результат, термин отброшен.",
                $attempts,
            );
        }

        return new ScreenedItems($kept, $rejections, $repairs);
    }

    /**
     * Re-ask for this item's translations until they come back in the right language or the budget
     * runs out. A transport failure burns an attempt rather than looping for free: the caller is a
     * user waiting on a generation, and an endlessly retrying barrier is an outage.
     *
     * @param  array{field: string, value: string, letters: list<string>}  $offence
     * @return array{0: GeneratedItem, 1: array{field: string, value: string, letters: list<string>}|null, 2: int, 3: list<RepairedTranslation>}
     */
    private function repair(GeneratedItem $item, array $offence, GenerationBrief $brief): array
    {
        $spent = [];
        $attempts = 0;

        while ($attempts < self::MAX_ATTEMPTS) {
            $attempts++;

            try {
                $repaired = $this->repairer->repair(new TranslationRepairBrief(
                    text: $item->text,
                    type: $item->type,
                    sentence: $item->example,
                    targetLang: $brief->targetLang,
                    sourceLang: $brief->sourceLang,
                ));
            } catch (Throwable) {
                continue;
            }

            $spent[] = $repaired;

            // The target-language side is carried over untouched — the repair was never asked to
            // reconsider it, and an adapter that returned one anyway must not be able to smuggle it in.
            $item = new GeneratedItem(
                text: $item->text,
                type: $item->type,
                translation: $repaired->translation,
                example: $item->example,
                cefr: $item->cefr,
                transcription: $item->transcription,
                exampleTranslation: $repaired->exampleTranslation ?? $item->exampleTranslation,
                imageApiPrompt: $item->imageApiPrompt,
            );

            $offence = $this->offence($item, self::LEARNER_FIELDS, $brief->sourceLang->value);
            if ($offence === null) {
                break;
            }
        }

        return [$item, $offence, $attempts, $spent];
    }

    /**
     * The first field of $fields not written in $lang. First, not all: one bad field is already a
     * refusal, and naming the one that tripped is what makes the log actionable.
     *
     * @param  list<string>  $fields
     * @return array{field: string, value: string, letters: list<string>}|null
     */
    private function offence(GeneratedItem $item, array $fields, string $lang): ?array
    {
        foreach ($fields as $field) {
            $value = trim((string) $this->field($item, $field));
            if ($value === '') {
                continue;
            }
            $letters = $this->purity->foreignLetters($lang, $value);
            if ($letters !== []) {
                return ['field' => $field, 'value' => $value, 'letters' => $letters];
            }
        }

        return null;
    }

    private function field(GeneratedItem $item, string $field): ?string
    {
        return match ($field) {
            'translation' => $item->translation,
            'example_translation' => $item->exampleTranslation,
            'text' => $item->text,
            'example' => $item->example,
            default => null,
        };
    }

    /** @param  array{field: string, value: string, letters: list<string>}  $offence */
    private function reason(array $offence, string $lang): string
    {
        $letters = implode(' ', $offence['letters']);

        return "Поле {$offence['field']} должно быть на «{$lang}», а содержит чужие буквы ({$letters}): «{$offence['value']}».";
    }
}
