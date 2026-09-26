<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Shared\Domain\Service\LanguageRoles;

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

/**
 * The script each language of the plan is written in (наряд LANG-1 §5; `docs/research/lang-1/pack-keys.md` §3.2): the
 * only thing a test cannot read from the packs themselves, since the letters are what is being checked.
 *
 * @return array<string, string> code → script
 */
function deployedPackScripts(): array
{
    $scripts = [];
    foreach (array_unique([...LanguageRoles::planTargets(), ...LanguageRoles::planNatives()]) as $code) {
        $scripts[$code] = in_array($code, ['ru', 'uk', 'be'], true) ? 'cyrillic' : 'latin';
    }

    return $scripts;
}

// Canon (LANG-1 §4.6): «no key may be null — null counts lang.pack_missing»; a rule a language has no use for is written as
// its no-op. The one null a pack may hold is `negation.after`, which is a value there («the negation may stand anywhere»,
// pack-keys §4.3). CATCHES a key left null in any deployed pack — a check skipped on every day of its pairs — and a null
// field inside a key, which the readers take for «not written» too.
it('writes no key of a deployed pack as null', function () {
    $nulls = [];
    $walk = static function (mixed $value, string $path) use (&$walk, &$nulls): void {
        if ($value === null && ! str_ends_with($path, 'negation.after')) {
            $nulls[] = $path;
        }
        if (is_array($value)) {
            foreach ($value as $key => $inner) {
                $walk($inner, "{$path}.{$key}");
            }
        }
    };
    foreach (deployedLanguagePacks() as $code => $pack) {
        foreach ($pack as $key => $value) {
            $walk($value, "{$code}.{$key}");
        }
    }

    expect($nulls)->toBe([]);
});

// Canon (LANG-1, the main session's update to the key spec): `script_letters` is EXACTLY '/^[\p{Latin}]$/u' for a Latin
// language and EXACTLY '/^[\p{Cyrillic}]$/u' for a Cyrillic one — the guard of the translation finds a learner's
// neighbours by comparing the strings, and `pronunciation.foreign_script` is fatal, so a pattern of the language's own
// alphabet would fail a valid day on one borrowed letter. Every language of the plan writes it, English too (a neighbour,
// never a learner). CATCHES a pack that writes an equivalent pattern in other words (it would be nobody's neighbour), a
// strict alphabet in place of the script, and a language of the plan with no letters at all.
it('writes the one letters pattern of its script, exactly, for every language of the plan', function () {
    $packs = new LanguagePacks(deployedLanguagePacks());
    $reference = ['latin' => '/^[\p{Latin}]$/u', 'cyrillic' => '/^[\p{Cyrillic}]$/u'];
    $wrong = [];
    foreach (deployedPackScripts() as $code => $script) {
        $letters = $packs->for($code)->asNeighbour()['script_letters'];
        if ($letters !== $reference[$script]) {
            $wrong[] = "{$code}: ".var_export($letters, true)." — not the {$script} reference";
        }
    }

    expect($wrong)->toBe([]);
});

// Canon (LANG-1, the update to `common_words`): each entry is ONE run of letters, lower-case, as LanguagePack::normal() keeps
// it — the pack is read as written by nobody else, so an entry in capitals or with an apostrophe is one the guard never
// meets —, and every language of the plan writes some. CATCHES «Sie», «c'est», «il y a» written in a list, and a language
// of the plan whose words tell it from nobody.
it('writes every frequent word as the guard meets it, and a list for every language of the plan', function () {
    $written = deployedLanguagePacks();
    $wrong = [];
    foreach ($written as $code => $pack) {
        foreach (is_array($pack['common_words'] ?? null) ? $pack['common_words'] : [] as $word) {
            if (! is_string($word) || preg_match('/^[\p{L}\p{M}]+$/u', $word) !== 1 || LanguagePack::normal($word) !== $word) {
                $wrong[] = "{$code}: ".var_export($word, true);
            }
        }
    }
    $packs = new LanguagePacks($written);
    foreach (array_keys(deployedPackScripts()) as $code) {
        if ($packs->for($code)->commonWords() === []) {
            $wrong[] = "{$code}: no common_words";
        }
    }

    expect($wrong)->toBe([]);
});

// Canon (LANG-1, the update to `common_words`): «frequent AND distinctive — not an ordinary word (same spelling) in ANY
// same-script neighbour». A word two neighbours both list is an ordinary word of each — and the guard drops it from the
// comparison of the two, so it tells them apart from nobody but a third language, where it counts for one of them only.
// CATCHES the same word written into two lists of one script («для» in ru and uk: a Russian line with «для» and «до» read
// as Ukrainian, the probe of the order).
it('keeps the frequent words of two packs in the same letters apart', function () {
    $packs = new LanguagePacks(deployedLanguagePacks());
    $shared = [];
    foreach ($packs->codes() as $one) {
        foreach ($packs->codes() as $other) {
            $letters = $packs->for($one)->asNeighbour()['script_letters'];
            if ($one >= $other || $letters === null || $letters !== $packs->for($other)->asNeighbour()['script_letters']) {
                continue;
            }
            $both = array_values(array_intersect($packs->for($one)->commonWords(), $packs->for($other)->commonWords()));
            if ($both !== []) {
                $shared[] = "{$one} ∩ {$other}: ".implode(', ', $both);
            }
        }
    }

    expect($shared)->toBe([]);
});

// Canon (LANG-1, key `talk_title_template`): every learner's language whose declension the code does not know writes the
// template (be pl ro es it de fr), the three the code titles itself (ru, uk; en is never a learner) write the no-op, and
// the title built from a template names every role, joins the last two with the language's «and» and falls back to its
// «anyone» with no roles. CATCHES a learner's language whose talks would be titled in English, a ru/uk template that
// would shadow the declension, and a template whose title loses a role.
it('writes a talk title template for every learner\'s language the code does not decline, and titles every role with it', function () {
    $packs = new LanguagePacks(deployedLanguagePacks());
    $wrong = [];
    foreach (['ru', 'uk', 'en'] as $code) {
        if ($packs->for($code)->talkTitleTemplate() !== null) {
            $wrong[] = "{$code}: writes a template the code does not read";
        }
    }
    foreach (array_diff(LanguageRoles::planNatives(), ['ru', 'uk']) as $code) {
        $pack = $packs->for($code);
        $template = $pack->talkTitleTemplate();
        if ($template === null) {
            $wrong[] = "{$code}: no template";

            continue;
        }
        $title = (new NativeStrings($code))->talkTitle(['Alpha', 'Beta', 'MRI'], $pack);
        foreach (['lpha', 'eta', 'MRI', ' '.$template['and'].' '] as $part) {
            if (! str_contains($title, $part)) {
                $wrong[] = "{$code}: «{$title}» lacks «{$part}»";
            }
        }
        if ((new NativeStrings($code))->talkTitle([], $pack) !== $template['anyone']) {
            $wrong[] = "{$code}: no roles is not «{$template['anyone']}»";
        }
    }

    expect($wrong)->toBe([]);
});

// Canon (pack-keys §3.3, `frame.native_punct`): the kind a target frame ends with is compared with the kind its native frame
// ends with — across two packs —, and `exchange.second_question` (fatal) asks for the kind `question`. So every language of
// the plan names the four marks every language here ends a sentence with by the same four kinds, and no other kind. CATCHES
// a pack that calls «…» a statement (every frame ending in it «punctuated differently» from its translation), one that
// leaves «?» out (no question seen, the fatal check blind) and a kind misspelt.
it('ends a sentence at the same four marks, of the same four kinds, in every language of the plan', function () {
    $written = deployedLanguagePacks();
    $kinds = ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'];
    $wrong = [];
    foreach (array_keys(deployedPackScripts()) as $code) {
        $ends = $written[$code]['sentence_ends'] ?? null;
        if (! is_array($ends)) {
            $wrong[] = "{$code}: no sentence_ends";

            continue;
        }
        foreach ($kinds as $mark => $kind) {
            if (($ends[$mark] ?? null) !== $kind) {
                $wrong[] = "{$code}: «{$mark}» is not «{$kind}»";
            }
        }
        foreach ($ends as $mark => $kind) {
            if (! in_array($kind, $kinds, true)) {
                $wrong[] = "{$code}: «{$mark}» is of an unknown kind ".var_export($kind, true);
            }
        }
    }

    expect($wrong)->toBe([]);
});
