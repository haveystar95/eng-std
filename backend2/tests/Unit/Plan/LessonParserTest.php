<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Lesson\VocabularyItem;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * THE READINGS, AS THE PARSER READS THEM (наряд LANG-1, валидатор; `docs/research/lang-1/baseline.md`): a Latin letter
 * drawn inside a Cyrillic word is put back into Cyrillic — every `pronunciation_native` of a frame, a filler, a word and
 * a learner line, and of a repaired card — and nothing else is touched.
 */

/** @return array<string, mixed> the fake's lesson for a Russian learner of English */
function lpPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
}

/** The reading a frame is read with when the model wrote `$reading` for it. */
function lpFrameReading(string $reading): string
{
    $p = lpPayload();
    $p['phrases'][0]['pronunciation_native'] = $reading;

    return (new LessonParser)->parse($p)->phrases[0]->pronunciationNative;
}

// The scouting days of LANG-1 (ru→pl, ru→fr): the stress written with a Latin «á», the Polish «pasuje» leaving its Latin
// «e», a Latin «o» in «телефон» — each a fatal `pronunciation.foreign_script` for a letter drawn from the other table.
// CATCHES a homoglyph left in a Cyrillic word, an acute left precomposed (or dropped) instead of a Cyrillic vowel with
// U+0301, and a capital or a decomposed accent missed.
it('puts the Latin letters of a Cyrillic word back into Cyrillic, the acute a combining stress', function (string $model, string $read) {
    expect(lpFrameReading($model))->toBe($read);
})->with([
    'the stress in a Latin á' => ['до лекáжа', "до лека\u{0301}жа"],
    'the Polish «pasuje» leaving its e' => ['___ ми пасуe', '___ ми пасуе'],
    'a Latin o in the middle of a word' => ['нюмэро дё телефoн', 'нюмэро дё телефон'],
    'every twin, lower-case' => ['дaeocpxyk', 'даеосрхук'],
    'every twin, capital' => ['ДAEOCPXYBHKMT', 'ДАЕОСРХУВНКМТ'],
    'each acute, both cases' => ['дáéóýÁÉÓÝ', "да\u{0301}е\u{0301}о\u{0301}у\u{0301}А\u{0301}Е\u{0301}О\u{0301}У\u{0301}"],
    'a Latin a already decomposed' => ["лекa\u{0301}жа", "лека\u{0301}жа"],
    'a Cyrillic word opening with a Latin capital' => ['Cэнк ю', 'Сэнк ю'],
]);

// Canon of the fix: «латиница рядом с кириллицей остаётся латиницей; другие письменности не чинятся». CATCHES a Latin word
// next to Cyrillic ones turned into Cyrillic («SMS-ку»), the Latin reading of a learner who reads Latin letters touched,
// a letter with no Cyrillic twin forced into one, a Cyrillic word already right changed (ё, й and a stress kept), a
// letter of another writing «mended» — it has to stay the real finding it is — and a reading the pattern cannot read
// (bytes that are not UTF-8) emptied instead of kept as written.
it('touches nothing but a Latin twin inside a Cyrillic word', function (string $reading) {
    expect(lpFrameReading($reading))->toBe($reading);
})->with([
    'a Latin word next to Cyrillic ones' => ['эс-эм-эс SMS-ку'],
    'a Latin word, then a Cyrillic one' => ['CT скан'],
    'a reading in Latin letters' => ['łajk tu kam at tu pi em'],
    'a Latin letter with no Cyrillic twin' => ['пасуj'],
    'a Cyrillic word already right' => ["ёлка йогурт лека\u{0301}жа"],
    'an Armenian letter' => ['ֆоут'],
    'a Georgian pair' => ['პლиз'],
    'a Greek theta' => ['θэнк ю'],
    'bytes that are not UTF-8' => ["ми пасуe \xFF"],
]);

// Every reading the parser reads, and only the readings: a filler's, a word's, a learner line's and a repaired card's
// too. CATCHES a field of the four left unread (the fatal finding stays on that card), the text of a line or a frame
// rewritten alongside its reading, and a repair's card let through with the homoglyph the whole lesson would have lost.
it('reads every reading of a lesson and of a repaired card so, and no other text', function () {
    $p = lpPayload();
    $p['phrases'][0]['slot']['fillers'][1]['pronunciation_native'] = 'нeк';
    $p['phrases'][0]['slot']['fillers'][1]['native'] = 'шеo';
    $p['vocabulary'][1]['pronunciation_native'] = 'шaрп';
    $p['vocabulary'][1]['translation_native'] = 'острaя';
    $p['dialogue'][0]['messages'][1]['pronunciation_native'] = 'ит хёртс ин хиз лoуэр бэк';
    $p['dialogue'][0]['messages'][1]['text_native'] = 'У него болит поясницa.';
    $lesson = (new LessonParser)->parse($p);
    $line = $lesson->exchanges[0]->learner();

    expect($lesson->phrases[0]->fillers()[1]->pronunciationNative)->toBe('нек')
        ->and($lesson->phrases[0]->fillers()[1]->native)->toBe('шеo')
        ->and($lesson->vocabulary[1]->pronunciationNative)->toBe('шарп')
        ->and($lesson->vocabulary[1]->translationNative)->toBe('острaя')
        ->and($line?->pronunciationNative)->toBe('ит хёртс ин хиз лоуэр бэк')
        ->and($line?->textNative)->toBe('У него болит поясницa.');

    $parser = new LessonParser;
    $frame = $parser->card(LessonCard::FRAME, ['id' => 'p1', 'kind' => 'answer', 'frame_target' => 'I need ___.', 'frame_native' => 'Мне нужно ___.', 'pronunciation_native' => 'aй нид ___', 'slot' => [
        'hint_native' => 'что', 'fillers' => [['target' => 'a form', 'native' => 'бланк', 'pronunciation_native' => 'э фoрм', 'in_dialogue' => true]],
    ]]);
    $term = $parser->card(LessonCard::TERM, ['id' => 'v1', 'term_target' => 'form', 'translation_native' => 'бланк', 'pronunciation_native' => 'фoрм', 'kind' => 'word']);
    $learner = $parser->card(LessonCard::LINE, ['speaker' => 'B', 'text_target' => 'I need a form.', 'text_native' => 'Мне нужен бланк.', 'pronunciation_native' => 'aй нид э фoрм']);

    expect($frame)->toBeInstanceOf(Phrase::class)
        ->and($frame instanceof Phrase ? [$frame->pronunciationNative, $frame->fillers()[0]->pronunciationNative] : null)->toBe(['ай нид ___', 'э форм'])
        ->and($term instanceof VocabularyItem ? $term->pronunciationNative : null)->toBe('форм')
        ->and($learner instanceof Message ? $learner->pronunciationNative : null)->toBe('ай нид э форм');
});

// The point of the fix: the day the scouting ru→pl answer failed on is not failed by the fatal code any more, and a
// letter of another writing still is. CATCHES a repair that leaves the validator reading the model's text unmended.
it('leaves the validator no foreign_script to find in a homoglyph, and still the finding in another writing', function () {
    $homoglyph = lpPayload();
    $homoglyph['phrases'][0]['pronunciation_native'] = 'ит хёртс ин хиз ___ oу';
    $homoglyph['vocabulary'][0]['pronunciation_native'] = 'лоуэр бáк';
    $armenian = lpPayload();
    $armenian['vocabulary'][0]['pronunciation_native'] = 'ֆоут';
    $foreign = static fn (array $p): array => array_values(array_map(
        static fn (LessonViolation $v): string => $v->address,
        array_filter(
            (new LessonValidator)->run((new LessonParser)->parse($p), lessonContext('ru', 'en')),
            static fn (LessonViolation $v): bool => $v->code === LessonCodes::PRONUNCIATION_FOREIGN_SCRIPT,
        ),
    ));

    expect($foreign($homoglyph))->toBe([])
        ->and($foreign($armenian))->toBe(['v1']);
});
