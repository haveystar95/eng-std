<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\SpeechBalance;
use App\Modules\Generation\Application\Dto\SpeechLine;
use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Generation\Application\Port\SpeechAccountError;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Shared\Domain\Service\SpeechCost;

/**
 * Детерминированная озвучка без сети — `SPEECH_DRIVER=fake` и тесты; двойник вендора, не второй вендор. Байты производные
 * от текста и голоса, поэтому «тот же файл на ту же строку» проверяемо, а «другой голос — другой файл» видно. Кредиты —
 * символ на четыре, как списывает v3 Conversational на Starter (живьём 15.09), цена — $0.20 за тысячу кредитов.
 *
 * Режимы: `ok` → звук; `refused` → отказ прочитать текст (строка пропускается); `rate_limited` / `transient_error` →
 * {@see TransientSpeechError}, то есть ветка ожидания; `no_credits` → {@see SpeechAccountError} 402.
 */
final class FakeSpeechSynthesizer implements SpeechSynthesizerPort
{
    public const OK = 'ok';
    public const REFUSED = 'refused';
    public const RATE_LIMITED = 'rate_limited';
    public const TRANSIENT_ERROR = 'transient_error';
    public const NO_CREDITS = 'no_credits';

    /** How many vendor calls were made — one per line: the assertion behind «каждая реплика — свой вызов». */
    public int $calls = 0;

    /** @var list<SpeechLine> every line asked for, in the order asked */
    public array $lines = [];

    public function __construct(
        private readonly string $mode = self::OK,
        /** What `balance()` reports; null — the vendor would not say. */
        public ?SpeechBalance $balance = null,
    ) {}

    public function speakLines(array $lines, callable $spoken): void
    {
        foreach ($lines as $i => $line) {
            $this->calls++;
            $this->lines[] = $line;
            match ($this->mode) {
                self::RATE_LIMITED => throw TransientSpeechError::rateLimited('fake', null, 'concurrent_limit_exceeded'),
                self::TRANSIENT_ERROR => throw TransientSpeechError::upstream('fake', 503),
                self::NO_CREDITS => throw SpeechAccountError::refused('fake', 402, 'quota_exceeded', 'fake account has no credits'),
                default => null,
            };
            if ($this->mode === self::REFUSED) {
                continue;
            }
            $text = trim($line->text);
            $characters = SpeechCost::charactersOf($text);
            $credits = (int) ceil($characters / 4);
            $spoken($i, new SpokenLine(
                bytes: 'FAKEMP3:'.md5($line->voice->key().'|'.$line->voice->variant().'|'.$text),
                format: 'mp3',
                durationMs: max(200, $characters * 70),
                characters: $characters,
                credits: $credits,
                costUsd: SpeechCost::ofCredits($credits, 0.20),
                requestId: 'fake-'.$this->calls,
            ));
        }
    }

    public function creditsFor(array $lines): int
    {
        return array_sum(array_map(static fn (SpeechLine $line): int => (int) ceil(SpeechCost::charactersOf($line->text) / 4), $lines));
    }

    public function balance(): ?SpeechBalance
    {
        return $this->balance;
    }
}
