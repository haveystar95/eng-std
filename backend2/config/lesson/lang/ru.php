<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · ru — what the lesson validator reads of Russian
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b). Russian is written as the LEARNER'S OWN language: the reading of the
| target, the listening questions and their options, the native frames and fillers. The keys only a target
| language needs (closers, the STOP LIST, articles, clauses, pronouns a frame leans on) are null — no plan has
| Russian as the language being learnt.
*/
return [
    // A reading of the target is written in Cyrillic letters, digits, punctuation, whitespace and the stress
    // mark U+0301 — nothing else («пáспорт» with a Latin «á» leaves the script).
    'script' => '/^[\p{Cyrillic}\p{N}\p{P}\s\x{0301}]*$/u',

    // One LETTER of a reading, matched alone: only these are the learner's own alphabet. A letter that is not one
    // of them is fatal, not untidy — «ֆоутoуз» cannot be read at all. Spaces, hyphens, apostrophes and the slot's
    // underscores are no letters and are not matched here.
    'script_letters' => '/^[\p{Cyrillic}]$/u',

    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence (наряд CHECK-1) — read where a native frame is asked whether it ends with a mark
    // («т. е.» inside a frame ends nothing). A space inside one is any run of spaces. Without the key every dot ends a
    // sentence.
    'abbreviations' => ['т. е.', 'т. д.', 'т. п.', 'г.', 'ул.'],

    // The word order of a question — a target-language key, not written for Russian.
    'question_word_order' => null,

    // Words that carry no content of their own — what a listening question and a line of the visit may share
    // without being about the same thing.
    'function_words' => [
        'и', 'в', 'во', 'на', 'с', 'со', 'у', 'к', 'ко', 'о', 'об', 'по', 'за', 'из', 'от', 'до', 'для', 'при',
        'про', 'над', 'под', 'без', 'не', 'ни', 'ли', 'же', 'бы', 'что', 'чтобы', 'как', 'так', 'это', 'этот',
        'эта', 'эти', 'тот', 'та', 'те', 'он', 'она', 'оно', 'они', 'его', 'её', 'ее', 'их', 'ему', 'ей', 'им',
        'него', 'нее', 'неё', 'них', 'нам', 'вам', 'мне', 'мы', 'вы', 'я', 'ты', 'меня', 'тебя', 'вас', 'нас',
        'а', 'но', 'или', 'если', 'когда', 'где', 'куда', 'там', 'тут', 'здесь', 'уже', 'ещё', 'еще', 'очень',
        'только', 'да', 'нет', 'вот', 'все', 'всё', 'весь', 'вся', 'свой', 'своя', 'свои', 'мой', 'моя', 'мои',
        'ваш', 'ваша', 'ваши', 'наш', 'наша', 'наши', 'какой', 'какая', 'какое', 'какие', 'который', 'которая',
        'чем', 'чём', 'кто', 'быть', 'есть', 'будет', 'можно', 'нужно', 'надо',
    ],

    // THE WORDS A RECOGNISER EATS (наряд FIX-2, п. 2) — prepositions, particles and the forms of «быть»: left out of
    // BOTH sides when a line the learner is LOOKING AT is compared with what was said
    // ({@see \App\Modules\Shared\Domain\ValueObject\SpeechMode::Repeat}). Narrower than `function_words`, which
    // holds «что», «нет», «какой» — words a repeat must not forgive; «не» and «ни» are not here either, they flip
    // the meaning. Russian has no articles, so the list opens with prepositions.
    'unstressed_words' => [
        'в', 'во', 'на', 'с', 'со', 'у', 'к', 'ко', 'о', 'об', 'обо', 'по', 'за', 'из', 'изо', 'от', 'ото', 'до',
        'для', 'при', 'про', 'над', 'надо', 'под', 'подо', 'без', 'через', 'перед', 'между',
        'и', 'а', 'же', 'бы', 'ли', 'б',
        'быть', 'был', 'была', 'было', 'были', 'есть', 'буду', 'будешь', 'будет', 'будем', 'будете', 'будут',
    ],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER (наряд FIX-2, п. 2). Only the bare nominative forms: an inflected
    // form («трёх», «двумя») is not what a recogniser writes for a digit, and folding it would make «в двух шагах»
    // read as «в 2 шагах» on one side only.
    'number_words' => [
        'ноль' => '0', 'один' => '1', 'одна' => '1', 'два' => '2', 'две' => '2', 'три' => '3', 'четыре' => '4',
        'пять' => '5', 'шесть' => '6', 'семь' => '7', 'восемь' => '8', 'девять' => '9', 'десять' => '10',
        'одиннадцать' => '11', 'двенадцать' => '12', 'тринадцать' => '13', 'четырнадцать' => '14',
        'пятнадцать' => '15', 'шестнадцать' => '16', 'семнадцать' => '17', 'восемнадцать' => '18',
        'девятнадцать' => '19', 'двадцать' => '20', 'тридцать' => '30', 'сорок' => '40', 'пятьдесят' => '50',
        'шестьдесят' => '60', 'семьдесят' => '70', 'восемьдесят' => '80', 'девяносто' => '90', 'сто' => '100',
        'тысяча' => '1000',
    ],

    // Two forms of one word in an inflected language: both at least four letters, sharing all but the last two
    // letters of the shorter («пояснице» — «поясница», «неделю» — «неделя»). One letter is no content word.
    'word_forms' => ['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2],

    // Number words, ordinals and «раз» — whole words; a word that starts with a digit («14A», «38,5»).
    'number_pattern' => '/^(?:\d[\p{L}\d:.,]*|один|одн[аоуиы]\w*|два|две|двух|двум|двумя|трое|тр[её]х|тр[её]м|тремя|три|четыр\w*|пят[ьи]|пятью|пятнадцат\w*|пятьдесят\w*|пятьсот|шест[ьи]|шестью|шестнадцат\w*|шестьдесят\w*|сем[ьи]|семнадцат\w*|семьдесят\w*|восем\w*|восьм\w*|девят\w*|девяност\w*|десят\w*|одиннадцат\w*|двенадцат\w*|тринадцат\w*|двадцат\w*|тридцат\w*|сорок\w*|сто|ста|сотн\w*|двест\w*|трист\w*|четырест\w*|тысяч\w*|миллион\w*|половин\w*|полтор\w*|перв\w*|втор[оаы]\w*|трет\w*|четв[её]рт\w*|пят[ыо]\w*|шест[ыо]\w*|седьм\w*|раз|раза)$/u',

    // Time and duration words — units, parts of the day, days, months, «вчера», «назад», «через».
    'time_pattern' => '/^(?:назад|спустя|тому|через|раньше|позже|скоро|недавно|давно|сейчас|потом|секунд\w*|минут\w*|час|часа|часов|часу|сутк\w*|суток|ден[ьи]|дня|дней|дн[её]м|недел\w*|месяц\w*|год|года|году|годы|лет|утр[оау]\w*|вечер\w*|ноч\w*|полдень|полночь|вчера|вчерашн\w*|сегодня|сегодняшн\w*|завтра|завтрашн\w*|позавчера|послезавтра|понедельник\w*|вторник\w*|сред[ауы]|четверг\w*|пятниц\w*|суббот\w*|воскресень\w*|январ\w*|феврал\w*|март\w*|апрел\w*|ма[йя]|июн\w*|июл\w*|август\w*|сентябр\w*|октябр\w*|ноябр\w*|декабр\w*|выходн\w*)$/u',

    // The units something is COUNTED in — what makes a value an amount and not a date («через неделю», «два дня»
    // against «сегодня», «в пятницу»). Read by «Поймай число» (34-7) for its options: numerals are amounts by
    // `number_pattern`, these words are amounts without one. Parts of the day, weekdays and months are not here.
    'amount_pattern' => '/^(?:секунд\w*|минут\w*|час|часа|часов|часу|сутк\w*|суток|ден[ьи]|дня|дней|недел\w*|месяц\w*|год|года|году|годы|лет|раз|раза|градус\w*|процент\w*|метр\w*|килограмм\w*|грамм\w*|литр\w*|миллиграмм\w*|таблет\w*|капл[ьия]\w*)$/u',

    // What carries an amount and is said WITH it — prepositions and determiners standing right before it. «Поймай
    // число» offers a value in the form the line says it («на этой неделе», «через неделю»), never the bare noun
    // («Неделе»), so the option grows to the left over these words. Read only to the left, and only next to the value.
    'amount_prefix' => '/^(?:на|в|во|за|через|до|после|с|со|по|около|примерно|спустя|этой|этот|эту|эти|этим|этих|прошл\w*|следующ\w*|ближайш\w*|т[ое]й|кажд\w*)$/u',

    // Target-language keys: not written for Russian.
    'everyday_words' => null,
    'ordinary_heads' => null,
    'closers' => null,
    'saying_verbs' => null,
    'alternative_words' => null,
    'second_question_pattern' => null,
    'articles' => null,
    'seam_repeatable_words' => null,
    'article_sound' => null,
    'clause' => null,
    'unresolved_pronouns' => null,

    // A past-tense form right after «я» (a «не», «уже», «раньше», «тоже», «сам», «сама», «давно», «недавно» may
    // stand between) — the learner's gender said in their own line while it is unknown.
    'gendered_past_pattern' => '/(?<![\p{L}])я\s+(?:(?:не|уже|раньше|тоже|сам|сама|давно|недавно)\s+)?(\p{Cyrillic}{2,}[аяеиыуоё]л(?:а|ся|ась)?)(?![\p{L}])/u',

    // «The native frame contains NO word that agrees with the slot in gender or number — never «___ разрешён?»,
    // «Какая/какой ___?», «мой/моё ___»» (FRAMES, v4.5). Read at the slot, never elsewhere in the frame («У моего
    // сына ___» agrees with «сына»):
    //  - the word right before `___` agrees when it is one of `words` (possessives, demonstratives, «какой»,
    //    «один», «каждый» — in their cases) or one of `short_forms`, or is an adjective — ends with one of
    //    `suffixes_before_slot` and has at least `min_letters` letters («новый ___», «вторую ___»);
    //  - one of the `after_slot_words` words after `___` agrees when it is one of `words` or `short_forms` — the
    //    predicate of a slot that is the subject («___ разрешён?», «___ будет открыт?»).
    // Verbs agree in number too, and the prompt's own rephrase keeps one («Сколько стоит ___?») — no verb is listed;
    // «это» and «нужно» agree with nothing.
    'agreement' => [
        'words' => [
            'мой', 'моя', 'моё', 'мое', 'мои', 'моего', 'моей', 'моему', 'моим', 'моём', 'моем', 'мою', 'моих', 'моими',
            'твой', 'твоя', 'твоё', 'твое', 'твои', 'твоего', 'твоей', 'твоему', 'твоим', 'твоём', 'твоем', 'твою', 'твоих',
            'свой', 'своя', 'своё', 'свое', 'свои', 'своего', 'своей', 'своему', 'своим', 'своём', 'своем', 'свою', 'своих',
            'наш', 'наша', 'наше', 'наши', 'нашего', 'нашей', 'нашему', 'нашим', 'нашем', 'нашу', 'наших',
            'ваш', 'ваша', 'ваше', 'ваши', 'вашего', 'вашей', 'вашему', 'вашим', 'вашем', 'вашу', 'ваших',
            'этот', 'эта', 'эти', 'этого', 'этой', 'этому', 'этим', 'этом', 'эту', 'этих',
            'тот', 'та', 'те', 'того', 'той', 'тому', 'тем', 'том', 'ту', 'тех',
            'какой', 'какая', 'какое', 'какие', 'какого', 'какому', 'каким', 'каком', 'какую', 'каких',
            'который', 'которая', 'которое', 'которые', 'которого', 'которой', 'которому', 'которым', 'котором', 'которую', 'которых',
            'чей', 'чья', 'чьё', 'чье', 'чьи',
            'один', 'одна', 'одно', 'одни', 'одного', 'одной', 'одному', 'одним', 'одном', 'одну',
            'каждый', 'каждая', 'каждое', 'каждые', 'каждого', 'каждой', 'каждому', 'каждым', 'каждом', 'каждую',
            'такой', 'такая', 'такое', 'такие', 'другой', 'другая', 'другое', 'другие', 'весь', 'вся',
        ],
        'short_forms' => [
            'разрешён', 'разрешен', 'разрешена', 'разрешено', 'разрешены', 'включён', 'включен', 'включена', 'включено', 'включены',
            'нужен', 'нужна', 'нужны', 'готов', 'готова', 'готово', 'готовы', 'свободен', 'свободна', 'свободно', 'свободны',
            'занят', 'занята', 'занято', 'заняты', 'открыт', 'открыта', 'открыто', 'открыты', 'закрыт', 'закрыта', 'закрыто', 'закрыты',
            'доступен', 'доступна', 'доступно', 'доступны', 'оплачен', 'оплачена', 'оплачено', 'оплачены',
            'забронирован', 'забронирована', 'забронировано', 'забронированы', 'должен', 'должна', 'должно', 'должны',
            'необходим', 'необходима', 'необходимо', 'необходимы', 'обязателен', 'обязательна', 'обязательно', 'обязательны',
        ],
        'suffixes_before_slot' => ['ый', 'ий', 'ой', 'ая', 'яя', 'ое', 'ые', 'ие', 'ую', 'юю', 'ого', 'его', 'ому', 'ему', 'ым', 'ых'],
        'min_letters' => 4,
        'after_slot_words' => 2,
    ],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а) — what a rescue move of the talk says in the learner's own bubble
    // (кадр 37-7, en «Sorry?»).
    'rescue_line' => 'Простите?',

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9) — the same line as every pack's, in this language: said by the role
    // in a talk held in Russian, or the translation of the target's line for a Russian learner (en «I see. Please go on.»).
    'neutral_reply' => 'Понятно. Продолжайте, пожалуйста.',
];
