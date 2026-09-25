<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Service\ReplyNative;

/**
 * THE GUARD OF THE ROLE'S TRANSLATION (наряд FIX-4c §6): «reply_native не может совпадать с reply_target (без регистра и
 * знаков) и должен быть на родном языке пары (по письменности пакета)». And for languages that share their letters
 * (наряд LANG-1 §5): «common_words родного < 2 и целевого ≥ 2» — against every other pack in the same letters.
 */

/**
 * Seven packs as {@see LanguagePacks} wires them — each one knows the others — holding only what the guard reads: the
 * letters (one pattern string per script, as the key spec asks) and some thirty of the language's most frequent words in
 * conversation. Written here, not read from `config/lesson/lang`, so the guard's rule is pinned whatever the language
 * packs come to hold.
 *
 * @param  array<string, array<string, mixed>>  $override  code → keys that replace the ones below
 */
function replyNativePacks(array $override = []): LanguagePacks
{
    $latin = '/^[\p{Latin}]$/u';
    $cyrillic = '/^[\p{Cyrillic}]$/u';
    $packs = [
        'en' => ['script_letters' => $latin, 'common_words' => [
            'i', 'you', 'the', 'to', 'a', 'it', 'and', 'that', 'of', 'is', 'what', 'in', 'me', 'this', 'we', 'your',
            'do', 'for', 'my', 'not', 'be', 'on', 'have', 'are', 'can', 'know', 'no', 'want', 'go', 'with',
        ]],
        'pl' => ['script_letters' => $latin, 'common_words' => [
            'nie', 'to', 'się', 'w', 'i', 'na', 'że', 'jest', 'z', 'co', 'do', 'tak', 'ja', 'a', 'o', 'jak', 'ale',
            'mi', 'po', 'tu', 'ty', 'mnie', 'go', 'za', 'już', 'czy', 'on', 'dla', 'tylko', 'jestem',
        ]],
        'de' => ['script_letters' => $latin, 'common_words' => [
            'ich', 'sie', 'das', 'ist', 'du', 'nicht', 'die', 'und', 'es', 'der', 'wir', 'was', 'zu', 'er', 'ein',
            'in', 'mit', 'ja', 'mir', 'den', 'sich', 'auf', 'dich', 'eine', 'so', 'mich', 'hier', 'wie', 'haben', 'dass',
        ]],
        'fr' => ['script_letters' => $latin, 'common_words' => [
            'je', 'de', 'est', 'pas', 'le', 'vous', 'la', 'tu', 'que', 'un', 'il', 'et', 'à', 'a', 'ne', 'les', 'ce',
            'en', 'on', 'ça', 'une', 'ai', 'pour', 'des', 'moi', 'qui', 'nous', 'mais', 'me', 'y',
        ]],
        'ru' => ['script_letters' => $cyrillic, 'common_words' => [
            'и', 'в', 'не', 'на', 'я', 'что', 'он', 'с', 'как', 'а', 'то', 'это', 'все', 'она', 'так', 'его', 'но',
            'да', 'ты', 'к', 'у', 'же', 'вы', 'за', 'бы', 'по', 'только', 'ее', 'мне', 'было',
        ]],
        'uk' => ['script_letters' => $cyrillic, 'common_words' => [
            'і', 'в', 'у', 'не', 'на', 'що', 'з', 'я', 'та', 'це', 'він', 'як', 'до', 'а', 'так', 'але', 'його',
            'ти', 'ми', 'за', 'по', 'від', 'вона', 'для', 'був', 'вже', 'де', 'бо', 'коли', 'й',
        ]],
        'be' => ['script_letters' => $cyrillic, 'common_words' => [
            'і', 'у', 'ў', 'не', 'на', 'з', 'што', 'я', 'да', 'гэта', 'ён', 'як', 'а', 'па', 'так', 'але', 'яго',
            'ты', 'мы', 'за', 'ад', 'яна', 'для', 'быў', 'ужо', 'дзе', 'бо', 'калі', 'ці', 'яшчэ',
        ]],
    ];
    foreach ($override as $code => $keys) {
        $packs[$code] = array_merge($packs[$code] ?? [], $keys);
    }

    return new LanguagePacks($packs);
}

// Canon (§6). CATCHES the FIX-4b rehearsal's turn 14 — «How long has he had these symptoms?» under itself — passed as a
// translation, an English line with Russian marks passed, an empty one passed, and a Russian line refused for naming a
// drug or a scan in Latin letters.
it('finds the translation missing when it is empty, the same words, or not in the learner\'s letters', function () {
    $ru = lessonPacks()->for('ru');
    $line = 'How long has he had these symptoms?';

    expect(ReplyNative::missing($line, '', $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, '  — ', $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, $line, $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, 'how long has he had these symptoms', $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, 'How long has he had the symptoms?', $ru))->toBeTrue()
        ->and(ReplyNative::missing($line, 'Как давно у него эти симптомы?', $ru))->toBeFalse()
        ->and(ReplyNative::missing('Did you give him Ibuprofen?', 'Вы давали ему Ibuprofen?', $ru))->toBeFalse()
        ->and(ReplyNative::missing('He needs an MRI.', 'Ему нужна МРТ, то есть MRI.', $ru))->toBeFalse();
});

// Canon (LANG-1 §5; the live pair en → ru keeps its guard): an ordinary Russian line of a role stays a translation with
// the packs the deployment HAS — the neighbours included. Russian says «для», «до», «та», «мы» as often as Ukrainian and
// Belarusian do; a Russian `common_words` that leaves out a word its Cyrillic neighbours list hands that word to them, and
// two such words make a Russian line «Ukrainian» (a probe with subtitle top-30 lists for ru, uk and be, «для» and «до» in
// the Ukrainian one only, refused the first four lines below). CATCHES a set of deployed lists that turns the live pair's
// own translations into «native_missing» — a second paid answer, then a line with no translation at all.
it('keeps ordinary Russian lines a translation with the deployed packs and their neighbours', function () {
    $ru = lessonPacks()->for('ru');
    $refused = array_values(array_filter([
        'Для записи к врачу приходите до двенадцати.',
        'Подождите до обеда, для вас найдётся окно.',
        'Это лекарство для детей до двенадцати лет.',
        'Та медсестра уже ушла, подождите до завтра.',
        'Мы можем принять вас до пятницы.',
        'Для этого нужен рецепт от врача.',
        'Мы по записи, на десять.',
        'До свидания, всего доброго!',
        'Как давно у него эти симптомы?',
    ], static fn (string $line): bool => ReplyNative::missing('—', $line, $ru)));

    expect($refused)->toBe([]);
});

// Canon (§6): a language whose pack does not write its letters is judged by the two plain checks only. CATCHES a guard
// that refuses every line of such a pair, or none. Built on a pack with nothing written (LANG-1: `en` may come to write
// its letters, so it no longer stands for «a pack without letters»).
it('judges a language without its letters written by emptiness and sameness only', function () {
    $unwritten = LanguagePack::none('en');

    expect(ReplyNative::missing('Bună ziua.', 'Good afternoon.', $unwritten))->toBeFalse()
        ->and(ReplyNative::missing('Bună ziua.', 'Bună ziua!', $unwritten))->toBeTrue()
        ->and(ReplyNative::missing('Bună ziua.', '', $unwritten))->toBeTrue();
});

// Canon (LANG-1 §5, the order's pairs). CATCHES a Latin-letter learner (Polish, German) or a Cyrillic one (Russian,
// Belarusian) handed the target line's language as its «translation» — the letters are right, so only the words tell —
// and the learner's own line refused beside it, a short one or one quoting a sign in English (the AND of the rule: the
// Polish words there are what keeps it).
it('finds the translation missing when it is another language in the learner\'s own letters', function (string $native, string $line, bool $missing) {
    $learner = replyNativePacks()->for($native);

    expect(ReplyNative::missing('—', $line, $learner))->toBe($missing);
})->with([
    'pl ← English' => ['pl', 'I want to go to the doctor on Monday', true],
    'pl ← Polish' => ['pl', 'Chcę iść do lekarza w poniedziałek', false],
    'pl ← a short Polish line' => ['pl', 'Dzień dobry!', false],
    'pl ← Polish quoting an English name' => ['pl', 'Nie wiem, czy to jest „The Doctor Is In”, ale tak mówi recepcja.', false],
    'de ← French' => ['de', 'Je ne sais pas si le médecin est là', true],
    'de ← German' => ['de', 'Ich weiß nicht, ob der Arzt da ist', false],
    'ru ← Ukrainian' => ['ru', 'Я не знаю, що це таке і де лікар', true],
    'ru ← Russian' => ['ru', 'Я не знаю, что это такое', false],
    'be ← Russian' => ['be', 'Я не знаю, что это такое и где врач', true],
    'be ← Belarusian' => ['be', 'Я не ведаю, што гэта і дзе лекар', false],
]);

// Canon (LANG-1 §5): «both counts», and a word is a word whatever its case. CATCHES a guard that refuses a short native
// line for holding none of its language's frequent words — the AND of the rule —, one that lets one Polish word carry an
// English line, one that takes a single English word said over and over for English, or one that misses English shouted.
it('keeps a short native line, needs two native words, and reads a word once whatever its case', function () {
    $pl = replyNativePacks()->for('pl');

    expect(ReplyNative::missing('Yes.', 'Tak.', $pl))->toBeFalse()
        ->and(ReplyNative::missing('Good morning!', 'Dzień dobry!', $pl))->toBeFalse()
        ->and(ReplyNative::missing('—', 'Tak, I want the doctor.', $pl))->toBeTrue()
        ->and(ReplyNative::missing('—', 'The doctor, the doctor, the doctor!', $pl))->toBeFalse()
        ->and(ReplyNative::missing('—', 'The doctor, I want the doctor!', $pl))->toBeTrue()
        ->and(ReplyNative::missing('—', 'WHAT DO YOU WANT?', $pl))->toBeTrue();
});

// Canon (LANG-1 §5): «родного < 2» is the border — two of the learner's own words keep a line even beside two of the other
// language's, and the words both languages use often count for neither. CATCHES a guard that needs three native words
// before it believes a Polish line quoting an English one, and one that counts «a», «to», «do» — Polish as much as
// English — as English and refuses «A to do czego?».
it('keeps a line with two of the learner\'s own words, and counts no word both languages share', function () {
    $pl = replyNativePacks()->for('pl');

    expect(ReplyNative::missing('—', 'Pani mówi „I want the doctor”, ale nie wiem.', $pl))->toBeFalse()
        ->and(ReplyNative::missing('—', 'Pani mówi „I want the doctor”, wiem.', $pl))->toBeTrue()
        ->and(ReplyNative::missing('What is it for?', 'A to do czego?', $pl))->toBeFalse();
});

// Canon (LANG-1 §5, the superset stated in the guard's docblock): the learner's pack knows every same-letter neighbour,
// not only the target. CATCHES a guard that compares with one language and lets a German line through under a Polish
// learner of English.
it('refuses a line in any other language of the learner\'s letters, not only the target\'s', function () {
    $pl = replyNativePacks()->for('pl');

    expect(ReplyNative::missing('I don\'t know if the doctor is there.', 'Ich weiß nicht, ob der Arzt da ist', $pl))->toBeTrue()
        ->and(ReplyNative::missing('I don\'t know if the doctor is there.', 'Je ne sais pas si le médecin est là', $pl))->toBeTrue()
        ->and(ReplyNative::missing('I don\'t know if the doctor is there.', 'Nie wiem, czy lekarz tu jest', $pl))->toBeFalse();
});

// Canon (LANG-1 §5): «apostrophes split elisions». CATCHES a guard that reads «j'ai» and «c'est» as words of their own,
// finds no French in «J'ai mal, c'est là.» and lets it stand under a German learner — with either apostrophe.
it('splits an elision on its apostrophe, plain or typographic', function () {
    $de = replyNativePacks()->for('de');

    expect(ReplyNative::missing('—', 'J\'ai mal, c\'est là.', $de))->toBeTrue()
        ->and(ReplyNative::missing('—', 'J’ai mal, c’est là.', $de))->toBeTrue()
        ->and(ReplyNative::missing('—', 'Mir tut es weh, das ist hier.', $de))->toBeFalse();
});

// Canon (LANG-1 §5): a language in OTHER letters is never a neighbour. CATCHES a guard that sets a Russian line quoting a
// drug or a scan in Latin letters against English words once the Russian pack writes its frequent words.
it('never compares a line with the words of a language in other letters', function () {
    $ru = replyNativePacks()->for('ru');

    expect(ReplyNative::missing('Did you give him Ibuprofen?', 'Вы давали ему Ibuprofen?', $ru))->toBeFalse()
        ->and(ReplyNative::missing('He needs an MRI.', 'Ему нужна МРТ, то есть MRI.', $ru))->toBeFalse()
        ->and(ReplyNative::missing('How long has he had these symptoms?', 'How long has he had these symptoms, doctor?', $ru))->toBeTrue();
});

// Canon (LANG-1 §5): the words are compared only where both packs write them and write the SAME letters. CATCHES a guard
// that refuses on a neighbour whose letters are an equivalent pattern written differently, on a neighbour with no words,
// for a learner with no words — or for a learner whose letters are not written at all.
it('compares only packs that write their words and the very same letters', function () {
    $english = 'I want to go to the doctor on Monday';

    expect(ReplyNative::missing('—', $english, replyNativePacks(['en' => ['script_letters' => '/^[a-z]$/iu']])->for('pl')))->toBeFalse()
        ->and(ReplyNative::missing('—', $english, replyNativePacks(['en' => ['common_words' => null]])->for('pl')))->toBeFalse()
        ->and(ReplyNative::missing('—', $english, replyNativePacks(['pl' => ['common_words' => []]])->for('pl')))->toBeFalse()
        ->and(ReplyNative::missing('—', $english, replyNativePacks(['pl' => ['script_letters' => null]])->for('pl')))->toBeFalse()
        ->and(ReplyNative::missing('—', $english, new LanguagePack('pl', ['script_letters' => '/^[\p{Latin}]$/u', 'common_words' => ['nie', 'się', 'w', 'jest']])))->toBeFalse()
        ->and(ReplyNative::missing('—', $english, replyNativePacks()->for('pl')))->toBeTrue();
});
