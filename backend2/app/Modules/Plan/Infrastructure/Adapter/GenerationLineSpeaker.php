<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\SpeechLine;
use App\Modules\Generation\Application\Dto\SpokenLine;
use App\Modules\Generation\Application\Port\SpeechAccountError;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Dto\VoiceBalance;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\LineVoice;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use App\Modules\Shared\Domain\ValueObject\VoiceRole;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The language pack's voices for what a scene says (`generation.speech.voices`, TTS-2): every line on a call of its
 * own, in the voice the pack gives its speaker's role and gender.
 *
 * Switched off, or a language without the voice a line needs, means that line is not said — the client uses the phone's
 * voice. A refusal to read a text is the synthesizer's to skip; a transient error and a refusal of the vendor account
 * propagate.
 *
 * Every line is asked for in the language it is in — `$lang`, the plan's target, lower-cased (наряд LANG-1, п. 9): one set
 * of voices speaks every target, and the vendor is told which one instead of guessing it from the letters. The language is
 * the line's, not the voice's: the key a file is stored and found under ({@see self::key()}) does not carry it, so no file
 * bought before LANG-1 goes unread (DECISIONS п. 248).
 */
final readonly class GenerationLineSpeaker implements LineSpeaker
{
    public function __construct(
        private SpeechSynthesizerPort $synthesizer,
        private VoiceCatalog $voices,
        private bool $enabled,
    ) {}

    public function sayEach(string $lang, array $lines, callable $keep): void
    {
        if (! $this->enabled) {
            return;
        }
        $said = [];
        $asked = [];
        foreach ($lines as $line) {
            $voice = $this->voiceOf($lang, $line);
            if ($voice !== null) {
                $said[] = $line;
                $asked[] = new SpeechLine($line->text, $voice, self::languageCode($lang));
            }
        }
        if ($asked === []) {
            return;
        }

        try {
            $this->synthesizer->speakLines($asked, static function (int $i, SpokenLine $audio) use ($said, $asked, $keep): void {
                $keep($said[$i]->ref, self::audio($audio, $asked[$i]->voice));
            });
        } catch (TransientSpeechError|SpeechAccountError $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('plan lines not spoken', ['lang' => $lang, 'lines' => count($asked), 'error' => $e->getMessage()]);
        }
    }

    public function creditsFor(string $lang, array $lines): int
    {
        if (! $this->enabled) {
            return 0;
        }
        $asked = [];
        foreach ($lines as $line) {
            $voice = $this->voiceOf($lang, $line);
            if ($voice !== null) {
                $asked[] = new SpeechLine($line->text, $voice, self::languageCode($lang));
            }
        }

        return $asked === [] ? 0 : $this->synthesizer->creditsFor($asked);
    }

    public function voiceKeyFor(string $lang, Speaker $speaker, VoiceGender $gender, ?string $voice = null): ?string
    {
        $found = $this->enabled ? $this->voices->forLanguage($lang, VoiceRole::from($speaker->value), $gender, $voice) : null;

        return $found === null ? null : self::key($found);
    }

    public function balance(): ?VoiceBalance
    {
        $balance = $this->enabled ? $this->synthesizer->balance() : null;

        return $balance === null ? null : new VoiceBalance(
            used: $balance->used,
            limit: $balance->limit,
            source: VoiceBalance::VENDOR,
            resetsAt: $balance->resetsAtUnix === null ? null : new DateTimeImmutable('@'.$balance->resetsAtUnix),
        );
    }

    private function voiceOf(string $lang, LineToSay $line): ?LineVoice
    {
        return $this->voices->forLanguage($lang, VoiceRole::from($line->speaker->value), $line->gender, $line->voice);
    }

    /** The language a plan's line is in, as the vendor names it: the target, lower-case; none when there is none. */
    private static function languageCode(string $lang): ?string
    {
        $code = strtolower(trim($lang));

        return $code === '' ? null : $code;
    }

    private static function key(LineVoice $voice): string
    {
        return $voice->key().':'.$voice->variant();
    }

    private static function audio(SpokenLine $audio, LineVoice $voice): SpokenAudio
    {
        return new SpokenAudio(
            bytes: $audio->bytes,
            format: $audio->format,
            voiceKey: self::key($voice),
            durationMs: $audio->durationMs,
            characters: $audio->characters,
            credits: $audio->credits,
            costUsd: $audio->costUsd,
            requestId: $audio->requestId,
        );
    }
}
