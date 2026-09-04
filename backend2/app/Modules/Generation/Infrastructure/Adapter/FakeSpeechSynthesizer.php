<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Shared\Domain\ValueObject\LineVoice;
use RuntimeException;

/**
 * Детерминированная озвучка без сети — `SPEECH_DRIVER=fake` и тесты. Байты производные от текста и
 * голоса, поэтому «тот же файл на ту же реплику» проверяемо, а «другой голос — другой файл» видно.
 *
 * Режимы: `ok` → звук; `refused` → невозвратный отказ; `rate_limited` / `transient_error` →
 * {@see TransientSpeechError}, то есть ветка ретрая.
 */
final class FakeSpeechSynthesizer implements SpeechSynthesizerPort
{
    public const OK = 'ok';
    public const REFUSED = 'refused';
    public const RATE_LIMITED = 'rate_limited';
    public const TRANSIENT_ERROR = 'transient_error';

    /** How many lines this synthesizer was asked for — the assertion behind «второй раз не покупается». */
    public int $calls = 0;

    public function __construct(private readonly string $mode = self::OK) {}

    public function speak(string $text, string $lang, LineVoice $voice): SpokenLine
    {
        $this->calls++;
        $line = trim($text);

        return match ($this->mode) {
            self::REFUSED => throw new RuntimeException('fake speech refused'),
            self::RATE_LIMITED => throw TransientSpeechError::rateLimited('fake', 1),
            self::TRANSIENT_ERROR => throw TransientSpeechError::upstream('fake', 503),
            default => $line === ''
                ? throw new RuntimeException('nothing to speak')
                : new SpokenLine(
                    bytes: 'FAKEMP3:' . md5($voice->key() . '|' . $voice->variant() . '|' . $lang . '|' . $line),
                    format: 'mp3',
                    durationMs: max(200, mb_strlen($line) * 70),
                    costUsd: '0.000000',
                ),
        };
    }
}
