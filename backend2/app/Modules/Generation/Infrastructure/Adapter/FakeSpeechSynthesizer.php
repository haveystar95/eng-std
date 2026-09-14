<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\SpeechScript;
use App\Modules\Generation\Application\Dto\SpeechTurn;
use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Generation\Application\Port\SpeechNotCut;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use RuntimeException;

/**
 * Детерминированная озвучка без сети — `SPEECH_DRIVER=fake` и тесты. Байты производные от текста и
 * голоса, поэтому «тот же файл на ту же строку» проверяемо, а «другой голос — другой файл» видно.
 *
 * Режимы: `ok` → звук; `refused` → невозвратный отказ; `not_cut` → звук не разрезался;
 * `rate_limited` / `daily_limit` / `transient_error` → {@see TransientSpeechError}, то есть ветка ожидания.
 */
final class FakeSpeechSynthesizer implements SpeechSynthesizerPort
{
    public const OK = 'ok';
    public const REFUSED = 'refused';
    public const NOT_CUT = 'not_cut';
    public const RATE_LIMITED = 'rate_limited';
    public const DAILY_LIMIT = 'daily_limit';
    public const TRANSIENT_ERROR = 'transient_error';

    /** How many vendor calls were made — one per script: the assertion behind «диалог = один вызов». */
    public int $calls = 0;

    /** @var list<SpeechScript> */
    public array $scripts = [];

    public function __construct(private readonly string $mode = self::OK) {}

    public function speakScript(SpeechScript $script): array
    {
        $this->calls++;
        $this->scripts[] = $script;

        return match ($this->mode) {
            self::REFUSED => throw new RuntimeException('fake speech refused'),
            self::NOT_CUT => throw SpeechNotCut::because('fake'),
            self::RATE_LIMITED => throw TransientSpeechError::rateLimited('fake', 1),
            self::DAILY_LIMIT => throw TransientSpeechError::rateLimited('fake', 3600, true),
            self::TRANSIENT_ERROR => throw TransientSpeechError::upstream('fake', 503),
            default => array_map(static function (SpeechTurn $turn) use ($script): SpokenLine {
                $voice = $script->voices[$turn->speaker];
                $line = trim($turn->text);

                return new SpokenLine(
                    bytes: 'FAKEMP3:'.md5($voice->key().'|'.$voice->variant().'|'.$script->lang.'|'.$line),
                    format: 'mp3',
                    durationMs: max(200, mb_strlen($line) * 70),
                    costUsd: '0.000000',
                );
            }, $script->turns),
        };
    }
}
