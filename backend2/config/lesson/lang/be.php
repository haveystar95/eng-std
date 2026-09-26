<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · be — what the lesson validator reads of Belarusian
|--------------------------------------------------------------------------
|
| Наряд LANG-1 (key spec: docs/research/lang-1/pack-keys.md). Belarusian is written as the LEARNER'S OWN language only
| (`LanguageRoles::planNatives()`; it is no plan's target): the reading of the target, the native frames and fillers, the
| listening questions and their options, the role's translated line, the title of the talk. Belarusian is written in the
| official orthography (наркамаўка): «ў» after a vowel, «і» and never «и», no «щ» or «ъ» — the apostrophe stands where
| Russian writes «ъ» («сям'я», «пад'езд»).
|
| The keys only a TARGET reads (closers, the STOP LIST, articles, clauses, pronouns a frame leans on, what the judge of the
| talk forgives) are written as the spec's no-ops, never null: nothing reads them for Belarusian, and a no-op is a decision
| where a null is a forgotten key. `number_words`, `negation` and `rescue_line` are written for real although no reader
| asks them of a learner's language (dead data, kept so the pack says what Belarusian is).
|
| Words are compared as LanguagePack::normal() gives them — folded, lower case, the typographic apostrophe ’ a plain one.
| The modifier-letter apostrophe ʼ (U+02BC) is a LETTER to Unicode and is not folded, so a pattern that reads a word with
| an apostrophe names all three: ['’ʼ].
*/

return [
    // A reading of the target is written in the BELARUSIAN alphabet (32 letters: «і», «ў», «ы», «э», no «и», «щ», «ъ»),
    // digits, punctuation, whitespace, the stress mark U+0301 and the apostrophe — the Belarusian «ʼ» (U+02BC, a letter
    // to Unicode, so named here) and the plain or typographic one (punctuation). A reading that borrows a Russian «и», «щ»
    // or «ъ» or a Ukrainian «ї», «є», «ґ» — «уот из», «ґуд» — leaves the alphabet: a WARNING (`pronunciation.script`), never
    // a failed day; the fatal check is `script_letters` below, which only asks for Cyrillic.
    'script' => '/^[абвгдеёжзійклмнопрстуўфхцчшыьэюяАБВГДЕЁЖЗІЙКЛМНОПРСТУЎФХЦЧШЫЬЭЮЯ\p{N}\p{P}\s\x{0301}\x{02BC}]*$/u',

    // One LETTER of a reading, matched alone — the fatal check (`pronunciation.foreign_script`) and the key the guard of
    // the role's translation finds the NEIGHBOURS by: exactly the string every Cyrillic pack (ru, uk, be) writes, so the
    // three are told apart by their `common_words`. Cyrillic, not the Belarusian alphabet: a Russian «и» in a reading is
    // untidy (the warning above), never unreadable. (Known limit, not the pack's: the letter-apostrophe «ʼ» U+02BC is no
    // Cyrillic letter, so a reading «інтэрвʼю» written with it — not with ' or ’ — is read as a foreign letter.)
    'script_letters' => '/^[\p{Cyrillic}]$/u',

    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence — the abbreviations a Belarusian text of a clinic, a timetable or an address
    // writes: «і г. д.» (і гэтак далей), «г. зн.» (гэта значыць), «т. п.», «т. зв.», «і інш.», «2026 г.», «вул.», «д. 5»,
    // «кв.», «напр.», «тэл.», «праз 15 хв.», «а 9 гадз.», «5 тыс.», «руб.», «кап.», «сп.»/«спн.» (спадар, спадарыня). None
    // of them is an ordinary word with a dot: «каб.» (кабінет) is left out, «каб» is a word. The multi-word ones stand
    // before the one-word ones they start with («г. д.», «г. зн.» before «г.»). A space inside one is any run of spaces.
    'abbreviations' => [
        'г. д.', 'г. зн.', 'т. п.', 'т. зв.', 'г.', 'гг.', 'інш.', 'вул.', 'пр.', 'пл.', 'д.', 'кв.', 'напр.', 'тэл.', 'хв.',
        'гадз.', 'тыс.', 'руб.', 'кап.', 'ст.', 'сп.', 'спн.',
    ],

    // The word order of a question — a target-language key (Belarusian asks by intonation and «ці»): the no-op.
    'question_word_order' => ['auxiliaries' => [], 'subjects' => []],

    // Words that carry no content of their own — what a listening question and a line of the visit may share without
    // being about the same thing, and what an option's kind is read past: prepositions, conjunctions, particles, pronouns
    // in their cases, possessives, the forms of «быць», «можна / трэба / патрэбна / няма», «калі ласка», «дзякуй»,
    // «прабачце». Both spellings of the non-syllabic «у» («у вас», «ў вас») and of the words it starts («ужо»/«ўжо»,
    // «усё»/«ўсё»). «а» is also the «at» of a time («а чацвёртай»).
    'function_words' => [
        'і', 'й', 'ды', 'у', 'ў', 'на', 'з', 'са', 'із', 'да', 'ад', 'аб', 'па', 'за', 'пра', 'праз', 'пры', 'пад', 'над',
        'без', 'для', 'дзеля', 'каля', 'ля', 'пасля', 'перад', 'паміж', 'між', 'акрамя', 'замест', 'сярод', 'супраць',
        'не', 'ні', 'ці', 'ж', 'жа', 'б', 'бы', 'ну', 'дык', 'хай', 'няхай', 'што', 'каб', 'як', 'так',
        'гэта', 'гэты', 'гэтая', 'гэтае', 'гэтыя', 'той', 'тая', 'тое', 'тыя',
        'ён', 'яна', 'яно', 'яны', 'яго', 'яе', 'іх', 'яму', 'ёй', 'ім', 'імі', 'ёю',
        'нам', 'вам', 'мне', 'мы', 'вы', 'я', 'ты', 'мяне', 'цябе', 'вас', 'нас', 'табе', 'сабе', 'сябе', 'мной',
        'мною', 'табой', 'табою', 'сабой', 'намі', 'вамі', 'хто', 'каго', 'каму', 'кім', 'чаго', 'чым', 'чаму',
        'а', 'але', 'або', 'альбо', 'калі', 'бо', 'таму', 'дзе', 'куды', 'адкуль',
        'там', 'тут', 'сюды', 'туды', 'ужо', 'ўжо', 'яшчэ', 'вельмі', 'толькі', 'таксама', 'нават', 'вось', 'усе',
        'усё', 'ўсе', 'ўсё', 'увесь', 'уся', 'ўвесь', 'ўся', 'свой', 'свая', 'сваё', 'свае', 'мой', 'мая', 'маё', 'мае',
        'твой', 'твая', 'тваё', 'твае', 'ваш', 'ваша', 'вашы', 'наш', 'наша', 'нашы', 'які', 'якая', 'якое', 'якія',
        'каторы', 'каторая', 'быць', 'ёсць', 'быў', 'была', 'было', 'былі', 'буду', 'будзе', 'будуць',
        'можна', 'трэба', 'патрэбна', 'няма', 'ласка', 'дзякуй', 'дзякую', 'прабачце',
    ],

    // The words a recogniser eats — read only for the TARGET's speech: the no-op.
    'unstressed_words' => [],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER — read only for the target's speech ({@see LanguagePack::speech()}), so
    // dead data for Belarusian, written so the pack says its numbers (and pinned by BePackTest). The bare nominative
    // forms only, like ru: an inflected «трох», «двума» is not what a recogniser writes for a digit. Belarusian joins
    // tens and units with no word («дваццаць пяць»), so both joiner lists are empty.
    'number_words' => [
        'нуль' => '0', 'адзін' => '1', 'адна' => '1', 'адно' => '1', 'два' => '2', 'дзве' => '2', 'тры' => '3',
        'чатыры' => '4', 'пяць' => '5', 'шэсць' => '6', 'сем' => '7', 'восем' => '8', 'дзевяць' => '9', 'дзесяць' => '10',
        'адзінаццаць' => '11', 'дванаццаць' => '12', 'трынаццаць' => '13', 'чатырнаццаць' => '14', 'пятнаццаць' => '15',
        'шаснаццаць' => '16', 'сямнаццаць' => '17', 'васямнаццаць' => '18', 'дзевятнаццаць' => '19',
        'дваццаць' => '20', 'трыццаць' => '30', 'сорак' => '40', 'пяцьдзясят' => '50', 'шэсцьдзясят' => '60',
        'семдзесят' => '70', 'восемдзесят' => '80', 'дзевяноста' => '90',
        'сто' => '100', 'дзвесце' => '200', 'трыста' => '300', 'чатырыста' => '400', 'пяцьсот' => '500',
        'шэсцьсот' => '600', 'семсот' => '700', 'васямсот' => '800', 'дзевяцьсот' => '900',
        'тысяча' => '1000', 'тысячы' => '1000', 'тысяч' => '1000', 'тысячу' => '1000',
        'мільён' => '1000000', 'мільёны' => '1000000', 'мільёна' => '1000000', 'мільёнаў' => '1000000',
    ],
    'number_joiners' => [],
    'number_tens_joiners' => [],

    // Two forms of one word in an inflected language: both at least four letters, sharing all but the last two letters
    // of the shorter («горле» — «горла», «прыём» — «прыёму»). One letter is no content word.
    'word_forms' => ['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2],

    // A number: a word that starts with a digit («14», «38,5», «14:30», «4-й»), a cardinal in its cases, a collective
    // («двое», «пяцёра»), an ordinal («першы», «чацвёртай» — «а чацвёртай» is the time «at four»), «палова», «паўтара»,
    // «раз», «двойчы». Whole words, spelled out where a stem would catch an ordinary word: «аднак» (however) is no «адн-»,
    // «сотавы» (mobile) no «сот-», «сям'я» (family) no «сям-», «чацвер» (Thursday) no «чацвёрты»; the ordinals «пяты» and
    // «другі» form by form — «пятух» (a rooster) and «друг», «друга» (a friend) are no numbers.
    'number_pattern' => '/^(?:\d[\p{L}\d:.,-]*|нуль|нуля|нулю|нулём|нулі|адзін|адна|адно|адны|аднаго|аднаму|адным|адной|адною|адну|адных|аднымі|два|дзве|двух|двум|двума|дзвюх|дзвюм|дзвюма|двое|дваіх|тры|трох|тром|трыма|трое|траіх|чатыр\w*|чацвёра|чацв[её]рт\w*|пяць|пяці|пяццю|пяцёра|пяцьдзяс\w*|пяцідз\w*|пяцьсот|пяцісот\w*|пятнацца\w*|пят(?:ы|ая|ае|ыя|ага|ай|аму|ую|ым|аю|ых|ымі)|шэсць|шасці|шасцю|шасцёра|шэсцьдзяс\w*|шасцідз\w*|шэсцьсот|шасцісот\w*|шаснацца\w*|шост\w*|сем|сямі|сямю|сямёра|семдзес\w*|сямідз\w*|семсот|сямісот\w*|сямнацца\w*|сём[ыаоу]\w*|восем|васьмі|васьмю|васьмёра|восемдзес\w*|васьмідз\w*|васямсот|васьмісот\w*|васямнацца\w*|восьм\w*|дзевяць|дзевяці|дзевяццю|дзевяцера|дзевятнацца\w*|дзевяност\w*|дзевяцьсот|дзевяцісот\w*|дзявят\w*|дзесяць|дзесяці|дзесяццю|дзясят\w*|адзінацца\w*|дванацца\w*|трынацца\w*|дваццат\w*|дваццац\w*|трыццат\w*|трыццац\w*|сорак|сарака|саракав\w*|сто|ста|сотн\w*|соты|сотая|сотага|сотай|сотую|сотым|дзвесце|двухсот\w*|трыста|трохсот\w*|тысяч\w*|мільён\w*|мільярд\w*|палов\w*|паўтара|паўтары|паў(?:гадзіны|года|дня|тыдня|месяца|хвіліны)|перш\w*|друг(?:і|ая|ое|ія|ога|ой|ою|ому|ую|ім|іх|імі)|трэц\w*|раз|разы|разу|разоў|двойчы|тройчы)$/u',

    // Time and duration words — units, parts of the day, days, months, «учора», «таму» (тры дні таму), «праз», «сёлета».
    // The month «мая» is left out: it is first of all «мая» (my, feminine); «май» and «маі» stay.
    'time_pattern' => '/^(?:назад|таму|праз|пасля|раней|пазней|хутка|нядаўна|даўно|зараз|цяпер|потым|сёлета|летась|секунд\w*|хвілін\w*|гадзін\w*|сутк\w*|сутак|дзень|дня|дню|дні|дзён|днём|дням|днямі|днях|тыдз\w*|тыдн\w*|месяц\w*|год|года|годзе|гады|гадоў|гадамі|гадах|раніц\w*|ранак|ранку|ранкам|зранку|вечар\w*|увечары|ўвечары|ноч\w*|уначы|ўначы|поўдзень|поўдня|апоўдні|поўнач|поўначы|апоўначы|удзень|ўдзень|учора|ўчора|учарашн\w*|ўчарашн\w*|сёння|сённяшн\w*|заўтра|заўтрашн\w*|пазаўчора|паслязаўтра|панядзел\w*|аўтор\w*|серад\w*|чацвер|чацвярг\w*|пятніц\w*|субот\w*|нядзел\w*|студзен\w*|лют\w*|сакавік\w*|красавік\w*|май|маі|чэрвен\w*|ліпен\w*|жнівен\w*|жніўн\w*|верас\w*|кастрычнік\w*|лістапад\w*|снежань|снежня|снежні|выхадн\w*)$/u',

    // The units something is COUNTED in — what makes a value an amount and not a date («праз тыдзень», «тры дні» against
    // «сёння», «у пятніцу»). Parts of the day, weekdays and months are not here; «метр», «грам» form by form («метро»,
    // «граматыка» are none).
    'amount_pattern' => '/^(?:секунд\w*|хвілін\w*|гадзін\w*|сутк\w*|сутак|дзень|дня|дню|дні|дзён|тыдз\w*|тыдн\w*|месяц\w*|год|года|гады|гадоў|раз|разы|разу|разоў|градус\w*|працэнт\w*|метр|метра|метры|метраў|метрам|метрамі|кіламетр\w*|сантыметр\w*|міліметр\w*|кілаграм\w*|грам|грама|грамы|грамаў|грамам|грамамі|літр\w*|міліграм\w*|мілілітр\w*|таблет\w*|капсул\w*|кропл\w*|кропель|лыжк\w*|лыжак|доза|дозы|дозу|доз)$/u',

    // What carries an amount and is said WITH it — prepositions and determiners standing right before it («на гэтым
    // тыдні», «праз тыдзень», «кожны дзень», «на працягу тыдня», «увесь тыдзень») and the «а» of a time («а чацвёртай» —
    // at four: the option says it so, as ru says «в четыре»). Read only to the left, and only next to the value.
    'amount_prefix' => '/^(?:на|у|ў|а|за|праз|да|пасля|з|са|па|каля|прыкладна|амаль|працягу|гэты|гэтая|гэтую|гэту|гэтыя|гэтага|гэтым|гэтых|гэтай|мінул\w*|наступн\w*|бліжэйш\w*|той|тую|тым|кожн\w*|увесь|ўвесь|усю|ўсю|усе|ўсе|цэлы|цэлую|цэлыя)$/u',

    // Target-language keys — Belarusian is no plan's target: the spec's no-ops.
    'everyday_words' => [],
    'ordinary_heads' => [],
    'closers' => [],
    'saying_verbs' => [],
    'alternative_words' => [],
    'second_question_pattern' => '/(?!)/u',
    'articles' => [],
    'dangling_words' => [],
    'seam_repeatable_words' => [],
    'article_sound' => [
        'before_vowel' => '', 'before_consonant' => '',
        'vowel' => '/(?!)/u', 'consonant' => '/(?!)/u', 'spelled' => '/(?!)/u', 'exception' => '/(?!)/u',
    ],
    'clause' => ['subjects' => [], 'finite' => [], 'contractions' => [], 'subordinators' => [], 'subordinators_before_subject' => []],
    'unresolved_pronouns' => [
        'words' => [], 'frame_initial_subject' => [], 'existential' => [], 'determiner_or_number' => [], 'partitive' => [],
        'be_forms' => [], 'determiners' => [],
    ],

    // A past-tense form right after «я» — the learner's gender said in their own line while it is unknown. Up to two of
    // «не», «б/бы», «ужо», «зноў», «таксама», «сам(а)», «вельмі», «сёння», «учора», «усё», «гэта» and the object pronouns
    // «вас», «вам», «яго», «яе», «іх» may stand between («Я б хацела», «Я ўжо не хадзіла», «Я вас не пачуў»). Belarusian writes the masculine past after a vowel with «ў» («быў», «хадзіў», «запісаўся», «з'еў») and the
    // feminine with «-ла» («была», «пайшла», «запісалася»); of the masculine forms on a consonant the common ones are read —
    // «(з)мог», «дапамог», «прынёс», «прывёз», «лёг». Not a past form: «зноў» (again), «мала», «ўдала» (little, luckily —
    // «Я мала сплю»). «я» inside «сям'я» after an apostrophe of any kind is no «я».
    'gendered_past_pattern' => '/(?<![\p{L}\'’\x{02BC}])я\s+(?:(?:не|б|бы|ж|ужо|ўжо|яшчэ|зноў|раней|таксама|сам|сама|даўно|нядаўна|толькі|проста|вельмі|нават|заўсёды|ніколі|сёння|учора|ўчора|тут|там|так|усё|ўсё|гэта|вас|вам|яго|яе|іх)\s+){0,2}(?!(?:зноў|мала|замала|нямала|удала|ўдала|няўдала)(?![\p{L}]))((?:\p{Cyrillic}[\p{Cyrillic}\'’\x{02BC}]*(?:[аеёіоуыэюя]ў(?:ся)?|ла(?:ся)?))|(?:з|да|дапа|пера|па)?мог|(?:пры|за|пера|ад|вы|з|да|пад)?нёс|(?:пры|за|вы|з|да|пера|ад)?вёз|(?:за|пера|па|с)?лёг)(?![\p{L}])/u',

    // «The native frame contains NO word that agrees with the slot in gender or number» (FRAMES) — read at the slot:
    //  - the word right before `___` agrees when it is one of `words` (possessives, demonstratives, «які», «адзін»,
    //    «кожны» — in their cases) or one of `short_forms`, or is an adjective by its ending with at least `min_letters`
    //    letters («бліжэйшы ___», «наступную ___»);
    //  - one of the `after_slot_words` words after `___` agrees when it is listed — the predicate of a slot that is the
    //    subject («___ вольны?», «___ дазволены?»). Belarusian says it with the full form, so the full forms are here.
    // Left out on purpose: «гэта» and «тое» (it is / that — agree with nothing), «такое» («Што такое ___?»), «таму»
    // (therefore), «мае»/«маю» (also «has»/«I have»: «Ён мае ___»). The endings are the ones no other frequent word ends
    // with: an adjective ending shared with a verb or a noun is narrowed to the letter before it — «-ыя» to «-ныя», «-выя»…
    // (not «кансультацыя», «рэгістрацыя»), «-ія» to «-нія», «-кія» (not «алергія»), «-ую» to «-ную», «-вую»… (not «працую»,
    // «рэкамендую»), «-ым» to «-ным»… (not «паглядзім», «зробім»), «-ай» to «-най»… (not «давай»), «-ой» to «-гой», «-лой»,
    // «-хой» (not «сабой», «табой»), «-аму» to «-наму»… (not «чаму», «каму», «таму»), «-ое» to «-лое», «-гое», «-хое» (not
    // «такое»); «-ае», «-яе» are left out (3rd person verbs: «знае», «прымае», «адчувае», «правярае» — the neuter
    // «вольнае» is a listed word), and so are «-кі» (колькі, толькі), «-ты» (дакументы) and a lone «-ы»/«-і».
    'agreement' => [
        'words' => [
            'мой', 'мая', 'маё', 'майго', 'майму', 'маёй', 'маёю', 'маім', 'маіх', 'маімі',
            'твой', 'твая', 'тваё', 'твае', 'твайго', 'твайму', 'тваёй', 'тваім', 'тваю', 'тваіх',
            'свой', 'свая', 'сваё', 'свае', 'свайго', 'свайму', 'сваёй', 'сваім', 'сваю', 'сваіх',
            'наш', 'наша', 'нашае', 'нашы', 'нашага', 'нашай', 'нашаму', 'нашым', 'нашу', 'нашых',
            'ваш', 'ваша', 'вашае', 'вашы', 'вашага', 'вашай', 'вашаму', 'вашым', 'вашу', 'вашых',
            'гэты', 'гэтая', 'гэтае', 'гэтыя', 'гэтага', 'гэтай', 'гэтаму', 'гэтым', 'гэтую', 'гэту', 'гэтых',
            'той', 'тая', 'тыя', 'таго', 'тым', 'тую', 'тых',
            'які', 'якая', 'якое', 'якія', 'якога', 'якой', 'якому', 'якім', 'якую', 'якіх',
            'каторы', 'каторая', 'каторае', 'каторыя',
            'чый', 'чыя', 'чыё', 'чые',
            'адзін', 'адна', 'адно', 'адны', 'аднаго', 'адной', 'аднаму', 'адным', 'адну',
            'кожны', 'кожная', 'кожнае', 'кожныя', 'кожнага', 'кожнай', 'кожнаму', 'кожным', 'кожную',
            'такі', 'такая', 'такія', 'іншы', 'іншая', 'іншае', 'іншыя', 'увесь', 'уся', 'ўвесь', 'ўся',
        ],
        'short_forms' => [
            'патрэбен', 'патрэбна', 'патрэбны', 'патрэбная', 'патрэбнае', 'патрэбныя',
            'гатовы', 'гатова', 'гатовая', 'гатовае', 'гатовыя', 'вольны', 'вольная', 'вольнае', 'вольныя',
            'заняты', 'занята', 'занятая', 'занятае', 'занятыя', 'адчынены', 'адчынена', 'адчыненая', 'адчыненае', 'адчыненыя',
            'зачынены', 'зачынена', 'зачыненая', 'зачыненае', 'зачыненыя', 'даступны', 'даступная', 'даступнае', 'даступныя',
            'аплачаны', 'аплачана', 'аплачаная', 'аплачанае', 'аплачаныя',
            'забраніраваны', 'забраніравана', 'забраніраваная', 'забраніраванае', 'забраніраваныя',
            'дазволены', 'дазволена', 'дазволеная', 'дазволенае', 'дазволеныя', 'уключаны', 'уключана', 'уключаная', 'уключанае', 'уключаныя',
            'павінен', 'павінна', 'павінны', 'неабходны', 'неабходная', 'неабходнае', 'неабходныя',
            'абавязковы', 'абавязковая', 'абавязковае', 'абавязковыя',
        ],
        'suffixes_before_slot' => [
            'ны', 'вы', 'шы', 'ні', 'гі', 'ая', 'яя', 'лое', 'гое', 'хое',
            'ныя', 'выя', 'шыя', 'лыя', 'нія', 'кія',
            'ную', 'вую', 'шую', 'лую', 'юю',
            'ага', 'яга', 'ога', 'наму', 'ваму', 'шаму',
            'ным', 'вым', 'шым', 'лым', 'ых', 'іх',
            'най', 'шай', 'лай', 'яй', 'гой', 'лой', 'хой',
        ],
        'min_letters' => 4,
        'after_slot_words' => 2,
    ],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а) — a target key, written so every pack has its «Sorry?»: what a
    // Belarusian says who did not catch the other.
    'rescue_line' => 'Прабачце?',

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9) — the same line as every pack's: the translation of the target's
    // line for a Belarusian learner (en «I see. Please go on.»).
    'neutral_reply' => 'Зразумела. Працягвайце, калі ласка.',

    // THE FORMS OF ONE WORD and the persons swapped — target keys of the speech comparison: the no-ops.
    'irregular_forms' => [],
    'inflection_rules' => [],
    'person_swap' => [],

    // THE JUDGE OF THE TALK'S CONSTRUCTIONS reads only the target's pack (ConversationMoves: `for($plan->targetLang())`),
    // and Belarusian is no plan's target — all of this is dead data. Nothing is forgiven but the negation, written as the
    // order says: «не» and «ні» free anywhere in the move («У мяне не баліць горла» says «У мяне баліць ___»).
    'contractions' => [],
    'contractions_before' => [],
    'intro_words' => [],
    'clause_starters' => [],
    'negation' => ['words' => ['не', 'ні'], 'after' => null, 'do_support' => []],
    'partitive' => [],

    // THE WORDS THAT TELL BELARUSIAN FROM ITS CYRILLIC NEIGHBOURS (наряд LANG-1 §5) — read by the guard of the role's
    // translation (ReplyNative) against ru and uk, the packs that write the same `script_letters`. FREQUENT AND
    // DISTINCTIVE, not merely the most frequent: every word here is an ordinary Belarusian word of short dialogue lines
    // and is NOT an ordinary word, in the same spelling, of Russian or Ukrainian. A word the neighbours also say («і», «у»,
    // «не», «на», «да», «так», «як», «для», «вы», «мне») would be counted against their own lines — two such words make
    // an ordinary Russian or Ukrainian line «Belarusian». So: no «і» (uk «і»), «ці» (uk «these»), «але», «або», «бо»,
    // «хто», «можна», «зараз», «ласка» (uk), «да» (ru «yes»), «ад» (ru «hell»), «па» (ru), «добра», «добры» (ru short
    // forms), «мая», «мае», «маю» (ru «May» in its cases, uk «I have»), «учора» (uk). One run of letters each, lower case.
    'common_words' => [
        'што', 'гэта', 'гэты', 'гэтая', 'ёсць', 'мяне', 'цябе', 'яго', 'яе', 'ён', 'яна', 'яны', 'вельмі', 'таксама',
        'толькі', 'яшчэ', 'ужо', 'ўжо', 'калі', 'дзе', 'сёння', 'заўтра', 'дзень', 'дзякуй', 'дзякую', 'прабачце',
        'трэба', 'хачу', 'магу', 'можаце', 'каб', 'вось', 'цяпер', 'будзе', 'няма', 'колькі', 'чаму', 'чым', 'якая',
        'якія', 'усё', 'ўсё', 'пра', 'праз', 'пасля', 'вядома', 'ў',
    ],

    // THE TITLE OF A TALK (наряд LANG-1 §6): the code declines no Belarusian role, so the title names the roles as they
    // are written — «Размова: рэгістратар і лекар», lower-cased (Belarusian nouns take no capital), «і» (the neutral
    // written «and»; «ды» is colloquial, «й» after a vowel optional).
    'talk_title_template' => [
        'title' => 'Размова: {roles}',
        'and' => 'і',
        'anyone' => 'Размова',
        'lower_first' => true,
    ],
];
