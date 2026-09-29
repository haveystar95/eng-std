<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\EarlierDay;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/*
 * THE CHECK OF THE SKELETON (наряд GEN-4, 3.3) — every rule on the canon day with ONE defect put in: the canon is clean
 * (`CanonDayTest`), the defect is what the rule finds, at the card it stands at. Fatal or not by the order.
 */

it('asks the skeleton again for exactly the order\'s fatal rules, and repairs the cards of the rest', function () {
    $fatal = array_values(array_map(static fn (SkeletonRule $r): string => $r->code(), array_filter(SkeletonCheck::rules(), static fn (SkeletonRule $r): bool => $r->fatal())));

    expect($fatal)->toBe([
        'frame.count', 'frame.must_say', 'frame.known_repeat', 'partner.item_missing', 'partner.item_unknown', 'vocab.not_found',
        'vocab.count', 'pronunciation.foreign_script', 'pronunciation.equals_native', 'skeleton.ids', 'partner.pairs_many',
    ]);
});

it('frame.count — a frame beyond the survival set', function () {
    $skeleton = dayCanonSkeleton(static function (array $raw): array {
        $extra = $raw['phrases'][0];
        $extra['id'] = 'p8';
        $extra['must_say'] = [];
        $extra['frame_target'] = 'Locuiesc în ___';
        $extra['frame_native'] = 'Я живу в ___';
        $raw['phrases'][] = $extra;

        return $raw;
    });

    expect(skeletonFound($skeleton))->toContain('frame.count@skeleton');
});

it('frame.must_say — a number out of the list, a number of two frames, a frame with none', function (array $numbers, string $at) {
    $skeleton = dayCanonSkeleton(scAt('phrases', $at, static fn (array $f): array => [...$f, 'must_say' => $numbers]));

    expect(skeletonFound($skeleton))->toContain("frame.must_say@{$at}");
})->with([
    'out of the list' => [[9], 'p2'],
    'served twice' => [[2], 'p3'],
    'none' => [[], 'p4'],
]);

it('frame.known_repeat — a frame an earlier day taught, in either language, its full stop aside', function (string $target, string $native) {
    $earlier = new EarlierDays([new EarlierDay(1, 'Prima zi', 'Recepționer', VoiceGender::Female, [], [['target' => $target, 'native' => $native]], ['cuvânt'])]);

    expect(skeletonFound(dayCanonSkeleton(), dayCanonSkeletonContext(earlier: $earlier)))->toContain('frame.known_repeat@p1');
})->with([
    'target' => ['Mă numesc ___.', 'Как меня зовут ___.'],
    'native' => ['Numele meu e ___.', 'Меня зовут ___.'],
]);

it('partner.item_missing — an item of must_understand no partner line delivers', function () {
    $skeleton = dayCanonSkeleton(static function (array $raw): array {
        $raw['partner_lines'] = array_values(array_filter($raw['partner_lines'], static fn (array $l): bool => $l['id'] !== 'a7'));

        return $raw;
    });

    expect(skeletonFound($skeleton))->toContain('partner.item_missing@skeleton');
});

it('partner.item_unknown — a partner line of an item the list does not have', function () {
    expect(skeletonFound(dayCanonSkeleton(scAt('partner_lines', 'a7', static fn (array $l): array => [...$l, 'must_understand' => 9]))))
        ->toContain('partner.item_unknown@a7');
});

it('vocab.not_found — a word no frame and no partner line says, in any form', function () {
    expect(skeletonFound(dayCanonSkeleton(scAt('vocabulary', 'v5', static fn (array $v): array => [...$v, 'term_target' => 'fereastră']))))
        ->toContain('vocab.not_found@v5');
});

it('vocab.not_found — finds the dictionary form in the inflected one, and a chunk by its content words', function () {
    // «a candida» → «Candidez», «a se ocupa de» → «Mă ocupam de», «în ture» → «cu ture»: the canon says all three so.
    expect(skeletonFound(dayCanonSkeleton()))->not->toContain('vocab.not_found@v1')
        ->not->toContain('vocab.not_found@v4')
        ->not->toContain('vocab.not_found@v9');
});

it('vocab.count — fewer words than VOCABULARY_COUNT allows', function () {
    $skeleton = dayCanonSkeleton(static function (array $raw): array {
        $raw['vocabulary'] = array_slice($raw['vocabulary'], 0, 7);

        return $raw;
    });

    expect(skeletonFound($skeleton))->toContain('vocab.count@skeleton');
});

it('pronunciation.foreign_script — a letter of another alphabet in a reading, a word of two alphabets or a Ukrainian letter in a native field', function () {
    $reading = dayCanonSkeleton(scAt('phrases', 'p3', static fn (array $f): array => [...$f, 'pronunciation_native' => 'ам лукрат ֆла ___']));
    $native = dayCanonSkeleton(scAt('phrases', 'p3', static fn (array $f): array => [...$f, 'frame_native' => 'Я рaботал в ___']));
    $cyrillic = dayCanonSkeleton(scAt('partner_lines', 'a3', static fn (array $l): array => [...$l, 'text_native' => 'Где вы працювали раніше?']));

    expect(skeletonFound($reading))->toContain('pronunciation.foreign_script@p3')
        ->and(skeletonFound($native))->toContain('pronunciation.foreign_script@p3')
        ->and(skeletonFound($cyrillic))->toContain('pronunciation.foreign_script@a3');
});

it('pronunciation.equals_native — a reading that is the native text, never a name read as it is written', function () {
    $skeleton = dayCanonSkeleton(scAt('phrases', 'p1', static fn (array $f): array => [...$f, 'pronunciation_native' => 'Меня зовут ___']));

    expect(skeletonFound($skeleton))->toContain('pronunciation.equals_native@p1')
        // «Andrei» / «Андрей» read «андрей» is the canon's own: a name.
        ->and(skeletonFound(dayCanonSkeleton()))->not->toContain('pronunciation.equals_native@p1.f1');
});

// `lesson_skeleton.v1`: «every pronunciation_native is the sound of the TARGET text, never the native text or something close
// to it» — a word the two languages share SOUNDS close to its native text, and that sound is what the prompt asks for.
// Catches a day failed (or a card paid for) over «taxi» read «такси» beside «такси».
it('pronunciation.equals_native, pronunciation.near_native — a word the two languages share is read by its sound, no finding', function (string $target, string $native, string $reading) {
    $skeleton = dayCanonSkeleton(static function (array $raw) use ($target, $native, $reading): array {
        $raw['phrases'][1]['slot']['fillers'][1] = ['target' => $target, 'native' => $native, 'pronunciation_native' => $reading, 'in_dialogue' => false];

        return $raw;
    });

    expect(skeletonFound($skeleton))->not->toContain('pronunciation.equals_native@p2.f2')
        ->not->toContain('pronunciation.near_native@p2.f2');
})->with([
    'the same word' => ['taxi', 'такси', 'такси'],
    'a cognate' => ['operator', 'оператора', 'опэратор'],
    'a longer cognate' => ['administrator', 'администратора', 'администратор'],
]);

it('skeleton.ids — two words under one id', function () {
    expect(skeletonFound(dayCanonSkeleton(scAt('vocabulary', 'v9', static fn (array $v): array => [...$v, 'id' => 'v8']))))
        ->toContain('skeleton.ids@v8');
});

// The gate run of GEN-4 (Luna, plan 04): «Für Sie passt das Basiskonto. Es kostet vier Euro im Monat» paired with two ask frames
// — the day failed on frame.unused after two paid dialogues. Catches a line of two frames let through to a dialogue that
// cannot be written from it, and two items of ONE frame read as two frames.
it('partner.pairs_many — a partner line paired with two frames, never with two items of one', function () {
    $two = dayCanonSkeleton(scAt('partner_lines', 'a6', static fn (array $l): array => [...$l, 'pairs_with' => [6, 7]]));
    $one = dayCanonSkeleton(static function (array $raw): array {
        // p6 says both asks — the same pattern — and a6 replies to both items of it.
        $raw['phrases'][5]['must_say'] = [6, 7];
        $raw['phrases'] = array_values(array_filter($raw['phrases'], static fn (array $f): bool => $f['id'] !== 'p7'));
        $raw['partner_lines'][5]['pairs_with'] = [6, 7];

        return $raw;
    });

    expect(skeletonFound($two))->toContain('partner.pairs_many@a6')
        ->and(skeletonFound($one))->not->toContain('partner.pairs_many@a6');
});

it('pronunciation.near_native — a filler\'s reading close to its native text', function () {
    $skeleton = dayCanonSkeleton(static function (array $raw): array {
        $raw['phrases'][3]['slot']['fillers'][1]['pronunciation_native'] = 'шесть месяцэв';

        return $raw;
    });

    expect(skeletonFound($skeleton))->toContain('pronunciation.near_native@p4.f2');
});

it('frame.native_twin — two frames with one native frame', function () {
    expect(skeletonFound(dayCanonSkeleton(scAt('phrases', 'p4', static fn (array $f): array => [...$f, 'frame_native' => 'Я работал в ___']))))
        ->toContain('frame.native_twin@p4');
});

it('filler.common_prefix — every filler opens with the word that is the frame\'s, an article or a preposition aside', function () {
    $prefixed = dayCanonSkeleton(scAt('phrases', 'p2', static function (array $f): array {
        $f['frame_target'] = 'Candidez pentru ___';
        foreach ($f['slot']['fillers'] as $i => $filler) {
            $f['slot']['fillers'][$i]['target'] = 'postul de '.$filler['target'];
        }

        return $f;
    }));

    expect(skeletonFound($prefixed))->toContain('filler.common_prefix@p2')
        // «un magazin», «un birou» and «o școală» open with articles; «в магазине» would with a preposition — no finding.
        ->and(skeletonFound(dayCanonSkeleton()))->not->toContain('filler.common_prefix@p3');
});

it('partner.names_filler — a partner line that says a filler of the frame it pairs with', function () {
    expect(skeletonFound(dayCanonSkeleton(scAt('partner_lines', 'a3', static fn (array $l): array => [...$l, 'text_target' => 'Unde ați lucrat înainte, la un magazin?']))))
        ->toContain('partner.names_filler@a3');
});

it('vocab.stop_word — a word of the stop list or a bare function word', function (string $term, string $usedIn) {
    expect(skeletonFound(dayCanonSkeleton(scAt('vocabulary', 'v8', static fn (array $v): array => [...$v, 'term_target' => $term, 'used_in' => [$usedIn]]))))
        ->toContain('vocab.stop_word@v8');
})->with([
    'a function word' => ['este', 'p7'],
    'a number' => ['doi', 'p4'],
]);

it('vocab.stop_word — reads a number by its every part, not a word with a digit in it', function () {
    $term = static fn (string $term): array => skeletonFound(dayCanonSkeleton(scAt('vocabulary', 'v8', static fn (array $v): array => [...$v, 'term_target' => $term])));

    expect($term('douăzeci-unu'))->toContain('vocab.stop_word@v8')
        ->and($term('COVID-19'))->not->toContain('vocab.stop_word@v8');
});

it('vocab.used_in_wrong — used_in names a place the word is not in, or no place at all', function (array $usedIn) {
    expect(skeletonFound(dayCanonSkeleton(scAt('vocabulary', 'v7', static fn (array $v): array => [...$v, 'used_in' => $usedIn]))))
        ->toContain('vocab.used_in_wrong@v7');
})->with([
    'a line without it' => [['p7', 'a1']],
    'no such place' => [['p7', 'a9']],
    'empty' => [[]],
]);

it('vocab.reading — a word\'s reading close to its translation', function () {
    expect(skeletonFound(dayCanonSkeleton(scAt('vocabulary', 'v2', static fn (array $v): array => [...$v, 'pronunciation_native' => 'должносць']))))
        ->toContain('vocab.reading@v2');
});

it('vocab.definition_language — a Romanian word defined in English', function () {
    expect(skeletonFound(dayCanonSkeleton(scAt('vocabulary', 'v3', static fn (array $v): array => [...$v, 'definition_target' => 'to have a job and do it every day']))))
        ->toContain('vocab.definition_language@v3');
});

it('learner.gender — a Russian learner\'s past tense after «я» in the other gender, or in any gender when it is unknown', function (?VoiceGender $gender, string $frame) {
    $skeleton = dayCanonSkeleton(scAt('phrases', 'p3', static fn (array $f): array => [...$f, 'frame_native' => $frame]));

    expect(skeletonFound($skeleton, dayCanonSkeletonContext(gender: $gender)))->toContain('learner.gender@p3.f1');
})->with([
    'a male learner says «работала»' => [VoiceGender::Male, 'Я работала в ___'],
    'a female learner says «работал»' => [VoiceGender::Female, 'Я работал в ___'],
    'an unknown learner says either' => [null, 'Я работал в ___'],
]);

it('learner.gender — reads the learner\'s own gender as right', function () {
    expect(skeletonFound(dayCanonSkeleton(), dayCanonSkeletonContext(gender: VoiceGender::Male)))->not->toContain('learner.gender@p3.f1');
});

it('filler.repeats_frame — a filler that says again the frame\'s word at the slot', function () {
    $skeleton = dayCanonSkeleton(static function (array $raw): array {
        $raw['phrases'][2]['slot']['fillers'][0]['native'] = 'в магазине';

        return $raw;
    });

    expect(skeletonFound($skeleton))->toContain('filler.repeats_frame@p3.f1');
});

it('frame.too_long — a frame part of more than seven words', function () {
    expect(skeletonFound(dayCanonSkeleton(scAt('phrases', 'p2', static fn (array $f): array => [...$f, 'frame_target' => 'Astăzi eu candidez aici la voi pentru postul de ___']))))
        ->toContain('frame.too_long@p2');
});

it('partner.too_long — a partner line of more than eighteen words', function () {
    $long = 'Programul obișnuit este de luni până vineri, cu ture de dimineață și de seară, iar pauza de masă este de o oră întreagă.';

    expect(skeletonFound(dayCanonSkeleton(scAt('partner_lines', 'a7', static fn (array $l): array => [...$l, 'text_target' => $long]))))
        ->toContain('partner.too_long@a7');
});

it('frame.missing_item — an item of must_say no frame serves, a warning only', function () {
    $skeleton = dayCanonSkeleton(static function (array $raw): array {
        $raw['phrases'] = array_values(array_filter($raw['phrases'], static fn (array $f): bool => $f['id'] !== 'p7'));
        $raw['vocabulary'] = array_map(static fn (array $v): array => [...$v, 'used_in' => array_values(array_diff($v['used_in'], ['p7']))], $raw['vocabulary']);

        return $raw;
    });

    expect(skeletonFound($skeleton))->toContain('frame.missing_item@skeleton')
        ->not->toContain('frame.count@skeleton');
});
