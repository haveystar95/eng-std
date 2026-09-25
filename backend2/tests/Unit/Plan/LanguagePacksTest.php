<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;

/**
 * THE REGISTRY OF LANGUAGE PACKS WIRES THE NEIGHBOURS (наряд LANG-1 §5): every pack it hands out knows what the other packs
 * are — their letters and most frequent words — so the guard of the role's translation, holding the learner's pack only,
 * can tell that language from the others in the same letters. And the deployment's own packs write what the guard and
 * the talk's title read the way those readers can read it.
 */

// Canon (LANG-1 §5). CATCHES a pack handed out without its neighbours, with itself among them, or a code with no pack
// handed out as a `none` that knows nobody.
it('hands every pack the other packs as neighbours, never itself', function () {
    $packs = new LanguagePacks([
        'pl' => ['script_letters' => '/^[\p{Latin}]$/u', 'common_words' => ['Nie', 'się']],
        'EN ' => ['script_letters' => '/^[\p{Latin}]$/u', 'common_words' => ['the', 'to']],
        'ru' => ['script_letters' => '/^[\p{Cyrillic}]$/u'],
        'uk' => ['script_letters' => null, 'common_words' => ['що']],
        'xx' => 'not a pack',
    ]);
    $pl = ['script_letters' => '/^[\p{Latin}]$/u', 'common_words' => ['nie', 'się']];
    $en = ['script_letters' => '/^[\p{Latin}]$/u', 'common_words' => ['the', 'to']];
    $ru = ['script_letters' => '/^[\p{Cyrillic}]$/u', 'common_words' => []];
    $uk = ['script_letters' => null, 'common_words' => ['що']];

    expect($packs->codes())->toBe(['pl', 'en', 'ru', 'uk'])
        ->and($packs->for('pl')->neighbours())->toBe(['en' => $en, 'ru' => $ru, 'uk' => $uk])
        ->and($packs->for('en')->neighbours())->toBe(['pl' => $pl, 'ru' => $ru, 'uk' => $uk])
        ->and($packs->for(' UK ')->neighbours())->toBe(['pl' => $pl, 'en' => $en, 'ru' => $ru])
        ->and($packs->for('de')->neighbours())->toBe(['pl' => $pl, 'en' => $en, 'ru' => $ru, 'uk' => $uk])
        ->and($packs->for('de')->commonWords())->toBe([]);
});

// Canon (LANG-1 §5): wiring the neighbours changes nothing else a pack hands out. CATCHES a registry that builds its
// packs from something other than the config's keys.
it('keeps every pack\'s own keys as the config writes them', function () {
    $packs = new LanguagePacks(['pl' => ['rescue_line' => 'Słucham?', 'common_words' => ['nie']], 'en' => ['rescue_line' => 'Sorry?']]);

    expect($packs->for('pl')->rescueLine())->toBe('Słucham?')
        ->and($packs->for('pl')->commonWords())->toBe(['nie'])
        ->and($packs->for('en')->rescueLine())->toBe('Sorry?');
});

/**
 * Every pack the deployment has, read from `config/lesson/lang/*.php` by path — every file of the directory, so a pack a
 * language executor adds is read here the day it is written — and read here as written, apart from {@see lessonPacks()},
 * so these checks hold whatever that helper comes to pick.
 *
 * @return array<string, array<string, mixed>>
 */
function deployedLanguagePacks(): array
{
    $packs = [];
    foreach (glob(dirname(__DIR__, 3).'/config/lesson/lang/*.php') ?: [] as $file) {
        /** @var array<string, mixed> $pack */
        $pack = require $file;
        $packs[basename($file, '.php')] = $pack;
    }

    return $packs;
}

// The registry holds a pack for every file of `config/lesson/lang` — the four written before LANG-1 among them — and a
// `none` only for a code with no file. CATCHES the deployment checks below running over nothing.
it('reads a pack for every file of config/lesson/lang', function () {
    $written = deployedLanguagePacks();
    $packs = new LanguagePacks($written);

    expect(array_keys($written))->toContain('en', 'ru', 'uk', 'ro')
        ->and($packs->codes())->toBe(array_keys($written))
        ->and($packs->for('zz')->asNeighbour())->toBe(['script_letters' => null, 'common_words' => []]);
});

// Canon (LANG-1 §5): the guard splits a line on everything that is not a letter, the apostrophe too. CATCHES a pack whose
// `common_words` holds an entry the guard could never meet — «don't», «il y a», «c'est» — and so tells its language
// apart from its neighbours by fewer words than it seems to write.
it('writes every frequent word of a deployed pack as one run of letters', function () {
    $packs = new LanguagePacks(deployedLanguagePacks());
    $unmet = [];
    foreach ($packs->codes() as $code) {
        foreach ($packs->for($code)->commonWords() as $word) {
            if (preg_match('/^[\p{L}\p{M}]+$/u', $word) !== 1) {
                $unmet[] = "{$code}: «{$word}»";
            }
        }
    }

    expect($unmet)->toBe([]);
});

// Canon (LANG-1 §5): two languages share a script exactly when their packs write the SAME `script_letters` string. CATCHES
// two Latin packs (or two Cyrillic ones) writing equivalent patterns in different words — each would then be nobody's
// neighbour and the guard would never tell them apart. The script is found by a letter each pattern must take.
it('writes one letters pattern per script across the deployed packs', function () {
    $packs = new LanguagePacks(deployedLanguagePacks());
    $byScript = ['latin' => [], 'cyrillic' => []];
    foreach ($packs->codes() as $code) {
        $letters = $packs->for($code)->asNeighbour()['script_letters'];
        foreach (['latin' => 'a', 'cyrillic' => 'а'] as $script => $letter) {
            if ($letters !== null && preg_match($letters, $letter) === 1) {
                $byScript[$script][$letters][] = $code;
            }
        }
    }

    expect(count($byScript['latin']))->toBeLessThanOrEqual(1, 'latin: '.json_encode($byScript['latin'], JSON_UNESCAPED_SLASHES))
        ->and(count($byScript['cyrillic']))->toBeLessThanOrEqual(1, 'cyrillic: '.json_encode($byScript['cyrillic'], JSON_UNESCAPED_SLASHES));
});

// Canon (LANG-1, key `talk_title_template`): a deployed template is one the title can read — {@see LanguagePack::talkTitleTemplate()}
// throws on one written wrong — and its optional `and_before` (es «médico e internista») maps a pattern that compiles to
// a word. CATCHES a template the title would silently read as English (a blank joiner), a pattern that breaks at a talk's
// start, or an `and_before` the title drops entry by entry (a word left blank, a list instead of a map).
it('writes every deployed talk title template the way the title reads it', function () {
    $written = deployedLanguagePacks();
    $packs = new LanguagePacks($written);
    $wrong = [];
    foreach ($packs->codes() as $code) {
        if ($packs->for($code)->talkTitleTemplate() === null) {
            continue;
        }
        $template = $written[$code]['talk_title_template'];
        $before = is_array($template) ? ($template['and_before'] ?? []) : [];
        if (! is_array($before)) {
            $wrong[] = "{$code}: and_before is not a map";

            continue;
        }
        foreach ($before as $pattern => $word) {
            if (! is_string($pattern) || $pattern === '' || @preg_match($pattern, '') === false) {
                $wrong[] = "{$code}: and_before pattern «{$pattern}» does not compile";
            }
            if (! is_string($word) || trim($word) === '') {
                $wrong[] = "{$code}: and_before «{$pattern}» says no word";
            }
        }
    }

    expect($wrong)->toBe([]);
});
