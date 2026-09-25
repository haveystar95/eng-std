<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\ElevenLabsSpeechSynthesizer;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Shared\Domain\Service\LanguageRoles;
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

/**
 * The plan's targets (наряд LANG-1; DECISIONS п. 145) — the one list of them in code, which the config's literal list of
 * voiced languages must equal.
 *
 * @return list<string>
 */
function oneVoicePlanTargets(): array
{
    return LanguageRoles::planTargets();
}

// Canon (TTS-2, доработка): «в сцене голоса ролей всегда разные» — whatever gender each role has, the partner never speaks
// in the learner's voice — in every language a plan is taught in (наряд LANG-1, п. 9). Catches one woman's voice shared by
// both roles (the pack before this fix) or one man's, and a new language whose rows were copied with a role swapped.
it('gives the two roles of a scene different voices, whatever gender each role has, in every plan target', function () {
    $catalog = new VoiceCatalog((array) config('generation.speech.voices'));
    $pairs = [];
    foreach (oneVoicePlanTargets() as $lang) {
        foreach ([VoiceGender::Female, VoiceGender::Male] as $partner) {
            foreach ([VoiceGender::Female, VoiceGender::Male] as $learner) {
                $pairs["{$lang}: partner {$partner->value} · learner {$learner->value}"] = [
                    $catalog->forLanguage($lang, VoiceRole::Partner, $partner)?->voice,
                    $catalog->forLanguage($lang, VoiceRole::Learner, $learner)?->voice,
                ];
            }
        }
    }

    foreach ($pairs as $scene => [$partnerVoice, $learnerVoice]) {
        expect($partnerVoice)->not->toBeNull($scene)
            ->and($learnerVoice)->not->toBeNull($scene)
            ->and($partnerVoice)->not->toBe($learnerVoice, $scene);
    }
});

/** Two partner voices a gender since наряд FIX-4c §1 (scenes of one gender take turns) — six slots, every one apart. */
const ONE_VOICE_SLOTS = ['partner.female', 'partner.female_2', 'partner.male', 'partner.male_2', 'learner.female', 'learner.male'];

/**
 * The pack's rows, `lang.role.slot` → the row.
 *
 * @param  array<array-key, mixed>  $pack  `generation.speech.voices` as the config holds it
 * @return array<string, LineVoice>
 */
function oneVoiceRows(array $pack): array
{
    $voices = [];
    foreach ($pack as $lang => $roles) {
        foreach ((array) $roles as $role => $genders) {
            foreach ((array) $genders as $gender => $voice) {
                $voices["{$lang}.{$role}.{$gender}"] = LineVoice::fromArray((array) $voice);
            }
        }
    }

    return $voices;
}

/**
 * Has the deployment's `.env` named this language's slot a voice of its own — `SPEECH_VOICE_<LANG>_<ROLE>_<SLOT>`, not
 * empty (`config/generation.php`: an empty line is no line)? Such a slot is Den's choice, supported by the config, and
 * no longer the English one.
 */
function oneVoiceOverridden(string $lang, string $slot): bool
{
    $value = env('SPEECH_VOICE_'.strtoupper($lang).'_'.strtoupper(str_replace('.', '_', $slot)));

    return is_string($value) && trim($value) !== '';
}

/**
 * What is wrong with a pack's rows (наряд LANG-1, п. 9; DECISIONS пп. 318, 414) — nothing when they are right: every
 * plan target and no other, each with the six slots; one vendor, one model, one stability; six different ids in English
 * and six different ids within every language; and every slot `.env` has not named for its language on the English
 * row's id — a language nobody chose a voice for speaks the approved six, never ids of its own nobody has heard.
 *
 * @param  array<array-key, mixed>  $pack
 * @return list<string>
 */
function oneVoiceProblems(array $pack): array
{
    $voices = oneVoiceRows($pack);
    $expected = [];
    foreach (oneVoicePlanTargets() as $lang) {
        foreach (ONE_VOICE_SLOTS as $slot) {
            $expected[] = "{$lang}.{$slot}";
        }
    }
    $problems = [];
    if (array_keys($pack) !== oneVoicePlanTargets()) {
        $problems[] = 'languages: '.implode(', ', array_keys($pack));
    }
    if (array_keys($voices) !== $expected) {
        $problems[] = 'slots: '.implode(', ', array_diff($expected, array_keys($voices)) ?: array_diff(array_keys($voices), $expected));
    }
    foreach (['provider' => ['elevenlabs'], 'model' => ['eleven_v3_conversational'], 'stability' => [0.5]] as $field => $one) {
        $values = array_values(array_unique(array_map(static fn (LineVoice $v): string|float => $v->{$field}, $voices)));
        if ($values !== $one) {
            $problems[] = "{$field}: ".implode(', ', $values);
        }
    }
    foreach (oneVoicePlanTargets() as $lang) {
        // Six apart within each language: six ids across all rows would still let one language say two of its slots in
        // one voice (a slot copied over its neighbour) — the scene's two women, or the partner and the learner, as one.
        $own = array_filter($voices, static fn (string $key): bool => str_starts_with($key, "{$lang}."), ARRAY_FILTER_USE_KEY);
        if (count(array_unique(array_map(static fn (LineVoice $v): string => $v->voice, $own))) !== count(ONE_VOICE_SLOTS)) {
            $problems[] = "{$lang}: not six different voices";
        }
        foreach (ONE_VOICE_SLOTS as $slot) {
            if ($lang === 'en' || oneVoiceOverridden($lang, $slot) || ! isset($voices["{$lang}.{$slot}"], $voices["en.{$slot}"])) {
                continue;
            }
            if ($voices["{$lang}.{$slot}"]->voice !== $voices["en.{$slot}"]->voice) {
                $problems[] = "{$lang}.{$slot}: «{$voices["{$lang}.{$slot}"]->voice}», not the English «{$voices["en.{$slot}"]->voice}»";
            }
        }
    }

    return $problems;
}

// Canon (наряд LANG-1, п. 9; DECISIONS пп. 318, 414): «шесть строк голосов у каждой цели плана — en, pl, ro, es, it, de, fr;
// по умолчанию — утверждённые Деном голоса». CATCHES a plan target left without rows (its lines on the phone's voice), a
// target the plan does not teach given rows, a slot missing in one language, a second vendor or model slipped into a new
// language's rows, and a new language that brought voices of its own nobody has heard (the ids stay the six approved) —
// and holds whatever `.env` says: a language Den named voices for is his choice, not a defect.
it('voices every plan target with the six ElevenLabs slots on eleven_v3_conversational, the English ids unless .env names others', function () {
    expect(oneVoiceProblems((array) config('generation.speech.voices')))->toBe([]);
});

// The check above is only worth its row if it holds on the day `.env` names a language its own voice — and still catches
// a language whose rows moved off the English ids WITHOUT `.env` (наряд LANG-1, валидатор: the old «six ids across all 42
// rows» failed the first time Den chose a German voice). The config is built again under the override, as the app would
// boot with it; the line is taken back whatever happens.
it('holds a language .env names its own voice, and still catches a language that moved off the English ids unasked', function () {
    // The environment is read first from `$_SERVER`; whatever the deployment's own `.env` put there is given back.
    $name = 'SPEECH_VOICE_DE_PARTNER_FEMALE';
    $before = $_SERVER[$name] ?? null;
    $_SERVER[$name] = 'de-own-woman';
    try {
        $overridden = (array) ((require base_path('config/generation.php'))['speech']['voices'] ?? []);
        expect($overridden['de']['partner']['female']['voice'] ?? null)->toBe('de-own-woman')
            ->and(oneVoiceProblems($overridden))->toBe([]);
    } finally {
        if ($before === null) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $before;
        }
    }

    // A slot `.env` does not name, moved off the English id in the pack itself.
    $free = null;
    foreach (oneVoicePlanTargets() as $lang) {
        foreach (ONE_VOICE_SLOTS as $slot) {
            if ($lang !== 'en' && ! oneVoiceOverridden($lang, $slot)) {
                $free ??= [$lang, ...explode('.', $slot)];
            }
        }
    }
    expect($free)->not->toBeNull();
    [$lang, $role, $slot] = $free;
    $unasked = (array) config('generation.speech.voices');
    $unasked[$lang][$role][$slot]['voice'] = 'nobody-heard';

    expect(oneVoiceProblems($unasked))->toBe(["{$lang}.{$role}.{$slot}: «nobody-heard», not the English «{$unasked['en'][$role][$slot]['voice']}»"]);
});
