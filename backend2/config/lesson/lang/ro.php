<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · ro — what the day's checks, the judge of the talk and the speech comparison read of Romanian
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b), keys by `docs/research/lang-1/pack-keys.md` (наряд LANG-1). Romanian is BOTH sides
| of a plan: the language taught (ru→ro — what the learner says and hears, the frames, the talk) and the learner's own
| (ro→en — the readings, the native frames, the listening questions, the translation of the role). So every key of both
| sides is written; a rule that does not apply to Romanian is an explicit no-op, never null (null would
| switch its rule off on every day of the pair).
|
| HOW THE WORDS ARE SPELT. Every list is written in the standard spelling — ș ț with the comma below, ă â î — and read
| through `LanguagePack::normal()` / the kernel's canonical form, which fold the cedilla letters a model or a keyboard
| writes (ş ţ) into these. A PATTERN read against a folded word names the comma-below letters only; the two patterns
| matched against raw text (`second_question_pattern`, `gendered_past_pattern` — the latter lower-cased, not folded)
| name both spellings.
|
| Every list is a counter's reading of a rule, not the rule: the codes built on them are heuristics.
*/
return [
    // A reading of the target in the learner's own letters (a native-side key, `pronunciation.script` — a WARNING): the
    // Romanian alphabet itself (with q w y k of the loanwords, the cedilla ş ţ models write for ș ț, and the marks of a
    // decomposed ă â î ș ț), digits, punctuation, whitespace and the stress mark U+0301. Strict on purpose — an IPA «ə» in
    // «ai hv ə sor throuăt» (the ro→en scouting day) is untidy and is flagged, never failed: the fatal check is
    // `script_letters`.
    'script' => '/^[a-zA-ZăâîșțĂÂÎȘȚşţŞŢ\p{N}\p{P}\s\x{0301}\x{0302}\x{0306}\x{0326}\x{0327}]*$/u',

    // One LETTER of a reading, matched alone (`pronunciation.foreign_script` — FATAL): the Latin writing, exactly the
    // string every Latin pack writes — two packs are neighbours for the guard of the translation exactly when this string
    // is the same (pack-keys §3.2). Not the Romanian alphabet: a strict one here would fail a valid day on one «ə».
    'script_letters' => '/^[\p{Latin}]$/u',

    // The marks a sentence ends with, and what each says. The Romanian quotes „…” close a sentence as the English ones do
    // (SentenceEnds reads them).
    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence — as written in a text, letter case aside: «la dr. Lee», «Str. Florilor nr. 5,
    // bl. A2, ap. 14», «dvs.», «cu 10 min. înainte», «cca. o oră», and the «a.m.» / «p.m.» a model carries over from
    // English («ora 10 a.m.» in a slot is a time, not a sentence — a fatal `filler.ungrammatical` otherwise). Only
    // abbreviations that are no ordinary word with a dot: «sec.» is not here («Vinul este sec.» — dry), nor a bare «ex.»
    // («fostul meu ex.»); «de ex.» is.
    'abbreviations' => [
        'dl.', 'dna.', 'dra.', 'dr.', 'dvs.', 'dv.', 'prof.', 'ing.', 'nr.', 'str.', 'bd.', 'bl.', 'sc.', 'et.', 'ap.',
        'jud.', 'tel.', 'pag.', 'aprox.', 'cca.', 'min.', 'a.m.', 'p.m.', 'etc.', 'de ex.', 'p. ex.', 'ș.a.', 'ș.a.m.d.',
    ],

    // Romanian asks by intonation and the question mark, never by inverting a subject and an auxiliary: a question is
    // only its mark (pack-keys §3.5 — the no-op for ro).
    'question_word_order' => ['auxiliaries' => [], 'subjects' => []],

    // Words that carry no content of their own — prepositions, conjunctions, pronouns and clitics, the articles, the
    // forms of «a fi», «a avea», the modals and the auxiliaries, «aici / acolo / atunci», «da / nu / vă rog / mulțumesc».
    // A hyphenated clitic group is one word of the text («n-am», «s-a», «într-o», «mi-e») and is listed whole.
    'function_words' => [
        // conjunctions and particles
        'și', 'sau', 'ori', 'dar', 'iar', 'însă', 'ci', 'deci', 'că', 'să', 'dacă', 'ca', 'decât', 'nici', 'nu', 'n', 'da',
        'deoarece', 'fiindcă', 'deși', 'mai', 'foarte', 'prea', 'doar', 'numai', 'chiar', 'încă', 'deja', 'tot', 'cam',
        'aici', 'acolo', 'atunci', 'așa',
        // prepositions (and the «într», «dintr», «printr» of «într-o» when a text splits it)
        'de', 'la', 'cu', 'în', 'pe', 'pentru', 'din', 'despre', 'fără', 'până', 'după', 'spre', 'prin', 'către', 'între',
        'lângă', 'sub', 'peste', 'contra', 'dintre', 'printre', 'într', 'dintr', 'printr',
        // question words
        'ce', 'cine', 'care', 'cum', 'când', 'unde', 'cât', 'câtă', 'câți', 'câte', 'cui',
        // articles, determiners, quantifiers
        'un', 'o', 'unei', 'unui', 'niște', 'unul', 'al', 'a', 'ai', 'ale', 'cel', 'cea', 'cei', 'cele',
        'acest', 'această', 'acești', 'aceste', 'acestui', 'acestei', 'acesta', 'aceasta', 'aceștia', 'acestea',
        'acel', 'acea', 'acei', 'acele', 'acela', 'aceea', 'asta', 'ăsta', 'ăștia', 'astea', 'aia', 'ăla',
        'fiecare', 'orice', 'oricare', 'vreun', 'vreo', 'niciun', 'nicio', 'ceva', 'cineva', 'nimic', 'nimeni',
        'toți', 'toate', 'toată',
        // pronouns and clitics
        'eu', 'tu', 'el', 'ea', 'noi', 'voi', 'ei', 'ele', 'dumneavoastră', 'dumneata', 'dvs', 'lui', 'lor',
        'mă', 'mine', 'te', 'tine', 'se', 'sine', 'îl', 'îi', 'le', 'ne', 'vă', 'îmi', 'îți', 'își', 'mi', 'ți', 'ni', 'vi',
        'li', 'l', 'i', 'm', 's', 'v',
        'meu', 'mea', 'mei', 'mele', 'tău', 'ta', 'tăi', 'tale', 'său', 'sa', 'săi', 'sale',
        'nostru', 'noastră', 'noștri', 'noastre', 'vostru', 'voastră', 'voștri', 'voastre',
        // «a fi», «a avea», the modals and the auxiliaries
        'este', 'e', 'sunt', 'ești', 'suntem', 'sunteți', 'era', 'eram', 'erau', 'fi', 'fost', 'fie',
        'am', 'are', 'avem', 'aveți', 'au', 'avea', 'aveam', 'avut', 'aș', 'ar', 'ați', 'va', 'vom', 'veți', 'vor',
        'pot', 'poți', 'poate', 'putem', 'puteți', 'trebuie',
        // the clitic groups a text writes with a hyphen, each one word of it
        'n-am', 'n-ai', 'n-a', 'n-are', 'n-au', 'n-avem', 'n-aveți', 'n-o', 'n-aș', 'n-ar', 'n-ați', 'nu-i', 'nu-mi',
        'nu-ți', 'nu-l', 'nu-s', 'nu-și', 's-a', 's-au', 's-ar', 'm-am', 'm-a', 'm-ar', 'te-ai', 'te-a', 'te-am', 'v-ați',
        'v-am', 'v-a', 'v-ar', 'v-aș', 'ne-am', 'ne-a', 'l-am', 'l-a', 'l-ați', 'i-am', 'i-a', 'i-ați', 'le-am', 'le-a',
        'mi-a', 'mi-e', 'mi-am', 'mi-ar', 'ți-a', 'ți-e', 'ți-am', 'ți-ar', 'și-a', 'să-mi', 'să-ți', 'să-i', 'să-l',
        'să-și', 's-o', 'ce-mi', 'ce-ți', 'într-o', 'într-un', 'dintr-o', 'dintr-un', 'printr-o', 'printr-un', 'de-a',
        // the small words of a reply
        'rog', 'mulțumesc', 'mersi', 'bine', 'ok', 'okay', 'păi', 'aha', 'oh', 'scuze', 'scuzați', 'pardon', 'sigur',
        'desigur', 'poftim',
    ],

    // THE WORDS A RECOGNISER EATS — articles, prepositions, the forms of «a fi» / «a avea», the modals and auxiliaries:
    // left out of both sides when a line the learner is LOOKING AT is compared with what was said. In the canonical form
    // of the comparison (a hyphen is a space: «într-o» is «într o», «n-am» is «n am»), so «într», «dintr» are words here.
    // «nu» and «n» are NOT here — they flip the meaning; nor are the clitics «mă», «te», «vă» — they say who.
    'unstressed_words' => [
        'un', 'o', 'unei', 'unui', 'niște', 'a', 'al', 'ai', 'ale',
        'de', 'la', 'cu', 'în', 'într', 'dintr', 'printr', 'pe', 'pentru', 'din', 'spre', 'prin', 'despre', 'fără', 'până',
        'după', 'către', 'să',
        'este', 'e', 'sunt', 'ești', 'suntem', 'sunteți', 'era', 'fi', 'fost',
        'am', 'are', 'avem', 'aveți', 'au', 'aș', 'ar', 'ați', 'va', 'vom', 'veți', 'vor',
        'pot', 'poți', 'poate', 'putem', 'puteți', 'trebuie',
    ],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER: 0–19 (with «una», «două», «douăsprezece» and the spoken teens a
    // recogniser may write as heard — «unșpe», «cinșpe»), the tens, the scales with their plural forms («două sute» 200,
    // «două mii» 2000), and the scales said with their article as one entry («o sută» 100, «o mie» 1000, «un milion») —
    // Romanian writes no `articles` (п. 89), so «o» before a scale is read through these entries; «o» and «un» alone are
    // no number («o programare» stays «o programare»). No bare «mie»: 1000 is said «o mie» and never «mie» alone, while
    // «mie» alone is the dative «to me» — «Mie două bilete» would read 1002 in words and «1000 2» in digits. «douăzeci și
    // unu» is 21 by `number_tens_joiners`.
    'number_words' => [
        'zero' => '0', 'unu' => '1', 'una' => '1', 'doi' => '2', 'două' => '2', 'trei' => '3', 'patru' => '4', 'cinci' => '5',
        'șase' => '6', 'șapte' => '7', 'opt' => '8', 'nouă' => '9', 'zece' => '10',
        'unsprezece' => '11', 'doisprezece' => '12', 'douăsprezece' => '12', 'treisprezece' => '13', 'paisprezece' => '14',
        'patrusprezece' => '14', 'cincisprezece' => '15', 'șaisprezece' => '16', 'șaptesprezece' => '17', 'optsprezece' => '18',
        'nouăsprezece' => '19',
        'unșpe' => '11', 'doișpe' => '12', 'treișpe' => '13', 'paișpe' => '14', 'cinșpe' => '15', 'șaișpe' => '16',
        'șaptișpe' => '17', 'optișpe' => '18', 'nouășpe' => '19',
        'douăzeci' => '20', 'treizeci' => '30', 'patruzeci' => '40', 'cincizeci' => '50', 'șaizeci' => '60', 'șaptezeci' => '70',
        'optzeci' => '80', 'nouăzeci' => '90',
        'sută' => '100', 'sute' => '100', 'o sută' => '100',
        'mii' => '1000', 'o mie' => '1000',
        'milion' => '1000000', 'milioane' => '1000000', 'un milion' => '1000000',
        'miliard' => '1000000000', 'miliarde' => '1000000000', 'un miliard' => '1000000000',
    ],

    // After a scale Romanian joins nothing: «o sută cinci» is 105, «două mii cinci sute» 2500 — and «între o sută și două
    // sute» must stay two numbers (with «și» here it would read «102 100»).
    'number_joiners' => [],

    // «și» between the tens and the unit: «douăzeci și unu» 21, «trei sute douăzeci și cinci» 325.
    'number_tens_joiners' => ['și'],

    // Two forms of one word in an inflected language with a suffixed article: both at least four letters, sharing all but
    // the last two letters of the shorter («programare» — «programarea» — «programării», «simptome» — «simptomele»). A
    // three-letter noun is compared whole («gât» is not «gâtul»): with a shorter stem «pat» would be «patru». One letter is
    // no content word («zi», «an» are).
    'word_forms' => ['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2],

    // THE FORMS OF A DICTIONARY WORD (наряд GEN-4 — `vocab.not_found`, FATAL, and `vocab.used_in_wrong`; {@see TermForms}):
    // the vocabulary of a day writes a word in its dictionary form, the frames and lines say it inflected; the forms no
    // rule of letters reaches are listed here under the word as a term's content word. Read by the day's checks only —
    // not the talk's `irregular_forms` (a form → its base, in the canonical form of speech, read by `WordBases`).
    // Romanian: the verb without its «a» («putea» — «pot», «fi» — «sunt», «durea» — «doare»); ș ț with the comma below.
    'lemma_forms' => [
        'fi' => ['sunt', 'ești', 'este', 'e', 'suntem', 'sunteți', 'eram', 'erai', 'era', 'erați', 'erau', 'fost', 'fie'],
        'avea' => ['am', 'ai', 'are', 'avem', 'aveți', 'au', 'aveam', 'avut', 'aș', 'ar', 'aibă'],
        'putea' => ['pot', 'poți', 'poate', 'putem', 'puteți', 'putut', 'poată'],
        'vrea' => ['vreau', 'vrei', 'vrem', 'vreți', 'vor', 'vrut', 'voiam', 'voia'],
        'merge' => ['merg', 'mergi', 'mergem', 'mergeți', 'mers'],
        'face' => ['fac', 'faci', 'facem', 'faceți', 'făcut', 'făcea'],
        'da' => ['dau', 'dai', 'dă', 'dăm', 'dați', 'dat', 'dea'],
        'lua' => ['iau', 'iei', 'ia', 'luăm', 'luați', 'luat'],
        'ști' => ['știu', 'știi', 'știe', 'știm', 'știți', 'știut'],
        'veni' => ['vin', 'vii', 'vine', 'venim', 'veniți', 'venit'],
        'spune' => ['spun', 'spui', 'spus'],
        'trebui' => ['trebuie', 'trebuia'],
        'sta' => ['stau', 'stai', 'stă', 'stăm', 'stați', 'stat'],
        'bea' => ['beau', 'bei', 'bem', 'beți', 'băut'],
        'vedea' => ['văd', 'vezi', 'vede', 'vedem', 'vedeți', 'văzut'],
        'cere' => ['cer', 'ceri', 'cerut'],
        'duce' => ['duc', 'duci', 'dus'],
        'durea' => ['doare', 'dor', 'durut'],
        'ține' => ['țin', 'ții', 'ținut'],
        'pune' => ['pun', 'pui', 'pus'],
        'scrie' => ['scriu', 'scris'],
        'mânca' => ['mănânc', 'mănânci', 'mănâncă'],
    ],

    // A number: a digit anywhere in the word, a number word, an ordinal («al doilea», «a treia», «prima»), «jumătate»,
    // «sfert». Not «un» / «o» (the articles), not «mie» (also «to me»), not «noua» («the new»): a line with one of them
    // says no number. «nouă» stays — «la nouă» is nine — though it is also «new» and «to us». Matched against the folded
    // word — ș ț with the comma below.
    'number_pattern' => '/\d|^(?:zero|unu|una|doi|două|trei|patru|cinci|șase|șapte|opt|nouă|zece|unsprezece|doisprezece|douăsprezece|treisprezece|paisprezece|patrusprezece|cincisprezece|șaisprezece|șaptesprezece|optsprezece|nouăsprezece|unșpe|doișpe|treișpe|paișpe|cinșpe|șaișpe|șaptișpe|optișpe|nouășpe|douăzeci|treizeci|patruzeci|cincizeci|șaizeci|șaptezeci|optzeci|nouăzeci|sută|sute|mii|milion|milioane|miliard|miliarde|jumătat\w*|sfert\w*|prim|primul|prima|primii|primele|primului|primei|întâi|întâiul|întâia|doilea|doua|treilea|treia|patrulea|patra|cincilea|cincea|șaselea|șasea|șaptelea|șaptea|optulea|opta|nouălea|zecelea|zecea)$/u',

    // Time words — units (with the «min» of «10 min.»), parts of the day, the days and months, «ieri / azi / mâine /
    // aseară / diseară» (and the hyphenated «azi-dimineață», «mâine-seară» of the norm). Read on both sides: native (the listening's kinds of value)
    // and target (which line of the visit says a number or a time — «Поймай число»), so it is kept to words that NAME a
    // time: no «acum», «apoi», «înainte», «peste», «mai» (the month is also «more»).
    'time_pattern' => '/^(?:ieri|alaltăieri|azi|astăzi|mâine|poimâine|aseară|diseară|astăseară|(?:azi|ieri|mâine|astă)-(?:dimineață|dimineața|seară|seara|noapte|noaptea)|devreme|târziu|dimineață|dimineața|diminețile|seară|seara|serii|serile|noapte|noaptea|nopții|nopți|nopțile|amiază|amiaza|după-amiază|după-amiaza|prânz|prânzul|secundă|secunde|secunda|minut|minute|minutul|minutele|min|oră|ora|ore|orei|orele|zi|zile|ziua|zilei|zilele|săptămână|săptămâna|săptămâni|săptămânii|săptămânile|lună|luna|luni|lunii|lunile|an|ani|anul|anului|anii|anilor|lunea|marți|marțea|miercuri|miercurea|joi|joia|vineri|vinerea|sâmbătă|sâmbăta|duminică|duminica|weekend|weekendul|ianuarie|februarie|martie|aprilie|iunie|iulie|august|septembrie|octombrie|noiembrie|decembrie)$/u',

    // The units something is COUNTED in — what makes a value an amount and not a date («de trei zile», «cu zece minute»).
    // Weekdays, months and parts of the day are not here. The reader grows a value only over number and time words, so
    // of these only the time units ever take part; the rest are written as the Russian pack writes its own.
    'amount_pattern' => '/^(?:secund\w*|minut\w*|min|oră|ore|orei|orele|zi|zile|zilei|zilele|săptămân\w*|lună|luni|lunii|lunile|an|ani|anul|anului|anii|anilor|grad|grade|gradul|procent\w*|metr\w*|kilometr\w*|kilogram\w*|gram|grame|litr\w*|miligram\w*|pastil\w*|tablet\w*|comprimat\w*|picătur\w*)$/u',

    // What carries an amount and is said WITH it, standing right before it: the prepositions and determiners of «de trei
    // zile», «cu 15 minute», «peste o săptămână», «acum două zile», «timp de o lună», «cu un sfert».
    'amount_prefix' => '/^(?:în|la|de|cu|peste|după|până|timp|acum|pentru|aproximativ|cam|circa|vreo|un|o|niște|acest|această|acești|aceste|fiecare|ultim\w*|următo\w*|trecut\w*)$/u',

    // The prompt's STOP LIST (numbers, family, time words, colours, a fi / a avea / a merge) and plain words a learner
    // knows at any level of the plan: not vocabulary.
    'everyday_words' => [
        'unu', 'una', 'doi', 'două', 'trei', 'patru', 'cinci', 'șase', 'șapte', 'opt', 'nouă', 'zece', 'douăzeci', 'treizeci',
        'sută', 'sute', 'mie', 'mii', 'primul', 'prima',
        'mamă', 'mama', 'tată', 'tatăl', 'tata', 'părinte', 'părinți', 'părinții', 'frate', 'fratele', 'frați', 'soră', 'sora',
        'surori', 'fiu', 'fiul', 'fiică', 'fiica', 'copil', 'copilul', 'copii', 'copiii', 'bebeluș', 'familie', 'familia',
        'soț', 'soțul', 'soție', 'soția', 'bunică', 'bunica', 'bunic', 'bunicul',
        'zi', 'ziua', 'zile', 'zilele', 'săptămână', 'săptămâna', 'săptămâni', 'lună', 'luna', 'luni', 'an', 'anul', 'ani',
        'azi', 'astăzi', 'mâine', 'ieri', 'dimineață', 'dimineața', 'seară', 'seara', 'noapte', 'noaptea', 'timp', 'oră',
        'ora', 'ore', 'minut', 'minute', 'acum', 'târziu', 'devreme',
        'roșu', 'roșie', 'albastru', 'albastră', 'verde', 'galben', 'galbenă', 'negru', 'neagră', 'alb', 'albă', 'maro',
        'gri', 'portocaliu', 'roz', 'mov',
        'fi', 'este', 'e', 'sunt', 'era', 'fost', 'avea', 'am', 'ai', 'are', 'avem', 'aveți', 'au', 'merge', 'merg', 'mers',
        'lucru', 'lucruri', 'muncă', 'munca', 'serviciu', 'casă', 'casa', 'acasă', 'școală', 'școala', 'om', 'omul', 'bărbat',
        'femeie', 'femeia', 'oameni', 'oamenii', 'persoană', 'prieten', 'prietenul', 'prietenă', 'mâncare', 'mâncarea', 'apă',
        'apa', 'mașină', 'mașina', 'cameră', 'camera', 'ușă', 'ușa', 'masă', 'masa', 'nume', 'numele', 'bun', 'bună', 'rău',
        'mare', 'mic', 'mică', 'nou', 'vechi', 'salut', 'mânca', 'mănânc', 'bea', 'beau', 'vedea', 'văd', 'veni', 'vin', 'vine',
        'face', 'fac', 'lua', 'iau', 'vrea', 'vreau', 'vrei', 'place', 'știu', 'cred', 'spune', 'spun', 'ajuta', 'ajutor',
        'loc', 'locul', 'oraș', 'orașul', 'stradă', 'strada', 'bani', 'banii', 'carte', 'cartea', 'telefon', 'telefonul',
        'câine', 'pisică', 'mână', 'mâna', 'cap', 'capul', 'ochi', 'problemă', 'problema', 'întrebare', 'răspuns',
    ],

    // Ordinary quantifiers and adjectives that stand BEFORE a noun: the head of a free combination («multe lucruri»,
    // «alte probleme») that is no chunk. Romanian puts most adjectives after the noun, so the list is short; «bun / bună»
    // are left out — «Bună ziua», «Bun venit» are fixed phrases.
    'ordinary_heads' => [
        'mult', 'multă', 'mulți', 'multe', 'puțin', 'puțină', 'puțini', 'puține', 'câțiva', 'câteva', 'alt', 'altă', 'alți',
        'alte', 'niște', 'fiecare', 'orice', 'unii', 'unele', 'toți', 'toate', 'mare', 'mari', 'mic', 'mică', 'mici',
    ],

    // A partner line that says nothing but «we are done» — its whole text, punctuation aside.
    'closers' => [
        'altceva', 'mai doriți ceva', 'mai doriți altceva', 'mai aveți nevoie de ceva', 'perfect', 'bine', 'bun', 'foarte bine',
        'în regulă', 'e în regulă', 'în ordine', 'de acord', 'ok', 'okay', 'mulțumesc', 'mulțumesc frumos', 'mulțumesc mult',
        'vă mulțumesc', 'vă mulțumesc mult', 'mersi', 'cu plăcere', 'cu drag', 'nicio problemă', 'sigur', 'desigur',
        'bineînțeles', 'o zi bună', 'o zi frumoasă', 'o seară bună', 'o zi bună vă doresc', 'la revedere', 'pe curând',
        'ne vedem', 'ne auzim', 'am înțeles', 'înțeleg', 'excelent', 'minunat', 'grozav', 'super', 'e bine', 'este bine',
        'asta e tot', 'gata',
    ],

    // The verbs a check uses to name who said something («Ce spune recepționera?»). «vor» is not here — it is the future
    // auxiliary too.
    'saying_verbs' => [
        'spune', 'spun', 'spui', 'spunem', 'spuneți', 'spus', 'zice', 'zic', 'zici', 'zis', 'răspunde', 'răspund', 'răspunzi',
        'răspuns', 'menționează', 'menționez', 'menționat', 'vrea', 'vreau', 'vrei', 'vrem', 'vreți', 'voia', 'vrut',
        'dorește', 'doresc', 'dorești', 'dorim', 'doriți', 'dorit',
    ],

    // The words a partner names alternatives with. Not «ori» — also «times» («de două ori pe zi»).
    'alternative_words' => ['sau'],

    // One sentence that goes on after a comma with «și / sau» and asks again — a question word or a verb that opens a
    // question («Aveți febră, și de când aveți simptomele?»). Read on the raw text, so the cedilla letters are named too.
    'second_question_pattern' => '/,\s*(?:[șş]i|sau)\s+(?:ce|cum|când|unde|cine|care|cât|câtă|câ[țţ]i|câte|de ce|de când|ave[țţ]i|ai|are|sunte[țţ]i|e[șş]ti|este|pute[țţ]i|po[țţ]i|vre[țţ]i|vrei|dori[țţ]i|a[țţ]i)\b[^?]*\?/iu',

    // No articles to leave out (DECISIONS п. 89: «для ro не трогать — артикль суффиксальный»): the definite article is
    // a suffix of the noun, and «un» / «o» stay words of the comparison («o» is a pronoun too).
    'articles' => [],

    // WORDS A SENTENCE CANNOT END ON: the indefinite article «un» and the determiners that only ever stand before a noun
    // («acest», «niciun», «vreo», the possessive «al / ale»), the conjunctions and «să», the prepositions — Romanian
    // strands no preposition («Cu cine vorbesc?»), so «Am nevoie de», «Pot mâine la» broke off. Not «o»: it closes a
    // sentence as a clitic («Am văzut-o»); not «iar»: it is also «again» («Mă doare iar.»).
    'dangling_words' => [
        'un', 'unei', 'unui', 'al', 'ale', 'acest', 'această', 'acești', 'aceste', 'niște', 'niciun', 'nicio', 'vreun', 'vreo',
        'să', 'și', 'că', 'dar', 'sau', 'de', 'la', 'cu', 'în', 'pe', 'pentru', 'din', 'despre', 'fără', 'prin', 'spre',
        'către',
    ],

    // No word the seam of a frame and its filler may say twice and stay Romanian: «Pot mâine la la zece» (the ru→ro
    // scouting day, p5) is the model's mistake, and every doubled word at the seam stays one.
    'seam_repeatable_words' => [],

    // No article that changes with the next word's SOUND: «un» / «o» follow the noun's gender, never its first letter.
    'article_sound' => [
        'before_vowel' => '',
        'before_consonant' => '',
        'vowel' => '/(?!)/u',
        'consonant' => '/(?!)/u',
        'spelled' => '/(?!)/u',
        'exception' => '/(?!)/u',
    ],

    // A clause where a value should stand. Narrow on purpose (a SENTENCE at the start of a filler after a frame's words is
    // fatal): an explicit subject pronoun and a form of «a fi» / «a avea» — «el are febră», «eu sunt pacient». Romanian
    // drops its subject, so «am febră» is no sentence here: that is the rule's limit, not a finding. No clitic group is a
    // subject with its verb, and «după» + «el» is «after him» as well as «after he…» — so neither is listed.
    'clause' => [
        'subjects' => ['eu', 'tu', 'el', 'ea', 'noi', 'voi', 'ei', 'ele', 'dumneavoastră', 'dvs'],
        'finite' => ['sunt', 'ești', 'este', 'e', 'suntem', 'sunteți', 'am', 'ai', 'are', 'avem', 'aveți', 'au'],
        'contractions' => [],
        'subordinators' => ['dacă', 'deși', 'fiindcă', 'deoarece', 'întrucât', 'când'],
        'subordinators_before_subject' => [],
    ],

    // «The frame must stand alone»: a frame that leans on «îl» (him / it) with nothing in it that «îl» stands for. Only
    // «îl»: «o» is the article and the «o» of the future «o să», and «asta», «acesta» follow the noun they point at
    // («camera asta») — both would be flagged on a frame that stands alone.
    'unresolved_pronouns' => [
        'words' => ['îl'],
        'frame_initial_subject' => [],
        'existential' => [],
        'determiner_or_number' => [],
        'partitive' => [],
        'be_forms' => [],
        'determiners' => ['un', 'o', 'niște', 'acest', 'această', 'acești', 'aceste'],
    ],

    // THE LEARNER'S GENDER, SAID ABOUT THEMSELVES (`native.gendered_past`, a warning, only while the gender is unknown).
    // The Romanian perfect has no gender («am mers» for both), but what a patient says of themselves with «a fi» has:
    // «Sunt programat / programată», «Am fost operat / operată», «Mă simt obosit / obosită», «Sunt alergic / alergică la…».
    // Read after the first-person forms «sunt», «eram», «am fost», «aș fi», «voi fi», «să fiu», «mă simt», «m-am simțit»
    // (an adverb — «deja», «încă», «foarte», «puțin», «destul de», «și» — may stand between): a word that ends like a
    // participle or an adjective of two genders (-at -it -ut -ât -s -av -ic -ur, and their -ă). The plural forms (-ați,
    // -ate) are no match, so the «sunt» of «they are» («Analizele sunt programate») is not read. Left alone: «acasă», «jos»,
    // «sus», «cât», «atât», «imediat», «practic» and the nouns «medic», «mecanic». Group 1 is the form. Matched against the
    // lower-cased text as written — not folded — so the cedilla spellings are named too.
    'gendered_past_pattern' => '/(?<![\p{L}])(?:sunt|eram|am\s+fost|a[șş]\s+fi|voi\s+fi|s[ăa]\s+fiu|m[ăa]\s+simt|m-am\s+sim[țţ]it)\s+(?:(?:deja|[îi]nc[ăa]|foarte|mai|pu[țţ]in|cam|tot|[șş]i|destul\s+de|un\s+pic)\s+)*(?!(?:acas[ăa]|jos|sus|c[âa]t|at[âa]t|imediat|practic|medic|mecanic)(?![\p{L}]))(\p{L}{2,}(?:at|it|ut|ât|ată|ită|ută|âtă|s|să|av|avă|ic|ică|ur|ură))(?![\p{L}])/u',

    // «The native frame contains NO word that agrees with the slot»: the article (also inside «într-un», «dintr-o»), the
    // demonstrative, «cât», the possessive article, a quantifier right before `___` («Am nevoie de un ___», «Lucrez
    // într-un ___», «Câte ___ aveți?»), and a possessive or a participle / adjective among the two words after it («___
    // meu are febră», «___ este inclus?»). No suffix rule: Romanian puts its adjectives after the noun, and an ending read
    // before the slot would take nouns.
    'agreement' => [
        'words' => [
            'un', 'o', 'unui', 'unei', 'într-un', 'într-o', 'dintr-un', 'dintr-o', 'printr-un', 'printr-o',
            'acest', 'această', 'acești', 'aceste', 'acestui', 'acestei', 'acel', 'acea', 'acei',
            'acele', 'cel', 'cea', 'cei', 'cele', 'cât', 'câtă', 'câți', 'câte', 'niciun', 'nicio', 'vreun', 'vreo', 'al', 'ale',
            'alt', 'altă', 'alți', 'alte', 'primul', 'prima', 'mult', 'multă', 'mulți', 'multe', 'puțin', 'puțină', 'puțini',
            'puține', 'tot', 'toată', 'toți', 'toate',
            'meu', 'mea', 'mei', 'mele', 'tău', 'ta', 'tăi', 'tale', 'său', 'sa', 'săi', 'sale', 'nostru', 'noastră', 'noștri',
            'noastre', 'vostru', 'voastră', 'voștri', 'voastre',
        ],
        'short_forms' => [
            'inclus', 'inclusă', 'incluși', 'incluse', 'deschis', 'deschisă', 'deschiși', 'deschise', 'închis', 'închisă',
            'închiși', 'închise', 'liber', 'liberă', 'liberi', 'libere', 'ocupat', 'ocupată', 'ocupați', 'ocupate',
            'disponibil', 'disponibilă', 'disponibili', 'disponibile', 'plătit', 'plătită', 'plătiți', 'plătite',
            'rezervat', 'rezervată', 'rezervați', 'rezervate', 'necesar', 'necesară', 'necesari', 'necesare',
            'obligatoriu', 'obligatorie', 'obligatorii', 'permis', 'permisă', 'permiși', 'permise', 'valabil', 'valabilă',
            'valabili', 'valabile', 'gratuit', 'gratuită', 'gratuiți', 'gratuite', 'programat', 'programată', 'programați',
            'programate', 'confirmat', 'confirmată', 'confirmați', 'confirmate',
        ],
        'suffixes_before_slot' => [],
        'min_letters' => 99,
        'after_slot_words' => 2,
    ],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а) — what a rescue move of the talk says in the learner's own bubble
    // (кадр 37-7, en «Sorry?»): the Romanian «Pardon?» of a listener who did not catch it.
    'rescue_line' => 'Poftim?',

    // THE RESCUE KIT (наряд LANG-1b §2): the six lines a learner of this language says when stuck, each with its translation into
    // every learner's language of the plan. Row 1 is `rescue_line`. Said in the learner's voice, filed by (target,
    // gender, voice, line) — `RescueKits`. The same matrix writes every pack: `docs/research/lang-1b/tools/rescue-kit.py`.
    'rescue' => [
        ['target' => 'Poftim?', 'native' => ['ru' => 'Простите?', 'uk' => 'Перепрошую?', 'be' => 'Прабачце?', 'pl' => 'Słucham?', 'ro' => 'Poftim?', 'es' => '¿Perdón?', 'it' => 'Scusi?', 'de' => 'Wie bitte?', 'fr' => "Pardon\u{00A0}?"]],
        ['target' => 'Puteți vorbi mai rar, vă rog?', 'native' => ['ru' => 'Можно помедленнее, пожалуйста?', 'uk' => 'Можна повільніше, будь ласка?', 'be' => 'Можна павольней, калі ласка?', 'pl' => 'Proszę mówić trochę wolniej.', 'ro' => 'Puteți vorbi mai rar, vă rog?', 'es' => '¿Puede hablar más despacio, por favor?', 'it' => 'Può parlare più lentamente, per favore?', 'de' => 'Können Sie bitte langsamer sprechen?', 'fr' => "Vous pouvez parler plus lentement, s'il vous plaît\u{00A0}?"]],
        ['target' => 'Nu înțeleg.', 'native' => ['ru' => 'Я не понимаю.', 'uk' => 'Я не розумію.', 'be' => 'Я не разумею.', 'pl' => 'Nie rozumiem.', 'ro' => 'Nu înțeleg.', 'es' => 'No entiendo.', 'it' => 'Non capisco.', 'de' => 'Ich verstehe nicht.', 'fr' => 'Je ne comprends pas.']],
        ['target' => 'Un moment.', 'native' => ['ru' => 'Одну минуту.', 'uk' => 'Хвилинку.', 'be' => 'Хвілінку.', 'pl' => 'Chwileczkę.', 'ro' => 'Un moment.', 'es' => 'Un momento.', 'it' => 'Un momento.', 'de' => 'Einen Moment.', 'fr' => 'Un instant.']],
        ['target' => 'Îmi puteți scrie asta?', 'native' => ['ru' => 'Можете это записать?', 'uk' => 'Можете це записати?', 'be' => 'Можаце гэта запісаць?', 'pl' => 'Proszę mi to zapisać.', 'ro' => 'Îmi puteți scrie asta?', 'es' => '¿Me lo puede escribir?', 'it' => 'Me lo può scrivere?', 'de' => 'Können Sie mir das aufschreiben?', 'fr' => "Vous pouvez me l'écrire\u{00A0}?"]],
        ['target' => 'Mulțumesc.', 'native' => ['ru' => 'Спасибо.', 'uk' => 'Дякую.', 'be' => 'Дзякуй.', 'pl' => 'Dziękuję.', 'ro' => 'Mulțumesc.', 'es' => 'Gracias.', 'it' => 'Grazie.', 'de' => 'Danke.', 'fr' => 'Merci.']],
    ],

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9) — the same line as every pack's, in this language (en «I see.
    // Please go on.»).
    'neutral_reply' => 'Înțeleg. Continuați, vă rog.',

    // THE FORMS OF ONE WORD and THE PERSONS SWAPPED (BACK-TAILS-2): not written for Romanian — the no-op. A conjugated
    // verb says its person («am» / «aveți»), and one `person_swap` entry per word cannot say both «tău» and
    // «dumneavoastră»; a Romanian echo is compared by its words as said.
    'irregular_forms' => [],
    'inflection_rules' => [],
    'person_swap' => [],

    // ─── THE JUDGE OF THE TALK'S CONSTRUCTIONS (наряд FIX-4 §2, LANG-1 §1).

    // Romanian writes no apostrophe contractions: a clitic group is hyphenated, and the judge splits a word at its hyphen
    // («n-am» is «n am», «într-o» is «într o»). The order of LANG-1 keeps this empty for every language but fr and it.
    'contractions' => [],
    'contractions_before' => [],

    // THE WORDS A MOVE MAY OPEN WITH before its construction: «Bună ziua, vreau o programare», «Da, mă doare gâtul»,
    // «Alo, bună ziua», «Mă scuzați, cât costă consultația?».
    'intro_words' => [
        'bună ziua', 'bună seara', 'bună dimineața', 'bună', 'salut', 'alo', 'da', 'nu', 'bine', 'bun', 'ok', 'okay', 'deci',
        'păi', 'ei bine', 'aha', 'ah', 'oh', 'mulțumesc', 'mulțumesc frumos', 'mulțumesc mult', 'vă mulțumesc', 'mersi',
        'vă rog', 'te rog', 'sigur', 'desigur', 'perfect', 'super', 'în regulă', 'de acord', 'și', 'mă scuzați', 'scuzați',
        'scuzați-mă', 'scuze', 'pardon', 'îmi pare rău', 'hm', 'ăă',
    ],

    // THE WORDS A NEW CLAUSE OPENS WITH: «Am febră și mă doare gâtul» says «Mă doare ___» after «și»; «Am febră, așa că
    // vreau o programare» says «Vreau ___» after «așa că».
    'clause_starters' => ['și', 'dar', 'iar', 'apoi', 'sau', 'deci', 'însă', 'așa că'],

    // A CONSTRUCTION SAID IN THE NEGATIVE IS THE SAME CONSTRUCTION: «nu» — and «n» of «n-am», which the judge reads as
    // «n am» — anywhere in the move, the first word too: «Nu mă doare gâtul» says «Mă doare ___», «N-am nevoie de ___»
    // says «Am nevoie de ___».
    'negation' => ['words' => ['nu', 'n'], 'after' => null, 'do_support' => []],

    // A QUANTITY SAYS ITS OWN «DE»: «Am ___ de experiență» is said as «Am puțină experiență», «Nu am nicio experiență»,
    // «Aveți vreo experiență?» — a window of one of these determiners may leave out the frame's «de» after it. Compared
    // as written: folded, lower case.
    'partitive' => [
        'word' => 'de',
        'determiners' => [
            'puțin', 'puțină', 'puțini', 'puține', 'mult', 'multă', 'mulți', 'multe', 'ceva', 'destul', 'destulă', 'destui',
            'destule', 'suficient', 'suficientă', 'suficienți', 'suficiente', 'niciun', 'nicio', 'vreun', 'vreo', 'niște',
        ],
    ],

    // THE LANGUAGE'S FREQUENT AND DISTINCTIVE WORDS (наряд LANG-1 §5; the order asked for «30 самых частых слов» — these
    // are «частые и отличительные»): the guard of the role's translation compares a Romanian learner's grey line with
    // EVERY other Latin pack — en, pl, es, it, de, fr — and a word that is also an ordinary word of a neighbour would make
    // that neighbour's ordinary lines look Romanian (or Romanian lines look foreign under the neighbour's learner). So
    // these are frequent AND written the same way in no neighbour: not «de», «la», «a», «o», «un» (es, it, fr), «el» (es),
    // «ce» (fr, it), «am» (de, en), «da» (it, de, es, pl), «care» (en, it), «pot» (en, fr), «mai» (it, fr, de), «dar»
    // (pl), «din» (en), «nu» (fr «nu»), «ne» (fr), «ai» (fr «j'ai»), «este» (es), «asta» (es), «sau» (de «Sau»), «mea»
    // (es), «cum» (en), «lui» (it), «ora» (it, es). One run of letters each, lower case.
    'common_words' => [
        'și', 'să', 'că', 'sunt', 'ești', 'sunteți', 'mă', 'vă', 'îmi', 'cu', 'în', 'pe', 'pentru', 'după', 'până', 'foarte',
        'acum', 'aici', 'unde', 'când', 'cât', 'dacă', 'avem', 'aveți', 'ați', 'fost', 'vreau', 'vrea', 'doriți', 'aș',
        'trebuie', 'poate', 'puteți', 'doar', 'încă', 'bine', 'mulțumesc', 'rog', 'sigur', 'azi', 'mâine', 'meu', 'această',
        'nevoie', 'dumneavoastră',
    ],

    // THE TITLE OF A TALK (наряд LANG-1 §6): Romanian declension is not in the code, so the title names the roles in no
    // case — «Conversație: recepționer și medic».
    'talk_title_template' => [
        'title' => 'Conversație: {roles}',
        'and' => 'și',
        'anyone' => 'Conversație',
        'lower_first' => true,
    ],
];
