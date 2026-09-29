<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · de — what the day's checks read of German
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b), наряд LANG-1: German is written as BOTH sides. As the TARGET (ru → de) it is what
| the learner says and hears — the lesson's frames and fillers, the checks, the judge of the talk, the comparison of
| speech; as the LEARNER'S OWN language (de → en) it is the reading of the target, the native frames, the listening
| questions and their options, the title of the talk. Every key the code reads is written — a rule that does not apply
| to German is its no-op (`docs/research/lang-1/pack-keys.md`), never null.
|
| Three decisions of German shape this pack more than any list:
|  - THE ARTICLE CARRIES THE GENDER (DECISIONS п. 89: «для de не снимать»): `articles` is empty, and no article stands
|    in `unstressed_words` either — «der Termin» for «den Termin» is not forgiven anywhere, and «ein», «eine», «einen» are
|    no number words (folding them into «1» would forgive the gender through the back door).
|  - EVERY NOUN IS WRITTEN WITH A CAPITAL: a rule that took a capitalised word inside a sentence for a name would read
|    every German noun as one (the lesson validator's `check.verbatim` did, until it went in GEN-4; the dialogue's
|    `check.verbatim` reads no names) — no key of the pack can change that (a code question, not a list).
|  - A WORD LIST MAY BE WRITTEN IN ITS OWN SPELLING, A PATTERN MAY NOT: `LanguagePack::normal()` folds before it lower-
|    cases (ß → ss), on the list and on the word asked of it, and `speech()` folds the lists handed down — so «dreißig»,
|    «weiß», «heißen» stand here as German writes them. The regular expressions meet the FOLDED word: they write «ss»
|    (`dreissig`), never «ß». Maps read against the canonical form of speech (`irregular_forms`, `person_swap`) are
|    written folded too — nobody folds their keys.
|
| One limit no key can lift: the dot of a German ORDINAL («am 3. Mai», «im 2. Stock») stands before a space, and
| `SentenceEnds` reads it as a sentence's end — «Ich habe am 3. Mai einen Termin» is two sentences to the judge of the
| talk. Listing «1.»…«31.» as abbreviations would glue every sentence that ends with a house number to the next one
| («… Hauptstraße 12. Ich habe Fieber»), which the key spec forbids (a word that is also a word with a dot); the rule
| «a number's dot before a month or a noun» is the code's to learn (наряд LANG-1, отчёт исполнителя de).
*/
return [
    // A reading of the target in the learner's own alphabet: the 26 Latin letters, ä ö ü ß (and the capital ẞ), digits,
    // punctuation, spaces, the stress mark U+0301 — nothing else. A letter of another alphabet that is still Latin (an IPA
    // «ə», a French «é») is a WARNING here, never a failed day: the fatal key is `script_letters` below.
    'script' => '/^[a-zA-ZäöüÄÖÜß\x{1E9E}\p{N}\p{P}\s\x{0301}]*$/u',

    // One LETTER of a reading, matched alone — the reference string of the Latin script, character for character (the
    // guard of the translation finds its neighbours by this very string; a strict alphabet here would fail valid days,
    // `pronunciation.foreign_script` is fatal). A Cyrillic letter in a German learner's reading is fatal, and rightly.
    'script_letters' => '/^[\p{Latin}]$/u',

    // FREQUENT AND DISTINCTIVE (наряд LANG-1 §5): the order said «the 30 most frequent words», and the list is «частые и
    // отличительные» on purpose. The guard of the role's translation (ReplyNative) reads a learner's grey line against
    // EVERY pack in the same letters (en pl ro es it fr), and a word that is also an ordinary word of the learner's own
    // language, sitting in the German list only, counts as German inside that learner's honest line. So the most
    // frequent German words another Latin language of the plan spells alike are left out — even «ich», the most frequent
    // of all: «die», «was», «bin», «hat», «man», «also», «so», «in», «an», «am», «um», «den», «gut» (English); «ich»
    // (their), «was» (you), «ja», «wie», «im», «mit» (myth), «wir» (whirl) (Polish); «es», «das», «mal» (Spanish); «des»,
    // «hier», «mit» (French). What stays is what German lines are full of and nobody else writes: «Sie», «nicht», «ist»,
    // «und», the modals, the prepositions a German sentence cannot do without. One run of letters each, as the guard
    // splits a line.
    'common_words' => [
        'sie', 'mich', 'mir', 'sich', 'ihnen', 'ihre', 'nicht', 'keine', 'ist', 'sind', 'haben', 'habe', 'kann',
        'können', 'möchte', 'gibt', 'und', 'oder', 'aber', 'dass', 'wenn', 'denn', 'doch', 'der', 'dem', 'ein', 'eine',
        'einen', 'auf', 'für', 'zu', 'von', 'auch', 'noch', 'schon', 'sehr', 'jetzt', 'dann', 'heute', 'bitte', 'danke',
        'nein', 'etwas', 'uhr',
    ],

    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence (наряд CHECK-1): «Kommen Sie bitte 15 Min. früher.», «Die Praxis ist in der
    // Hauptstraße Nr. 12.» and «Wir haben Mo. bis Fr. geöffnet.» are one sentence each. Read case-insensitively, as a word
    // on its own (after a space, a quote or a bracket): «Bahnhofstr.» glued to its street is no «Str.» and its dot still
    // ends a sentence. Both spellings of the two-letter ones, with and without the space. Nothing that is also an ordinary
    // word — or a name — with a dot: no weekday «So.» (the word «so»), no month «Jan.» (the name «Jan»), no «max.» (Max),
    // no bare ordinal «3.» (a sentence may end on a house number, see the header).
    'abbreviations' => [
        'z. B.', 'z.B.', 'd. h.', 'd.h.', 'u. a.', 'z. T.', 'u. U.', 'v. a.', 'i. d. R.', 'usw.', 'bzw.', 'etc.', 'ca.',
        'bspw.', 'evtl.', 'ggf.', 'inkl.', 'zzgl.', 'vgl.', 'Nr.', 'Dr.', 'Prof.', 'Hr.', 'Fr.', 'Str.', 'Tel.', 'Hbf.',
        'geb.', 'Min.', 'Std.', 'Mio.', 'Mrd.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Sa.',
    ],

    // A QUESTION BY ITS WORD ORDER, NARROWLY (a fatal key — `exchange.second_question` reads it): a last sentence that
    // opens with a finite verb and a subject pronoun asks, mark or no mark — «Können Sie mir die Adresse sagen», «Tut es
    // weh», «Gibt es hier eine Apotheke». Left out on purpose, each a statement German opens with the same order:
    // «haben» and «werden», whose form with «Sie» is also the IMPERATIVE («Haben Sie keine Angst.», «Werden Sie bald
    // gesund!») — a doctor's closing line that would read as a second question and fail the day; «sollte» and «sollten»,
    // whose sentence-initial use in service German is the CONDITION («Sollten Sie Fragen haben, rufen Sie uns an.» — a
    // receptionist's stock closing line); and «das» as a subject («Ist das alles?» goes by its mark). What stays can still
    // open a statement in speech that drops its first word («Kann ich machen.», «Habe ich dabei.») — rare in a lesson's
    // written lines, and «Ja, kann ich machen.» opens with «ja». Spoken moves arrive without marks, and the echo guard of
    // the talk reads a German question by this order too.
    'question_word_order' => [
        'auxiliaries' => [
            'bin', 'bist', 'ist', 'sind', 'seid', 'war', 'warst', 'waren', 'habe', 'hast', 'hat', 'habt', 'hatte', 'hattest',
            'hatten', 'wird', 'wirst', 'kann', 'kannst', 'können', 'könnt', 'könnte', 'könnten', 'darf', 'darfst', 'dürfen',
            'muss', 'musst', 'müssen', 'soll', 'sollst', 'sollen', 'will', 'willst', 'wollen', 'möchte', 'möchtest', 'möchten',
            'gibt', 'geht', 'tut',
        ],
        'subjects' => ['ich', 'du', 'er', 'sie', 'es', 'wir', 'ihr', 'man'],
    ],

    // THE ANSWER A YES-OR-NO QUESTION OPENS WITH (наряд GEN-4c, `partner.yes_no_missing` / `partner.yes_no_extra`): the
    // partner's reply to a yes-or-no question of the learner's opens with one of these words, a reply to a question that
    // asks for a fact opens with none. «Yes» first, «no» second — the finding names the two —, then «doch», the yes to a
    // question asked in the negative. Read against the reply's first word, case and marks aside.
    'yes_no' => ['ja', 'nein', 'doch'],

    // THE WORDS OF A QUESTION THAT ASKS FOR A FACT (наряд GEN-4c): the w-words and their phrases standing in a row («wie
    // viel», «wie lange», «um wie viel Uhr», «was für»), «wieviel» written as one word. An ask frame that holds none of them,
    // anywhere, and offers no choice (`alternative_words` between two of its words — «Zahle ich bar oder mit ___?»; the
    // «…, oder?» at its end asks yes or no) is a yes-or-no question: «Ist ___ erlaubt?», «Gibt es Regeln für ___?».
    'question_words' => [
        'was', 'wer', 'wen', 'wem', 'wessen', 'wo', 'wohin', 'woher', 'wann', 'warum', 'wieso', 'weshalb', 'weswegen',
        'wie', 'welcher', 'welche', 'welches', 'welchen', 'welchem', 'wie viel', 'wie viele', 'wieviel', 'wie lange',
        'wie oft', 'wie spät', 'um wie viel uhr', 'was für', 'wozu', 'womit', 'wofür', 'worauf', 'woran', 'worüber',
        'wovon', 'wodurch',
    ],

    // Words that carry no content of their own: articles, pronouns (the polite «Sie», «Ihnen», «Ihr» among them),
    // possessives, prepositions and their fusions with the article, conjunctions, the forms of sein/haben/werden — the
    // polite «hätte», «wäre» too — and the modals, negation, question words, the pronominal adverbs, particles,
    // «ja/nein/bitte/danke».
    'function_words' => [
        'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'eines',
        'kein', 'keine', 'keinen', 'keinem', 'keiner', 'keines', 'nicht',
        'ich', 'du', 'er', 'sie', 'es', 'wir', 'ihr', 'mich', 'dich', 'sich', 'uns', 'euch', 'mir', 'dir', 'ihm', 'ihn',
        'ihnen', 'man',
        'mein', 'meine', 'meinen', 'meinem', 'meiner', 'meines', 'dein', 'deine', 'deinen', 'deinem', 'deiner', 'deines',
        'sein', 'seine', 'seinen', 'seinem', 'seiner', 'seines', 'ihre', 'ihren', 'ihrem', 'ihrer', 'ihres',
        'unser', 'unsere', 'unseren', 'unserem', 'unserer', 'unseres', 'euer', 'eure', 'euren', 'eurem', 'eurer', 'eures',
        'dies', 'dieser', 'diese', 'dieses', 'diesen', 'diesem', 'jede', 'jeder', 'jedes', 'jeden', 'jedem', 'alle', 'alles',
        'beide', 'beiden', 'einige', 'einigen', 'etwas', 'nichts',
        'in', 'im', 'ins', 'an', 'am', 'ans', 'auf', 'aus', 'bei', 'beim', 'mit', 'nach', 'seit', 'von', 'vom', 'zu', 'zum',
        'zur', 'für', 'gegen', 'ohne', 'um', 'durch', 'bis', 'über', 'unter', 'vor', 'hinter', 'neben', 'zwischen',
        'während', 'wegen', 'ab', 'außer',
        'dabei', 'dafür', 'davon', 'dazu', 'darauf', 'darüber', 'daran',
        'und', 'oder', 'aber', 'denn', 'sondern', 'wenn', 'weil', 'dass', 'ob', 'als', 'damit', 'bevor', 'nachdem',
        'obwohl', 'falls', 'sobald', 'sodass', 'seitdem', 'solange',
        'bin', 'bist', 'ist', 'sind', 'seid', 'war', 'warst', 'waren', 'gewesen', 'wäre', 'wären', 'wärst',
        'habe', 'hab', 'hast', 'hat', 'haben', 'habt', 'hatte', 'hattest', 'hatten', 'gehabt', 'hätte', 'hätten', 'hättest',
        'werde', 'wirst', 'wird', 'werden', 'werdet', 'wurde', 'wurden', 'worden', 'würde', 'würden',
        'kann', 'kannst', 'können', 'könnt', 'könnte', 'könnten', 'muss', 'musst', 'müssen', 'müsst', 'musste', 'müsste',
        'soll', 'sollst', 'sollen', 'sollt', 'sollte', 'sollten', 'will', 'willst', 'wollen', 'wollt', 'wollte', 'darf',
        'darfst', 'dürfen', 'dürft', 'durfte', 'möchte', 'möchtest', 'möchten', 'mag', 'magst', 'mögen',
        'was', 'wer', 'wen', 'wem', 'wessen', 'wie', 'wo', 'wann', 'warum', 'wieso', 'weshalb', 'woher', 'wohin', 'womit',
        'wofür', 'worum', 'wozu', 'welche', 'welcher', 'welches', 'welchen', 'welchem',
        'ja', 'nein', 'doch', 'noch', 'schon', 'auch', 'nur', 'sehr', 'so', 'hier', 'da', 'dort', 'dann', 'jetzt', 'nun',
        'mal', 'bitte', 'danke', 'gern', 'gerne', 'okay', 'ok', 'ach', 'oh', 'na', 'also', 'genau', 'klar', 'entschuldigung',
    ],

    // THE WORDS A RECOGNISER EATS (наряд FIX-2, п. 2) — prepositions and their fusions with the article, the forms of
    // sein/haben/werden, the modals: left out of both sides when a line the learner is LOOKING AT is compared with what
    // was said. No article (DECISIONS п. 89 — the article is the gender the learner is learning), no «nicht», «kein»,
    // «ohne», «gegen» (they flip the meaning: «ohne Termin» is no «mit Termin»), no pronoun, no «sein» (also «his»).
    // Handed down in the canonical form of speech — «muß» and «muss» are one.
    'unstressed_words' => [
        'in', 'im', 'ins', 'an', 'am', 'ans', 'auf', 'aus', 'bei', 'beim', 'mit', 'nach', 'seit', 'von', 'vom', 'zu', 'zum',
        'zur', 'für', 'um', 'über', 'unter', 'vor', 'durch', 'bis',
        'bin', 'bist', 'ist', 'sind', 'seid', 'war', 'waren', 'habe', 'hast', 'hat', 'haben', 'habt', 'hatte',
        'hatten', 'werde', 'wirst', 'wird', 'werden', 'würde', 'würden',
        'kann', 'kannst', 'können', 'könnte', 'könnten', 'muss', 'musst', 'müssen', 'soll', 'sollen', 'sollte', 'will',
        'willst', 'wollen', 'darf', 'dürfen', 'möchte', 'möchten',
    ],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER (наряд FIX-2, п. 2; LANG-1 §4). German writes 21–99 as ONE word
    // («einundzwanzig», «neunundneunzig») and the reader cuts no word, so every one of them is an entry — built below
    // from the units and the tens, never typed out by hand. The hundreds are one word too (zweihundert = 200); «zwei
    // hundert» split by a recogniser is read by the rule of the kernel (a scale after a smaller number multiplies it), and
    // «ein hundert», «eine Million» — the scale with its «one» split off — are entries of two words (the longest entry
    // wins; «ein» alone stays a word). The counting «eins» is 1; «ein», «eine», «einen» alone are not (see the header:
    // the article's gender). Compounds above a hundred written as one word («hundertfünfzig», «zweihundertfünfzig») are
    // not listed — a recogniser writes them in digits, and 900 entries would ride to the phone with every day.
    'number_words' => [
        'null' => '0', 'eins' => '1', 'zwei' => '2', 'zwo' => '2', 'drei' => '3', 'vier' => '4', 'fünf' => '5',
        'sechs' => '6', 'sieben' => '7', 'acht' => '8', 'neun' => '9', 'zehn' => '10', 'elf' => '11', 'zwölf' => '12',
        'dreizehn' => '13', 'vierzehn' => '14', 'fünfzehn' => '15', 'sechzehn' => '16', 'siebzehn' => '17',
        'achtzehn' => '18', 'neunzehn' => '19',
        'zwanzig' => '20', 'dreißig' => '30', 'vierzig' => '40', 'fünfzig' => '50', 'sechzig' => '60', 'siebzig' => '70',
        'achtzig' => '80', 'neunzig' => '90',
        // 21–99: the unit, «und», the tens — «einundzwanzig» … «neunundneunzig».
        ...(static function (): array {
            $units = ['ein' => 1, 'zwei' => 2, 'drei' => 3, 'vier' => 4, 'fünf' => 5, 'sechs' => 6, 'sieben' => 7, 'acht' => 8, 'neun' => 9];
            $tens = ['zwanzig' => 20, 'dreißig' => 30, 'vierzig' => 40, 'fünfzig' => 50, 'sechzig' => 60, 'siebzig' => 70, 'achtzig' => 80, 'neunzig' => 90];
            $out = [];
            foreach ($tens as $ten => $tenValue) {
                foreach ($units as $unit => $unitValue) {
                    $out[$unit.'und'.$ten] = (string) ($tenValue + $unitValue);
                }
            }

            return $out;
        })(),
        'hundert' => '100', 'einhundert' => '100', 'ein hundert' => '100',
        // 200–900: «zweihundert» … «neunhundert».
        ...(static function (): array {
            $out = [];
            foreach (['zwei' => 2, 'drei' => 3, 'vier' => 4, 'fünf' => 5, 'sechs' => 6, 'sieben' => 7, 'acht' => 8, 'neun' => 9] as $unit => $value) {
                $out[$unit.'hundert'] = (string) ($value * 100);
            }

            return $out;
        })(),
        'tausend' => '1000', 'eintausend' => '1000', 'ein tausend' => '1000',
        'million' => '1000000', 'millionen' => '1000000', 'eine million' => '1000000',
        'milliarde' => '1000000000', 'milliarden' => '1000000000', 'eine milliarde' => '1000000000',
    ],

    // The joiner after a SCALE (the rule of the kernel, as en «and»): «tausend und eins» is 1001, «hundert und zwei»
    // 102 — the old spelling a recogniser may split. German writes no joiner after a tens word (the unit comes first,
    // inside one word), so its `number_tens_joiners` is the no-op: «zwischen zwanzig und dreißig Euro» stays two numbers.
    'number_joiners' => ['und'],
    'number_tens_joiners' => [],

    // Two forms of one word in an inflected language, as ru: both at least four letters, sharing all but the last two of
    // the shorter («Termin» — «Termine», «Tage» — «Tagen»). A three-letter stem («Tag» — «Tage») is not met: the price of
    // keeping «Haut» and «Haus» apart.
    'word_forms' => ['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2],

    // THE FORMS OF A DICTIONARY WORD (наряд GEN-4 — `vocab.not_found`, FATAL, and `vocab.used_in_wrong`; {@see TermForms}):
    // the vocabulary of a day writes a word in its dictionary form, the frames and lines say it inflected; the forms no
    // rule of letters reaches are listed here under the word as a term's content word. Read by the day's checks only —
    // not the talk's `irregular_forms` (a form → its base, in the canonical form of speech, read by `WordBases`).
    // German: the strong and modal verbs by the infinitive («können» — «kann», «nehmen» — «nimmt»); keyed folded, ß as ss.
    'lemma_forms' => [
        'sein' => ['bin', 'bist', 'ist', 'sind', 'seid', 'war', 'warst', 'waren', 'wart', 'gewesen', 'wäre', 'wären', 'sei'],
        'haben' => ['habe', 'hast', 'hat', 'habt', 'hatte', 'hattest', 'hatten', 'gehabt', 'hätte', 'hätten'],
        'werden' => ['werde', 'wirst', 'wird', 'werdet', 'wurde', 'wurden', 'geworden', 'würde', 'würden'],
        'können' => ['kann', 'kannst', 'könnt', 'konnte', 'konnten', 'könnte', 'könnten', 'gekonnt'],
        'wollen' => ['will', 'willst', 'wollt', 'wollte', 'wollten', 'gewollt'],
        'müssen' => ['muss', 'musst', 'müsst', 'musste', 'mussten', 'müsste', 'gemusst'],
        'dürfen' => ['darf', 'darfst', 'dürft', 'durfte', 'durften', 'dürfte'],
        'sollen' => ['soll', 'sollst', 'sollt', 'sollte', 'sollten'],
        'mögen' => ['mag', 'magst', 'mögt', 'mochte', 'möchte', 'möchtest', 'möchten', 'möchtet'],
        'wissen' => ['weiß', 'weißt', 'wisst', 'wusste', 'wussten', 'gewusst'],
        'gehen' => ['ging', 'gingen', 'gegangen'],
        'geben' => ['gibt', 'gibst', 'gab', 'gaben', 'gegeben'],
        'nehmen' => ['nimmt', 'nimmst', 'nahm', 'nahmen', 'genommen'],
        'sehen' => ['sieht', 'siehst', 'sah', 'sahen', 'gesehen'],
        'sprechen' => ['spricht', 'sprichst', 'sprach', 'gesprochen'],
        'helfen' => ['hilft', 'hilfst', 'half', 'geholfen'],
        'tun' => ['tut', 'tat', 'getan'],
        'wehtun' => ['tut', 'tun', 'weh'],
        'bringen' => ['brachte', 'gebracht'],
        'kommen' => ['kam', 'kamen', 'gekommen'],
        'fahren' => ['fährt', 'fährst', 'fuhr', 'gefahren'],
        'essen' => ['isst', 'aß', 'gegessen'],
        'lesen' => ['liest', 'las', 'gelesen'],
        'schlafen' => ['schläft', 'schlief'],
        'laufen' => ['läuft', 'lief'],
        'tragen' => ['trägt', 'trug'],
        'finden' => ['fand', 'gefunden'],
        'bleiben' => ['blieb', 'geblieben'],
        'heissen' => ['heiße', 'heißt', 'hieß'],
        'schreiben' => ['schrieb', 'geschrieben'],
        'treffen' => ['trifft', 'traf', 'getroffen'],
        'vergessen' => ['vergisst', 'vergaß'],
        'beginnen' => ['begann', 'begonnen'],
        'verstehen' => ['verstand', 'verstanden'],
        'stehen' => ['stand', 'gestanden'],
        'liegen' => ['lag', 'gelegen'],
        'sitzen' => ['saß', 'gesessen'],
        'ziehen' => ['zog', 'gezogen'],
        'bekommen' => ['bekam'],
        'denken' => ['dachte', 'gedacht'],
        'kennen' => ['kannte', 'gekannt'],
        'anrufen' => ['rufe', 'ruft', 'rief', 'angerufen'],
        'anfangen' => ['fange', 'fängt', 'fing', 'angefangen'],
        'mitbringen' => ['brachte', 'mitgebracht'],
        'erziehen' => ['erzieht', 'erzog', 'erzogen'], 'einziehen' => ['zog', 'eingezogen'], 'umziehen' => ['zog', 'umgezogen'],
        'anziehen' => ['zog', 'angezogen'], 'bieten' => ['bot', 'geboten'], 'anbieten' => ['bot', 'angeboten'],
        'empfehlen' => ['empfiehlt', 'empfahl', 'empfohlen'], 'gefallen' => ['gefällt', 'gefiel'], 'verlieren' => ['verlor', 'verloren'],
        'unterschreiben' => ['unterschrieb', 'unterschrieben'], 'halten' => ['hält', 'hielt'],
        'lassen' => ['lässt', 'ließ'], 'fallen' => ['fällt', 'fiel'], 'rufen' => ['rief', 'gerufen'], 'schliessen' => ['schloss', 'geschlossen'],
        'waschen' => ['wäscht', 'wusch'], 'steigen' => ['stieg', 'gestiegen'], 'umsteigen' => ['stieg', 'umgestiegen'],
        'aussteigen' => ['stieg', 'ausgestiegen'], 'einsteigen' => ['stieg', 'eingestiegen'],
        // GEN-4b: what the gate run found said and not read — «gelten» as «gilt», «anmelden» as «angemeldet» — and the
        // participles of the separable verbs of a visit, the «ge» inside them («ausgefüllt» for «ausfüllen»).
        'gelten' => ['gilt', 'galt', 'gegolten'], 'anmelden' => ['angemeldet', 'melde', 'meldet'], 'abmelden' => ['abgemeldet'],
        'ausfüllen' => ['ausgefüllt'], 'abholen' => ['abgeholt'], 'vorstellen' => ['vorgestellt'], 'einkaufen' => ['eingekauft'],
        'aufstehen' => ['stand', 'aufgestanden'], 'ausziehen' => ['zog', 'ausgezogen'], 'abgeben' => ['gibt', 'gab', 'abgegeben'],
        'mitnehmen' => ['nimmt', 'nahm', 'mitgenommen'], 'zurückrufen' => ['rufe', 'ruft', 'rief', 'zurückgerufen'],
        'teilnehmen' => ['nimmt', 'nahm', 'teilgenommen'], 'stattfinden' => ['findet', 'fand', 'stattgefunden'],
    ],

    // A number, one word (folded: «dreißig» is `dreissig` here): a digit anywhere («0176», «14a»), a cardinal made of the
    // number words (dreizehn, einundzwanzig, zweihundert), an ordinal of them (zweite, dritten, zwanzigste, achter), a
    // multiple (einmal, dreimal), a half or a quarter. Never «ein», «eine», «einen» (the article) nor a bare «und» — and
    // never «achten», which a doctor's line says as the VERB far more often than as «the eighth» («Bitte achten Sie
    // darauf, viel zu trinken»): a line read as saying a number that its translation does not say leaves the scene
    // without its «Поймай число» card.
    'number_pattern' => '/\d|^(?!(?:ein|und)$)(?:(?:null|eins|ein|zwei|zwo|drei|vier|fünf|sechs|sieben|acht|neun|zehn|elf|zwölf|sechzehn|siebzehn|zwanzig|dreissig|vierzig|fünfzig|sechzig|siebzig|achtzig|neunzig|hundert|tausend|und)+(?:s?te[nrms]?|mal)?|erste[nrms]?|dritte[nrms]?|siebte[nrms]?|achte[rms]?|halb|halbe[nrms]?|hälfte|anderthalb|eineinhalb|viertel|dutzend|million|millionen|milliarde|milliarden)$/u',

    // Time words, one word: units, the clock («Uhr»), parts of the day, the days and the months, «heute/morgen/gestern»,
    // «früh/spät», «früher/später/pünktlich». Prepositions (seit, vor, nach, um) are function words, not time words. «Tag»
    // is a time word here («seit einem Tag», «dreimal am Tag» — a listening option of de-en says it) though a line's
    // greeting «Guten Tag» is read as a time too, as «Guten Morgen» is («Morgen» is «tomorrow»): no word pattern can tell
    // a greeting from a time — the code's question (see `amount_pattern` for what the pack does about it).
    'time_pattern' => '/^(?:sekunde|sekunden|minute|minuten|stunde|stunden|viertelstunde|viertelstunden|uhr|uhrzeit|tag|tage|tagen|tages|tags|woche|wochen|wochenende|wochenenden|monat|monate|monaten|monats|jahr|jahre|jahren|jahres|morgen|morgens|vormittag|vormittags|mittag|mittags|nachmittag|nachmittage|nachmittags|abend|abende|abenden|abends|nacht|nächte|nächten|nachts|mitternacht|heute|heutige[nmrs]?|gestern|gestrige[nmrs]?|vorgestern|morgige[nmrs]?|übermorgen|jetzt|sofort|bald|demnächst|früh|spät|später|früher|vorher|nachher|vorhin|neulich|damals|lange|pünktlich|montag|dienstag|mittwoch|donnerstag|freitag|samstag|sonnabend|sonntag|montags|dienstags|mittwochs|donnerstags|freitags|samstags|sonntags|januar|jänner|februar|märz|april|mai|juni|juli|august|september|oktober|november|dezember)$/u',

    // The units something is COUNTED in — what makes «eine Woche», «drei Tage» an amount and «heute», «am Freitag» a date
    // («Поймай число» offers amounts). Parts of the day, weekdays, months and «Uhr» are not here, and neither is the
    // singular «Tag»: in a dialogue it is the greeting «Guten Tag» far more often than «einen Tag», and as an amount the
    // greeting became the option a German learner was offered («Guten Tag, ich habe um vier einen Termin» → «Tag», not
    // «Um vier»). «Tage», «Tagen» stay.
    'amount_pattern' => '/^(?:sekunde|sekunden|minute|minuten|stunde|stunden|viertelstunde|viertelstunden|tage|tagen|tages|woche|wochen|monat|monate|monaten|monats|jahr|jahre|jahren|jahres|nächte|nächten|mal|grad|prozent|meter|kilometer|kilo|kilogramm|gramm|liter|milliliter|milligramm|tablette|tabletten|tropfen|euro|cent|stück|packung|packungen)$/u',

    // What carries an amount and is said WITH it, to its left: prepositions and determiners — «seit drei Tagen», «vor
    // einer Woche», «in zwei Stunden», «um vier», «eine halbe Stunde», «jeden Tag».
    'amount_prefix' => '/^(?:in|im|am|an|um|vor|nach|seit|für|ab|bis|zu|zum|zur|innerhalb|ca|circa|etwa|ungefähr|fast|knapp|genau|über|unter|mehr|weniger|als|pro|alle|jede|jeden|jeder|jedem|jedes|ein|eine|einen|einem|einer|eines|diese|diesen|dieser|diesem|dieses|nächste|nächsten|nächster|nächstem|letzte|letzten|letzter|letztem|vergangene|vergangenen|kommende|kommenden|ganze|ganzen|ganzer|halbe|halben|halber)$/u',

    // The prompt's STOP LIST (numbers, family, time words, colours, sein / haben / gehen) and plain words a learner knows
    // at any level — no word of the day.
    'everyday_words' => [
        'eins', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun', 'zehn', 'elf', 'zwölf', 'zwanzig',
        'dreißig', 'vierzig', 'fünfzig', 'hundert', 'tausend', 'erste', 'ersten', 'erster', 'zweite', 'zweiten', 'dritte',
        'dritten',
        'mutter', 'vater', 'mama', 'papa', 'eltern', 'bruder', 'brüder', 'schwester', 'schwestern', 'sohn', 'söhne',
        'tochter', 'töchter', 'kind', 'kinder', 'baby', 'familie', 'mann', 'männer', 'frau', 'frauen', 'oma', 'opa',
        'großmutter', 'großvater',
        'tag', 'tage', 'tagen', 'woche', 'wochen', 'monat', 'monate', 'jahr', 'jahre', 'heute', 'morgen', 'gestern',
        'abend', 'nacht', 'zeit', 'stunde', 'stunden', 'minute', 'minuten', 'uhr', 'jetzt', 'später', 'bald', 'früh', 'spät',
        'rot', 'blau', 'grün', 'gelb', 'schwarz', 'weiß', 'braun', 'grau', 'orange', 'rosa', 'lila',
        'sein', 'bin', 'bist', 'ist', 'sind', 'war', 'waren', 'haben', 'habe', 'hast', 'hat', 'hatte', 'hatten', 'gehen',
        'gehe', 'geht', 'ging', 'gegangen',
        'arbeit', 'haus', 'hause', 'schule', 'mensch', 'menschen', 'leute', 'person', 'freund', 'freundin', 'essen',
        'wasser', 'auto', 'zimmer', 'tür', 'tisch', 'name', 'namen', 'ding', 'sache', 'sachen', 'gut', 'schlecht', 'groß',
        'klein', 'neu', 'alt', 'hallo', 'trinken', 'sehen', 'kommen', 'machen', 'nehmen', 'geben', 'wollen', 'mögen',
        'wissen', 'denken', 'sagen', 'brauchen', 'helfen', 'platz', 'stadt', 'straße', 'geld', 'buch', 'telefon', 'handy',
        'hund', 'katze', 'hand', 'kopf', 'auge', 'augen', 'problem', 'frage', 'antwort',
    ],

    // Ordinary adjectives and quantifiers — the head of a free combination («große Schmerzen», «viele Sachen») that is
    // no chunk. German declines them: each stem with its five endings (-e -en -er -es -em), built below.
    'ordinary_heads' => (static fn (string ...$stems): array => array_merge(...array_map(
        static fn (string $stem): array => [$stem, $stem.'e', $stem.'en', $stem.'er', $stem.'es', $stem.'em'],
        $stems,
    )))('groß', 'klein', 'gut', 'schlecht', 'schön', 'nett', 'toll', 'schwer', 'leicht', 'viel', 'wenig', 'einig', 'ander', 'verschieden', 'wichtig', 'echt', 'ganz'),

    // A partner line that says nothing but «we are done» — its whole text, punctuation aside.
    'closers' => [
        'noch etwas', 'sonst noch etwas', 'noch was', 'sonst noch was', 'ist das alles', 'war das alles', 'das wäre alles',
        'gut', 'sehr gut', 'super', 'prima', 'perfekt', 'okay', 'ok', 'in ordnung', 'alles klar', 'klar', 'genau',
        'natürlich', 'danke', 'danke schön', 'danke sehr', 'vielen dank', 'bitte', 'bitte schön', 'bitte sehr', 'gern',
        'gerne', 'gern geschehen', 'kein problem', 'schönen tag', 'schönen tag noch', 'einen schönen tag',
        'einen schönen tag noch', 'gute besserung', 'alles gute', 'bis dann', 'bis bald', 'bis später', 'bis morgen',
        'auf wiedersehen', 'tschüss', 'das passt', 'passt', 'wunderbar', 'ausgezeichnet', 'schön', 'sehr schön', 'toll',
    ],

    // The verbs a check uses to name who said something («Was sagt der Patient?»), and «wollen / möchten» as en «want».
    // «meinen» is left out: it is also the possessive «meinen».
    'saying_verbs' => [
        'sagen', 'sagt', 'sagst', 'sage', 'sagte', 'sagten', 'gesagt', 'antworten', 'antwortet', 'antwortete', 'geantwortet',
        'erzählen', 'erzählt', 'erzählte', 'erwähnen', 'erwähnt', 'erwähnte', 'nennen', 'nennt', 'nannte', 'genannt', 'meint',
        'meinte', 'wollen', 'will', 'willst', 'wollte', 'möchte', 'möchten', 'möchtest', 'wünscht', 'wünschen', 'wünschte',
    ],

    // The word a partner names alternatives with («um neun oder um elf»).
    'alternative_words' => ['oder'],

    // One sentence that goes on after a comma with «und / oder» and asks again: «Seit wann haben Sie das, und haben Sie
    // Fieber?», «…, oder wo tut es weh?». The tag «…, oder?» asks once.
    'second_question_pattern' => '/,\s*(?:und|oder)\s+(?:bin|bist|ist|sind|seid|war|waren|habe|hast|hat|haben|habt|hatte|hatten|wird|werden|kann|können|könnten|darf|dürfen|muss|müssen|soll|sollen|sollte|sollten|will|wollen|möchte|möchten|gibt|geht|tut|wie|wo|wann|was|warum|wer|welche[nmrs]?|woher|wohin|wieso)\b[^?]*\?/iu',

    // THE ARTICLE CARRIES THE GENDER (DECISIONS п. 89: «для de не снимать»): no article is left out of a comparison —
    // not of the talk's frames, not of speech. «Artikel nach Artikel» at a seam is then no rule of this pack either.
    'articles' => [],

    // WORDS A SENTENCE CANNOT END ON (наряд FIX-3 §7): only the fusions of a preposition and an article and «des» — German
    // ends sentences on its separable particles («Ich trage Sie ein.», «Ich bringe sie mit.») and on article forms used as
    // pronouns («Ich nehme den.», «Ich habe eine.»), so neither may stand here.
    'dangling_words' => ['des', 'zum', 'zur', 'beim', 'im', 'am', 'vom', 'ins', 'ans', 'mein', 'dein'],

    // A word the seam may say twice and still be German (a fatal key — `filler.ungrammatical`): «Ist das ___?» + «das
    // Rezept» is «Ist das das Rezept?», «Können Sie ___?» + «sie mitbringen» is «Können Sie sie mitbringen?». Every other
    // doubled word at a seam stays a doubled word.
    'seam_repeatable_words' => ['das', 'sie'],

    // German has no article that changes with the next word's SOUND (en a / an): the no-op.
    'article_sound' => [
        'before_vowel' => '',
        'before_consonant' => '',
        'vowel' => '/(?!)/u',
        'consonant' => '/(?!)/u',
        'spelled' => '/(?!)/u',
        'exception' => '/(?!)/u',
    ],

    // A clause where a value should stand (a fatal key — `filler.ungrammatical` when the frame has its verb): a subject
    // pronoun and a form of sein / haben / werden or a modal open the filler («es ist dringend», «ich habe Fieber»); a
    // conjunction opens it («wenn ich huste», «weil es wehtut»); «als», «bis», «seit», «während», «damit» only before a
    // subject («seit ich hier bin», never «seit drei Tagen»). Kept narrow: German puts the verb second, and a filler
    // that starts with an adverb («morgen habe ich Zeit») is not read at all.
    'clause' => [
        'subjects' => ['ich', 'du', 'er', 'sie', 'es', 'wir', 'ihr', 'man'],
        'finite' => [
            'bin', 'bist', 'ist', 'sind', 'seid', 'war', 'waren', 'habe', 'hast', 'hat', 'haben', 'habt', 'hatte', 'hatten',
            'werde', 'wirst', 'wird', 'werden', 'kann', 'kannst', 'können', 'muss', 'musst', 'müssen', 'will', 'wollen',
            'möchte', 'möchten', 'darf', 'dürfen', 'soll', 'sollen',
        ],
        'contractions' => [],
        'subordinators' => ['wenn', 'weil', 'dass', 'ob', 'obwohl', 'falls', 'sobald', 'bevor', 'nachdem', 'sodass'],
        'subordinators_before_subject' => ['als', 'bis', 'seit', 'seitdem', 'während', 'damit', 'solange'],
    ],

    // «The frame must stand alone» (FRAMES): «es» and «das» lean on something said before — «Das habe ich ___.» (the
    // complaint). Left alone: «Es» opening the frame («Es tut weh, wenn ___»); «es» and «das» beside «gibt», a form of
    // sein, or the verbs whose «es» is the dummy of a time or a matter («Geht es ___?», «Worum geht es ___?», «Passt es
    // Ihnen ___?», «Klappt es ___?») — the dummy and the presentative («Gibt es ___?», «Das ist ___.», «Ist es ___?»);
    // «das» before the slot or a content word, the article («Ist das ___?», «Das Formular ___»); a pronoun after a
    // determiner with a content word.
    'unresolved_pronouns' => [
        'words' => ['es', 'das'],
        'frame_initial_subject' => ['es'],
        'existential' => ['es', 'das'],
        'determiner_or_number' => ['das'],
        'partitive' => ['von'],
        'be_forms' => ['gibt', 'ist', 'sind', 'war', 'waren', 'wäre', 'geht', 'passt', 'klappt'],
        'determiners' => [
            'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'eines', 'mein', 'meine',
            'meinen', 'meinem', 'meiner', 'dein', 'deine', 'sein', 'seine', 'ihr', 'ihre', 'ihren', 'ihrem', 'unser', 'unsere',
            'dieser', 'diese', 'dieses', 'diesen', 'diesem',
        ],
    ],

    // German's past has no gender for «ich» («ich war», «ich bin gegangen»): the no-op.
    'gendered_past_pattern' => '/(?!)/u',

    // «The native frame contains NO word that agrees with the slot» (FRAMES): the word right before `___` agrees when it
    // is an article, a possessive, a demonstrative, «welche», «jede», «kein» — in any case («Ich brauche einen ___»,
    // «Wo ist die ___?»). No adjective by its ending (-e, -en, -er are also the endings of verbs and nouns: «Ich habe
    // ___» would read as agreeing), and nothing after the slot: German predicates do not agree in gender.
    'agreement' => [
        'words' => [
            'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'eines',
            'kein', 'keine', 'keinen', 'keinem', 'keiner', 'keines',
            'mein', 'meine', 'meinen', 'meinem', 'meiner', 'meines', 'dein', 'deine', 'deinen', 'deinem', 'deiner', 'deines',
            'sein', 'seine', 'seinen', 'seinem', 'seiner', 'seines', 'ihr', 'ihre', 'ihren', 'ihrem', 'ihrer', 'ihres',
            'unser', 'unsere', 'unseren', 'unserem', 'unserer', 'unseres', 'euer', 'eure', 'euren', 'eurem', 'eurer', 'eures',
            'dieser', 'diese', 'dieses', 'diesen', 'diesem', 'jener', 'jene', 'jenes', 'jenen', 'jenem',
            'welcher', 'welche', 'welches', 'welchen', 'welchem', 'jeder', 'jede', 'jedes', 'jeden', 'jedem',
            'mancher', 'manche', 'manches', 'manchen', 'manchem', 'solcher', 'solche', 'solches', 'solchen', 'solchem',
        ],
        'short_forms' => [],
        'suffixes_before_slot' => [],
        'min_letters' => 99,
        'after_slot_words' => 0,
    ],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а): what a rescue move says in the learner's bubble (en «Sorry?»).
    'rescue_line' => 'Wie bitte?',

    // THE RESCUE KIT (наряд LANG-1b §2): the six lines a learner of this language says when stuck, each with its translation into
    // every learner's language of the plan. Row 1 is `rescue_line`. Said in the learner's voice, filed by (target,
    // gender, voice, line) — `RescueKits`. The same matrix writes every pack: `docs/research/lang-1b/tools/rescue-kit.py`.
    'rescue' => [
        ['target' => 'Wie bitte?', 'native' => ['ru' => 'Простите?', 'uk' => 'Перепрошую?', 'be' => 'Прабачце?', 'pl' => 'Słucham?', 'ro' => 'Poftim?', 'es' => '¿Perdón?', 'it' => 'Scusi?', 'de' => 'Wie bitte?', 'fr' => "Pardon\u{00A0}?"]],
        ['target' => 'Können Sie bitte langsamer sprechen?', 'native' => ['ru' => 'Можно помедленнее, пожалуйста?', 'uk' => 'Можна повільніше, будь ласка?', 'be' => 'Можна павольней, калі ласка?', 'pl' => 'Proszę mówić trochę wolniej.', 'ro' => 'Puteți vorbi mai rar, vă rog?', 'es' => '¿Puede hablar más despacio, por favor?', 'it' => 'Può parlare più lentamente, per favore?', 'de' => 'Können Sie bitte langsamer sprechen?', 'fr' => "Vous pouvez parler plus lentement, s'il vous plaît\u{00A0}?"]],
        ['target' => 'Ich verstehe nicht.', 'native' => ['ru' => 'Я не понимаю.', 'uk' => 'Я не розумію.', 'be' => 'Я не разумею.', 'pl' => 'Nie rozumiem.', 'ro' => 'Nu înțeleg.', 'es' => 'No entiendo.', 'it' => 'Non capisco.', 'de' => 'Ich verstehe nicht.', 'fr' => 'Je ne comprends pas.']],
        ['target' => 'Einen Moment.', 'native' => ['ru' => 'Одну минуту.', 'uk' => 'Хвилинку.', 'be' => 'Хвілінку.', 'pl' => 'Chwileczkę.', 'ro' => 'Un moment.', 'es' => 'Un momento.', 'it' => 'Un momento.', 'de' => 'Einen Moment.', 'fr' => 'Un instant.']],
        ['target' => 'Können Sie mir das aufschreiben?', 'native' => ['ru' => 'Можете это записать?', 'uk' => 'Можете це записати?', 'be' => 'Можаце гэта запісаць?', 'pl' => 'Proszę mi to zapisać.', 'ro' => 'Îmi puteți scrie asta?', 'es' => '¿Me lo puede escribir?', 'it' => 'Me lo può scrivere?', 'de' => 'Können Sie mir das aufschreiben?', 'fr' => "Vous pouvez me l'écrire\u{00A0}?"]],
        ['target' => 'Danke.', 'native' => ['ru' => 'Спасибо.', 'uk' => 'Дякую.', 'be' => 'Дзякуй.', 'pl' => 'Dziękuję.', 'ro' => 'Mulțumesc.', 'es' => 'Gracias.', 'it' => 'Grazie.', 'de' => 'Danke.', 'fr' => 'Merci.']],
    ],

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9) — the same line as every pack's, in German (en «I see. Please go
    // on.»): said by the role of a talk held in German, or the translation under an English one for a German learner.
    'neutral_reply' => 'Ich verstehe. Erzählen Sie bitte weiter.',

    // THE TITLE OF A TALK for a German learner (наряд LANG-1 §6): nothing is declined, the roles as written — German
    // nouns keep their capital («Gespräch: Empfangskraft und Arzt»).
    'talk_title_template' => ['title' => 'Gespräch: {roles}', 'and' => 'und', 'anyone' => 'Gespräch', 'lower_first' => false],

    // THE FORMS OF ONE WORD (наряд BACK-TAILS-2 §2) — what the echo guard of the talk reads a word's bases by: the forms of
    // sein / haben / werden and the modals, the strong verbs a visit says, the umlaut plurals, the object pronouns. Keys
    // and bases in the canonical form of speech: lower case, folded (weiß → `weiss`, aß → `ass`).
    'irregular_forms' => [
        'bin' => 'sein', 'bist' => 'sein', 'ist' => 'sein', 'sind' => 'sein', 'seid' => 'sein', 'war' => 'sein',
        'warst' => 'sein', 'waren' => 'sein', 'wart' => 'sein', 'gewesen' => 'sein', 'wäre' => 'sein', 'wären' => 'sein',
        'habe' => 'haben', 'hab' => 'haben', 'hast' => 'haben', 'hat' => 'haben', 'habt' => 'haben', 'hatte' => 'haben',
        'hattest' => 'haben', 'hatten' => 'haben', 'gehabt' => 'haben', 'hätte' => 'haben', 'hätten' => 'haben',
        'werde' => 'werden', 'wirst' => 'werden', 'wird' => 'werden', 'werdet' => 'werden', 'wurde' => 'werden',
        'wurden' => 'werden', 'geworden' => 'werden', 'würde' => 'werden', 'würden' => 'werden',
        'kann' => 'können', 'kannst' => 'können', 'könnt' => 'können', 'konnte' => 'können', 'konnten' => 'können',
        'könnte' => 'können', 'könnten' => 'können', 'muss' => 'müssen', 'musst' => 'müssen', 'müsst' => 'müssen',
        'musste' => 'müssen', 'mussten' => 'müssen', 'müsste' => 'müssen', 'darf' => 'dürfen', 'darfst' => 'dürfen',
        'durfte' => 'dürfen', 'will' => 'wollen', 'willst' => 'wollen', 'wollte' => 'wollen', 'wollten' => 'wollen',
        'soll' => 'sollen', 'sollst' => 'sollen', 'sollte' => 'sollen', 'sollten' => 'sollen', 'mag' => 'mögen',
        'magst' => 'mögen', 'mochte' => 'mögen', 'möchte' => 'mögen', 'möchtest' => 'mögen', 'möchten' => 'mögen',
        'weiss' => 'wissen', 'weisst' => 'wissen', 'wusste' => 'wissen', 'gewusst' => 'wissen',
        'gibt' => 'geben', 'gibst' => 'geben', 'gab' => 'geben', 'gegeben' => 'geben',
        'nimmt' => 'nehmen', 'nimmst' => 'nehmen', 'nahm' => 'nehmen', 'genommen' => 'nehmen',
        'sieht' => 'sehen', 'siehst' => 'sehen', 'sah' => 'sehen', 'gesehen' => 'sehen',
        'isst' => 'essen', 'ass' => 'essen', 'gegessen' => 'essen', 'spricht' => 'sprechen', 'sprach' => 'sprechen',
        'gesprochen' => 'sprechen', 'hilft' => 'helfen', 'half' => 'helfen', 'geholfen' => 'helfen', 'fährt' => 'fahren',
        'fuhr' => 'fahren', 'gefahren' => 'fahren', 'schläft' => 'schlafen', 'schlief' => 'schlafen',
        'geschlafen' => 'schlafen', 'tut' => 'tun', 'tat' => 'tun', 'getan' => 'tun', 'ging' => 'gehen', 'gingen' => 'gehen',
        'gegangen' => 'gehen', 'kam' => 'kommen', 'kamen' => 'kommen', 'gekommen' => 'kommen', 'brachte' => 'bringen',
        'gebracht' => 'bringen', 'dachte' => 'denken', 'gedacht' => 'denken', 'fand' => 'finden', 'gefunden' => 'finden',
        'blieb' => 'bleiben', 'geblieben' => 'bleiben', 'schrieb' => 'schreiben', 'geschrieben' => 'schreiben',
        'trank' => 'trinken', 'getrunken' => 'trinken', 'lag' => 'liegen', 'gelegen' => 'liegen', 'stand' => 'stehen',
        'gestanden' => 'stehen', 'trägt' => 'tragen', 'trug' => 'tragen', 'getragen' => 'tragen', 'läuft' => 'laufen',
        'lief' => 'laufen', 'gelaufen' => 'laufen', 'rief' => 'rufen', 'gerufen' => 'rufen', 'hiess' => 'heissen',
        'geheissen' => 'heissen',
        'ärzte' => 'arzt', 'zähne' => 'zahn', 'hände' => 'hand', 'füsse' => 'fuss', 'nächte' => 'nacht',
        'männer' => 'mann', 'häuser' => 'haus', 'kinder' => 'kind', 'söhne' => 'sohn', 'töchter' => 'tochter',
        'mütter' => 'mutter', 'väter' => 'vater', 'brüder' => 'bruder',
        // An object form of a pronoun is the same pronoun.
        'mich' => 'ich', 'mir' => 'ich', 'dich' => 'du', 'dir' => 'du', 'ihn' => 'er', 'ihm' => 'er', 'uns' => 'wir',
        'euch' => 'ihr', 'ihnen' => 'sie',
    ],

    // The regular endings, each a pattern and the base it leaves — every rule that matches gives one base, and a wrong
    // base meets nothing («Tagen» is `tag` and `tage`, «brauchst» and «gebraucht» are `brauch`). A stem keeps three letters
    // at least.
    'inflection_rules' => [
        ['/^ge(.{3,})t$/u', '$1'],
        ['/^ge(.{3,})en$/u', '$1'],
        ['/^(.{3,})ten$/u', '$1'],
        ['/^(.{3,})te$/u', '$1'],
        ['/^(.{3,})en$/u', '$1'],
        ['/^(.{3,})n$/u', '$1'],
        ['/^(.{3,})es$/u', '$1'],
        ['/^(.{3,})et$/u', '$1'],
        ['/^(.{3,})st$/u', '$1'],
        ['/^(.{3,})e$/u', '$1'],
        ['/^(.{3,})s$/u', '$1'],
        ['/^(.{3,})t$/u', '$1'],
    ],

    // THE FIRST AND THE SECOND PERSON, SWAPPED (наряд BACK-TAILS-2 §9) — «Ich habe Fieber» said back «Sie haben Fieber»: the
    // role of a visit speaks to the learner with the polite «Sie». The verb's person needs no swap: its bases meet
    // (`irregular_forms`, `inflection_rules`).
    'person_swap' => [
        'ich' => 'sie', 'mich' => 'sie', 'mir' => 'ihnen', 'mein' => 'ihr', 'meine' => 'ihre', 'meinen' => 'ihren',
        'meinem' => 'ihrem', 'meiner' => 'ihrer', 'meines' => 'ihres',
        'sie' => 'ich', 'ihnen' => 'mir', 'ihr' => 'mein', 'ihre' => 'meine', 'ihren' => 'meinen', 'ihrem' => 'meinem',
        'ihrer' => 'meiner', 'ihres' => 'meines',
    ],

    // ─── THE JUDGE OF THE TALK'S CONSTRUCTIONS (наряд FIX-4 §2, LANG-1 §1).

    // A SPOKEN SHORT FORM IS ITS WORDS: what a recogniser writes for colloquial German, spelt out on both sides — «Ich hab
    // Fieber» says «Ich habe ___», «Gibt's hier eine Apotheke?» says «Gibt es hier ___?», «Wie geht's Ihnen?» «Wie geht es
    // ___?» (with and without the apostrophe: «gibts», «gehts» are written too). The fusions «am», «im», «zum» are no
    // contraction: they are words of their own on both sides, and «an dem» for «am» would be another sentence.
    'contractions' => [
        'hab' => 'habe', "hab's" => 'habe es', "gibt's" => 'gibt es', 'gibts' => 'gibt es', "geht's" => 'geht es',
        'gehts' => 'geht es', "ist's" => 'ist es', "war's" => 'war es', "wie's" => 'wie es', "kann's" => 'kann es',
    ],
    'contractions_before' => [],

    // THE WORDS A MOVE MAY OPEN WITH before its construction: «Ja, das habe ich seit drei Tagen», «Guten Tag, ich brauche
    // einen Termin», «Ja gerne, ich komme um vier», «Alles klar, ich bringe sie mit».
    'intro_words' => [
        'hallo', 'hi', 'guten tag', 'guten morgen', 'guten abend', 'ja', 'nein', 'okay', 'ok', 'oh', 'ach', 'ach so',
        'also', 'gut', 'sehr gut', 'so', 'na', 'klar', 'alles klar', 'in ordnung', 'genau', 'stimmt', 'richtig', 'super',
        'prima', 'perfekt', 'natürlich', 'sicher', 'gern', 'gerne', 'danke', 'danke schön', 'vielen dank', 'bitte',
        'moment', 'einen moment', 'und', 'äh', 'ähm', 'hm', 'entschuldigung', 'entschuldigen sie', 'verzeihung',
    ],

    // THE WORDS A NEW CLAUSE OPENS WITH (наряд FIX-4b §1): «Ich habe Fieber und ich habe Halsschmerzen».
    'clause_starters' => ['und', 'aber', 'oder', 'dann', 'also', 'denn', 'sondern'],

    // A CONSTRUCTION SAID IN THE NEGATIVE IS THE SAME CONSTRUCTION (DECISIONS п. 395): «nicht» and the forms of «kein»
    // are free anywhere in the frame's words — «Haben Sie nicht etwas am Nachmittag?» says «Haben Sie etwas ___?». Only
    // an ADDED negation is free: «keinen» in place of the frame's «einen» is a word replaced, at most «almost».
    'negation' => [
        'words' => ['nicht', 'kein', 'keine', 'keinen', 'keinem', 'keiner', 'keines'],
        'after' => null,
        'do_support' => [],
    ],

    // German has no partitive word a quantity leaves out (en «I have ___ of experience»): the no-op.
    'partitive' => [],
];
