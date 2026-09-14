<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\SpeechScript;
use App\Modules\Generation\Application\Dto\SpeechTurn;
use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Generation\Application\Port\SpeechEncoder;
use App\Modules\Generation\Application\Port\SpeechNotCut;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\Service\SpeechCost;
use App\Modules\Shared\Domain\ValueObject\LineVoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Озвучка голосом Gemini TTS — `models/{model}:generateContent` с `responseModalities: [AUDIO]`.
 *
 * ## Сценарий — ОДИН вызов (DAY-UI-3)
 *
 * Бесплатный тариф режет ЗАПРОСЫ: 10 в минуту и 100 в сутки на модель. Поэтому диалог дня уходит
 * одним запросом с двумя говорящими (`multiSpeakerVoiceConfig`), а пачка слов или фраз — одним запросом
 * одним голосом со строкой на строку. Вендор отвечает ОДНИМ куском PCM без таймкодов строк; кусок
 * режется по паузам ({@see PcmTurnCutter}). Не разрезался — ещё ОДИН запрос (у синтезатора каждый
 * ответ свой), дальше {@see SpeechNotCut}: неверно разрезанный звук играл бы чужую строку. Цена строк —
 * сумма всех попыток, разложенная по длительности кусков.
 *
 * ## Формат: mp3; WAV — только если кодировщика нет
 *
 * Gemini отдаёт СЫРОЙ PCM (16 бит, моно, 24 кГц) в base64, и каждый кусок жмётся в mp3 здесь же
 * ({@see SpeechEncoder} → `lame` из образа, 64 кбит/с моно). Кодировщика в сборке нет — WAV.
 *
 * ## Темп — фразой, числовой ручки нет
 *
 * Темп задаётся текстом перед сценарием: наружу торчит один `LineVoice::$speed`, а как его исполнить,
 * знает адаптер. Два голоса одного сценария говорят в одном темпе — берётся темп первого.
 *
 * ## Лимит — поминутный или суточный
 *
 * 429 несёт `QuotaFailure.violations[].quotaId` (`…PerMinute…` / `…PerDay…`) и `RetryInfo.retryDelay`
 * («49480s» — до полуночи вендора). Оба уходят в {@see TransientSpeechError}: джоба ждёт ровно столько.
 */
final class GeminiSpeechSynthesizer implements SpeechSynthesizerPort
{
    private const SAMPLE_RATE = 24000;

    /** Имена говорящих в тексте сценария — нейтральные: голос задаёт конфиг, а не имя. */
    private const SPEAKER_NAMES = ['Alex', 'Sam'];

    private const ATTEMPTS = 2;

    public function __construct(
        private readonly OutboundCallContext $context,
        private readonly string $apiKey,
        private readonly SpeechEncoder $encoder,
        private readonly PcmTurnCutter $cutter = new PcmTurnCutter(),
        private readonly int $timeout = 180,
        private readonly string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
    ) {}

    public function speakScript(SpeechScript $script): array
    {
        $texts = array_map(static fn (SpeechTurn $t): string => trim($t->text), $script->turns);
        $voices = array_values($script->voices);
        $model = $voices[0]->model;
        foreach ($voices as $voice) {
            if ($voice->model !== $model) {
                throw new RuntimeException('a script is one call: every voice of it must be the same model');
            }
        }

        $costs = '0.000000';
        $lastCut = null;
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            [$pcm, $rate] = $this->call($script, $model);
            $durationMs = (int) round(strlen($pcm) / ($rate * 2) * 1000);
            $costs = self::add($costs, SpeechCost::estimate($model, $script->characters(), $durationMs));

            try {
                $spans = $this->cutter->spans($pcm, $rate, $texts);
            } catch (SpeechNotCut $e) {
                $lastCut = $e;

                continue;
            }

            return $this->lines($pcm, $rate, $spans, $costs, $model);
        }

        throw $lastCut ?? SpeechNotCut::because('no attempt');
    }

    /** @return array{0: string, 1: int} raw PCM and its sample rate */
    private function call(SpeechScript $script, string $model): array
    {
        $url = rtrim($this->baseUrl, '/').'/models/'.$model.':generateContent';

        try {
            $response = $this->context->run('speech', null, fn () => Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
            ])
                ->timeout($this->timeout)
                ->post($url, [
                    'contents' => [['parts' => [['text' => self::prompt($script)]]]],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO'],
                        'speechConfig' => self::speechConfig($script),
                    ],
                ]));
        } catch (ConnectionException $e) {
            throw TransientSpeechError::network('gemini', $e->getMessage());
        }

        if ($response->status() === 429) {
            throw self::rateLimited($response);
        }
        if ($response->serverError()) {
            throw TransientSpeechError::upstream('gemini', $response->status());
        }
        if ($response->failed()) {
            throw new RuntimeException('Gemini speech error: '.$response->status().' '.$response->body());
        }

        $data = $response->json('candidates.0.content.parts.0.inlineData.data');
        if (! is_string($data) || $data === '') {
            throw new RuntimeException('Gemini speech returned no audio: '.mb_substr($response->body(), 0, 500));
        }
        $pcm = base64_decode($data, true);
        if ($pcm === false || $pcm === '') {
            throw new RuntimeException('Gemini speech returned undecodable audio');
        }
        $mime = $response->json('candidates.0.content.parts.0.inlineData.mimeType');

        return [$pcm, is_string($mime) ? self::rateOf($mime) : self::SAMPLE_RATE];
    }

    /**
     * Every cut piece as mp3 (or WAV), its duration and its share of the script's cost.
     *
     * @param  list<array{0: int, 1: int}>  $spans
     * @return list<SpokenLine>
     */
    private function lines(string $pcm, int $rate, array $spans, string $cost, string $model): array
    {
        $total = max(1, array_sum(array_map(static fn (array $s): int => $s[1] - $s[0], $spans)));
        $out = [];
        foreach ($spans as [$from, $to]) {
            $piece = substr($pcm, intdiv($from * $rate, 1000) * 2, intdiv(($to - $from) * $rate, 1000) * 2);
            $mp3 = $this->encoder->pcmToMp3($piece, $rate);
            $out[] = new SpokenLine(
                bytes: $mp3 ?? self::wav($piece, $rate),
                format: $mp3 === null ? 'wav' : 'mp3',
                durationMs: $to - $from,
                costUsd: number_format((float) $cost * ($to - $from) / $total, 6, '.', ''),
            );
        }

        return $out;
    }

    private static function prompt(SpeechScript $script): string
    {
        $pace = self::pace(array_values($script->voices)[0]);
        if ($script->isBatch()) {
            $lines = implode("\n", array_map(static fn (SpeechTurn $t): string => trim($t->text), $script->turns));

            return 'Read each line below aloud as a separate item, in a warm, natural voice — a real person, '
                ."not an announcer. {$pace}. Pause for about one second after every line. Read only the lines:\n\n"
                .$lines;
        }

        $names = self::names($script);
        $lines = implode("\n", array_map(
            static fn (SpeechTurn $t): string => $names[$t->speaker].': '.trim($t->text),
            $script->turns,
        ));

        return 'TTS the following conversation between '.implode(' and ', array_values($names)).', two real people '
            ."talking face to face — warm and natural, not announcers. {$pace}. Leave a clear pause between turns.\n\n"
            .$lines;
    }

    /** @return array<string, mixed> */
    private static function speechConfig(SpeechScript $script): array
    {
        if ($script->isBatch()) {
            return ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => array_values($script->voices)[0]->voice]]];
        }
        $names = self::names($script);
        $configs = [];
        foreach ($script->voices as $speaker => $voice) {
            $configs[] = [
                'speaker' => $names[$speaker],
                'voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice->voice]],
            ];
        }

        return ['multiSpeakerVoiceConfig' => ['speakerVoiceConfigs' => $configs]];
    }

    /** @return array<string, string> speaker key → the name the script text uses */
    private static function names(SpeechScript $script): array
    {
        $names = [];
        foreach (array_keys($script->voices) as $i => $speaker) {
            $names[$speaker] = self::SPEAKER_NAMES[$i];
        }

        return $names;
    }

    private static function pace(LineVoice $voice): string
    {
        return $voice->speed < 0.95
            ? 'Slightly slower than normal, clearly articulated'
            : 'At a normal conversational pace';
    }

    private static function rateLimited(Response $response): TransientSpeechError
    {
        $perDay = false;
        $retry = null;
        $details = $response->json('error.details');
        foreach (is_array($details) ? $details : [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }
            foreach (is_array($detail['violations'] ?? null) ? $detail['violations'] : [] as $violation) {
                if (is_array($violation) && str_contains((string) ($violation['quotaId'] ?? ''), 'PerDay')) {
                    $perDay = true;
                }
            }
            if (is_string($detail['retryDelay'] ?? null) && preg_match('/^(\d+)(?:\.\d+)?s$/', $detail['retryDelay'], $m) === 1) {
                $retry = (int) $m[1] + 1;
            }
        }

        return TransientSpeechError::rateLimited('gemini', $retry ?? ($perDay ? null : 60), $perDay);
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

    private static function add(string $sum, ?string $cost): string
    {
        return number_format((float) $sum + (float) ($cost ?? '0'), 6, '.', '');
    }

    /** 44-байтовый RIFF-заголовок вокруг того же PCM — mono, 16 bit. */
    private static function wav(string $pcm, int $rate): string
    {
        return 'RIFF'
            .pack('V', 36 + strlen($pcm))
            .'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            .'data'
            .pack('V', strlen($pcm))
            .$pcm;
    }
}
