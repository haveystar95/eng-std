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
 * `{partner: {female: {…}, female_2: {…}, male: {…}, male_2: {…}}, learner: {female: {…}, male: {…}}}`. У ролей свои
 * голоса каждого пола, чтобы собеседник никогда не звучал как сам ученик.
 *
 * У СОБЕСЕДНИКА ДВА ГОЛОСА НА ПОЛ (наряд FIX-4c §1): регистратор и врач — обе женщины — звучали одним голосом, и смена
 * сцены не была слышна. Голос сцены закреплён за ней (`plan_scenes.partner_voice_id`) — соседние сцены одного пола
 * чередуют голос 1 и 2; этот класс отдаёт оба ({@see partnerVoices()}) и находит строку пакета по id голоса сцены.
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

    /**
     * The voice of a role and gender in this language. `$voice` — the partner's voice a scene has had fixed for it
     * (наряд FIX-4c §1): the pack's row of that id among the gender's voices, or, when the pack no longer names it, the
     * gender's first row said with that id — a scene keeps the voice it was voiced with, whatever the pack says later.
     * Null or a learner's line — the gender's first voice, as before.
     */
    public function forLanguage(string $targetLang, VoiceRole $role, VoiceGender $gender, ?string $voice = null): ?LineVoice
    {
        $first = $this->row($targetLang, $role, $gender->value);
        if ($first === null) {
            return null;
        }
        $voice = trim((string) $voice);
        if ($role !== VoiceRole::Partner || $voice === '' || $first->voice === $voice) {
            return $first;
        }
        $second = $this->row($targetLang, $role, $gender->value.'_2');
        if ($second !== null && $second->voice === $voice) {
            return $second;
        }

        return new LineVoice($first->provider, $first->model, $voice, $first->stability);
    }

    /**
     * THE PARTNER'S VOICES OF A GENDER (наряд FIX-4c §1), voice 1 first — what a new scene is cast from: its voice
     * is the other one of the nearest earlier scene of the same gender. One when the pack has no second; none when the
     * language has no voice at all.
     *
     * @return list<string> the voice ids
     */
    public function partnerVoices(string $targetLang, VoiceGender $gender): array
    {
        $out = [];
        foreach ([$gender->value, $gender->value.'_2'] as $key) {
            $row = $this->row($targetLang, VoiceRole::Partner, $key);
            if ($row !== null && ! in_array($row->voice, $out, true)) {
                $out[] = $row->voice;
            }
        }

        return $out;
    }

    private function row(string $targetLang, VoiceRole $role, string $key): ?LineVoice
    {
        $pack = $this->voices[strtolower(trim($targetLang))] ?? null;
        $roles = is_array($pack) ? ($pack[$role->value] ?? null) : null;
        $row = is_array($roles) ? ($roles[$key] ?? null) : null;
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
