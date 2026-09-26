<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Lesson\VocabularyRules;
use App\Modules\Plan\Domain\Lesson\LessonParser;

/**
 * THE DEFINITION OF A WORD IS IN THE TARGET LANGUAGE (наряд LANG-1b §4, `vocab.definition_language`; a warning there, FATAL
 * since §10 — its card goes to P2R, which writes the definition anew). The
 * lessons are the LANG-1 scouting's own answers (`docs/research/lang-1/answers/`): ru→de defined every German word in
 * English, de→en defined its English words in English — as it should. The definitions are the ones the model wrote, one
 * rewritten in German where a German one is needed; the packs are the deployment's (`config/lesson/lang`).
 */

/**
 * `v1: detail` of every `vocab.definition_language` finding over the scouting answer of `$pair`, its definitions replaced
 * by `$definitions` where given (item index → text).
 *
 * @param  array<int, string>  $definitions
 * @return list<string>
 */
function dlFindings(string $pair, array $definitions = []): array
{
    [$native, $target] = explode('-', $pair);
    $raw = json_decode((string) file_get_contents(__DIR__.'/../../../docs/research/lang-1/answers/'.$pair.'.json'), true);
    foreach ($definitions as $index => $text) {
        $raw['vocabulary'][$index]['definition_target'] = $text;
    }
    $context = lessonContext($native, $target);

    return array_values(array_map(
        static fn (LessonViolation $v): string => "{$v->address}: {$v->detail}",
        array_filter(
            (new VocabularyRules)->violations((new LessonParser)->parse($raw), $context),
            static fn (LessonViolation $v): bool => $v->code === LessonCodes::VOCAB_DEFINITION_LANGUAGE,
        ),
    ));
}

// The owner's find (LANG-1, «на глаз» части D): «в ru→de все definition_target — по-английски». CATCHES the English
// definitions of a German day let through, and a definition read by the wrong pack.
it('finds a German word defined in English, by the English words only English uses often', function () {
    expect(dlFindings('ru-de'))->toContain(
        'v2: the definition «pain in the throat» of «Halsschmerzen» reads as en, not de (the)',
        'v3: the definition «a high body temperature when you are ill» of «Fieber» reads as en, not de (when, you)',
    )->and(count(dlFindings('ru-de')))->toBeGreaterThanOrEqual(6);
});

// CATCHES a guard that holds German itself for a foreign language — and one that reads a definition of no frequent word
// («Schmerzen im Hals») as anybody's.
it('lets a German definition be, and one that says nothing either way', function () {
    $german = [
        1 => 'Schmerzen im Hals, wenn man schluckt',
        2 => 'eine hohe Temperatur, wenn man krank ist',
        5 => 'eine Karte für die Krankenversicherung',
    ];
    $found = dlFindings('ru-de', $german);

    expect(array_filter($found, static fn (string $f): bool => str_starts_with($f, 'v2:') || str_starts_with($f, 'v3:') || str_starts_with($f, 'v6:')))->toBe([]);
});

// CATCHES the guard firing on the right language: seven X→en days of the scouting defined their English words in English.
it('finds nothing where the definitions are in the target language', function (string $pair) {
    expect(dlFindings($pair))->toBe([]);
})->with(['be-en', 'de-en', 'es-en', 'fr-en', 'it-en', 'pl-en', 'ro-en', 'uk-en']);

// CATCHES a definition in other letters let through because its words are nobody's frequent ones.
it('finds a definition written in the letters of another writing', function () {
    expect(dlFindings('de-en', [0 => 'запланированное время визита к врачу']))->toBe([
        'v1: the definition «запланированное время визита к врачу» of «doctor\'s appointment» is not written in the letters of the target language (en)',
    ]);
});

// Наряд LANG-1b §10: the owner's ru→ro day defined its Romanian words in English («a place where goods are sold»), as the
// ru→ro scouting day of LANG-1 had. CATCHES a fatal guard that lets a Romanian day's English definitions through. Five of
// the eight are told by the words only English uses often; «an arranged time to see a doctor», «an official identity
// document» and «earlier than a set time» hold none of them (the English pack keeps only the words no neighbour shares) and
// pass — the prompt's own rule (v4.10, VOCABULARY) is what keeps them in Romanian.
it('finds a Romanian word defined in English', function () {
    $found = dlFindings('ru-ro');

    expect($found)->toContain(
        'v2: the definition «the general doctor you visit first» of «medicul de familie» reads as en, not ro (the, you)',
        'v3: the definition «the front part of the neck used for swallowing and speaking» of «gât» reads as en, not ro (the, of, for, and)',
    )->and(array_map(static fn (string $f): string => explode(':', $f)[0], $found))->toBe(['v2', 'v3', 'v4', 'v6', 'v7']);
});
