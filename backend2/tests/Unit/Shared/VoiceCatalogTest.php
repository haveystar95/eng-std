<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use App\Modules\Shared\Domain\ValueObject\VoiceRole;

/**
 * THE VOICES OF EVERY PLAN TARGET, AS THE DEPLOYMENT'S CONFIG GIVES THEM (наряд LANG-1, п. 9; DECISIONS пп. 248, 318, 414).
 *
 * `config/generation.php` is read by path — the file production reads, no application booted — with lines of `.env` set
 * or taken away for the length of one read (English's lines left as the process has them where the test says so). Every line touched is
 * put back as it was (the process's `$_ENV`, `$_SERVER` and `getenv()` alike), so the tests after these read what they
 * would have read without them.
 */

/** The six slots of a language's rows: role, the pack's key of the slot, the `.env` name of the slot. */
const VC_SLOTS = [
    ['partner', 'female', 'PARTNER_FEMALE'],
    ['partner', 'female_2', 'PARTNER_FEMALE_2'],
    ['partner', 'male', 'PARTNER_MALE'],
    ['partner', 'male_2', 'PARTNER_MALE_2'],
    ['learner', 'female', 'LEARNER_FEMALE'],
    ['learner', 'male', 'LEARNER_MALE'],
];

/** The ids Den approved (15.09, 25.09) — what `en` said before LANG-1 with no line of `.env`. */
const VC_APPROVED = [
    'PARTNER_FEMALE' => '4NejU5DwQjevnR6mh3mb',
    'PARTNER_FEMALE_2' => 'QtY3JBOUKEB5xzrRfOKc',
    'PARTNER_MALE' => 'EnjklPXGBMNldCJ7jqkE',
    'PARTNER_MALE_2' => 'AaOhDHYJ1XLZk74lXhdE',
    'LEARNER_FEMALE' => 'Nhs7eitvQWFTQBsf0yiT',
    'LEARNER_MALE' => 'TWutjvRaJqAX89preB4e',
];

const VC_TARGETS = ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr'];

/**
 * `config/generation.php` read afresh with these lines of the environment — a string sets the line, null takes it away —
 * and every line put back afterwards.
 *
 * @param  array<string, string|null>  $lines
 * @return array<string, mixed> the file's `speech` section
 */
function vcSpeech(array $lines = []): array
{
    $before = [];
    foreach ($lines as $name => $value) {
        $before[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
        vcPut($name, $value, $value, $value);
    }

    try {
        /** @var array{speech: array<string, mixed>} $config */
        $config = require dirname(__DIR__, 3).'/config/generation.php';

        return $config['speech'];
    } finally {
        foreach ($before as $name => [$env, $server, $getenv]) {
            vcPut($name, $env, $server, $getenv === false ? null : $getenv);
        }
    }
}

function vcPut(string $name, mixed $env, mixed $server, ?string $getenv): void
{
    if ($env === null) {
        unset($_ENV[$name]);
    } else {
        $_ENV[$name] = $env;
    }
    if ($server === null) {
        unset($_SERVER[$name]);
    } else {
        $_SERVER[$name] = $server;
    }
    putenv($getenv === null ? $name : "{$name}={$getenv}");
}

/**
 * Every `SPEECH_VOICE_*` line of every plan target, and the switch of the line's language, taken away — the config as a
 * deployment without any of them reads it.
 *
 * @return array<string, null>
 */
function vcNoVoiceLines(): array
{
    $lines = ['SPEECH_LANGUAGE_CODE' => null, 'SPEECH_MODEL' => null, 'SPEECH_STABILITY' => null];
    foreach (VC_TARGETS as $lang) {
        foreach (VC_SLOTS as [, , $name]) {
            $lines['SPEECH_VOICE_'.strtoupper($lang).'_'.$name] = null;
        }
    }

    return $lines;
}

/**
 * Every `SPEECH_VOICE_*` line of the NEW targets taken away, English's left as the process has them — a deployment that
 * has named no new language a voice of its own yet, whatever English's lines say. A serial run reads the tree's `.env`
 * into the process on the first Feature test's boot, and a `SPEECH_VOICE_DE_*` Den adds there later must not turn the
 * order's default into a false failure.
 *
 * @return array<string, null>
 */
function vcNoNewLanguageLines(): array
{
    $lines = [];
    foreach (VC_TARGETS as $lang) {
        if ($lang === 'en') {
            continue;
        }
        foreach (VC_SLOTS as [, , $name]) {
            $lines['SPEECH_VOICE_'.strtoupper($lang).'_'.$name] = null;
        }
    }

    return $lines;
}

/** @param  array<string, mixed>  $speech */
function vcCatalog(array $speech): VoiceCatalog
{
    /** @var array<string, mixed> $voices */
    $voices = $speech['voices'];

    return new VoiceCatalog($voices);
}

function vcVoice(VoiceCatalog $catalog, string $lang, string $role, string $slot): ?string
{
    $gender = VoiceGender::from(str_replace('_2', '', $slot));
    if (str_ends_with($slot, '_2')) {
        return $catalog->partnerVoices($lang, $gender)[1] ?? null;
    }

    return $catalog->forLanguage($lang, VoiceRole::from($role), $gender)?->voice;
}

// Canon (наряд LANG-1, п. 9): «VoiceCatalog для de, partner female === id en» — every new language speaks with the voices
// Den approved until `.env` names it its own. CATCHES a new language left without rows (its lines on the phone's voice), a
// slot of one language pointing at another slot's voice, and a new language's voices under keys of their own — the same
// voice must be the same file address (DECISIONS п. 248).
it('speaks every plan target with the English rows: de partner female is the en id, and so is every slot of every target', function () {
    $catalog = vcCatalog(vcSpeech(vcNoNewLanguageLines()));

    expect($catalog->forLanguage('de', VoiceRole::Partner, VoiceGender::Female)?->voice)
        ->not->toBeNull()
        ->toBe($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Female)?->voice)
        ->and($catalog->forLanguage('de', VoiceRole::Partner, VoiceGender::Female)?->key())
        ->toBe($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Female)?->key());

    foreach (VC_TARGETS as $lang) {
        foreach (VC_SLOTS as [$role, $slot]) {
            expect(vcVoice($catalog, $lang, $role, $slot))->not->toBeNull("{$lang}.{$role}.{$slot}")
                ->toBe(vcVoice($catalog, 'en', $role, $slot), "{$lang}.{$role}.{$slot}");
        }
        expect($catalog->partnerVoices($lang, VoiceGender::Male))->toBe($catalog->partnerVoices('en', VoiceGender::Male));
    }
});

// Canon (наряд LANG-1, п. 9): «для en ничего не меняется». CATCHES an English default moved while the rows were rebuilt
// for seven languages (every file already bought would go unread), and a language switch that is off by default.
it('keeps English exactly as it was with no line of .env: the six approved ids, v3 Conversational, Natural — and names the language', function () {
    $speech = vcSpeech(vcNoVoiceLines());
    $catalog = vcCatalog($speech);

    foreach (VC_SLOTS as [$role, $slot, $name]) {
        foreach (VC_TARGETS as $lang) {
            expect(vcVoice($catalog, $lang, $role, $slot))->toBe(VC_APPROVED[$name], "{$lang}.{$role}.{$slot}");
        }
    }
    expect($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Female)?->key())->toBe('elevenlabs:eleven_v3_conversational:4NejU5DwQjevnR6mh3mb')
        ->and($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Female)?->variant())->toBe('s50')
        ->and($speech['language_code'])->toBeTrue()
        ->and(vcSpeech(['SPEECH_LANGUAGE_CODE' => 'false'])['language_code'])->toBeFalse();
});

// Canon (наряд LANG-1, п. 9): «SPEECH_VOICE_<LANG>_<SLOT> меняет только этот язык». CATCHES an override of one language that
// leaks into the others or into another slot of its own, and an override that is not read at all.
it('lets one line of .env give one language one voice of its own, and changes nothing else', function () {
    $base = vcCatalog(vcSpeech(vcNoVoiceLines()));
    $german = vcCatalog(vcSpeech(['SPEECH_VOICE_DE_PARTNER_FEMALE' => 'de-woman'] + vcNoVoiceLines()));

    expect($german->forLanguage('de', VoiceRole::Partner, VoiceGender::Female)?->voice)->toBe('de-woman')
        ->and($german->partnerVoices('de', VoiceGender::Female))->toBe(['de-woman', VC_APPROVED['PARTNER_FEMALE_2']]);
    foreach (VC_TARGETS as $lang) {
        foreach (VC_SLOTS as [$role, $slot]) {
            if ($lang === 'de' && $role === 'partner' && $slot === 'female') {
                continue;
            }
            expect(vcVoice($german, $lang, $role, $slot))->toBe(vcVoice($base, $lang, $role, $slot), "{$lang}.{$role}.{$slot}");
        }
    }
});

// Canon (наряд LANG-1, п. 9): «SPEECH_VOICE_<LANG>_<SLOT> → SPEECH_VOICE_EN_<SLOT> → утверждённый id». CATCHES a new language
// pinned to the approved id instead of following the English line (a voice changed for English would leave six languages
// in the old one), a language's own line losing to the English one, and an empty line (`…=`) read as «no voice».
it('falls back from a language\'s own line to the English line to the approved id, an empty line being no line', function () {
    $catalog = vcCatalog(vcSpeech([
        'SPEECH_VOICE_EN_PARTNER_MALE' => 'en-man-new',
        'SPEECH_VOICE_IT_PARTNER_MALE' => 'it-man',
        'SPEECH_VOICE_FR_PARTNER_MALE' => '',
        'SPEECH_VOICE_EN_LEARNER_FEMALE' => '  ',
    ] + vcNoVoiceLines()));

    expect($catalog->forLanguage('en', VoiceRole::Partner, VoiceGender::Male)?->voice)->toBe('en-man-new')
        ->and($catalog->forLanguage('de', VoiceRole::Partner, VoiceGender::Male)?->voice)->toBe('en-man-new')
        ->and($catalog->forLanguage('fr', VoiceRole::Partner, VoiceGender::Male)?->voice)->toBe('en-man-new')
        ->and($catalog->forLanguage('it', VoiceRole::Partner, VoiceGender::Male)?->voice)->toBe('it-man')
        ->and($catalog->forLanguage('en', VoiceRole::Learner, VoiceGender::Female)?->voice)->toBe(VC_APPROVED['LEARNER_FEMALE'])
        ->and($catalog->forLanguage('ro', VoiceRole::Learner, VoiceGender::Female)?->voice)->toBe(VC_APPROVED['LEARNER_FEMALE']);
});

// The environment is the process's: what a read set is gone after it, what was there is back.
it('puts every line of the environment back as it was after a read', function () {
    $before = [getenv('SPEECH_VOICE_DE_PARTNER_FEMALE'), $_ENV['SPEECH_VOICE_DE_PARTNER_FEMALE'] ?? null, $_SERVER['SPEECH_VOICE_DE_PARTNER_FEMALE'] ?? null];

    vcSpeech(['SPEECH_VOICE_DE_PARTNER_FEMALE' => 'de-woman']);

    expect([getenv('SPEECH_VOICE_DE_PARTNER_FEMALE'), $_ENV['SPEECH_VOICE_DE_PARTNER_FEMALE'] ?? null, $_SERVER['SPEECH_VOICE_DE_PARTNER_FEMALE'] ?? null])->toBe($before);
});
