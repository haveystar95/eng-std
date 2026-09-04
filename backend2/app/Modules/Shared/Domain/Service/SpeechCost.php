<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * Во что обходится ОЗВУЧКА — одна таблица на всё приложение, рядом с {@see ModelCost} и по тем же
 * правилам: модель, которой тут нет, стоит `null`, то есть «не тарифицируется», и никогда «ноль».
 *
 * Отдельно от `ModelCost`, потому что счёт другой формы. Текстовая модель берёт за ТОКЕНЫ, которые
 * нам возвращает сама; за озвучку одни вендоры берут за СИМВОЛЫ входа (OpenAI `tts-1`), другие — за
 * ТОКЕНЫ выходного звука, то есть фактически за длительность (OpenAI `gpt-4o-mini-tts`, Gemini
 * TTS). Второе означает вещь, которую легко не заметить: чем МЕДЛЕННЕЕ читает голос, тем дороже
 * стоит та же строка. Реплики канон просит читать медленнее слов (§7) — значит эта строка кода
 * оплачивается ручкой темпа, и лучше, чтобы это было видно.
 *
 * Ставки сверены с прайсами вендоров 04.09.2026 (docs/research/tts-1.md, Ч.0.2).
 */
final class SpeechCost
{
    /**
     * Битрейт mp3, который OpenAI отдаёт на `/v1/audio/speech`: 128 кбит/с = 16 000 байт/с.
     * Измерено по 20 живым файлам наряда TTS-1 — вес/длительность даёт 16 000 ± 0 на всех.
     */
    private const MP3_BYTES_PER_SECOND = 16000;

    /**
     * Модели, которые берут за СИМВОЛЫ входа: USD за 1M символов.
     *
     * @var array<string, float>
     */
    private const PER_CHARACTER = [
        'tts-1' => 15.0,
        'tts-1-hd' => 30.0,
    ];

    /**
     * Модели, которые берут за ТОКЕНЫ: [текст-вход USD/1M токенов, звук-выход USD/1M токенов,
     * звуковых токенов в секунде].
     *
     * Токенов в секунде: OpenAI — 1 токен на 50 мс выходного звука (20/с, та же цифра, что в
     * `ModelCost::REALTIME_PRICING`); Gemini — 25/с.
     *
     * @var array<string, array{0: float, 1: float, 2: int}>
     */
    private const PER_TOKEN = [
        'gpt-4o-mini-tts' => [0.60, 12.0, 20],
        'gemini-2.5-flash-preview-tts' => [0.50, 10.0, 25],
        'gemini-2.5-pro-preview-tts' => [1.00, 20.0, 25],
        'gemini-3.1-flash-tts-preview' => [1.00, 20.0, 25],
    ];

    /** Сколько миллисекунд звучит mp3 такого веса. */
    public static function mp3DurationMs(int $bytes): int
    {
        return (int) round($bytes / self::MP3_BYTES_PER_SECOND * 1000);
    }

    /**
     * Цена ОДНОЙ озвученной реплики, USD строкой с шестью знаками. `null` — модель без ставки.
     *
     * Текстовый вход у токенных моделей считается по грубому «4 символа = токен»: доля этой
     * половины в счёте — единицы процентов (звук дороже входа в двадцать раз), и точный токенайзер
     * ради неё не стоит вызова.
     */
    public static function estimate(string $model, int $chars, ?int $durationMs): ?string
    {
        $key = ModelCost::baseModel($model);

        if (isset(self::PER_CHARACTER[$key])) {
            return number_format($chars / 1_000_000 * self::PER_CHARACTER[$key], 6, '.', '');
        }

        if (! isset(self::PER_TOKEN[$key]) || $durationMs === null) {
            return null;
        }

        [$textPer1M, $audioPer1M, $tokensPerSecond] = self::PER_TOKEN[$key];
        $audioTokens = $durationMs / 1000 * $tokensPerSecond;
        $textTokens = $chars / 4;

        $cost = $audioTokens / 1_000_000 * $audioPer1M + $textTokens / 1_000_000 * $textPer1M;

        return number_format($cost, 6, '.', '');
    }

    /**
     * Ставка, приведённая к ОДНОЙ мере — USD за 1M символов при заданном темпе чтения
     * (символов в секунду). Только для отчётов: сравнивать посимвольного вендора с
     * подлительностным иначе нечем, а «$15 против $12» — сравнение двух разных величин.
     */
    public static function perMillionCharacters(string $model, float $charsPerSecond): ?float
    {
        $key = ModelCost::baseModel($model);

        if (isset(self::PER_CHARACTER[$key])) {
            return self::PER_CHARACTER[$key];
        }
        if (! isset(self::PER_TOKEN[$key]) || $charsPerSecond <= 0) {
            return null;
        }

        [$textPer1M, $audioPer1M, $tokensPerSecond] = self::PER_TOKEN[$key];
        $audioTokensPerChar = $tokensPerSecond / $charsPerSecond;

        return $audioTokensPerChar * $audioPer1M + $textPer1M / 4;
    }
}
