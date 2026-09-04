<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Vocabulary\Application\Query\TermAudioReader;

/**
 * КАКИЕ ИЗ ЭТИХ КАРТОЧЕК УЖЕ ЗВУЧАТ — один вопрос на всю посадку, а не по карточке.
 *
 * Читает Vocabulary через его Application и ТЕМ ЖЕ голосом, каким станок покупал: голос приходит
 * из одного {@see VoiceCatalog}, поэтому «купили одним, ищем другим» невозможно построением.
 *
 * Пусто — это ответ, а не сбой: озвучки нет, клиент читает реплику системным синтезом. Так же
 * выглядит выключенная труба, и это правильно — тумблер обязан быть неотличим от «ещё не успело».
 */
final readonly class LineAudioIndex
{
    public function __construct(
        private TermAudioReader $audios,
        private VoiceCatalog $voices,
    ) {}

    /**
     * @param  list<string>  $termIds
     * @return array<string, string>  term id → audio row id (the address the file is served at)
     */
    public function forTerms(array $termIds, string $targetLang): array
    {
        $voice = $this->voices->forLanguage($targetLang);
        if ($voice === null || $termIds === []) {
            return [];
        }

        $out = [];
        foreach ($this->audios->forTerms(array_values(array_unique($termIds)), $voice) as $termId => $row) {
            $out[$termId] = $row->id;
        }

        return $out;
    }
}
