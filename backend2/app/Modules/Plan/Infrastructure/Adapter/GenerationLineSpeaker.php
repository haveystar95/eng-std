<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The language pack's premium voice for the partner's lines (`generation.speech.*`). Switched off,
 * or a language with no voice, means null — the client uses the system voice, as it does for
 * words, phrases and the learner's own lines. A vendor refusal is a null too (the line stays
 * unspoken); a transient error propagates so the job retries.
 */
final readonly class GenerationLineSpeaker implements LineSpeaker
{
    public function __construct(
        private SpeechSynthesizerPort $synthesizer,
        private VoiceCatalog $voices,
        private bool $enabled,
    ) {}

    public function speak(string $text, string $lang): ?SpokenAudio
    {
        $voice = $this->enabled ? $this->voices->forLanguage($lang) : null;
        if ($voice === null) {
            return null;
        }

        try {
            $spoken = $this->synthesizer->speak($text, $lang, $voice);
        } catch (TransientSpeechError $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('plan line not spoken', ['lang' => $lang, 'error' => $e->getMessage()]);

            return null;
        }

        return new SpokenAudio(
            bytes: $spoken->bytes,
            format: $spoken->format,
            voiceKey: $voice->key().':'.$voice->variant(),
            durationMs: $spoken->durationMs,
            costUsd: $spoken->costUsd,
        );
    }

    public function voiceKeyFor(string $lang): ?string
    {
        $voice = $this->enabled ? $this->voices->forLanguage($lang) : null;

        return $voice === null ? null : $voice->key().':'.$voice->variant();
    }
}
