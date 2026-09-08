<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ОДИН ОТВЕТ ПРО ОДНУ СКАЗАННУЮ РЕПЛИКУ (наряд SPEECH-2, Ч.3).
 *
 * Здесь лежит не только «зачёт или нет», но и всё, чем этот вердикт можно объяснить: сколько
 * покрыто, каким порогом мерили и КАКИХ СЛОВ не хватило. Причина ровно одна и она из живого
 * прогона: «Не то» без списка неотличимо от «микрофон не расслышал», и человек, получивший его на
 * реплике, где пропал один предлог, чинит не то, что сломано.
 *
 * [$missing] — слова ЦЕЛИ в том виде, в каком они написаны на карточке (не канонизированные
 * токены): их читает человек, а не грейдер.
 */
final readonly class SpokenVerdict
{
    /** @param list<string> $missing */
    public function __construct(
        public SpokenCredit $credit,
        public float $coverage,
        public array $missing = [],
        /** Имя применённого порога — `read_aloud` | `key_and_rest` | `whole_line`. Для дев-строки. */
        public string $threshold = '',
        /** Транскрипт после нормализации аббревиатур — то, что грейдер на самом деле сравнивал. */
        public string $normalized = '',
    ) {}

    public function isAccepted(): bool
    {
        return $this->credit->isAccepted();
    }
}
