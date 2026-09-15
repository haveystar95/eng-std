<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * Во что обходится ОЗВУЧКА — одна таблица на всё приложение, рядом с {@see ModelCost} и по тем же правилам: модель,
 * которой тут нет, стоит `null`, то есть «не тарифицируется», и никогда «ноль» (наряд TTS-2).
 *
 * Голос покупается у ElevenLabs, и счёт идёт ЗА СИМВОЛЫ ТЕКСТА, а не за секунды звука. Тариф API — за 1 000 символов по
 * модели: v3 Conversational, Flash, Turbo — $0.05, Eleven v3 и Multilingual v2 — $0.10 (прайс 15.09.2026,
 * `elevenlabs.io/pricing/api`).
 *
 * ФАКТИЧЕСКАЯ цена строки — из ответа вендора: заголовок `character-cost` называет кредиты, списанные с аккаунта, и
 * кредит стоит цену тарифа аккаунта (Starter — $6 за 30 000, $0.20 за тысячу). Символы и кредиты — разные числа: за
 * символ модель берёт долю кредита по тарифу аккаунта (живьём 15.09 на Starter: v3 Conversational — «Hello, how are you
 * doing today?» 31 символ → 8 кредитов, v3 — 16; на Free v3 — символ за кредит). Кредиты × цена кредита сходятся с
 * символами × тарифом модели до округления вендора вверх — поэтому деньги пишутся по кредитам, а тариф модели —
 * справка, по которой видно, что они сошлись.
 */
final class SpeechCost
{
    /** mp3 128 кбит/с — формат, в котором голос покупается (`mp3_44100_128`): 16 000 байт в секунду. */
    private const MP3_BYTES_PER_SECOND = 16000;

    /**
     * USD за 1 000 символов текста, по модели.
     *
     * @var array<string, float>
     */
    private const PER_THOUSAND_CHARACTERS = [
        'eleven_v3_conversational' => 0.05,
        'eleven_flash_v2_5' => 0.05,
        'eleven_flash_v2' => 0.05,
        'eleven_turbo_v2_5' => 0.05,
        'eleven_turbo_v2' => 0.05,
        'eleven_v3' => 0.10,
        'eleven_multilingual_v2' => 0.10,
    ];

    /** Тариф модели — USD за 1 000 символов текста; `null` — модели нет в таблице. */
    public static function perThousandCharacters(string $model): ?float
    {
        return self::PER_THOUSAND_CHARACTERS[$model] ?? null;
    }

    /** Цена символов текста по тарифу модели, USD строкой с шестью знаками; `null` — модели нет в таблице. */
    public static function ofCharacters(string $model, int $characters): ?string
    {
        $rate = self::perThousandCharacters($model);

        return $rate === null ? null : number_format(max(0, $characters) / 1000 * $rate, 6, '.', '');
    }

    /** Цена списанных кредитов по цене кредита тарифа аккаунта, USD строкой с шестью знаками. */
    public static function ofCredits(int $credits, float $usdPerThousandCredits): string
    {
        return number_format(max(0, $credits) / 1000 * max(0.0, $usdPerThousandCredits), 6, '.', '');
    }

    /** Сколько символов вендор считает в тексте строки: без пробелов по краям. */
    public static function charactersOf(string $text): int
    {
        return mb_strlen(trim($text));
    }

    /** Сколько миллисекунд звучит mp3 такого веса. */
    public static function mp3DurationMs(int $bytes): int
    {
        return (int) round($bytes / self::MP3_BYTES_PER_SECOND * 1000);
    }
}
