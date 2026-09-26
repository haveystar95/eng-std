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
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\ReplyNative;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Shared\Domain\Service\SpokenNumbers;

/**
 * THE BELARUSIAN PACK (наряд LANG-1, `config/lesson/lang/be.php`) — a learner's own language only: the reading of the
 * target, the native frames, the listening, the role's translated line, the title of the talk. Every line below that
 * is not marked «written» is the model's own Belarusian from the scouting run of the order (be→en, «Запіс на прыём»,
 * `docs/research/lang-1/days/be-en.md`, `answers/be-en.json`), read with the deployment's packs (`lessonPacks()`).
 */

// Canon (pack-keys §7.2; the order: «Skips check: be→en native side empty»). CATCHES a native key of the Belarusian pack
// left null or missing — a check the live pair be→en would skip as `lang.pack_missing` — a key written in a shape its
// reader throws on, and a key written as null at all (the order: «NO key may be null»). Belarusian is no plan's target, so
// only the native side is read here.
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

    expect(LanguageRoles::planTargets())->not->toContain($code)
        ->and(LanguageRoles::planNatives())->toContain($code)
        ->and($gaps('native'))->toBe([]);
    foreach (['abbreviations', 'amount_pattern', 'amount_prefix', 'neutral_reply', 'common_words'] as $key) {
        expect($pack->has($key))->toBeTrue("the native side reads `{$key}`");
    }
    expect($pack->talkTitleTemplate())->not->toBeNull()
        ->and((new NativeStrings($code))->talkTitle(['Recepcjonistka', 'MRI'], $pack))->toContain('MRI')
        ->and(NumberValues::of($pack))->not->toBeNull();
    $words->agreeingWithSlot('a b ___ c d.');
    $words->genderedPast('a b');
    $words->foreignLetters('ab');
    $words->readsInScript('ab');
    $words->valueKind('a 2');

    $written = require dirname(__DIR__, 4)."/config/lesson/lang/{$code}.php";
    expect(array_keys(array_filter($written, static fn (mixed $value): bool => $value === null)))->toBe([]);
})->with(['be']);

// Canon (the order: «SentenceEnds on 5 real lines of the language, with its abbreviations»). CATCHES a pack whose marks
// miss a Belarusian question, and an abbreviation of a clinic's or a timetable's text («гадз.», «вул.», «д.», «і г. д.»,
// «г. зн.», «і інш.») read as the end of a sentence — a native frame or line then counted as two sentences, or a value as
// a sentence of its own. The lines with abbreviations are written (the scouting day has none); the rest are the model's.
it('ends a Belarusian sentence where Belarusian ends it, and never on an abbreviation', function () {
    $ends = lessonPacks()->for('be')->sentenceEnds();

    expect($ends)->not->toBeNull();
    expect($ends->sentences('Вядома. Што вас турбуе?'))->toBe(['Вядома', 'Што вас турбуе'])
        ->and($ends->terminalKind('Вядома. Што вас турбуе?'))->toBe('question')
        ->and($ends->count('Гатова. Вы запісаны на сёння а чацвёртай.'))->toBe(2)
        ->and($ends->count('Рэгістратар прапануе два варыянты: сёння а 4-й і заўтра а 9-й.'))->toBe(1)
        ->and($ends->terminalKind('Скажыце, калі ласка, павольней.'))->toBe('statement')
        ->and($ends->count('Калі ласка, запішыце мяне на сёння а чацвёртай.'))->toBe(1)
        ->and($ends->closesText('Калі ласка, запішыце мяне на ___'))->toBeFalse()
        // written: the abbreviations of the pack
        ->and($ends->count('Прыходзьце заўтра а 9 гадз. на вул. Незалежнасці, д. 5, кв. 12.'))->toBe(1)
        ->and($ends->count('Вазьміце пашпарт, медкарту і г. д.'))->toBe(1)
        ->and($ends->terminal('Вазьміце пашпарт, медкарту і г. д.'))->toBe('.')
        ->and($ends->count('Прыходзьце ў пятніцу, г. зн. праз два дні.'))->toBe(1)
        ->and($ends->count('Вазьміце пашпарт, даведкі і інш. дакументы.'))->toBe(1)
        ->and($ends->count('Прыём праз 15 хв. у кабінеце № 3, тэл. 123-45-67.'))->toBe(1)
        ->and($ends->carriesSentence('а 9 гадз.'))->toBeFalse()
        ->and($ends->carriesSentence('Да сустрэчы.'))->toBeTrue();
});

// Canon (the order, (c) for a native-only pack). The judge of the talk's constructions reads the TARGET's pack only
// (`ConversationMoves`: `$this->packs->for($plan->targetLang())`), and Belarusian is no plan's target
// (`LanguageRoles::planTargets()`): nothing here runs for a real plan. Read as a target anyway, the pack forgives no
// opening word, no clause starter, no contraction and no partitive — its judge keys are no-ops — and only the negation
// the order has every pack write («не», «ні», anywhere: dead data here). CATCHES a native-only pack that grew target
// rules nobody reads and nobody checked on a live talk.
it('forgives nothing as a target but the negation it writes, and is never read so', function () {
    $be = lessonPacks()->for('be');
    $judge = new FrameJudge;
    $frame = [new ConversationPhrase('x', 'p6', 'Мне падыходзіць ___.', '', null, null)];

    expect(LanguageRoles::planTargets())->not->toContain('be')
        ->and($judge->move('Мне падыходзіць чацвёртая.', $frame, $be)->said)->toBe(['x:p6'])
        ->and($judge->move('Так, мне падыходзіць чацвёртая.', $frame, $be)->said)->toBe([])
        ->and($judge->move('Я ўсё зразумеў, і мне падыходзіць чацвёртая.', $frame, $be)->said)->toBe([])
        ->and($judge->move('Мне не падыходзіць чацвёртая.', $frame, $be)->said)->toBe(['x:p6']);
});

// Canon (the order: «numbers: SpokenNumbers::fold with the pack's speech() lists on 3–5 real number phrases»). The number
// words are dead data for Belarusian (only the target's speech is compared), written so the pack says its numbers:
// nominative forms, tens and units joined with no word. CATCHES an entry misspelt (akanne: «пяцьдзясят», «васямсот»), a
// value not a string, a joiner Belarusian does not say — and an ordinal («а чацвёртай» — at four) read as a number.
it('reads the Belarusian number words as one number each', function () {
    $speech = lessonPacks()->for('be')->speech();
    $fold = static fn (string $text): array => SpokenNumbers::fold(
        explode(' ', (new LexicalNormalizer)->canonicalize($text)),
        $speech->numberWords, $speech->articles, $speech->numberJoiners, $speech->numberTensJoiners,
    );

    expect($fold('Ужо тры дні.'))->toBe(['ужо', '3', 'дні'])
        ->and($fold('Адзін дзень'))->toBe(['1', 'дзень'])
        ->and($fold('Ужо два дні'))->toBe(['ужо', '2', 'дні'])
        ->and($fold('сёння а чацвёртай'))->toBe(['сёння', 'а', 'чацвёртай'])
        // written
        ->and($fold('Прыходзьце праз дваццаць пяць хвілін'))->toBe(['прыходзьце', 'праз', '25', 'хвілін'])
        ->and($fold('Прыём каштуе дзвесце пяцьдзясят рублёў'))->toBe(['прыём', 'каштуе', '250', 'рублёў'])
        ->and($fold('дзве тысячы дваццаць шэсць'))->toBe(['2026']);
});

// Canon (pack-keys §3.11–3.14 on the scouting day's own native text). A value of «Поймай число» is the amount as the line
// says it: «а чацвёртай» with the «а» of a time (as ru «в четыре»), «праз тыдзень» with its preposition. CATCHES a
// number pattern that takes a friend («друг», «друга») or a rooster («пятух») for an ordinal, a family («сям'я») for
// «сям-», «аднак» for «адн-», and an «а» of a time left off the option («Чацвёртай» — a form nobody answers with).
it('reads the values a Belarusian line says, and no ordinary word as a number', function () {
    $be = lessonPacks()->for('be');
    $words = new LanguageWords($be);
    $values = NumberValues::of($be);

    expect($values)->not->toBeNull();
    expect($values->values('Сёння а чацвёртай або заўтра а дзявятай.'))->toBe([
        ['text' => 'А чацвёртай', 'number' => true], ['text' => 'А дзявятай', 'number' => true],
    ])
        ->and($values->value('Ужо тры дні.'))->toBe(['text' => 'Тры дні', 'number' => true])
        ->and($values->value('Час а чацвёртай яшчэ вольны.'))->toBe(['text' => 'А чацвёртай', 'number' => true])
        // written
        ->and($values->value('Прыходзьце праз тыдзень.'))->toBe(['text' => 'Праз тыдзень', 'number' => false])
        ->and($values->value('Прымайце лекі на працягу тыдня.'))->toBe(['text' => 'На працягу тыдня', 'number' => false])
        ->and($words->valueKind('Двойчы на дзень'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME)
        ->and($words->valueKind('Другі паверх'))->toBe(LanguageWords::VALUE_MIXED)
        ->and($words->valueKind('Мой друг'))->toBe(LanguageWords::VALUE_OTHER)
        ->and($words->valueKind('Да сябра і яго друга'))->toBe(LanguageWords::VALUE_OTHER)
        ->and($words->valueKind('Пятух'))->toBe(LanguageWords::VALUE_OTHER)
        ->and($words->valueKind("Мая сям'я"))->toBe(LanguageWords::VALUE_OTHER)
        ->and($words->valueKind('Аднак сотавы тэлефон'))->toBe(LanguageWords::VALUE_OTHER)
        ->and($words->valueKind('У чацвер'))->toBe(LanguageWords::VALUE_NUMBER_OR_TIME);
});

// Canon (LANG-1 §5 with the main session's update: «common_words — частые и отличительные»). The guard of the role's
// translation keeps an ordinary Belarusian line — five of the model's own — with the deployment's packs, the Cyrillic
// neighbours ru and uk among them, and refuses a Russian and a Ukrainian line under a Belarusian learner; the other way
// round, Belarusian under a Russian or a Ukrainian learner is refused by the Belarusian words. CATCHES a Belarusian list
// that holds a word Russian or Ukrainian also say («і», «ці», «да», «але») — which would count against their lines — and a
// list too thin to tell Belarusian at all.
it('keeps Belarusian lines a translation and tells them from Russian and Ukrainian', function () {
    $packs = lessonPacks();
    $be = $packs->for('be');
    $kept = [
        'Вядома. Што вас турбуе?',
        'Так, у мяне таксама ёсць тэмпература.',
        'Гатова. Вы запісаны на сёння а чацвёртай.',
        'Час а чацвёртай яшчэ вольны.',
        'Як даўно гэта ў вас?',
        // written: the pack's neutral line and a line of the Russian guard's own probe, in Belarusian
        'Зразумела. Працягвайце, калі ласка.',
        'Для запісу да лекара прыходзьце да дванаццаці.',
        "Добра, пачакайце хвілінку. Мая сям'я таксама тут.",
    ];
    $belarusian = [
        'Вядома. Што вас турбуе?',
        // written
        'Добры дзень! Чым я магу вам дапамагчы?',
        'Вы можаце прыйсці заўтра а дзявятай раніцы.',
        'Не хвалюйцеся, усё будзе добра.',
    ];

    expect(array_values(array_filter($kept, static fn (string $line): bool => ReplyNative::missing('—', $line, $be))))->toBe([])
        ->and(ReplyNative::missing('—', 'Я не знаю, что это такое и где врач.', $be))->toBeTrue()
        ->and(ReplyNative::missing('—', 'Я не знаю, що це таке і де лікар.', $be))->toBeTrue();
    foreach ($belarusian as $line) {
        expect(ReplyNative::missing('—', $line, $packs->for('ru')))->toBeTrue("ru ← {$line}")
            ->and(ReplyNative::missing('—', $line, $packs->for('uk')))->toBeTrue("uk ← {$line}");
    }
    // …and never an ordinary Russian or Ukrainian line under its own learner for a word Belarusian also says.
    expect(ReplyNative::missing('—', 'Яна, проходите, пожалуйста. Как давно у вас это?', $packs->for('ru')))->toBeFalse()
        ->and(ReplyNative::missing('—', 'Але зараз у мене немає часу, можна завтра?', $packs->for('uk')))->toBeFalse();
});

// Canon (the order: «a word that is also ordinary in the learner's language but sits only in the neighbour's list makes
// ordinary lines look foreign»). As far as the Cyrillic neighbours' own packs name their ordinary words — their frequent
// and their function words —, no frequent Belarusian word is one of them, and every one is a single run of letters.
// CATCHES a word added to the Belarusian list that the Russian or the Ukrainian pack says is theirs.
it('writes frequent words no Cyrillic neighbour calls its own', function () {
    $be = lessonPacks()->for('be')->commonWords();
    $theirs = [];
    foreach (['ru', 'uk'] as $code) {
        $pack = lessonPacks()->for($code);
        foreach (['common_words', 'function_words'] as $key) {
            $theirs = [...$theirs, ...($pack->has($key) ? $pack->words($key) : [])];
        }
    }

    expect(count($be))->toBeGreaterThanOrEqual(30)
        ->and(array_values(array_intersect($be, $theirs)))->toBe([])
        ->and(array_values(array_filter($be, static fn (string $word): bool => preg_match('/^[\p{L}\p{M}]+$/u', $word) !== 1)))->toBe([]);
});

// Canon (LANG-1 §6: the code declines no Belarusian role). CATCHES a template that inflects, capitalises a role in the
// middle of the title, lowers an acronym, or joins the roles with anything but «і».
it('titles a talk with its roles as written, joined with «і»', function () {
    $be = lessonPacks()->for('be');
    $strings = new NativeStrings('be');

    expect($strings->talkTitle(['Рэгістратар', 'Лекар'], $be))->toBe('Размова: рэгістратар і лекар')
        ->and($strings->talkTitle(['Рэгістратар', 'Медсястра', 'Лекар'], $be))->toBe('Размова: рэгістратар, медсястра і лекар')
        ->and($strings->talkTitle(['МРТ-тэхнік', 'Лекар'], $be))->toBe('Размова: МРТ-тэхнік і лекар')
        ->and($strings->talkTitle([], $be))->toBe('Размова');
});

// Canon (pack-keys §3.1, §3.2, §3.27 on the scouting day's own native text). CATCHES a strict alphabet written in
// `script_letters` (the Russian «и» of the model's reading «уот из…» would fail the day — it must stay the WARNING of
// `script`), a `script` that lets the Russian «и» through, an apostrophe read as a letter, and a gendered past the pattern
// misses («запісаўся», «была», «Я б хацела», «Я вас не пачуў») or invents — «зноў» (again), «мала» (little) and the «я»
// of «сям'я» are no past forms.
it('reads the learner\'s side of the scouting day as Belarusian', function () {
    $words = new LanguageWords(lessonPacks()->for('be'));

    expect($words->readsInScript('айд лайк ту бук э докторз эпойнтмэнт'))->toBeTrue()
        ->and($words->readsInScript('куд ю сэй зэт мор слоўлі, пліз'))->toBeTrue()
        ->and($words->readsInScript('уот из зэ нірыст эвэйлэбл тайм'))->toBeFalse()
        ->and($words->foreignLetters('уот из зэ нірыст эвэйлэбл тайм'))->toBe([])
        ->and($words->foreignLetters('фівер'))->toBe([])
        ->and($words->foreignLetters('fівер'))->toBe(['f'])
        ->and($words->genderedPast('Я хачу запісацца на прыём да лекара.'))->toBe([])
        // written
        ->and($words->readsInScript("інтэрв'ю, інтэрв’ю"))->toBeTrue()
        ->and($words->foreignLetters("інтэрв'ю, інтэрв’ю"))->toBe([])
        ->and($words->foreignLetters('зэ нірыст'))->toBe([])
        ->and($words->genderedPast('Я ўжо запісаўся да лекара.'))->toBe(['запісаўся'])
        ->and($words->genderedPast('Я была ў лекара ўчора.'))->toBe(['была'])
        ->and($words->genderedPast('Я б хацела запісацца на заўтра.'))->toBe(['хацела'])
        ->and($words->genderedPast('Прабачце, я вас не пачуў.'))->toBe(['пачуў'])
        ->and($words->genderedPast('Учора я не змог прыйсці.'))->toBe(['змог'])
        ->and($words->genderedPast('Я ўжо дапамог сыну.'))->toBe(['дапамог'])
        ->and($words->genderedPast('Я зноў прыйшоў да вас.'))->toBe(['прыйшоў'])
        ->and($words->genderedPast('Я зноў тут.'))->toBe([])
        ->and($words->genderedPast('Я мала сплю і шмат кашляю.'))->toBe([])
        ->and($words->genderedPast("Мая сям'я ўжо была тут."))->toBe([]);
});

// Canon (pack-keys §3.28 on the scouting day's frames and written ones). The agreement is read at the slot: «Які ___?» of
// the scouting day agrees (the model's own frame p5 — the warning is right), «Калі ласка, запішыце мяне на ___» does not.
// CATCHES an adjective ending that is also a question word («чаму», «каму»: «-аму»), «таму», a verb («працую»: «-ую»,
// «прымае», «знае»: «-ае», «паглядзім»: «-ім», «давай»: «-ай»), a noun («кансультацыя»: «-ыя», «алергія»: «-ія», «сабой»:
// «-ой») or «Што такое ___?» read as a word that agrees — and an agreement rule deaf to «наступную ___», «бліжэйшай ___»,
// «___ вольны?».
it('reads a word that agrees with the slot, and no verb, noun or question word for one', function () {
    $words = new LanguageWords(lessonPacks()->for('be'));
    $silent = [
        'Калі ласка, запішыце мяне на ___', 'У мяне таксама ___', 'Мне падыходзіць ___', 'Я хачу запісацца на ___',
        'Ужо ___',
        // written
        'Чаму ___?', 'Каму ___?', 'Таму ___.', 'Колькі ___?', 'Мне трэба ___.', 'Мне патрэбна кансультацыя ___.',
        'У мяне алергія ___.', 'Я працую ___.', 'Я рэкамендую ___.', 'Лекар прымае ___.', 'Ён знае ___.', 'Мы паглядзім ___.',
        'Давай ___.', 'Вазьміце з сабой ___.', 'Што такое ___?', 'Дзякую за ___.',
    ];

    expect(array_values(array_filter($silent, static fn (string $frame): bool => $words->agreeingWithSlot($frame) !== [])))->toBe([])
        ->and($words->agreeingWithSlot('Які ___?'))->toBe(['які'])
        // written
        ->and($words->agreeingWithSlot('Запішыце мяне на наступную ___.'))->toBe(['наступную'])
        ->and($words->agreeingWithSlot('У бліжэйшай ___.'))->toBe(['бліжэйшай'])
        ->and($words->agreeingWithSlot('Мне патрэбен ___.'))->toBe(['патрэбен'])
        ->and($words->agreeingWithSlot('Ці ___ вольны?'))->toBe(['вольны'])
        ->and($words->agreeingWithSlot('Гэта мой ___.'))->toBe(['мой']);
});
