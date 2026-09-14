<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\SpeechScript;
use App\Modules\Generation\Application\Dto\SpeechTurn;
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
 * `POST /v1/audio/speech` — озвучка голосом OpenAI. Отдаёт mp3 БАЙТАМИ.
 *
 * ## Сценарий — строка за строкой
 *
 * Говорящих в одном вызове у вендора нет, поэтому сценарий (DAY-UI-3) говорится строкой на вызов, в
 * порядке сценария. Резать нечего — у каждой строки свой файл. У OpenAI платный тариф без суточного
 * лимита запросов, и правило «вызов на сценарий» — правило бесплатного Gemini, а не порта.
 *
 * ## Две модели, две ручки темпа, и это не наша прихоть
 *
 * `tts-1` / `tts-1-hd` слушают числовой `speed`. `gpt-4o-mini-tts` управляется словами —
 * `instructions`, — и темп там задаётся фразой, а не числом. Обе ветки живут здесь, потому что это
 * различие ВЕНДОРА: наружу и там и там торчит один `LineVoice::$speed`, иначе конфиг пакета
 * пришлось бы писать по-разному для двух моделей одного вендора.
 */
final class OpenAiSpeechSynthesizer implements SpeechSynthesizerPort
{
    public function __construct(
        private readonly OutboundCallContext $context,
        private readonly string $apiKey,
        private readonly int $timeout = 60,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function speakScript(SpeechScript $script): array
    {
        return array_map(
            fn (SpeechTurn $turn): SpokenLine => $this->speak($turn->text, $script->voices[$turn->speaker]),
            $script->turns,
        );
    }

    private function speak(string $text, LineVoice $voice): SpokenLine
    {
        $line = trim($text);
        if ($line === '') {
            throw new RuntimeException('nothing to speak');
        }

        $payload = [
            'model' => $voice->model,
            'voice' => $voice->voice,
            'input' => $line,
            'response_format' => 'mp3',
        ];

        if (str_starts_with($voice->model, 'gpt-')) {
            $payload['instructions'] = self::instructionsFor($voice->speed);
        } else {
            $payload['speed'] = $voice->speed;
        }

        try {
            $response = $this->context->run('speech', null, fn () => Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post(rtrim($this->baseUrl, '/') . '/audio/speech', $payload));
        } catch (ConnectionException $e) {
            throw TransientSpeechError::network('openai', $e->getMessage());
        }

        if ($response->status() === 429) {
            $after = $response->header('Retry-After');
            throw TransientSpeechError::rateLimited('openai', $after === '' ? null : (int) $after);
        }
        if ($response->serverError()) {
            throw TransientSpeechError::upstream('openai', $response->status());
        }
        if ($response->failed()) {
            // 4xx, не 429 — текст, который вендор отказался читать. Повтор купит тот же отказ.
            throw new RuntimeException('OpenAI speech error: ' . $response->status() . ' ' . $response->body());
        }

        $bytes = $response->body();
        if ($bytes === '') {
            throw new RuntimeException('OpenAI speech returned no audio');
        }

        return new SpokenLine(
            bytes: $bytes,
            format: 'mp3',
            // Длительность вендор не называет; считаем её из веса файла — mp3 у OpenAI постоянного
            // битрейта, поэтому оценка честная и стоит ноль запросов. Это СПРАВКА (экономику
            // считаем по символам), но именно она отвечает «сколько секунд звучит сцена».
            durationMs: SpeechCost::mp3DurationMs(strlen($bytes)),
            costUsd: SpeechCost::estimate(
                $voice->model,
                mb_strlen($line),
                SpeechCost::mp3DurationMs(strlen($bytes)),
            ),
        );
    }

    /**
     * Темп словами — единственный способ замедлить `gpt-4o-mini-tts`. Замерено в Ч.0: с этой
     * инструкцией модель читает ~10–11 знаков в секунду против ~15 у `tts-1` на speed 0.9, то есть
     * инструкция работает и работает сильнее числа.
     */
    private static function instructionsFor(float $speed): string
    {
        $pace = $speed < 0.95
            ? 'Slightly slower than normal, with clear articulation.'
            : 'At a normal conversational pace.';

        return 'Speak in a warm, natural, conversational tone, as a real person talking to one '
            . 'other person. Not an announcer. ' . $pace;
    }
}
