<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\SpeechEncoder;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * `lame` из образа (см. `Dockerfile`): сырой PCM 24 кГц моно → mp3.
 *
 * Битрейт — 64 кбит/с моно, выбран на слух по образцам Ч.0.3: речь на 24 кГц на нём неотличима от
 * исходного PCM, а план весит ~0.6 МБ вместо 3.5 МБ WAV-ом и вдвое меньше, чем 128 кбит/с у OpenAI.
 *
 * Через ФАЙЛЫ, а не через пайпы: `lame` на stdin не умеет `--decode`-независимый seek и на сырой
 * поток без длины ведёт себя по-разному между версиями. Файл во временном каталоге стоит миллисекунду
 * и снимает целый класс «на моей машине работало».
 *
 * Никогда не бросает. Нет бинарника, кривой выход, пустой результат — `null`, и вызывающий кладёт
 * WAV. Озвучка, падающая из-за кодировщика, была бы хуже озвучки, которая весит втрое больше.
 */
final class LameSpeechEncoder implements SpeechEncoder
{
    public function __construct(
        private readonly int $bitrateKbps = 64,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function pcmToMp3(string $pcm, int $sampleRate): ?string
    {
        if ($pcm === '') {
            return null;
        }

        $in = tempnam(sys_get_temp_dir(), 'tts_pcm_');
        $out = tempnam(sys_get_temp_dir(), 'tts_mp3_');
        if ($in === false || $out === false) {
            return null;
        }

        try {
            file_put_contents($in, $pcm);

            $process = new Process([
                'lame',
                '--quiet',
                '-r',                                   // сырой PCM на входе
                '-s', (string) ($sampleRate / 1000),    // частота дискретизации, кГц
                '--bitwidth', '16',
                '--signed',
                '--little-endian',
                '-m', 'm',                              // моно
                '-b', (string) $this->bitrateKbps,
                $in,
                $out,
            ]);
            $process->setTimeout($this->timeoutSeconds);
            $process->mustRun();

            $mp3 = @file_get_contents($out);

            return is_string($mp3) && $mp3 !== '' ? $mp3 : null;
        } catch (ProcessFailedException $e) {
            Log::warning('lame could not encode a line; keeping WAV', ['error' => $e->getMessage()]);

            return null;
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }
}
