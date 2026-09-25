<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\ElevenLabsSpeechSynthesizer;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\LineVoice;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use App\Modules\Shared\Domain\ValueObject\VoiceRole;

/**
 * ONE VOICE IN THE PRODUCT (наряд TTS-2): the server speaks with ElevenLabs and with nothing else, and the voice it
 * replaced is gone without a trace — its adapter, its config, its daily-quota arithmetic, its tests, its words in the
 * docs.
 */

/**
 * The previous voice vendor by the names only its voice ever had (its content model and its live dialog stay), and the
 * pipeline that served it and is gone with it: the batches packed for its daily quota, the sound cut into lines by its
 * pauses and the tool that listened to the cut (TTS-2, architect: «нарезка и verify_cut не нужны, снести»).
 */
const RETIRED_VOICE = '/gemini[\w.-]*tts|tts-preview|GeminiSpeech|gemini speech|gemini tts|multiSpeakerVoiceConfig|prebuiltVoiceConfig'
    .'|speechConfig|QuotaFailure|retryDelay|secondsToPacificMidnight|America\/Los_Angeles|midnight Pacific|полуноч\w* Pacific'
    .'|полночь Pacific|\bAoede\b|\bPuck\b|OpenAiSpeechSynthesizer|SpeechEncoder|speakScript|SpeechScript|SpeechNotCut|PcmTurnCutter'
    .'|verify_cut|cut_check|synth_dialogue|VoiceBackfillQueue|BuyVoicePacket|VoicePacket|VoiceBatch/iu';

/** @return list<string> every text file under these paths of the project */
function oneVoiceFiles(array $dirs): array
{
    $files = [];
    foreach ($dirs as $dir) {
        $path = base_path($dir);
        if (is_file($path)) {
            $files[] = $path;

            continue;
        }
        $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($walk as $file) {
            if ($file->isFile() && preg_match('/\.(php|md|ya?ml|json|txt|py|neon|xml|sh|html|js|csv)$/', $file->getFilename()) === 1
                && ! str_contains($file->getPathname(), '/bootstrap/cache/')) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

/** @return list<string> `path:line: text` of every line matching the pattern */
function oneVoiceFind(string $pattern, array $files): array
{
    $found = [];
    foreach ($files as $path) {
        if ($path === __FILE__) {
            continue;
        }
        foreach (file($path) ?: [] as $number => $line) {
            if (preg_match($pattern, $line) === 1) {
                $found[] = substr($path, strlen(base_path()) + 1).':'.($number + 1).': '.trim(mb_substr($line, 0, 120));
            }
        }
    }

    return $found;
}

// Canon (TTS-2): «Gemini TTS: код, конфиг, очередь, тесты, разбор 429 по квоте, „ждать позднее из двух окон“ — снести без
// остатка (grep 0)». Catches the voice that stayed in a comment, a config default, a test double, a report or the image.
it('has no trace of the retired voice vendor anywhere: code, config, tests, docs, contract, the image', function () {
    $files = oneVoiceFiles(['app', 'bootstrap', 'config', 'database', 'docs', 'openapi', 'routes', 'tests', 'Dockerfile', '.env.example', 'composer.json', 'phpunit.xml']);

    expect(oneVoiceFind(RETIRED_VOICE, $files))->toBe([]);
});

// Canon (TTS-2, architect's clarification): «Text to Dialogue не использовать: каждая реплика — отдельный вызов». Catches the
// dialogue endpoint kept in the code «just in case».
it('never asks for a dialogue in one call: no dialogue endpoint anywhere in the code', function () {
    expect(oneVoiceFind('/text-to-dialogue|with-timestamps|voice_segments/i', oneVoiceFiles(['app', 'config', 'routes', 'database'])))->toBe([]);
});

// Canon (TTS-2): «один интерфейс синтеза с одной реализацией — ElevenLabs». Catches a second vendor kept «just in case»
// behind a driver switch.
it('builds one synthesizer — ElevenLabs — and the double only when the driver names it', function () {
    $implementations = [];
    $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));
    foreach ($walk as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php') && preg_match('/implements\s+SpeechSynthesizerPort\b/', (string) file_get_contents($file->getPathname())) === 1) {
            $implementations[] = $file->getBasename('.php');
        }
    }
    sort($implementations);

    config(['generation.speech.driver' => 'fake']);
    $fake = app(SpeechSynthesizerPort::class);
    allowLiveAdapters();
    config(['generation.speech.driver' => 'elevenlabs']);

    expect($implementations)->toBe(['ElevenLabsSpeechSynthesizer', 'FakeSpeechSynthesizer'])
        ->and($fake)->toBeInstanceOf(FakeSpeechSynthesizer::class)
        ->and(app(SpeechSynthesizerPort::class))->toBeInstanceOf(ElevenLabsSpeechSynthesizer::class);
});

// Canon (TTS-2, доработка): «в сцене голоса ролей всегда разные» — whatever gender each role has, the partner never speaks
// in the learner's voice. Catches one woman's voice shared by both roles (the pack before this fix) or one man's.
it('gives the two roles of a scene different voices, whatever gender each role has', function () {
    $catalog = new VoiceCatalog((array) config('generation.speech.voices'));
    $pairs = [];
    foreach ([VoiceGender::Female, VoiceGender::Male] as $partner) {
        foreach ([VoiceGender::Female, VoiceGender::Male] as $learner) {
            $pairs["partner {$partner->value} · learner {$learner->value}"] = [
                $catalog->forLanguage('en', VoiceRole::Partner, $partner)?->voice,
                $catalog->forLanguage('en', VoiceRole::Learner, $learner)?->voice,
            ];
        }
    }

    foreach ($pairs as $scene => [$partnerVoice, $learnerVoice]) {
        expect($partnerVoice)->not->toBeNull($scene)
            ->and($learnerVoice)->not->toBeNull($scene)
            ->and($partnerVoice)->not->toBe($learnerVoice, $scene);
    }
});

it('voices every language pack with ElevenLabs on eleven_v3_conversational, a man partner and a man learner apart', function () {
    $voices = [];
    foreach ((array) config('generation.speech.voices') as $lang => $roles) {
        foreach ((array) $roles as $role => $genders) {
            foreach ((array) $genders as $gender => $voice) {
                $voices["{$lang}.{$role}.{$gender}"] = LineVoice::fromArray((array) $voice);
            }
        }
    }

    // Two partner voices a gender since наряд FIX-4c §1 (scenes of one gender take turns) — six voices, every one apart.
    expect(array_keys($voices))->toBe(['en.partner.female', 'en.partner.female_2', 'en.partner.male', 'en.partner.male_2', 'en.learner.female', 'en.learner.male'])
        ->and(array_unique(array_map(static fn (LineVoice $v): string => $v->provider, $voices)))->toBe(['en.partner.female' => 'elevenlabs'])
        ->and(array_unique(array_map(static fn (LineVoice $v): string => $v->model, $voices)))->toBe(['en.partner.female' => 'eleven_v3_conversational'])
        ->and(array_unique(array_map(static fn (LineVoice $v): string => $v->voice, $voices)))->toHaveCount(6)
        ->and($voices['en.partner.female']->stability)->toBe(0.5);
});
