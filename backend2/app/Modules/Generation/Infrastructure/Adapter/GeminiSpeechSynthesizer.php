<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\Service\SpeechCost;
use App\Modules\Shared\Domain\ValueObject\LineVoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Озвучка голосом Gemini TTS — `models/{model}:generateContent` с `responseModalities: [AUDIO]`.
 *
 * ## Формат: WAV, и это цена этого вендора
 *
 * Gemini отдаёт СЫРОЙ PCM (16 бит, моно, 24 кГц) в base64. Сжать его в mp3 внутри контейнера нечем
 * — кодировщика там нет и тащить его туда ради одного адаптера мы не стали, — поэтому файл
 * записывается как WAV: тот же PCM с 44-байтовым заголовком. Это работает и играется на iOS без
 * единой зависимости, но весит примерно в шесть раз больше mp3 того же места (замер Ч.0.4). Голос
 * этого вендора стоит выбирать УШАМИ, а не потому что он дешевле; за него платят докачкой.
 *
 * ## Темп — фразой, числовой ручки нет
 *
 * У этой ручки вендора вообще нет параметра: темп задаётся текстом перед репликой. Поэтому здесь
 * стоит тот же приём, что у `gpt-4o-mini-tts`, и по той же причине — наружу торчит один
 * `LineVoice::$speed`, а как его исполнить, знает адаптер.
 */
final class GeminiSpeechSynthesizer implements SpeechSynthesizerPort
{
    private const SAMPLE_RATE = 24000;

    public function __construct(
        private readonly OutboundCallContext $context,
        private readonly string $apiKey,
        private readonly int $timeout = 60,
        private readonly string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
    ) {}

    public function speak(string $text, string $lang, LineVoice $voice): SpokenLine
    {
        $line = trim($text);
        if ($line === '') {
            throw new RuntimeException('nothing to speak');
        }

        $url = rtrim($this->baseUrl, '/') . '/models/' . $voice->model . ':generateContent';

        try {
            $response = $this->context->run('speech', null, fn () => Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
            ])
                ->timeout($this->timeout)
                ->post($url, [
                    'contents' => [['parts' => [['text' => self::promptFor($line, $voice->speed)]]]],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => [
                            'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice->voice]],
                        ],
                    ],
                ]));
        } catch (ConnectionException $e) {
            throw TransientSpeechError::network('gemini', $e->getMessage());
        }

        if ($response->status() === 429) {
            throw TransientSpeechError::rateLimited('gemini', null);
        }
        if ($response->serverError()) {
            throw TransientSpeechError::upstream('gemini', $response->status());
        }
        if ($response->failed()) {
            throw new RuntimeException('Gemini speech error: ' . $response->status() . ' ' . $response->body());
        }

        $data = $response->json('candidates.0.content.parts.0.inlineData.data');
        if (! is_string($data) || $data === '') {
            throw new RuntimeException('Gemini speech returned no audio: ' . $response->body());
        }

        $pcm = base64_decode($data, true);
        if ($pcm === false || $pcm === '') {
            throw new RuntimeException('Gemini speech returned undecodable audio');
        }

        $mime = $response->json('candidates.0.content.parts.0.inlineData.mimeType');
        $rate = is_string($mime) ? self::rateOf($mime) : self::SAMPLE_RATE;
        $durationMs = (int) round(strlen($pcm) / ($rate * 2) * 1000);

        return new SpokenLine(
            bytes: self::wav($pcm, $rate),
            format: 'wav',
            durationMs: $durationMs,
            costUsd: SpeechCost::estimate($voice->model, mb_strlen($line), $durationMs),
        );
    }

    private static function promptFor(string $line, float $speed): string
    {
        $pace = $speed < 0.95
            ? 'Slightly slower than normal, clearly articulated'
            : 'At a normal conversational pace';

        return 'Say this in a warm, natural, conversational tone, as a real person talking to one '
            . "other person — not an announcer. {$pace}: {$line}";
    }

    /** `audio/L16;codec=pcm;rate=24000` → 24000. */
    private static function rateOf(string $mime): int
    {
        foreach (explode(';', $mime) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'rate=')) {
                return max(8000, (int) substr($part, 5));
            }
        }

        return self::SAMPLE_RATE;
    }

    /** 44-байтовый RIFF-заголовок вокруг того же PCM — mono, 16 bit. */
    private static function wav(string $pcm, int $rate): string
    {
        $byteRate = $rate * 2;

        return 'RIFF'
            . pack('V', 36 + strlen($pcm))
            . 'WAVEfmt '
            . pack('VvvVVvv', 16, 1, 1, $rate, $byteRate, 2, 16)
            . 'data'
            . pack('V', strlen($pcm))
            . $pcm;
    }
}
