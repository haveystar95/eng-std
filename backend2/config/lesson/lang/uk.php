<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · uk — what the lesson validator reads of Ukrainian
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b), written in full by наряд LANG-1 (key spec: docs/research/lang-1/pack-keys.md).
| Ukrainian is written as the LEARNER'S OWN language only (LanguageRoles::planNatives()): the reading of the target,
| the listening questions and their options, the native frames and fillers, the grey translation of the role. No plan
| teaches Ukrainian, so every key only a TARGET reads is written as the spec's explicit no-op — never null, which would
| count `lang.pack_missing` — and is dead data here. Two target-side keys hold real Ukrainian all the same, never read
| for this side: `number_words` (as the Russian pack writes its own, and so the numbers of a Ukrainian line are pinned
| by UkPackTest) and `negation` (the order's «uk/be ['не','ні']»). `talk_title_template` is the no-op `[]`, as ru writes
| it: the title of a talk declines Ukrainian roles in code (NativeStrings::TALK_TITLE).
|
| Words are compared as LanguagePack::normal() gives them — folded, lower case, the typographic apostrophe ’ a plain
| one: «п’ятниця» is met as «п'ятниця». The Ukrainian modifier-letter apostrophe ʼ (U+02BC) is a LETTER to Unicode and
| is not folded, so every pattern that reads a word with an apostrophe names both: ['ʼ], and a listed word with an
| apostrophe is written in both spellings.
*/
return [
    // A reading of the target is written in the UKRAINIAN alphabet — 33 letters, «ґ є і ї» among them and no «ё ъ ы э» —,
    // digits, punctuation, whitespace, the stress mark U+0301 and the apostrophe (’ and ' are punctuation, ʼ U+02BC is
    // named). A warning, never a failure: «Ай хев э сор сроут» with the Russian «э» reads, it is only not Ukrainian
    // spelling (the scouting day uk→en wrote exactly that).
    'script' => '/^[абвгґдеєжзиіїйклмнопрстуфхцчшщьюяАБВГҐДЕЄЖЗИІЇЙКЛМНОПРСТУФХЦЧШЩЬЮЯ\p{N}\p{P}\s\x{0301}\x{02BC}]*$/u',

    // One LETTER of a reading, matched alone — the writing, not the alphabet: a letter of another writing is fatal
    // («ֆоутoуз» cannot be read), a Russian «э» in a Ukrainian reading is only the warning above. Exactly the string of
    // every Cyrillic pack (ru, be): two packs are neighbours for the guard of the role's translation when these strings
    // are the same. (The apostrophe ʼ U+02BC is a letter of no script to Unicode and fails this pattern — a code matter of
    // LanguageWords::foreignLetters(), not of the pack, which must write the string exactly.)
    'script_letters' => '/^[\p{Cyrillic}]$/u',

    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence (наряд CHECK-1): «Клініка на вул. Шевченка, буд. 5, каб. 12.» is one sentence,
    // «о 10 год.» in a slot is a time, not a sentence of its own. A space inside one is any run of spaces. None of them is
    // an ordinary Ukrainian word with a dot after it («р», «м», «хв», «год», «грн», «каб», «ім» are no words of the
    // language; «див.» is left out — «див» is the genitive plural of «диво»).
    'abbreviations' => [
        'т. д.', 'т. п.', 'т. ч.', 'т. зв.', 'та ін.', 'р.', 'рр.', 'вул.', 'просп.', 'пров.', 'пл.', 'буд.', 'корп.', 'кв.',
        'пов.', 'каб.', 'кім.', 'обл.', 'м.', 'с.', 'хв.', 'год.', 'тис.', 'грн.', 'коп.', 'напр.', 'ст.', 'проф.', 'доц.',
        'ім.', 'тел.',
    ],

    // Words that carry no content of their own — what a listening question and a line of the visit may share without
    // being about the same thing: prepositions, conjunctions, particles, pronouns and their cases, the possessives and
    // «який» in their cases, the forms of «бути», «можна / треба / потрібно», «так / ні», «будь ласка», «дякую», «гаразд»,
    // «вибачте».
    'function_words' => [
        'і', 'й', 'та', 'а', 'але', 'або', 'чи', 'ні', 'не', 'же', 'ж', 'б', 'би', 'бо', 'то', 'що', 'щоб', 'як', 'так',
        'якщо', 'коли', 'де', 'куди', 'звідки', 'чому', 'навіщо', 'скільки', 'хто', 'кого', 'кому', 'ким', 'чим', 'чого',
        'хоча', 'поки', 'ніж', 'тобто', 'адже', 'проте', 'однак', 'навіть', 'хай', 'нехай', 'ну', 'ага', 'окей',
        'в', 'у', 'на', 'з', 'із', 'зі', 'зо', 'до', 'від', 'по', 'за', 'під', 'над', 'при', 'про', 'для', 'без', 'через',
        'перед', 'після', 'між', 'біля', 'серед', 'крізь', 'о', 'об', 'щодо', 'проти',
        'це', 'цей', 'ця', 'ці', 'цього', 'цієї', 'цьому', 'цій', 'цим', 'цією', 'цю', 'цих',
        'той', 'те', 'ті', 'того', 'тієї', 'тому', 'тій', 'тим', 'тією', 'ту', 'тих',
        'я', 'ти', 'він', 'вона', 'воно', 'ми', 'ви', 'вони', 'мене', 'мені', 'мною', 'тебе', 'тобі', 'тобою', 'його',
        'йому', 'ним', 'нього', 'ньому', 'її', 'їй', 'нею', 'неї', 'ній', 'нас', 'нам', 'нами', 'вас', 'вам', 'вами', 'їх',
        'їм', 'ними', 'них', 'себе', 'собі',
        'мій', 'моя', 'моє', 'мої', 'мого', 'моєї', 'моєму', 'моїй', 'моїм', 'мою', 'моїх',
        'твій', 'твоя', 'твоє', 'твої', 'твого', 'твоєї', 'твою',
        'свій', 'своя', 'своє', 'свої', 'свого', 'своєї', 'своєму', 'своїй', 'своїм', 'свою', 'своїх',
        'наш', 'наша', 'наше', 'наші', 'нашого', 'нашої', 'нашому', 'нашій', 'нашим', 'нашу', 'наших',
        'ваш', 'ваша', 'ваше', 'ваші', 'вашого', 'вашої', 'вашому', 'вашій', 'вашим', 'вашу', 'ваших',
        'який', 'яка', 'яке', 'які', 'якого', 'якої', 'якому', 'якій', 'яким', 'якою', 'яку', 'яких', 'котрий',
        'весь', 'вся', 'все', 'усе', 'всі', 'усі', 'всього', 'усього', 'всім', 'усім', 'всіх', 'усіх',
        'там', 'тут', 'тоді', 'вже', 'уже', 'ще', 'дуже', 'тільки', 'лише', 'також', 'теж', 'ось', 'от',
        'бути', 'є', 'був', 'була', 'було', 'були', 'буду', 'будеш', 'буде', 'будемо', 'будете', 'будуть',
        'можна', 'треба', 'потрібно',
        'будь', 'будьте', 'ласка', 'дякую', 'гаразд', 'звісно', 'звичайно', 'добре', 'вибачте', 'перепрошую',
    ],

    // Two forms of one word in an inflected language, as the Russian pack reads them: both at least four letters,
    // sharing all but the last two letters of the shorter («лікаря» — «лікар», «хвилин» — «хвилини», «третю» —
    // «третя»). One letter is no content word («є», «і»).
    'word_forms' => ['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2],

    // A number: a word that starts with a digit («18», «3-го», «10-ї»), a numeral in its cases, an ordinal, «раз /
    // двічі», «половина / пів / півтора / чверть». The apostrophe of «п'ять», «дев'ять» in both spellings; the ordinals
    // of five are listed form by form, so «п'ятниця» (a time word) is not one; «друг-» only in the forms of «другий»
    // («друга», «другу» are also «a friend's», «to a friend» — heuristic); «сім» never takes «сім'я».
    'number_pattern' => '/^(?:\d[\p{L}\d:.,\'\x{02BC}\/-]*|нуль|нуля|нулю|нулем|один|одн(?:а|е|у|і|о|ого|ому|ій|им|их|ими|ієї|ією)|два|дві|двох|двом|двома|двоє|двоїх|три|трьох|трьом|трьома|троє|трійк\w*|чотир\w*|п[\'\x{02BC}]?ят(?:ь|и|ьма|ьох|еро|ий|а|е|і|ого|ому|ій|им|их|ими|ою|ої|у|надцят\w*|десят\w*|сот\w*)|шіст\w*|шест\w*|шост\w*|сім|сімох|сімома|сімнадцят\w*|сімдесят\w*|сімсот\w*|семи|семеро|сьом\w*|вісім\w*|вісьм\w*|восьм\w*|дев[\'\x{02BC}]?ят\w*|дев[\'\x{02BC}]?яност\w*|десят\w*|одинадцят\w*|дванадцят\w*|тринадцят\w*|двадцят\w*|тридцят\w*|сорок\w*|сто|ста|сотн\w*|сотий|сота|соте|сотого|двісті|двохсот\w*|трист\w*|трьохсот\w*|тисяч\w*|мільйон\w*|мільярд\w*|половин\w*|пів|півтора|півтори|півгодини|півроку|чверт\w*|раз|рази|разів|двічі|тричі|перш(?:ий|а|е|і|ого|ому|ій|им|их|ими|ою|ої|у)|друг(?:ий|а|е|і|ого|ому|ій|им|их|ими|ою|ої|у)|трет\w*|четверт\w*)$/u',

    // Time and duration words — units, parts of the day, days, months, «вчора», «тому» (ago), «через», «раніше»,
    // «вчасно». Weekdays and months form by form where a bare stem would take another word («середа», never «серед»;
    // «квітня», never «квітка»; «жовтня», never «жовтий»; «година», never «годинник»).
    'time_pattern' => '/^(?:тому|назад|через|раніше|пізніше|згодом|скоро|незабаром|нещодавно|недавно|давно|зараз|тепер|нині|потім|одразу|відразу|вчасно|заздалегідь|рано|пізно|досі|секунд\w*|хвилин\w*|хвилинк\w*|годин[аиуіо]?|годинку|годиною|годинах|годинами|доба|добу|доби|діб|добою|день|дня|дні|днів|днем|дню|днями|днях|вдень|удень|тиждень|тижн\w*|місяць|місяц\w*|рік|року|роки|років|роком|році|ранок|ранку|ранком|вранці|уранці|зранку|вечір|вечора|вечорі|вечором|ввечері|увечері|звечора|ніч|ночі|вночі|уночі|опівночі|північ|полудень|полудня|опівдні|пополудні|вчора|учора|вчорашн\w*|учорашн\w*|позавчора|сьогодні|сьогоднішн\w*|завтра|завтрашн\w*|післязавтра|понеділ\w*|вівтор\w*|середа|середу|середи|середою|четвер|четверга|четвергу|четвергом|п[\'\x{02BC}]?ятниц\w*|субот\w*|неділ\w*|вихідн\w*|січ(?:ень|ня|ні|нем)|лют(?:ий|ого|ому|ім)|берез(?:ень|ня|ні|нем)|квіт(?:ень|ня|ні|нем)|трав(?:ень|ня|ні|нем)|черв(?:ень|ня|ні|нем)|лип(?:ень|ня|ні|нем)|серп(?:ень|ня|ні|нем)|верес(?:ень|ня|ні|нем)|жовт(?:ень|ня|ні|нем)|листопад\w*|груд(?:ень|ня|ні|нем)|щодня|щоденн\w*|щотижня|щомісяця|щоранку|щовечора|щоночі|щороку)$/u',

    // The units something is COUNTED in — what makes a value an amount and not a date («через тиждень», «два дні»
    // against «сьогодні», «у п'ятницю»). Read by «Поймай число» (34-7) for its options: numerals are amounts by
    // `number_pattern`, these words are amounts without one. Parts of the day, weekdays and months are not here.
    'amount_pattern' => '/^(?:секунд\w*|хвилин\w*|хвилинк\w*|годин[аиуі]?|годинку|годиною|годинах|годинами|доба|добу|доби|діб|добою|день|дня|дні|днів|днем|дню|днями|днях|тиждень|тижн\w*|місяць|місяц\w*|рік|року|роки|років|роком|році|раз|рази|разів|разу|градус\w*|відсот\w*|метр\w*|кілометр\w*|кілограм\w*|кіло|грам\w*|літр\w*|міліграм\w*|мілілітр\w*|таблет\w*|пігулк\w*|пігулок|капсул\w*|крапл\w*|крапель|доз[аиуі]|дозою|доз|ложк\w*|ложок)$/u',

    // What carries an amount and is said WITH it — prepositions and determiners right before it: «на десять хвилин
    // раніше», «через тиждень», «цього тижня». Read only to the left, and only next to the value.
    'amount_prefix' => '/^(?:на|в|у|за|через|до|після|з|із|зі|по|о|об|близько|приблизно|десь|майже|щонайменше|понад|цього|цей|цю|ці|цих|цієї|цьому|минул\w*|наступн\w*|найближч\w*|кожн\w*|той|ту|того|тієї|тій)$/u',

    // ─── Target-language keys: no plan teaches Ukrainian — each is the spec's no-op, never read for this side ─────────
    'question_word_order' => ['auxiliaries' => [], 'subjects' => []],
    'unstressed_words' => [],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER (наряд FIX-2, п. 2) — dead data for a native-only pack (the speech of a
    // day is compared by the TARGET's pack), written as the Russian pack writes its own: only the bare nominative
    // forms — «десять», «дві», «двісті», not «десяти», «двома» —, in the spelling of the language; LanguagePack::speech()
    // puts them in the form of the text («п'ять» → «пять», the apostrophe gone, as a text spells it). Ukrainian joins
    // the words of one number with no joiner («двісті п'ятдесят», «дві тисячі двадцять шість»).
    'number_words' => [
        'нуль' => '0', 'один' => '1', 'одна' => '1', 'одне' => '1', 'два' => '2', 'дві' => '2', 'три' => '3',
        'чотири' => '4', "п'ять" => '5', 'шість' => '6', 'сім' => '7', 'вісім' => '8', "дев'ять" => '9', 'десять' => '10',
        'одинадцять' => '11', 'дванадцять' => '12', 'тринадцять' => '13', 'чотирнадцять' => '14', "п'ятнадцять" => '15',
        'шістнадцять' => '16', 'сімнадцять' => '17', 'вісімнадцять' => '18', "дев'ятнадцять" => '19',
        'двадцять' => '20', 'тридцять' => '30', 'сорок' => '40', "п'ятдесят" => '50', 'шістдесят' => '60',
        'сімдесят' => '70', 'вісімдесят' => '80', "дев'яносто" => '90',
        'сто' => '100', 'двісті' => '200', 'триста' => '300', 'чотириста' => '400', "п'ятсот" => '500',
        'шістсот' => '600', 'сімсот' => '700', 'вісімсот' => '800', "дев'ятсот" => '900',
        'тисяча' => '1000', 'тисячі' => '1000', 'тисяч' => '1000', 'тисячу' => '1000',
        'мільйон' => '1000000', 'мільйона' => '1000000', 'мільйони' => '1000000', 'мільйонів' => '1000000',
    ],
    'number_joiners' => [],
    'number_tens_joiners' => [],

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

    // A past-tense form right after «я» — the learner's gender said in their own line while it is unknown: masculine
    // «-в» after a vowel («хотів», «був», «записався»), the masculine forms without it («міг», «допоміг», «ніс», «виріс»,
    // «побіг», «звик»…), feminine «-ла» («хотіла», «була», «їла», «записалася»). Up to three short words may stand between —
    // particles («не», «б/би», «ж», «вже», «теж», «знов»…), «сам(а)», «там/тут», and the object pronouns Ukrainian puts
    // before its verb («Я вас не почула», «Я це вже робила»). «Я б хотів записатися» says the gender (the conditional
    // too), «Я хочу записатися» says none. «Я» inside «ім'я», «сім'я» is no «я»; «знов» (again) is no past form.
    'gendered_past_pattern' => '/(?<![\p{L}\'’\x{02BC}])я\s+(?:(?:не|б|би|ж|же|вже|уже|ще|щойно|раніше|теж|також|сам|сама|давно|нещодавно|вчора|учора|знову|знов|навіть|вперше|колись|двічі|тричі|там|тут|тоді|просто|тільки|лише|так|дуже|завжди|ніколи|вас|вам|його|йому|її|їй|їх|їм|це|цього|все|усе|нічого|нікого|тебе|тобі|себе|собі|нас|нам)\s+){0,3}(?!знов(?![\p{L}]))([\p{Cyrillic}\'’\x{02BC}]*[аеєиіїоуюя]в(?:ся|сь)?|[\p{Cyrillic}\'’\x{02BC}]+ла(?:ся|сь)?|(?:до|з|с|в|у|по|пере|при|за|від|ви|під|про|роз|на)?(?:по)?(?:міг|ніс|віз|ліг|мерз|тік|біг|ріс|пік|ліз|сох|вик))(?![\p{L}])/u',

    // «The native frame contains NO word that agrees with the slot in gender or number» (FRAMES, v4.5) — read at the
    // slot, as the Russian pack reads it:
    //  - the word right before `___` agrees when it is one of `words` (possessives, demonstratives, «який», «один»,
    //    «кожен», «такий», «інший» — in their cases) or one of `short_forms`, or is an adjective by an ending of
    //    `suffixes_before_slot` with at least `min_letters` letters («новий ___», «нової ___»);
    //  - one of the `after_slot_words` words after `___` agrees when it is one of `words` or `short_forms` — the
    //    predicate of a slot that is the subject («___ потрібен?», «___ відчинена?»).
    // Left out on purpose: «це» («Це ___» — «it is», agrees with nothing), «таке» («Що таке ___?» — «what is ___?»), «те»,
    // «все», «його / її / їх» (never change), «та» (and), «тому» («___ тому» — ago), the impersonal «потрібно /
    // необхідно / дозволено». The endings are the two-letter ones only — «-а», «-е», «-і», «-у» end every other word —
    // and neither «-ого», «-ому» nor «-ою»: «У нього ___» (he has), «Чому ___?», «Оплачу карткою ___» end so and agree with
    // nothing; «мого», «якому», «цьому», «моєю» are listed words.
    'agreement' => [
        'words' => [
            'мій', 'моя', 'моє', 'мої', 'мого', 'моєї', 'моєму', 'моїй', 'моїм', 'моєю', 'мою', 'моїх', 'моїми',
            'твій', 'твоя', 'твоє', 'твої', 'твого', 'твоєї', 'твоєму', 'твоїй', 'твоїм', 'твоєю', 'твою', 'твоїх', 'твоїми',
            'свій', 'своя', 'своє', 'свої', 'свого', 'своєї', 'своєму', 'своїй', 'своїм', 'своєю', 'свою', 'своїх', 'своїми',
            'наш', 'наша', 'наше', 'наші', 'нашого', 'нашої', 'нашому', 'нашій', 'нашим', 'нашою', 'нашу', 'наших', 'нашими',
            'ваш', 'ваша', 'ваше', 'ваші', 'вашого', 'вашої', 'вашому', 'вашій', 'вашим', 'вашою', 'вашу', 'ваших', 'вашими',
            'цей', 'ця', 'ці', 'цього', 'цієї', 'цьому', 'цій', 'цим', 'цією', 'цю', 'цих', 'цими',
            'той', 'ті', 'того', 'тієї', 'тій', 'тим', 'тією', 'ту', 'тих', 'тими',
            'який', 'яка', 'яке', 'які', 'якого', 'якої', 'якому', 'якій', 'яким', 'якою', 'яку', 'яких', 'якими',
            'котрий', 'котра', 'котре', 'котрі', 'котрого', 'котрої', 'котрому', 'котрій', 'котрим', 'котру', 'котрих',
            'чий', 'чия', 'чиє', 'чиї',
            'один', 'одна', 'одне', 'одні', 'одного', 'однієї', 'одному', 'одній', 'одним', 'однією', 'одну',
            'кожен', 'кожний', 'кожна', 'кожне', 'кожні', 'кожного', 'кожної', 'кожному', 'кожній', 'кожним', 'кожною', 'кожну',
            'такий', 'така', 'такі', 'такого', 'такої', 'такому', 'такій', 'таким', 'такою', 'таку', 'таких',
            'інший', 'інша', 'інше', 'інші', 'іншого', 'іншої', 'іншому', 'іншій', 'іншим', 'іншою', 'іншу', 'інших',
            'весь', 'вся',
        ],
        'short_forms' => [
            'потрібен', 'потрібна', 'потрібне', 'потрібні', 'повинен', 'повинна', 'повинне', 'повинні',
            'готовий', 'готова', 'готове', 'готові', 'вільний', 'вільна', 'вільне', 'вільні',
            'зайнятий', 'зайнята', 'зайняте', 'зайняті', 'відкритий', 'відкрита', 'відкрите', 'відкриті',
            'закритий', 'закрита', 'закрите', 'закриті', 'відчинений', 'відчинена', 'відчинене', 'відчинені',
            'зачинений', 'зачинена', 'зачинене', 'зачинені', 'доступний', 'доступна', 'доступне', 'доступні',
            'оплачений', 'оплачена', 'оплачене', 'оплачені', 'заброньований', 'заброньована', 'заброньоване', 'заброньовані',
            'дозволений', 'дозволена', 'дозволене', 'дозволені', 'включений', 'включена', 'включене', 'включені',
            'необхідний', 'необхідна', 'необхідне', 'необхідні',
            "обов'язковий", "обов'язкова", "обов'язкове", "обов'язкові",
            'обовʼязковий', 'обовʼязкова', 'обовʼязкове', 'обовʼязкові',
        ],
        'suffixes_before_slot' => ['ий', 'ій', 'ої', 'им', 'их'],
        'min_letters' => 5,
        'after_slot_words' => 2,
    ],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а) — what a rescue move of the talk says in the learner's own bubble
    // (кадр 37-7, en «Sorry?»). Dead data: read only for a target.
    'rescue_line' => 'Перепрошую?',

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9) — the same line as every pack's, in this language (en «I see.
    // Please go on.»): the translation of the target's line for a Ukrainian learner.
    'neutral_reply' => 'Зрозуміло. Продовжуйте, будь ласка.',

    // THE TITLE OF A TALK (наряд LANG-1 §6): the no-op, as ru writes it. A Ukrainian title declines its roles — «Поговори
    // з адміністратором і лікарем» — and that is the code's (`NativeStrings::TALK_TITLE`), whatever the pack says.
    'talk_title_template' => [],

    // THE FORMS OF ONE WORD and the persons swapped — target keys of the speech comparison: the no-ops.
    'irregular_forms' => [],
    'inflection_rules' => [],
    'person_swap' => [],

    // THE JUDGE OF THE TALK'S CONSTRUCTIONS (наряд FIX-4 §2, FIX-4b §1) — read only for a target: nothing forgiven. The
    // negation is the order's Ukrainian one («не», «ні», free anywhere), dead data for a native-only pack.
    'contractions' => [],
    'contractions_before' => [],
    'intro_words' => [],
    'clause_starters' => [],
    'negation' => ['words' => ['не', 'ні'], 'after' => null, 'do_support' => []],
    'partitive' => [],

    // THE LANGUAGE'S FREQUENT AND DISTINCTIVE WORDS (наряд LANG-1 §5) — what tells a Ukrainian line from a Russian or a
    // Belarusian one in the same letters (ReplyNative). The order asked for «30 самых частых слов»; these are frequent AND
    // distinctive: the guard compares a line with EVERY Cyrillic pack, and a word that is also an ordinary word of the
    // learner's language — «для», «до», «на», «не», «і», «але», «як», «так», «можна», «зараз», «вас» — sitting only in
    // this list would make an ordinary Russian or Belarusian line look Ukrainian (a probe refused «Для записи к врачу
    // приходите до двенадцати» for ru when a uk list held «для», «до»). Each is no ordinary word of ru or be: most are
    // spelled with «і», «ї», «є», «щ» or «и» that the other two do not write that way. Left out although frequent: «від»
    // (a Belarusian noun, «від» — kind, view), «ні», «з», «або», «маю», «бачу» (Belarusian words too), «коли», «ласка»,
    // «добре», «може», «можете», «прошу», «хочу», «тебе», «ось» (Russian words too). One run of letters each.
    'common_words' => [
        'що', 'це', 'є', 'чи', 'який', 'яка', 'дуже', 'також', 'теж', 'тільки', 'лише', 'він', 'вона', 'вони', 'ми', 'ви',
        'ти', 'мені', 'мене', 'його', 'її', 'після', 'вже', 'ще', 'сьогодні', 'буде', 'був', 'була', 'було', 'немає', 'щоб',
        'якщо', 'чому', 'скільки', 'треба', 'потрібно', 'можу', 'можливо', 'дякую', 'звісно', 'звичайно', 'гаразд',
        'вибачте', 'добрий',
    ],
];
