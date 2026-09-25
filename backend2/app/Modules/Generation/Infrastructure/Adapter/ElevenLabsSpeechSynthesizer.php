<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\SpeechBalance;
use App\Modules\Generation\Application\Dto\SpeechLine;
use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Generation\Application\Port\SpeechAccountError;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\Service\SpeechCost;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * ГОЛОС СЕРВЕРА — ElevenLabs, единственная реализация {@see SpeechSynthesizerPort} (наряд TTS-2).
 *
 * ## Строка — свой вызов, своим голосом
 *
 * `POST /v1/text-to-speech/{voice_id}?output_format=mp3_44100_128` с `model_id` голоса (`eleven_v3_conversational` —
 * id модели v3 Conversational по документации API) и `voice_settings.stability` (Natural = 0.5); остальные настройки —
 * по умолчанию вендора. Вендор отвечает самим mp3 — это и есть файл строки. Реплики диалога тоже говорятся по одной,
 * каждая голосом своего говорящего (решение архитектора TTS-2): диалог не собирается из одного звука и не режется.
 *
 * ## Язык строки
 *
 * Строка, чей язык назван ({@see SpeechLine::$languageCode} — цель плана), уходит с `language_code` (ISO 639-1): одни и те
 * же голоса говорят всеми целями плана, и v3 без кода угадывает язык по буквам (наряд LANG-1, п. 9). Живая проба 25.09 на
 * `eleven_v3_conversational` — код принят на 12 строках из 12. Выключатель `generation.speech.language_code`
 * (`SPEECH_LANGUAGE_CODE`, по умолчанию включён) снимает код со всех строк разом, если вендор начнёт отказывать модели в
 * нём; строка без названного языка уходит без кода всегда. Ключ файла код не трогает (DECISIONS п. 248).
 *
 * ## Сколько сразу
 *
 * Строки уходят раундами через `Http::pool` по числу одновременных запросов аккаунта: сначала по конфигу, дальше — по
 * заголовку вендора `maximum-concurrent-requests` (Starter — 3). Купленное отдаётся после каждого раунда.
 *
 * ## Во что обошлось — говорит вендор
 *
 * `character-cost` каждого ответа — кредиты, списанные с аккаунта; их цена — цена кредита тарифа аккаунта (конфиг,
 * {@see SpeechCost::ofCredits()}). Символы текста пишутся рядом: за символ модель берёт долю кредита по тарифу аккаунта
 * (живьём 15.09 на Starter: v3 Conversational — 31 символ за 8 кредитов). `request-id` сохраняется: один id — один
 * оплаченный вызов. Заголовка нет — кредиты оцениваются по тарифу модели.
 *
 * ## Отказы
 *
 * 429 (`concurrent_limit_exceeded` живьём приходит без `Retry-After`; `rate_limit_exceeded`), 5xx и оборванное
 * соединение повторяются здесь несколько раз с растущей паузой, дальше {@see TransientSpeechError} — и ждёт джоба.
 * 401, 402 и 403, а также коды отказа аккаунта при любом статусе — {@see SpeechAccountError} сразу, с кодом вендора.
 * Остальные 4xx — отказ прочитать этот текст: строка пропускается.
 */
final class ElevenLabsSpeechSynthesizer implements SpeechSynthesizerPort
{
    public const PROVIDER = 'elevenlabs';

    /** mp3 44,1 кГц 128 кбит/с — формат владельца (TTS-2), доступен на любом тарифе. */
    private const OUTPUT_FORMAT = 'mp3_44100_128';

    /** Короткие повторы транзиентного отказа, прежде чем попросить джобу подождать. */
    private const RETRIES = 4;

    /** Дольше этого процесс сам не ждёт: окно, названное вендором дальше, ждёт очередь. */
    private const LONGEST_PAUSE_MS = 30_000;

    /**
     * Отказы аккаунта, как бы вендор ни выставил им статус: голос, недоступный тарифу, живьём 15.09 пришёл не только
     * 402 `paid_plan_required`, но и 400 `free_users_not_allowed` («You need to be on the creator tier or above»).
     */
    private const ACCOUNT_CODES = [
        'paid_plan_required', 'free_users_not_allowed', 'quota_exceeded', 'insufficient_credits',
        'invalid_api_key', 'missing_api_key', 'missing_permissions', 'payment_required',
    ];

    private int $concurrency;

    /** Whether a line's named language goes to the vendor as `language_code` (наряд LANG-1, п. 9). */
    private readonly bool $sendsLanguageCode;

    public function __construct(
        private readonly OutboundCallContext $context,
        private readonly string $apiKey,
        /** Цена тысячи кредитов по тарифу аккаунта — то, во что обходится `character-cost`. */
        private readonly float $usdPerThousandCredits,
        int $concurrency = 3,
        private readonly int $timeout = 60,
        private readonly string $baseUrl = 'https://api.elevenlabs.io',
        private readonly int $backoffMs = 1000,
        /**
         * Send the line's language (`language_code`)? Null — the switch `generation.speech.language_code` as the
         * deployment has it (default on), so the container's binding reads it without an argument of its own.
         */
        ?bool $languageCode = null,
    ) {
        $this->concurrency = max(1, $concurrency);
        $this->sendsLanguageCode = $languageCode ?? (bool) config('generation.speech.language_code', true);
    }

    public function speakLines(array $lines, callable $spoken): void
    {
        $queue = array_keys($lines);
        $tries = [];
        while ($queue !== []) {
            $round = array_splice($queue, 0, $this->concurrency);
            $answers = $this->context->run('speech', null, fn (): array => Http::pool(
                fn (Pool $pool): array => array_map(
                    fn (int $i): mixed => $this->client($pool->as((string) $i))->post(
                        rtrim($this->baseUrl, '/').'/v1/text-to-speech/'.rawurlencode($lines[$i]->voice->voice).'?output_format='.self::OUTPUT_FORMAT,
                        $this->bodyOf($lines[$i]),
                    ),
                    $round,
                ),
                count($round),
            ));

            $again = [];
            $account = null;
            $wait = null;
            foreach ($round as $i) {
                $answer = $answers[(string) $i] ?? null;
                if (! $answer instanceof Response) {
                    $again[] = $i;
                    $wait = TransientSpeechError::network(self::PROVIDER, $answer instanceof Throwable ? $answer->getMessage() : 'no answer');

                    continue;
                }
                $this->learnConcurrency($answer);
                if ($answer->successful() && $answer->body() !== '') {
                    $spoken($i, $this->lineOf($answer, $lines[$i]));

                    continue;
                }
                $failure = $answer->successful() ? new RuntimeException('ElevenLabs answered no audio') : self::failure($answer);
                if ($failure instanceof SpeechAccountError) {
                    $account ??= $failure;
                } elseif ($failure instanceof TransientSpeechError) {
                    $again[] = $i;
                    $wait = $failure;
                } else {
                    Log::warning('elevenlabs line refused; it stays on the phone voice', ['status' => $answer->status(), 'characters' => SpeechCost::charactersOf($lines[$i]->text), 'error' => $failure->getMessage()]);
                }
            }
            if ($account !== null) {
                throw $account;
            }
            if ($again === [] || $wait === null) {
                continue;
            }
            $attempt = 0;
            foreach ($again as $i) {
                $tries[$i] = ($tries[$i] ?? 0) + 1;
                $attempt = max($attempt, $tries[$i]);
            }
            if ($attempt > self::RETRIES) {
                throw $wait;
            }
            $this->pause($attempt - 1, $wait);
            $queue = [...$again, ...$queue];
        }
    }

    public function creditsFor(array $lines): int
    {
        return array_sum(array_map(
            fn (SpeechLine $line): int => $this->creditsOf(SpeechCost::charactersOf($line->text), $line->voice->model),
            $lines,
        ));
    }

    public function balance(): ?SpeechBalance
    {
        try {
            $response = $this->context->run('speech', null, fn (): Response => $this->client(Http::withHeaders([]))
                ->timeout(min(15, $this->timeout))
                ->get(rtrim($this->baseUrl, '/').'/v1/user/subscription'));
        } catch (ConnectionException) {
            return null;
        }
        $used = $response->json('character_count');
        $limit = $response->json('character_limit');
        $reset = $response->json('next_character_count_reset_unix');
        if (! $response->successful() || ! is_int($used) || ! is_int($limit) || $limit <= 0) {
            return null;
        }

        return new SpeechBalance($used, $limit, is_int($reset) ? $reset : null);
    }

    /**
     * What one line asks for: its text, its voice's model and stability — and its language when the line names one and
     * the switch lets it go (наряд LANG-1, п. 9). Nothing else: the vendor's defaults for the rest (TTS-2).
     *
     * @return array<string, mixed>
     */
    private function bodyOf(SpeechLine $line): array
    {
        $body = [
            'text' => trim($line->text),
            'model_id' => $line->voice->model,
            'voice_settings' => ['stability' => $line->voice->stability],
        ];
        if ($this->sendsLanguageCode && $line->languageCode !== null) {
            $body['language_code'] = $line->languageCode;
        }

        return $body;
    }

    private function client(PendingRequest $request): PendingRequest
    {
        return $request->withHeaders(['xi-api-key' => $this->apiKey])->timeout($this->timeout);
    }

    private function lineOf(Response $response, SpeechLine $line): SpokenLine
    {
        $bytes = $response->body();
        $characters = SpeechCost::charactersOf($line->text);
        $header = $response->header('character-cost');
        // No header: the credits the model's rate says those characters are worth at this account's credit price.
        $credits = ctype_digit($header) ? (int) $header : $this->creditsOf($characters, $line->voice->model);
        $id = $response->header('request-id');

        return new SpokenLine(
            bytes: $bytes,
            format: 'mp3',
            // Длительность вендор не называет; mp3 постоянного битрейта, поэтому вес файла отвечает честно и бесплатно.
            durationMs: SpeechCost::mp3DurationMs(strlen($bytes)),
            characters: $characters,
            credits: $credits,
            costUsd: SpeechCost::ofCredits($credits, $this->usdPerThousandCredits),
            requestId: $id === '' ? null : mb_substr($id, 0, 64),
        );
    }

    /**
     * The credits a text's characters are worth: the model's rate at this account's credit price, rounded up per line as
     * the vendor rounds (live 15.09 on Starter: 31 characters on v3 Conversational — 8 credits). A model the rate table
     * does not know is taken at a credit a character, the dearest the vendor has charged — an estimate that errs high.
     */
    private function creditsOf(int $characters, string $model): int
    {
        $rate = SpeechCost::perThousandCharacters($model);
        if ($rate === null) {
            return $characters;
        }

        // Rounded first: 12 × 0.05 / 0.20 is 3.0000000000000004 in floating point, and a cap must not pay for the dust.
        return (int) ceil(round($characters * $rate / max(0.000001, $this->usdPerThousandCredits), 6));
    }

    /** The refusal a non-2xx answer is: the account's, a window to wait for, or a text the vendor will not read. */
    private static function failure(Response $response): RuntimeException
    {
        $status = $response->status();
        $detail = $response->json('detail');
        // `code` is the new field and `status` the legacy one; a generic code («bad_request», «unauthorized») names
        // less than the status beside it («free_users_not_allowed», «missing_permissions»).
        $named = is_array($detail) ? array_values(array_filter(
            [(string) ($detail['code'] ?? ''), (string) ($detail['status'] ?? '')],
            static fn (string $c): bool => $c !== '',
        )) : [];
        $specific = array_values(array_diff($named, ['bad_request', 'unauthorized', 'forbidden', 'not_found', 'invalid_argument']));
        $code = $specific[0] ?? $named[0] ?? 'unknown';
        $message = is_array($detail) && is_string($detail['message'] ?? null) ? $detail['message'] : mb_substr($response->body(), 0, 300);

        return match (true) {
            $status === 429 => TransientSpeechError::rateLimited(self::PROVIDER, self::retryAfter($response), $code),
            $status >= 500 => TransientSpeechError::upstream(self::PROVIDER, $status),
            in_array($status, [401, 402, 403], true) || array_intersect($named, self::ACCOUNT_CODES) !== []
                => SpeechAccountError::refused(self::PROVIDER, $status, $code, $message),
            default => new RuntimeException("ElevenLabs refused the text: {$status} {$code} {$message}"),
        };
    }

    /** A pause that grows with the attempt, never shorter than what the vendor asked for, never past the process's own patience. */
    private function pause(int $attempt, TransientSpeechError $failure): void
    {
        $asked = ($failure->retryAfterSeconds ?? 0) * 1000;
        if ($asked > self::LONGEST_PAUSE_MS) {
            throw $failure;
        }
        $ms = min(self::LONGEST_PAUSE_MS, max($asked, $this->backoffMs * (2 ** $attempt)));
        if ($ms > 0) {
            usleep(($ms + random_int(0, (int) ($ms / 10))) * 1000);
        }
    }

    private function learnConcurrency(Response $response): void
    {
        $max = $response->header('maximum-concurrent-requests');
        if (ctype_digit($max) && (int) $max > 0) {
            $this->concurrency = (int) $max;
        }
    }

    private static function retryAfter(Response $response): ?int
    {
        $after = $response->header('Retry-After');

        return ctype_digit($after) ? (int) $after : null;
    }
}
