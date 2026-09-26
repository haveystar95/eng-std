<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\NumberValues;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Language\PackSkip;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\Service\LineShare;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\ReplyNative;
use App\Modules\Plan\Domain\Service\WordBases;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\MoveVerdict;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Shared\Domain\Service\SpokenNumbers;

/**
 * THE UKRAINIAN PACK (наряд LANG-1, `config/lesson/lang/uk.php`) — Ukrainian is a learner's own language only. What its
 * readers are given, pinned on the lines of the scouting day uk→en (`docs/research/lang-1/days/uk-en.md`, the model's
 * raw answer `answers/uk-en.json`) and on a few written lines where that day has none (abbreviations, numbers).
 */

// Canon (key spec §7.2): «пропусков нет на каждой стороне, которой язык бывает; ни один читатель не бросает». CATCHES a key
// left null or missing (`lang.pack_missing` on every uk→en day) and a key written in a shape its reader throws on.
it('gives every reader of the pack what it reads, in the shape it reads it', function (string $code) {
    $pack = lessonPacks()->for($code);
    $words = new LanguageWords($pack);
    $gaps = static function (string $side) use ($code): array {
        $context = $side === 'target' ? lessonContext('ru', $code) : lessonContext($code, 'en');
        $request = new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays);
        (new LessonValidator)->run((new LessonParser)->parse(FakePlanModel::lessonPayload($request)), $context);

        return array_values(array_filter(
            array_map(static fn (PackSkip $skip): array => $skip->toArray(), $context->skips->all()),
            static fn (array $skip): bool => $skip['language'] === $code,
        ));
    };

    expect(in_array($code, LanguageRoles::planTargets(), true))->toBeFalse('uk is a learner\'s language only')
        ->and(in_array($code, LanguageRoles::planNatives(), true))->toBeTrue();
    expect($gaps('native'))->toBe([]);
    foreach (['abbreviations', 'amount_pattern', 'amount_prefix', 'neutral_reply', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the native side reads `{$key}`");
    }
    expect(NumberValues::of($pack))->not->toBeNull();
    $words->agreeingWithSlot('a b ___ c d.');
    $words->genderedPast('a b');
    $words->foreignLetters('ab');
    $words->readsInScript('ab');
    $words->valueKind('a 2');
    // The target side is never read for uk; its no-ops are still readable as the spec writes them.
    (new FrameJudge)->move('a b c', [new ConversationPhrase('x', 'p1', 'A ___.', '', null, null)], $pack);
    (new FrameJudge)->breaksOff('a b', [], $pack);
    (new LineShare)->share('a b', 'b a', $pack, swapPersons: true);
    WordBases::of('abc', $pack);
    $words->isQuestion('a b');
    $words->asksTwice('a, b?');
    $words->clause('a b c');
    $words->articleMismatch('a', 'b');
    $words->unresolvedPronoun('a b ___.');
})->with(['uk']);

// Canon (наряд CHECK-1, key `abbreviations`): «точка сокращения не кончает предложение внутри текста, а в самом конце текста
// закрывает его». CATCHES a Ukrainian address or time with «вул.», «буд.», «хв.», «р.», «год.» cut into sentences of its
// own, and a filler «о 10 год.» read as a sentence.
it('ends a Ukrainian sentence where it ends, never at an abbreviation inside it', function () {
    $ends = lessonPacks()->for('uk')->sentenceEnds();

    // Five lines of the scouting day uk→en, as the model wrote them.
    expect($ends->count('Ваш прийом о третій у лікаря Браун.'))->toBe(1)
        ->and($ends->terminalKind('Ваш прийом о третій у лікаря Браун.'))->toBe('statement')
        ->and($ends->count('Звісно. Що вас турбує?'))->toBe(2)
        ->and($ends->terminalKind('Звісно. Що вас турбує?'))->toBe('question')
        ->and($ends->count('Так. Це Грін-стріт, 18.'))->toBe(2)
        ->and($ends->count('Будь ласка, прийдіть на десять хвилин раніше для реєстрації.'))->toBe(1)
        ->and($ends->terminalKind('Не могли б ви дати мені ___?'))->toBe('question')
        // The abbreviations of the language: inside a line they end nothing, at its very end they close it.
        ->and($ends->count('Клініка на вул. Шевченка, буд. 5, приходьте за 10 хв. до прийому.'))->toBe(1)
        ->and($ends->count('Запис на 12 вересня 2026 р. о 10 год. ранку.'))->toBe(1)
        ->and($ends->count('Лікарня ім. Шевченка, корп. 2, каб. 14, тел. 044 123 45 67.'))->toBe(1)
        ->and($ends->closesText('Приходьте о 10 год.'))->toBeTrue()
        ->and($ends->carriesSentence('о 10 год.'))->toBeFalse()
        ->and($ends->carriesSentence('о десятій ранку.'))->toBeTrue();
});

// Why FrameJudge never reads this pack: the judge reads the TARGET's pack — what the learner says in the talk — and no plan
// teaches Ukrainian (LanguageRoles::planTargets()). Its keys are the spec's no-ops, so as a target Ukrainian would forgive
// nothing but a negation: no opening word, no conjunction before a construction, no contraction. The negation is the
// order's dead data («не», «ні», free anywhere) and reads as the order wrote it. CATCHES a no-op turned into a rule
// nobody asked for (an `intro_words` that forgives «Так,» in front of a construction).
it('judges a talk held in Ukrainian by the frame\'s own words, a negation aside', function () {
    $uk = lessonPacks()->for('uk');
    $have = new ConversationPhrase('s1', 'p2', 'У мене ___.', '', null, null);
    $come = new ConversationPhrase('s1', 'p5', 'Я прийду ___.', '', null, null);
    $judge = new FrameJudge;

    expect($judge->move('У мене болить горло і є температура.', [$have], $uk))
        ->toEqual(new MoveVerdict(['s1:p2'], [], ['s1:p2' => 'болить горло і є температура']))
        ->and($judge->move('Я прийду на десять хвилин раніше.', [$come], $uk)->said)->toBe(['s1:p5'])
        // No opening word is forgiven: «Так,» and «Добре,» make the construction almost said.
        ->and($judge->move('Так, у мене болить горло.', [$have], $uk))->toEqual(new MoveVerdict([], ['s1:p2']))
        ->and($judge->move('Добре, я прийду на десять хвилин раніше.', [$come], $uk))->toEqual(new MoveVerdict([], ['s1:p5']))
        // No conjunction opens a construction in the middle of a move.
        ->and($judge->move('Я прийду завтра і у мене болить горло.', [$have], $uk)->said)->toBe([])
        // The negation, anywhere — the first word too.
        ->and($judge->move('Я не прийду завтра.', [$come], $uk)->said)->toBe(['s1:p5'])
        ->and($judge->move('Ні, я прийду завтра.', [$come], $uk)->said)->toBe(['s1:p5']);
});

// Canon (наряд FIX-2 п. 2, FIX-3 §4): «число словами = числу цифрами». Dead data for a native-only pack — written as ru
// writes its own, the bare nominative forms only. CATCHES a numeral of the scouting day left in words, «п’ятнадцять» with
// its apostrophe missing the entry «п'ятнадцять», and «двісті п’ятдесят» read as two numbers.
it('reads a Ukrainian number said in words as its digits', function () {
    $speech = lessonPacks()->for('uk')->speech();
    $fold = static fn (string $line): string => implode(' ', SpokenNumbers::fold(
        explode(' ', (new LexicalNormalizer)->canonicalize($line)),
        $speech->numberWords, $speech->articles, $speech->numberJoiners, $speech->numberTensJoiners,
    ));

    expect($fold('Будь ласка, прийдіть на десять хвилин раніше для реєстрації.'))->toBe('будь ласка прийдіть на 10 хвилин раніше для реєстрації')
        ->and($fold('За п’ятнадцять хвилин до'))->toBe('за 15 хвилин до')
        ->and($fold('Так. Це Грін-стріт, 18.'))->toBe('так це грін стріт 18')
        ->and($fold('Прийом коштує двісті п’ятдесят гривень.'))->toBe('прийом коштує 250 гривень')
        ->and($fold('дві тисячі двадцять шість'))->toBe('2026')
        // An ordinal is no entry: «о восьмій» stays in words, as ru keeps «в двух шагах».
        ->and($fold('О восьмій ранку'))->toBe('о восьмій ранку');
});

// Canon (наряд LANG-1 §5): «common_words родного < 2 и соседа ≥ 2» against every Cyrillic pack — and the list is
// «частые и отличительные»: no word of it is an ordinary word of ru or be. CATCHES an ordinary Ukrainian line refused as
// Russian, a Russian line passed as a Ukrainian learner's translation, and the probe's mistake the other way round — a uk
// list holding «для», «до» that refused «Для записи к врачу приходите до двенадцати» for a Russian learner.
it('tells a Ukrainian translation from a Russian one in the same letters', function () {
    $uk = lessonPacks()->for('uk');
    $ru = lessonPacks()->for('ru');

    // Five lines of the scouting day uk→en: the role's translation for a Ukrainian learner.
    foreach ([
        'Of course. What seems to be the problem?' => 'Звісно. Що вас турбує?',
        'We have appointments at ten or three today.' => 'Сьогодні є записи на десяту або на третю.',
        'Please arrive ten minutes early for check-in.' => 'Будь ласка, прийдіть на десять хвилин раніше для реєстрації.',
        'Your appointment is at three with Dr. Brown.' => 'Ваш прийом о третій у лікаря Браун.',
        'Yes, we open at eight tomorrow.' => 'Так, завтра ми відчиняємося о восьмій.',
    ] as $target => $native) {
        expect(ReplyNative::missing($target, $native, $uk))->toBeFalse("«{$native}» is Ukrainian");
    }
    // Written lines, dense with the list's own words and one or none of the others.
    foreach (['Добрий день! Чи є у вас вільний час?', 'Вибачте, я можу прийти завтра?', 'Гаразд, якщо можливо, о десятій.', 'Він там, біля входу, з лівого боку.'] as $native) {
        expect(ReplyNative::missing('—', $native, $uk))->toBeFalse("«{$native}» is Ukrainian");
    }
    // The same lines in Russian under a Ukrainian learner: no translation.
    expect(ReplyNative::missing('He said it is very urgent.', 'Он сказал, что это очень срочно.', $uk))->toBeTrue()
        ->and(ReplyNative::missing('I only need to know where it is and when I can come.', 'Мне нужно только узнать, где это и когда можно прийти.', $uk))->toBeTrue()
        // The Ukrainian list refuses a Ukrainian line under a Russian learner…
        ->and(ReplyNative::missing('Of course. What seems to be the problem?', 'Звісно. Що вас турбує?', $ru))->toBeTrue()
        ->and(ReplyNative::missing('Could you give me the clinic address?', 'Не могли б ви дати мені адресу клініки?', $ru))->toBeTrue()
        // …and never an ordinary Russian or Belarusian one for a word the two share with Ukrainian.
        ->and(ReplyNative::missing('Please come before twelve to book.', 'Для записи к врачу приходите до двенадцати.', $ru))->toBeFalse()
        ->and(ReplyNative::missing('Thank you, I will come tomorrow morning.', 'Дзякуй, я прыйду заўтра раніцай.', lessonPacks()->for('be')))->toBeFalse()
        // «Добрий день! Чи є…» is Ukrainian for a Russian learner too — and «Добрый день! Есть ли…» stays Russian.
        ->and(ReplyNative::missing('—', 'Добрий день! Чи є у вас вільний час?', $ru))->toBeTrue()
        ->and(ReplyNative::missing('—', 'Добрый день! Есть ли у вас свободное время?', $ru))->toBeFalse();
});

// Canon (the order's update to LANG-1 §5): «common_words — частые и отличительные: ни одно не обычное слово соседа той же
// письменности (ru, be)». What the neighbours' packs call their own words — their frequent and their service words — is
// read as their ordinary words, and a few ordinary words no neighbour list holds are named here by hand: the Belarusian
// noun «від» (kind, view), «маю», «бачу», «або»; the Russian «коли» (stab!), «ласка», «ось», «прошу», «хочу», «можете».
// CATCHES a word added to the Ukrainian list that a Russian or a Belarusian line says as its own — two of them refuse an
// honest line of that learner.
it('writes frequent words no Cyrillic neighbour says as its own', function () {
    $uk = lessonPacks()->for('uk')->commonWords();
    $theirs = ['від', 'маю', 'бачу', 'або', 'ні', 'з', 'і', 'але', 'як', 'коли', 'ласка', 'ось', 'прошу', 'хочу', 'можете', 'може', 'тебе', 'добре', 'для', 'до', 'на', 'не', 'так', 'можна', 'зараз', 'вас'];
    foreach (['ru', 'be'] as $code) {
        $pack = lessonPacks()->for($code);
        foreach (['common_words', 'function_words'] as $key) {
            $theirs = [...$theirs, ...($pack->has($key) ? $pack->words($key) : [])];
        }
    }

    expect(count($uk))->toBeGreaterThanOrEqual(30)
        ->and(array_values(array_intersect($uk, $theirs)))->toBe([])
        ->and(array_values(array_filter($uk, static fn (string $word): bool => preg_match('/^[\p{L}\p{M}]+$/u', $word) !== 1)))->toBe([]);
});

// Canon (наряд BACK-TAILS-1 §3.2): «буква чужой письменности — фатально, всё прочее вне письменности — предупреждение». The
// scouting day uk→en wrote the Russian «э» in five readings. CATCHES a strict alphabet in `script_letters` (the day failed
// for a Russian letter) and a `script` that lets «э», «ы», «ё» pass as Ukrainian spelling.
it('warns of a Russian letter in a Ukrainian reading and never fails it', function () {
    $words = new LanguageWords(lessonPacks()->for('uk'));

    expect($words->readsInScript('Айд лайк ту бук е докторз епойнтмент.'))->toBeTrue()
        ->and($words->readsInScript('___ воркс бетер фор мі.'))->toBeTrue()
        ->and($words->readsInScript('лі́кар, п’ятниця'))->toBeTrue()
        ->and($words->readsInScript('Ай хев э сор сроут энд э фівер.'))->toBeFalse()
        ->and($words->foreignLetters('Ай хев э сор сроут энд э фівер.'))->toBe([])
        ->and($words->readsInScript('Зетс файн эт срі.'))->toBeFalse()
        ->and($words->foreignLetters('Зетс файн эт срі.'))->toBe([])
        ->and($words->readsInScript('ёлка, ы, ъ'))->toBeFalse()
        ->and($words->foreignLetters('фoр'))->toBe(['o']);
});

// Canon (FRAMES v4.5, TEXT QUALITY): «нативный каркас без слова, согласованного со слотом», «мужской род о ученике — только
// когда иначе нельзя». CATCHES a frame of the scouting day flagged for a word that agrees with nothing («У нього ___», «Чому
// ___?» end like adjectives), a real agreement missed, and «Я б хотів» — the day's own masculine line — passed.
it('reads the learner\'s gender and a word agreeing with the slot in a Ukrainian line', function () {
    $words = new LanguageWords(lessonPacks()->for('uk'));

    foreach (['Я б хотів записатися на ___.', 'У мене ___.', '___ мені підходить краще.', 'Не могли б ви дати мені ___?', 'Я прийду ___.', 'Ви працюєте ___?', 'Добре, ___ мені підходить.', 'Я принесу ___.', 'У нього ___.', 'Чому ___?', 'Мені потрібно ___.', 'Це почалося ___ тому.', 'Що таке ___?', 'Я оплачу карткою ___.'] as $frame) {
        expect($words->agreeingWithSlot($frame))->toBe([], $frame);
    }
    expect($words->agreeingWithSlot('Мені потрібен ___.'))->toBe(['потрібен'])
        ->and($words->agreeingWithSlot('Який ___ вам потрібен?'))->toBe(['який', 'потрібен'])
        ->and($words->agreeingWithSlot('Я хочу новий ___.'))->toBe(['новий'])
        ->and($words->agreeingWithSlot('Аптека ___ відчинена?'))->toBe(['відчинена'])
        ->and($words->agreeingWithSlot('Чи ___ обовʼязкове?'))->toBe(['обовʼязкове'])
        ->and($words->agreeingWithSlot('Чи ___ обов’язкове?'))->toBe(["обов'язкове"])
        ->and($words->genderedPast('Я б хотів записатися на прийом до лікаря.'))->toBe(['хотів'])
        ->and($words->genderedPast('Я вже записалася на завтра.'))->toBe(['записалася'])
        ->and($words->genderedPast('Я не міг прийти вчора.'))->toBe(['міг'])
        // The masculine forms without «-в», a particle or an object pronoun between «я» and the verb.
        ->and($words->genderedPast('Я вам допоміг би, але не можу.'))->toBe(['допоміг'])
        ->and($words->genderedPast('Я знов прийшов на прийом.'))->toBe(['прийшов'])
        ->and($words->genderedPast('Я ж казав, що буду раніше.'))->toBe(['казав'])
        ->and($words->genderedPast('Я вас не почула, повторіть, будь ласка.'))->toBe(['почула'])
        ->and($words->genderedPast('Я вже двічі був у цього лікаря.'))->toBe(['був'])
        ->and($words->genderedPast('Я нічого не їла з ранку.'))->toBe(['їла'])
        ->and($words->genderedPast('Я прийду на десять хвилин раніше.'))->toBe([])
        ->and($words->genderedPast('Я вас слухаю, що вас турбує?'))->toBe([])
        ->and($words->genderedPast('У мене болить горло і є температура.'))->toBe([])
        ->and($words->genderedPast('Моє ім’я Павла, я знов тут.'))->toBe([]);
});

// Canon (LISTENING, `listening.distractor_not_filler`; «Поймай число», 34-7): a value is a number or a time by the learner's
// language. CATCHES «На третю» / «На четверту» of the scouting day read as no number (ordinals), «Точно вчасно» — the frame's
// other filler — read as another kind than «На десять хвилин», and an option «Неділе»-style bare noun without its
// preposition.
it('reads the numbers and times of a Ukrainian line', function () {
    $uk = lessonPacks()->for('uk');
    $words = new LanguageWords($uk);
    $values = NumberValues::of($uk);

    expect($words->valueKind('На третю'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->valueKind('На четверту'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->valueKind('О дев’ятій ранку'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->valueKind('На десять хвилин'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->valueKind('Точно вчасно'))->toBe(LanguageWords::VALUE_MIXED)
        ->and($words->valueKind('Вдень'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->valueKind('Після полудня'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->isNumber('сім’я'))->toBeFalse()
        ->and($words->valueKind('Болить спина'))->toBe(LanguageWords::VALUE_OTHER)
        ->and($words->isNumber('п’ятницю'))->toBeFalse()
        ->and($words->isTime('п’ятницю'))->toBeTrue()
        ->and($values?->value('Будь ласка, прийдіть на десять хвилин раніше для реєстрації.'))->toBe(['text' => 'На десять хвилин раніше', 'number' => true])
        ->and($values?->value('Температура тримається вже три дні.'))->toBe(['text' => 'Три дні', 'number' => true])
        ->and($values?->value('Приходьте у п’ятницю.'))->toBeNull();
});

// Ukrainian writes the `talk_title_template` no-op `[]`, as ru: the title of a talk declines the roles in code
// (NativeStrings::TALK_TITLE). CATCHES a template written for uk that the title would read instead of declining.
it('leaves the title of a Ukrainian talk to the declension in code', function () {
    $uk = lessonPacks()->for('uk');

    expect($uk->talkTitleTemplate())->toBeNull()
        ->and((new NativeStrings('uk'))->talkTitle(['Адміністратор', 'Лікар'], $uk))->toBe('Поговори з адміністратором і лікарем');
});

// Canon (наряд LANG-1, the integrator's find): the Ukrainian and Belarusian apostrophe ʼ (U+02BC) is a letter of no
// alphabet — Unicode's Common script — so a reading spelled with it is not a foreign-script reading (FATAL); a letter
// of another writing still is.
it('reads the apostrophe ʼ as nobody\'s letter, and an Armenian or Latin letter still as foreign', function () {
    $uk = new LanguageWords(lessonPacks()->for('uk'));
    $be = new LanguageWords(lessonPacks()->for('be'));

    expect($uk->foreignLetters('пʼять хвилин'))->toBe([])
        ->and($be->foreignLetters('інтэрвʼю'))->toBe([])
        ->and($uk->foreignLetters('ֆоутоуз'))->toBe(['ֆ'])
        ->and($uk->foreignLetters('пʼять o'))->toBe(['o']);
});
