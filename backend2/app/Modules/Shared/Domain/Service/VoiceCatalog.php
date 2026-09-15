<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

use App\Modules\Shared\Domain\ValueObject\LineVoice;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use App\Modules\Shared\Domain\ValueObject\VoiceRole;
use InvalidArgumentException;

/**
 * КАКИМ ГОЛОСОМ ГОВОРИТ ЭТОТ ЯЗЫК — конфиг языкового пакета, не генерация и не код (наряды TTS-1, DAY-UI-3, TTS-2).
 *
 * В ядре, потому что вопрос задают ОБА берега и по-разному: Generation спрашивает «каким голосом покупать», Plan —
 * «за какой голос искать готовые файлы». Ответ обязан быть один: разойдись они на одну букву, озвучка купила бы
 * файл, который окно дня никогда не нашло бы.
 *
 * Сцена — два человека разного пола, и голос выбирается по РОЛИ и полу (TTS-2): строка пакета —
 * `{partner: {female: {…}, male: {…}}, learner: {female: {…}, male: {…}}}`. У мужчины-ученика и мужчины-собеседника
 * разные голоса, чтобы собеседник не звучал как сам ученик.
 *
 * Та же форма, что у {@see DistractorLength}: чистый класс, которому таблицу подают снаружи (`SharedServiceProvider`
 * из `generation.speech.voices`), — Domain не читает конфиг сам.
 *
 * `null` — «у этого языка такого голоса нет», и это не отказ: строка звучит системным синтезом телефона.
 */
final readonly class VoiceCatalog
{
    /** @param array<string, mixed> $voices target language → the pack's voices by role and gender */
    public function __construct(private array $voices) {}

    public function forLanguage(string $targetLang, VoiceRole $role, VoiceGender $gender): ?LineVoice
    {
        $pack = $this->voices[strtolower(trim($targetLang))] ?? null;
        $roles = is_array($pack) ? ($pack[$role->value] ?? null) : null;
        $row = is_array($roles) ? ($roles[$gender->value] ?? null) : null;
        if (! is_array($row)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $row */
            return LineVoice::fromArray($row);
        } catch (InvalidArgumentException) {
            // Кривая строка в пакете — это «голоса нет», а не падение. Читатель этого класса стоит либо на уже
            // готовом дне, либо на его озвучке, и ронять там нечего.
            return null;
        }
    }
}
