<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\SpeechScript;
use App\Modules\Generation\Application\Dto\SpeechTurn;
use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Generation\Application\Port\SpeechNotCut;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\LineVoice;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The language pack's two voices for what a scene says (`generation.speech.voices`, DAY-UI-3): one
 * script per call — the dialogue with both voices, a batch with the learner's.
 *
 * Switched off, or a language without both voices, means nothing is said — the client uses the phone's
 * voice. A vendor refusal or a sound that did not cut into its lines is logged and says nothing either
 * (the lines stay owed for the next run); a transient error propagates so the job waits for the
 * vendor's window.
 */
final readonly class GenerationLineSpeaker implements LineSpeaker
{
    public function __construct(
        private SpeechSynthesizerPort $synthesizer,
        private VoiceCatalog $voices,
        private bool $enabled,
    ) {}

    public function say(string $lang, array $lines): array
    {
        if (! $this->enabled || $lines === []) {
            return [];
        }
        /** @var array<string, LineVoice> $voices */
        $voices = [];
        foreach ($lines as $line) {
            $voice = $this->voices->forLanguage($lang, $line->voice);
            if ($voice === null) {
                return [];
            }
            $voices[$line->voice->value] = $voice;
        }

        try {
            $spoken = $this->synthesizer->speakScript(new SpeechScript(
                $lang,
                array_map(static fn (LineToSay $l): SpeechTurn => new SpeechTurn($l->voice->value, $l->text), $lines),
                $voices,
            ));
        } catch (TransientSpeechError $e) {
            throw $e;
        } catch (SpeechNotCut $e) {
            Log::warning('plan lines not cut; they stay on the phone voice', ['lang' => $lang, 'lines' => count($lines), 'error' => $e->getMessage()]);

            return [];
        } catch (Throwable $e) {
            Log::warning('plan lines not spoken', ['lang' => $lang, 'lines' => count($lines), 'error' => $e->getMessage()]);

            return [];
        }

        $out = [];
        foreach ($lines as $i => $line) {
            $audio = $spoken[$i] ?? null;
            if (! $audio instanceof SpokenLine) {
                continue;
            }
            $voice = $voices[$line->voice->value];
            $out[$line->ref] = new SpokenAudio(
                bytes: $audio->bytes,
                format: $audio->format,
                voiceKey: $voice->key().':'.$voice->variant(),
                durationMs: $audio->durationMs,
                costUsd: $audio->costUsd,
            );
        }

        return $out;
    }

    public function voiceKeyFor(string $lang, VoiceGender $voice): ?string
    {
        $found = $this->enabled ? $this->voices->forLanguage($lang, $voice) : null;

        return $found === null ? null : $found->key().':'.$found->variant();
    }
}
