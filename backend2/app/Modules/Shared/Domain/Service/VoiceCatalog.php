<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

use App\Modules\Shared\Domain\ValueObject\LineVoice;
use InvalidArgumentException;

/**
 * КАКИМ ГОЛОСОМ ГОВОРИТ ЭТОТ ЯЗЫК — конфиг языкового пакета, не генерация и не код (наряд TTS-1).
 *
 * В ядре, потому что вопрос задают ОБА берега и по-разному: Generation спрашивает «каким голосом
 * покупать», Learning — «за какой голос искать готовые файлы». Ответ обязан быть один: разойдись
 * они на одну букву, станок купил бы озвучку, которую сборка посадки никогда не нашла бы.
 *
 * Та же форма, что у {@see DistractorLength}: чистый класс, которому таблицу подают снаружи
 * (`SharedServiceProvider` из `generation.speech.voices`), — Domain не читает конфиг сам.
 *
 * `null` — «у этого языка голоса нет», и это не отказ: реплики звучат системным синтезом телефона,
 * ровно как до наряда.
 */
final readonly class VoiceCatalog
{
    /** @param array<string, mixed> $voices target language → the pack's voice row */
    public function __construct(private array $voices) {}

    public function forLanguage(string $targetLang): ?LineVoice
    {
        $row = $this->voices[strtolower(trim($targetLang))] ?? null;
        if (! is_array($row)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $row */
            return LineVoice::fromArray($row);
        } catch (InvalidArgumentException) {
            // Кривая строка в пакете — это «голоса нет», а не падение. Читатель этого класса стоит
            // либо на уже готовом дне, либо на сборке посадки, и ронять там нечего.
            return null;
        }
    }
}
