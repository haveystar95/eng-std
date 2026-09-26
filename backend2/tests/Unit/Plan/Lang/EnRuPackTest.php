<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\NumberValues;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Language\PackSkip;
use App\Modules\Plan\Domain\Check\Language\SentenceEnds;
use App\Modules\Plan\Domain\Check\Lesson\FillerRules;
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
 * THE ENGLISH AND THE RUSSIAN PACKS AFTER LANG-1 (наряд LANG-1, исполнитель en/ru). Both were complete for their sides
 * before the order — English as the only-target, Russian as the only-native — and LANG-1 adds to them only what the other
 * packs need of them: English's letters and frequent words (English is every Latin learner's neighbour), Russian's frequent
 * words (the Cyrillic learners' neighbour), and the no-ops of the new keys (`number_tens_joiners`, `talk_title_template`).
 * So every reader of the two packs reads what it read before, and only the guard of the role's translation
 * ({@see ReplyNative}) reads something new. The keys of the side each language never plays (English's learner keys,
 * Russian's target keys) were null and are the key spec's no-ops now (the order's update: no key is null) — read, where
 * anything could read them, exactly as the nulls were.
 *
 * The lines are the scouting run's (`docs/research/lang-1/days/*.md`: pl-en, uk-en, be-en for English, ru-pl, ru-fr, ru-de
 * for Russian) unless a comment says «natural» — a line written here where the run had none of the kind (an abbreviation
 * of Russian, a negated learner line). Every pack is the deployment's own ({@see lessonPacks()}); a row that needs a
 * neighbour's pack — Polish, Ukrainian… — skips until that pack writes its letters and its frequent words.
 */

// Canon (pack-keys §7.2, the pair ru → en of the order): no check of either side goes unrun for want of a key, and every
// reader outside the validator reads the pack in the shape it reads it. CATCHES a key of the pack written as null or in the
// wrong shape (a `LanguagePackKeyMissing` from a reader), and a pack that lost a key LANG-1 asks of it.
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

    if (in_array($code, LanguageRoles::planTargets(), true)) {
        expect($gaps('target'))->toBe([]);
        foreach (['abbreviations', 'question_word_order', 'unstressed_words', 'number_words', 'number_joiners', 'number_tens_joiners', 'irregular_forms', 'inflection_rules', 'person_swap', 'contractions', 'contractions_before', 'intro_words', 'clause_starters', 'negation', 'partitive', 'dangling_words', 'rescue_line', 'neutral_reply', 'script_letters', 'common_words'] as $key) {
            expect($pack->has($key))->toBeTrue("the target side reads `{$key}`");
        }
        expect(NumberValues::of($pack))->not->toBeNull();
        // Each call throws on a key written in the wrong shape.
        (new FrameJudge)->move('a b c', [new ConversationPhrase('x', 'p1', 'A ___.', '', null, null)], $pack);
        (new FrameJudge)->breaksOff('a b', [], $pack);
        (new LineShare)->share('a b', 'b a', $pack, swapPersons: true);
        WordBases::of('abc', $pack);
        $words->isQuestion('a b');
        $words->asksTwice('a, b?');
        $words->isCloser('a');
        $words->clause('a b c');
        $words->articleMismatch('a', 'b');
        $words->unresolvedPronoun('a b ___.');
        $words->valueKind('a 2');
    }
    if (in_array($code, LanguageRoles::planNatives(), true)) {
        expect($gaps('native'))->toBe([]);
        foreach (['abbreviations', 'amount_pattern', 'amount_prefix', 'neutral_reply', 'script_letters', 'common_words'] as $key) {
            expect($pack->has($key))->toBeTrue("the native side reads `{$key}`");
        }
        expect(NumberValues::of($pack))->not->toBeNull();
        $words->agreeingWithSlot('a b ___ c d.');
        $words->genderedPast('a b');
        $words->foreignLetters('ab');
        $words->readsInScript('ab');
        $words->valueKind('a 2');
    }
    expect($pack->commonWords())->not->toBe([]);
})->with(['en', 'ru']);

// The order: «ru→en (both sides) must stay empty». CATCHES any skip of the live pair at all — of either language, of
// either side — whatever the filter of the row above lets through.
it('leaves no check of the pair ru → en unrun for want of a key', function () {
    $context = lessonContext('ru', 'en');
    $request = new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays);
    (new LessonValidator)->run((new LessonParser)->parse(FakePlanModel::lessonPayload($request)), $context);

    expect(array_map(static fn (PackSkip $skip): array => $skip->toArray(), $context->skips->all()))->toBe([]);
});

// Canon (LANG-1: «en/ru behaviour byte-identical except the guard»). The new keys are no-ops for every reader but the guard:
// the speech block the phone is served has no `number_tens_joiners` (an empty list goes out as none), neither pack hands
// out a talk title template (the code declines Russian roles and builds the English title), and the letters are the key
// spec's reference strings — the guard finds neighbours by the very string. CATCHES a no-op that changes the wire, a
// template read for ru or en, and a letters pattern spelt otherwise (English would then be nobody's neighbour).
it('keeps the new keys no-ops for every reader but the guard', function (string $code, string $letters) {
    $pack = lessonPacks()->for($code);

    expect($pack->speech()->toArray())->not->toHaveKey('number_tens_joiners')
        ->and($pack->speech()->numberTensJoiners)->toBe([])
        ->and($pack->talkTitleTemplate())->toBeNull()
        ->and($pack->asNeighbour()['script_letters'])->toBe($letters);
})->with([
    'en' => ['en', '/^[\p{Latin}]$/u'],
    'ru' => ['ru', '/^[\p{Cyrillic}]$/u'],
]);

// Canon (pack-keys §3.3–3.4, the scouting lines): a dot of «Dr.», «a.m.», «p.m.» (ru «т. д.», «ул.») ends no sentence inside a
// line and closes it at its very end; every other end mark ends one. CATCHES a list that lost an abbreviation the model
// writes (the role's «with Dr. Lee» read as two sentences — a partner line «too long»), or one that swallows a real end.
it('cuts the scouting lines into sentences where they end, never at an abbreviation', function (string $code, string $line, int $count, string $kind) {
    $ends = new SentenceEnds(lessonPacks()->for($code));

    expect($ends->count($line))->toBe($count)
        ->and($ends->terminalKind($line))->toBe($kind);
})->with([
    'en: Dr. inside' => ['en', "You're booked for 3 p.m. today with Dr. Lee.", 1, 'statement'],
    'en: a.m. and p.m. inside' => ['en', 'We have 10 a.m. and 3 p.m. today.', 1, 'statement'],
    'en: p.m. at the very end' => ['en', "You're booked for tomorrow at 3 p.m.", 1, 'statement'],
    'en: two sentences' => ['en', "Yes. It's 12 Green Street, near the pharmacy.", 2, 'statement'],
    'en: a closing question' => ['en', 'Of course. What seems to be the problem?', 2, 'question'],
    'ru: a closing question' => ['ru', 'Хорошо. По какому вопросу вы хотите записаться?', 2, 'question'],
    'ru: three sentences' => ['ru', 'Я записала. Приём завтра в десять. Приходите за десять минут до начала.', 3, 'statement'],
    'ru: digits and a dash' => ['ru', 'Мой номер — 06 12 34 56 78.', 1, 'statement'],
    'ru: ул. inside (natural)' => ['ru', 'Клиника на ул. Садовой, приходите к девяти.', 1, 'statement'],
    'ru: т. д. at the very end (natural)' => ['ru', 'Возьмите паспорт, полис и т. д.', 1, 'statement'],
]);

// …and a slot's value that is an abbreviation carries no sentence of its own (the filler «3 p.m.» of it-en, the name «Dr.
// Brown» of uk-en). CATCHES the CHECK-1 regression: the filler «3 p.m.» read as «a filler with its own full stop» (fatal).
it('reads an abbreviation\'s value as no sentence of its own', function () {
    $en = new SentenceEnds(lessonPacks()->for('en'));

    expect($en->carriesSentence('3 p.m.'))->toBeFalse()
        ->and($en->carriesSentence('Dr. Brown'))->toBeFalse()
        ->and($en->carriesSentence('3 p.m. works for me.'))->toBeTrue();
});

// Canon (FIX-4 §2, DECISIONS п. 395; the frames of the scouting days): a frame is said as a coherent phrase where the move
// begins or after its opening words, a negative is the same construction, one difference of words is «almost». CATCHES an
// English pack whose judge keys stopped reading as before: an opening «Okay» that breaks the frame, «don't have» not taken
// for «have», a contraction not spelt out, a value that loses its article.
it('judges real English learner lines against their frames', function (string $frame, string $heard, MoveVerdict $verdict) {
    $move = (new FrameJudge)->move($heard, [new ConversationPhrase('x', 'p1', $frame, '', null, null)], lessonPacks()->for('en'));

    expect($move)->toEqual($verdict);
})->with([
    'pl-en p2, said' => ['I have ___', 'I have a sore throat and fever.', new MoveVerdict(['x:p1'], [], ['x:p1' => 'a sore throat and fever'])],
    'pl-en p4, said' => ['Can I come ___', 'Can I come today at four?', new MoveVerdict(['x:p1'], [], ['x:p1' => 'today at four'])],
    'pl-en p6, said after «Okay»' => ["I'll bring ___", "Okay, I'll bring my ID.", new MoveVerdict(['x:p1'], [], ['x:p1' => 'my ID'])],
    'be-en p7, said' => ['Please book me for ___', 'Please book me for today at four.', new MoveVerdict(['x:p1'], [], ['x:p1' => 'today at four'])],
    'uk-en p3, window first' => ['___ works better for me.', 'Three works better for me.', new MoveVerdict(['x:p1'], [], ['x:p1' => 'Three'])],
    'uk-en p2, negated (natural)' => ['I have ___.', "No, I don't have a fever.", new MoveVerdict(['x:p1'], [], ['x:p1' => 'a fever'])],
    'pl-en p5, «is» left out' => ['My name is ___', 'My name Jan Kowalski.', new MoveVerdict([], ['x:p1'])],
    'be-en p6, «works» said «work»' => ['___ works for me', "Four o'clock work for me.", new MoveVerdict([], ['x:p1'])],
]);

// Russian is never a target (LanguageRoles::planTargets()): the judge of the talk reads the TARGET's pack only
// (ConversationMoves: `$this->packs->for($plan->targetLang())`), so the judge keys of ru.php — `contractions`, `intro_words`,
// `clause_starters`, `negation`, `partitive`, all `[]` — are dead data, written so that a talk held in Russian would be judged
// by the frame's own words and FORGIVE NOTHING. CATCHES a Russian pack that starts forgiving (an opening «Да», a «не» inside
// the frame) without anybody deciding Russian becomes a target — where the English pack forgives both.
it('forgives nothing as a target — Russian is never one, and the judge never reads its keys', function () {
    $ru = lessonPacks()->for('ru');
    $frame = [new ConversationPhrase('x', 'p1', 'Я хочу записаться ___.', '', null, null)];
    $judge = new FrameJudge;

    expect($judge->move('Я хочу записаться к врачу.', $frame, $ru))->toEqual(new MoveVerdict(['x:p1'], [], ['x:p1' => 'к врачу']))
        ->and($judge->move('Я не хочу записаться к врачу.', $frame, $ru)->said)->toBe([])
        ->and($judge->move('Да, я хочу записаться к врачу.', $frame, $ru)->said)->toBe([])
        ->and(in_array('ru', LanguageRoles::planTargets(), true))->toBeFalse()
        ->and($judge->move("Yes, I don't have a fever.", [new ConversationPhrase('x', 'p1', 'I have ___.', '', null, null)], lessonPacks()->for('en'))->said)->toBe(['x:p1']);
});

// Canon (FIX-3 §4, LANG-1 §4): a number said in words is the number in digits, the words of one number read as one, with
// the pack's own lists as `speech()` hands them down. English joins «and» after a SCALE only — «between twenty and one
// hundred» stays two numbers, since English writes no tens joiner; Russian folds only the nominative forms. CATCHES a pack
// list that stops folding the scouting days' numbers («ten minutes», «в пятнадцать»), and a tens joiner that crept into
// English (the verifier's «between 2100 dollars»).
it('folds the scouting days\' numbers with the pack\'s own lists', function (string $code, string $text, array $folded) {
    $speech = lessonPacks()->for($code)->speech();
    $words = explode(' ', (new LexicalNormalizer)->canonicalize($text));

    expect(SpokenNumbers::fold($words, $speech->numberWords, $speech->articles, $speech->numberJoiners, $speech->numberTensJoiners))->toBe($folded);
})->with([
    'en uk-en B5' => ['en', "I'll arrive ten minutes early.", ['i', 'will', 'arrive', '10', 'minutes', 'early']],
    'en pl-en B4' => ['en', 'Can I come today at four?', ['can', 'i', 'come', 'today', 'at', '4']],
    'en be-en B3' => ['en', 'For three days.', ['for', '3', 'days']],
    'en a scale and «and»' => ['en', 'one hundred and twenty', ['120']],
    'en no tens joiner' => ['en', 'between twenty and one hundred', ['between', '20', 'and', '100']],
    'ru ru-fr A8' => ['ru', 'Приходите за десять минут до начала.', ['приходите', 'за', '10', 'минут', 'до', 'начала']],
    'ru ru-pl B5' => ['ru', 'Завтра в пятнадцать мне подходит.', ['завтра', 'в', '15', 'мне', 'подходит']],
    'ru ru-fr B2' => ['ru', 'Уже три дня.', ['уже', '3', 'дня']],
    'ru tens and units' => ['ru', 'двадцать пять', ['25']],
    'ru a year' => ['ru', 'две тысячи двадцать пять', ['2025']],
]);

// Canon (LANG-1 §5, the order's update: «частые и отличительные»): an ordinary Russian line of a role — the scouting days'
// own translations — stays a translation with the packs the deployment has, its Cyrillic neighbours among them. CATCHES a
// Russian list (or a neighbour's) holding a word both languages say: two of them in a Russian line and it reads
// Ukrainian or Belarusian — a second paid answer, then a line with no translation.
it('keeps ordinary Russian lines of the scouting days a translation', function () {
    $ru = lessonPacks()->for('ru');
    $refused = array_values(array_filter([
        'Хорошо. По какому вопросу вы хотите записаться?',
        'Сколько дней это уже длится?',
        'У нас есть место завтра в десять часов.',
        'Я записала. Приём завтра в десять. Приходите за десять минут до начала.',
        'Да, у нас есть завтра в пятнадцать.',
        'Пожалуйста, принесите документ и страховую карту.',
        'Конечно. Что случилось?',
        // The probe line the order's update names — refused once by a Ukrainian list holding «для» and «до».
        'Для записи к врачу приходите до двенадцати.',
    ], static fn (string $line): bool => ReplyNative::missing('—', $line, $ru)));

    expect($refused)->toBe([]);
});

// Canon (LANG-1 §5): under a Russian learner, a grey line in Ukrainian or Belarusian is no translation — the letters are the
// same, only the words tell. The lines are natural ones, dense with the words the order names as that language's own
// (uk «що, це, який, дуже, також, тільки»). Skips until the neighbour's pack writes the same letters and its frequent words.
// CATCHES a Russian list that holds the neighbour's words (they would then cancel out), and a neighbour's list that misses
// its own most frequent words.
it('refuses a line in a Cyrillic neighbour under a Russian learner', function (string $neighbour, array $lines) {
    $ru = lessonPacks()->for('ru');
    $theirs = $ru->neighbours()[$neighbour] ?? null;
    if ($theirs === null || $theirs['script_letters'] !== $ru->asNeighbour()['script_letters'] || $theirs['common_words'] === []) {
        $this->markTestSkipped("{$neighbour}.php does not write the Cyrillic letters and its frequent words yet");
    }

    foreach ($lines as $line) {
        expect(ReplyNative::missing('—', $line, $ru))->toBeTrue("{$neighbour}: {$line}");
    }
})->with([
    'uk' => ['uk', ['Що це таке? Я тільки хочу знати, який лікар сьогодні працює.', 'Він також дуже хоче записатися до лікаря.']],
    'be' => ['be', ['Калі ласка, скажыце, што гэта таксама вельмі важна.', 'Ён ужо тут, але яшчэ трэба крыху пачакаць.']],
]);

// Canon (LANG-1 §5): English is every Latin learner's neighbour — the role's English line, sent again as its «translation»,
// is refused under a Polish, Romanian, Spanish, Italian, German or French learner. The lines are the scouting days' own
// partner lines. Skips until that learner's pack writes the Latin letters and its frequent words. CATCHES an English list
// too thin to catch the role's own lines, and a learner's list that claims English words.
it('refuses the role\'s English line as a Latin learner\'s translation', function (string $learner) {
    $pack = lessonPacks()->for($learner);
    $en = lessonPacks()->for('en')->asNeighbour();
    if ($pack->asNeighbour()['script_letters'] !== $en['script_letters'] || $pack->commonWords() === []) {
        $this->markTestSkipped("{$learner}.php does not write the Latin letters and its frequent words yet");
    }

    foreach ([
        'Yes, that time is still free.',
        'I need your full name for the booking.',
        'Please bring your ID and arrive ten minutes early.',
        "You're booked for today at four with Dr. Lee.",
        'How long have you had it?',
    ] as $line) {
        expect(ReplyNative::missing('—', $line, $pack))->toBeTrue("{$learner}: {$line}");
    }
})->with(['pl', 'ro', 'es', 'it', 'de', 'fr']);

// …and the Polish learner's own lines of the pl-en day stay translations beside English — none of English's frequent words
// is a Polish word. Skips until pl.php writes its letters and frequent words. CATCHES an English list holding a word Polish
// says as often («to», «my», «go», «we») — two of them and the Polish line reads English.
it('keeps the Polish lines of the pl-en day a translation beside English', function () {
    $pl = lessonPacks()->for('pl');
    if ($pl->asNeighbour()['script_letters'] !== '/^[\p{Latin}]$/u' || $pl->commonWords() === []) {
        $this->markTestSkipped('pl.php does not write the Latin letters and its frequent words yet');
    }
    $refused = array_values(array_filter([
        'Oczywiście. Proszę powiedzieć, co się dzieje.',
        'Czy to coś pilnego na dziś?',
        'Mamy dziś o czwartej albo jutro o dziewiątej.',
        'Tak, ten termin jest jeszcze wolny.',
        'Potrzebuję twojego imienia i nazwiska do rezerwacji.',
        'Tak. To Green Street 12, obok apteki.',
    ], static fn (string $line): bool => ReplyNative::missing('—', $line, $pl)));

    expect($refused)->toBe([]);
});

// The same rule with the deployed English and Russian words and a stand-in learner of the same letters whose words no line
// here says — so it holds today, whatever the neighbours' packs come to write. Every partner line of the pl-en day (and
// three of be-en, and the role's «today … tomorrow» lines of be-en, it-en, es-en) holds two of English's frequent words;
// the Russian partner lines below hold two of Russian's — the second block among them only since the review added the
// pronoun forms, «тогда», «сегодня», «хотите», «чём», «понимаю», «значит», «отлично». The limit, pinned: a Russian line
// made of words Ukrainian and Belarusian say too («Ваше имя и ваша дата рождения.»: «и» alone is Russian's; «ваше»,
// «ваша» are Ukrainian and Belarusian as well) is not told apart — the guard lets it through, it never refuses a line for
// it. CATCHES a list too thin to tell its language apart — the refusals above would then rest on the learner's list alone.
it('tells the scouting lines by the pack\'s own frequent words alone', function (string $code, string $letters, array $lines, bool $told) {
    $packs = new LanguagePacks([
        $code => lessonPacks()->for($code)->asNeighbour(),
        'xx' => ['script_letters' => $letters, 'common_words' => ['qqq', 'zzz']],
    ]);

    foreach ($lines as $line) {
        expect(ReplyNative::missing('—', $line, $packs->for('xx')))->toBe($told, "{$code}: {$line}");
    }
})->with([
    'en: pl-en A1–A8, be-en A2–A4' => ['en', '/^[\p{Latin}]$/u', [
        'Of course. Please tell me the problem.', 'Is it something urgent today?', 'We have today at four or tomorrow at nine.',
        'Yes, that time is still free.', 'I need your full name for the booking.', 'Please bring your ID and arrive ten minutes early.',
        "Yes. It's 12 Green Street, near the pharmacy.", "You're booked for today at four with Dr. Lee.",
        'What symptoms do you have?', 'How long have you had it?', 'Do you also have a fever?',
        'Today at four, or tomorrow at nine.', 'We have 10 a.m. today or 3 p.m. tomorrow.', "No, but two o'clock today is still free.",
    ], true],
    'ru: ru-pl, ru-fr, ru-de, ru-es' => ['ru', '/^[\p{Cyrillic}]$/u', [
        'Хорошо. Какая причина визита?', 'Конечно. Что случилось?', 'Сколько дней это уже длится?',
        'Пожалуйста, принесите документ и страховую карту.', 'У вас есть что-нибудь на вторую половину дня?',
        'Да, в четыре часа ещё есть свободный приём.', 'У нас есть сегодня в четыре или завтра в десять.',
        'Хорошо, тогда я запишу вас на завтра на четыре.', 'Хорошо. В чём проблема?', 'Понимаю. Значит, это срочная запись.',
        'Мне нужны ваши имя и фамилия.', 'Отлично. Ваша запись на сегодня подтверждена.',
    ], true],
    'ru: shared words only — the limit' => ['ru', '/^[\p{Cyrillic}]$/u', ['Ваше имя и ваша дата рождения.'], false],
    // …and the other direction: the partner lines of the Latin learners' own days (ro-en, es-en, it-en, de-en, fr-en, pl-en)
    // hold fewer than two of English's words — an English list with «to», «on», «was», «das» would fail here.
    'en: the Latin learners\' own lines are no English' => ['en', '/^[\p{Latin}]$/u', [
        'Sigur. Pentru ce este consultația?', 'Avem azi la patru sau mâine la nouă.',
        'Te rog să aduci actul de identitate și cardul de asigurare.', 'Mai am nevoie și de numărul tău de telefon pentru programare.',
        '¿Cuánto tiempo llevas con eso?', 'Tenemos citas hoy a las diez y a las dos.', 'No, pero las dos de hoy siguen libres.',
        'Tienes cita hoy a las dos. Por favor, llega diez minutos antes.',
        'Da quanto tempo ha questi sintomi?', 'Abbiamo le 10 di oggi o le 3 di domani.', 'Per favore, venga dieci minuti prima del suo appuntamento.',
        'Natürlich. Was ist denn das Problem?', 'Wie lange haben Sie das schon?', 'Wir haben heute Termine um zehn oder um zwei.',
        'Bitte kommen Sie fünfzehn Minuten früher wegen des Formulars.', 'Ja, das ist King Street 14.',
        'Bien sûr. Quelle est la raison de votre visite ?', 'Depuis combien de temps avez-vous de la fièvre ?',
        "D'accord, je peux vous réserver 15 h aujourd'hui.", "Vous êtes inscrit pour 15 h aujourd'hui avec le Dr Lee.",
        'Tak. To Green Street 12, obok apteki.', 'Jesteś zapisany na dziś na czwartą do doktor Lee.',
    ], false],
    // The lines of uk-en and be-en, and two natural ones dense with the words all three languages say («для», «до», «вас»,
    // «вам», «так», «на»): fewer than two of Russian's words each — a Russian list holding a Ukrainian or Belarusian word
    // would fail here.
    'ru: the Ukrainian and Belarusian lines are no Russian' => ['ru', '/^[\p{Cyrillic}]$/u', [
        'Звісно. Що вас турбує?', 'Сьогодні є записи на десяту або на третю.', 'Так. Це Грін-стріт, 18.',
        'Будь ласка, прийдіть на десять хвилин раніше для реєстрації.', 'Так, завтра ми відчиняємося о восьмій.',
        'Ваш прийом о третій у лікаря Браун.', 'Будь ласка, принесіть посвідчення особи на стійку реєстрації.',
        'Вядома. Што вас турбуе?', 'Якія ў вас сімптомы?', 'Як даўно гэта ў вас?', 'У вас таксама ёсць тэмпература?',
        'У нас ёсць сёння а чацвёртай і заўтра а дзявятай.', 'Гатова. Вы запісаны на сёння а чацвёртай.',
        'Для запису до лікаря приходьте до дванадцятої, будь ласка.', 'Мы можам запісаць вас на заўтра, калі вам так зручна.',
    ], false],
]);

// Canon (the order's update to LANG-1 §5): a frequent word of a pack is NO ordinary word of a same-letter neighbour. What
// the neighbours' packs write as their own words — service words (`function_words`, `unstressed_words`), the words a move
// or a clause opens with, closers, number words, negation — is read here as their ordinary words (one run of letters at a
// time, as the guard reads a line): none of them may sit in the English or the Russian list unless the neighbour lists it
// among its frequent words too (then the two cancel out). Skips until a neighbour writes its service words. CATCHES «для»,
// «до» in a Cyrillic list, «to», «in», «a» in a Latin one — the probe that refused «Для записи к врачу приходите до
// двенадцати» — and «come», «on», «was» (it «come», fr «on», de «was») had they been written into English's.
it('writes no frequent word that a same-letter neighbour calls its own service word', function (string $code) {
    $pack = lessonPacks()->for($code);
    $letters = $pack->asNeighbour()['script_letters'];
    $clashes = [];
    $read = 0;
    foreach (lessonPacks()->codes() as $other) {
        $neighbour = lessonPacks()->for($other);
        if ($other === $code || $neighbour->asNeighbour()['script_letters'] !== $letters || ! $neighbour->has('function_words')) {
            continue;
        }
        $read++;
        $written = [];
        foreach (['function_words', 'unstressed_words', 'intro_words', 'clause_starters', 'closers'] as $key) {
            $written = [...$written, ...($neighbour->has($key) ? $neighbour->words($key) : [])];
        }
        $written = [...$written, ...array_map('strval', array_keys($neighbour->has('number_words') ? $neighbour->map('number_words') : []))];
        $negation = $neighbour->has('negation') ? $neighbour->map('negation') : [];
        $written = [...$written, ...(array) ($negation['words'] ?? []), ...(array) ($negation['word'] ?? [])];
        $runs = [];
        foreach ($written as $entry) {
            foreach (preg_split('/[^\p{L}\p{M}]+/u', (string) $entry, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $run) {
                $runs[] = LanguagePack::normal($run);
            }
        }
        $ordinary = array_diff(array_unique($runs), $neighbour->commonWords());
        foreach (array_intersect($pack->commonWords(), $ordinary) as $word) {
            $clashes[] = "{$other}: «{$word}»";
        }
    }
    if ($read === 0) {
        $this->markTestSkipped("no neighbour of {$code} writes its letters and its service words yet");
    }

    expect($clashes)->toBe([]);
})->with(['en', 'ru']);

// Canon (LANG-1 §6): Russian and English titles are the code's — declined, or English — and the pack's `[]` no-op leaves
// them as they were. CATCHES a template read for ru or en: «Разговор: администратор и врач» where the learner saw «Поговори
// с администратором и врачом».
it('titles a talk of two roles the way the code always has', function () {
    expect((new NativeStrings('ru'))->talkTitle(['Администратор', 'Врач'], lessonPacks()->for('ru')))->toBe('Поговори с администратором и врачом')
        ->and((new NativeStrings('en'))->talkTitle(['Receptionist', 'Doctor'], lessonPacks()->for('en')))->toBe('Talk to the receptionist and the doctor');
});

// Canon (LANG-1 §5, the review of this pack): a Russian line is the likeliest wrong «translation» a mini model gives a
// Ukrainian or a Belarusian learner — and only Russian's frequent words tell it, the letters being the same. The lines are
// the scouting days' own partner lines (ru-fr, ru-es, ru-de), each with two of Russian's words or more. Skips until the
// learner's pack writes the Cyrillic letters and its frequent words. CATCHES a Russian list too thin for its main use (with
// the first forty-four words, «Хорошо, тогда я запишу вас…», «Понимаю. Значит, это…», «Отлично. Ваша запись на сегодня…»
// all passed), and a learner's list claiming a Russian word — the two would cancel out.
it('refuses the role\'s Russian line as a Ukrainian or a Belarusian learner\'s translation', function (string $learner) {
    $pack = lessonPacks()->for($learner);
    if ($pack->asNeighbour()['script_letters'] !== lessonPacks()->for('ru')->asNeighbour()['script_letters'] || $pack->commonWords() === []) {
        $this->markTestSkipped("{$learner}.php does not write the Cyrillic letters and its frequent words yet");
    }

    foreach ([
        'Хорошо. По какому вопросу вы хотите записаться?',
        'Конечно. Что случилось?',
        'Сколько дней это уже длится?',
        'Хорошо, тогда я запишу вас на завтра на четыре.',
        'Понимаю. Значит, это срочная запись.',
        'Отлично. Ваша запись на сегодня подтверждена.',
        'Мне нужны ваши имя и фамилия.',
    ] as $line) {
        expect(ReplyNative::missing('—', $line, $pack))->toBeTrue("{$learner}: {$line}");
    }
})->with(['uk', 'be']);

// Canon (the order's update to LANG-1 §5: «frequent AND distinctive»), on the whole scouting run: not one string a learner of
// the X→en days reads in their own language — the lines, the frames, the fillers, the checks and their options, the
// listening questions (the readings aside: they spell ENGLISH) — holds a single word of English's frequent words (pl, ro,
// es, it, de, fr) or of Russian's (uk, be). The guard needs two of them to call a line foreign; the run has none at all.
// CATCHES a frequent word that is an ordinary word of a neighbour, as the neighbour's model writes it («to», «do», «come»,
// «was» in English's list; «для», «до», «так», «вас» in Russian's).
it('finds none of its frequent words in the learners\' own lines of the scouting days', function (string $code, array $pairs) {
    $mine = lessonPacks()->for($code)->commonWords();
    $found = [];
    foreach ($pairs as $pair) {
        $strings = [];
        $walk = static function (mixed $node) use (&$walk, &$strings): void {
            foreach (is_array($node) ? $node : [] as $key => $value) {
                $native = is_string($key) && (str_ends_with($key, '_native') || $key === 'native') && $key !== 'pronunciation_native';
                foreach ($native ? (is_array($value) ? $value : [$value]) : [] as $string) {
                    if (is_string($string)) {
                        $strings[] = $string;
                    }
                }
                $walk($value);
            }
        };
        $walk(json_decode((string) file_get_contents(dirname(__DIR__, 4)."/docs/research/lang-1/answers/{$pair}.json"), true));
        foreach (array_unique($strings) as $string) {
            $words = array_map(LanguagePack::normal(...), preg_split('/[^\p{L}\p{M}]+/u', $string, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            foreach (array_intersect(array_unique($words), $mine) as $word) {
                $found[] = "{$pair}: «{$word}» in «{$string}»";
            }
        }
        expect(count(array_unique($strings)))->toBeGreaterThan(100, "{$pair}: the answer's own strings");
    }

    expect($found)->toBe([]);
})->with([
    'en' => ['en', ['pl-en', 'ro-en', 'es-en', 'it-en', 'de-en', 'fr-en']],
    'ru' => ['ru', ['uk-en', 'be-en']],
]);

// Canon (the order's update to LANG-1: `script` — the STRICT alphabet, a WARNING; `script_letters` — the whole writing, the
// FATAL one): a Russian reading with a Ukrainian «і» is readable Cyrillic — never fatal — but no Russian spelling, and says
// so as `pronunciation.script`; a Latin «í» is both; «ё», the stress mark, digits, marks and the slot are Russian script.
// CATCHES a strict alphabet written into `script_letters` (a fatal day for an «і»), and a `script` as loose as the letters.
it('reads a reading with another Cyrillic alphabet\'s letter as untidy, never as unreadable', function (string $reading, bool $inScript, array $foreign) {
    $words = new LanguageWords(lessonPacks()->for('ru'));

    expect($words->readsInScript($reading))->toBe($inScript)
        ->and($words->foreignLetters($reading))->toBe($foreign);
})->with([
    'ru-fr B2' => ['Жэ маль а ла горж.', true, []],
    'ru-fr B3, an «ё»' => ['Дёпюи труа жур.', true, []],
    'ru-pl B4, the stress mark' => ['чы ест цось по полу́дню', true, []],
    'the slot, digits, a dash' => ['Мон нюмэро, сэ ___ — 06 12.', true, []],
    'a Ukrainian «і»' => ['ай хэв э фівер', false, []],
    'a Belarusian «ў»' => ['ўэн', false, []],
    'a Latin «í»' => ['ай хэв э фíвер', false, ['í']],
]);

// Canon (the review of LANG-1; FRAMES/FILLERS — «a» before a consonant SOUND): «a one-way ticket», «a once-daily tablet», «a
// euro account», «a European health insurance card» are English, and `filler.ungrammatical` is FATAL — the day fails
// unless a repair rewrites a healthy card. The spelling rule stays for everything else: «a earache», «an bandage» are still
// the seam's fault. CATCHES an `article_sound.exception` that lost «once» or «eu» (a bank or a travel day failing on «I'd
// like to open a ___» + «euro account»), and one so wide it forgives a real mismatch.
it('reads «a» before a «w» or a «y» sound as English at the seam', function (string $frame, string $filler, array $problems) {
    expect(FillerRules::seams($frame, $filler, new LanguageWords(lessonPacks()->for('en'))))->toBe($problems);
})->with([
    'a euro account' => ["I'd like to open a ___.", 'euro account', []],
    'a European card' => ['I have a ___.', 'European health insurance card', []],
    'a once-daily tablet' => ['Please take a ___.', 'once-daily tablet', []],
    'a one-way ticket' => ['I need a ___.', 'one-way ticket', []],
    'a earache — still wrong' => ['I have a ___.', 'earache', ['«a earache» before a vowel']],
    'an bandage — still wrong' => ['I need an ___.', 'bandage', ['«an bandage» before a consonant']],
]);

// The order's update to LANG-1: «NO key may be null (null counts lang.pack_missing); use the spec's no-op values». English
// is never a learner's language and Russian never a target (LanguageRoles), so the keys of the side each never plays were
// null; they are the key spec's no-ops now, and a no-op must read exactly as the null it replaced — nothing reads them for
// these two languages, and where something could (the numbers of «Поймай число» read a TARGET's time and amount words, the
// judge reads a pack's articles and dangling words, the phone is served its speech block), the answer is the same. CATCHES
// a key of either pack left null, and a «no-op» that is none: a time pattern that matches, a list that forgives, a speech
// block that changed on the wire.
it('writes no key as null, and its no-ops read as the nulls they replaced', function (string $code, array $wereNull, array $lines, string $frame) {
    $data = require dirname(__DIR__, 4)."/config/lesson/lang/{$code}.php";
    $asBefore = new LanguagePack($code, array_replace($data, array_fill_keys($wereNull, null)));
    $now = new LanguagePack($code, $data);
    $phrases = [new ConversationPhrase('x', 'p1', $frame, '', null, null)];
    $judge = new FrameJudge;

    expect(array_keys(array_filter($data, static fn (mixed $value): bool => $value === null)))->toBe([])
        ->and(array_values(array_filter($wereNull, static fn (string $key): bool => ! $now->has($key))))->toBe([])
        ->and($now->speech()->toArray())->toBe($asBefore->speech()->toArray());
    foreach ($lines as $line) {
        expect(NumberValues::of($now)?->runs($line))->toBe(NumberValues::of($asBefore)?->runs($line), $line)
            ->and(NumberValues::of($now)?->values($line))->toBe(NumberValues::of($asBefore)?->values($line), $line)
            ->and((new LanguageWords($now))->isQuestion($line))->toBe((new LanguageWords($asBefore))->isQuestion($line), $line)
            ->and($judge->move($line, $phrases, $now))->toEqual($judge->move($line, $phrases, $asBefore))
            ->and($judge->breaksOff($line, $phrases, $now))->toBe($judge->breaksOff($line, $phrases, $asBefore));
    }
})->with([
    'en: the learner\'s-side keys' => ['en', ['script', 'time_pattern', 'amount_pattern', 'amount_prefix', 'gendered_past_pattern', 'agreement'], [
        'You\'re booked for tomorrow at 3 p.m.', 'For three days.', 'We have today at four or tomorrow at nine.',
        'Please bring your ID and arrive ten minutes early.', 'I have a sore throat and fever.', 'I have a',
    ], 'I have ___.'],
    'ru: the target\'s keys' => ['ru', [
        'question_word_order', 'number_joiners', 'everyday_words', 'ordinary_heads', 'closers', 'saying_verbs', 'alternative_words',
        'second_question_pattern', 'articles', 'dangling_words', 'seam_repeatable_words', 'article_sound', 'clause', 'unresolved_pronouns',
    ], [
        'Уже три дня.', 'Приходите за десять минут до начала.', 'Да, у нас есть завтра в пятнадцать.',
        'Вы можете прийти через неделю', 'Я хочу записаться к врачу.', 'Да, я хочу записаться к врачу.', 'Я хочу',
    ], 'Я хочу записаться ___.'],
]);
