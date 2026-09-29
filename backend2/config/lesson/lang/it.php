<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · it — what the lesson's code-only checks read of Italian
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b), наряд LANG-1: Italian is taught (a TARGET — what the learner says and hears: lines,
| frames, fillers, checks, the talk) and spoken (a NATIVE — the readings of the target, native frames, listening, the
| role's translation, the talk's title), so every key of both sides is written. The key spec is
| `docs/research/lang-1/pack-keys.md`, with the main session's updates over it (common_words «частые и отличительные»,
| the exact `script_letters`, `number_tens_joiners`, the fold in `LanguagePack::normal()`).
|
| No key is null (null would switch its rule off): a rule that does not hold for Italian is written as the spec's no-op.
| Every list is a counter's reading of a rule, not the rule: the codes built on them are heuristics.
|
| HOW THE WORDS ARE WRITTEN. Lists are standard Italian spelling, lower case, the plain apostrophe; the pack's words and
| the text's are folded alike (`LanguagePack::normal()`, `LanguagePack::speech()`), and Italian has no letter the fold
| changes (ß, œ, ş) — the accented vowels stay as they are, so «è», «più», «perché» are written with their accents.
| An elided word is ONE word to the validator's tokens («l'ospedale», «c'è»), TWO to the judge of the talk (the
| `contractions` below spell «l'» out) and one word without its apostrophe to the comparison of speech («lospedale»).
*/
return [
    // A reading of the target in the learner's own letters (a WARNING, `pronunciation.script`): the Italian alphabet — the
    // 21 letters and the five a keyboard and every Italian school knows from loanwords (j k w x y: «ken», «okèi», «uiik»
    // spell English sounds) —, the accented vowels, digits, punctuation, spaces and the stress marks U+0300 / U+0301.
    // IPA («ə», «ð», «θ», «ʃ») and other alphabets' letters («ö», «ñ», «ł») are flagged here, never failed.
    'script' => '/^[a-zA-ZàèéìíîòóùúÀÈÉÌÍÎÒÓÙÚ\p{N}\p{P}\s\x{0300}\x{0301}]*$/u',

    // One LETTER of a reading, matched alone — exactly the Latin pattern every Latin pack writes (the guard of the
    // role's translation finds neighbours by this very string; `pronunciation.foreign_script` is FATAL, so it is the
    // script, not the alphabet).
    'script_letters' => '/^[\p{Latin}]$/u',

    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence (наряд CHECK-1): «Il dott. Rossi riceve alle 9.» is one sentence, and a filler «alla
    // Rossi S.p.A.» or «tra 10 min.» carries no sentence of its own (`filler.ungrammatical` is fatal). Only abbreviations
    // that are no ordinary word with a dot after them.
    'abbreviations' => [
        'ecc.', 'etc.', 'es.', 'p.es.', 'p. es.', 'sig.', 'sig.ra', 'sig.na', 'sigg.', 'dott.', 'dott.ssa', 'dr.', 'dr.ssa',
        'prof.', 'prof.ssa', 'ing.', 'avv.', 'n.', 'nr.', 'tel.', 'cell.', 'ca.', 'min.', 'pag.', 'cfr.', 's.p.a.', 's.r.l.',
    ],

    // Italian asks by intonation and the mark, never by inverting a subject and its auxiliary — the spec's no-op.
    'question_word_order' => ['auxiliaries' => [], 'subjects' => []],

    // THE ANSWER A YES-OR-NO QUESTION OPENS WITH (наряд GEN-4c, `partner.yes_no_missing` / `partner.yes_no_extra`): the
    // partner's reply to a yes-or-no question of the learner's opens with one of these words, a reply to a question that
    // asks for a fact opens with none. «Yes» first, «no» second — the finding names the two. «Sì» with its accent: «Si può…»
    // opens with the pronoun, not with a yes. Read against the reply's first word, case and marks aside.
    'yes_no' => ['sì', 'no'],

    // THE WORDS OF A QUESTION THAT ASKS FOR A FACT (наряд GEN-4c): a word, or a phrase of words standing in a row («che
    // cosa», «a che ora»), and the elided forms a word of the text is read in the parts of («Dov'è», «Com'è», «Cos'è»,
    // «Quant'è»). An ask frame that holds none of them, anywhere, and offers no choice (`alternative_words` between two of
    // its words) is a yes-or-no question: «Può dipendere da ___?», «Ha notato ___?». «Come» and «quando» are also «as» and
    // «when» of a clause («Posso lavorare come ___?»): such a frame reads as asking for a fact — the side that asks no «Sì».
    'question_words' => [
        'che', 'cosa', 'che cosa', 'chi', 'quale', 'quali', 'qual', 'dove', 'dov', 'quando', 'come', 'com', 'cos', 'perché',
        'quanto', 'quanta', 'quanti', 'quante', 'quant', 'a che ora', 'da quando', 'fino a quando', 'per quanto tempo',
    ],

    // Words that carry no content of their own: articles, prepositions and their forms with the article, conjunctions,
    // pronouns (clitic and stressed), possessives, demonstratives, the forms of essere / avere and the modals, question
    // words, «non», «sì», «no», the courtesy words, and the elided forms a token keeps whole («c'è», «l'ho»). Not «sei»:
    // it is the number 6 as often as «you are», and a function word is no value — «Alle sei» would read as no time at all.
    'function_words' => [
        'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una',
        'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 'ad',
        'del', 'dello', 'della', 'dei', 'degli', 'delle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle',
        'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle', 'nel', 'nello', 'nella', 'nei', 'negli', 'nelle',
        'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle', 'col', 'coi',
        'e', 'ed', 'o', 'oppure', 'ma', 'però', 'se', 'che', 'perché', 'quando', 'come', 'mentre', 'anche',
        'quindi', 'poi', 'allora',
        'io', 'tu', 'lui', 'lei', 'noi', 'voi', 'loro', 'mi', 'ti', 'ci', 'vi', 'si', 'li', 'ne', 'me', 'te', 'ce',
        've', 'sé', 'glielo', 'gliela',
        'mio', 'mia', 'miei', 'mie', 'tuo', 'tua', 'tuoi', 'tue', 'suo', 'sua', 'suoi', 'sue',
        'nostro', 'nostra', 'nostri', 'nostre', 'vostro', 'vostra', 'vostri', 'vostre',
        'questo', 'questa', 'questi', 'queste', 'quello', 'quella', 'quelli', 'quelle', 'quel', 'quei', 'quegli',
        'essere', 'sono', 'è', 'siamo', 'siete', 'ero', 'era', 'erano', 'stato', 'stata', 'sarà', 'sarò', 'sia',
        'avere', 'ho', 'hai', 'ha', 'abbiamo', 'avete', 'hanno', 'avevo', 'aveva', 'avuto',
        'posso', 'puoi', 'può', 'possiamo', 'potete', 'possono', 'potrei', 'potrebbe',
        'devo', 'devi', 'deve', 'dobbiamo', 'dovete', 'devono', 'dovrei', 'dovrebbe',
        'voglio', 'vuoi', 'vuole', 'vogliamo', 'volete', 'vogliono', 'vorrei', 'vorremmo',
        'cosa', 'chi', 'dove', 'quale', 'quali', 'quanto', 'quanta', 'quanti', 'quante',
        'non', 'sì', 'no', 'già', 'ancora', 'solo', 'molto', 'poco', 'più', 'meno', 'così', 'qui', 'qua', 'lì', 'là',
        'ecco', 'tutto', 'tutti', 'tutta', 'tutte', 'ogni', 'qualche', 'niente', 'nulla', 'mai', 'po',
        "c'è", "c'era", "l'ho", "l'ha", "dov'è", "com'è", "cos'è", "quant'è",
        'grazie', 'prego', 'favore', 'scusi', 'scusa', 'ok', 'okay', 'certo', 'beh', 'ah', 'oh', 'eh',
    ],

    // THE WORDS A RECOGNISER EATS (наряд FIX-2, п. 2) — articles, prepositions and their forms with the article, «e»,
    // the forms of essere / avere and the modals: left out of both sides when a line the learner is LOOKING AT is
    // compared with what was said. In the form of that comparison: no apostrophe — an elided «l'»/«dell'» is glued to its
    // noun there («lospedale») and is no word of its own. The indefinite «un», «una» are here as en «a», «an» are («uno» is
    // read as the number 1 before this list is). «non» is NOT here (it flips the meaning); «sei» is not either — it is
    // read as the number 6 on both sides before this list is.
    'unstressed_words' => [
        'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'una',
        'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 'ad',
        'del', 'dello', 'della', 'dei', 'degli', 'delle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle',
        'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle', 'nel', 'nello', 'nella', 'nei', 'negli', 'nelle',
        'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle',
        'e', 'ed',
        'sono', 'è', 'siamo', 'siete', 'ho', 'hai', 'ha', 'abbiamo', 'avete', 'hanno',
        'posso', 'puoi', 'può', 'possiamo', 'potete', 'possono', 'devo', 'devi', 'deve', 'dobbiamo', 'dovete', 'devono',
    ],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER (наряд FIX-2, п. 2; LANG-1 §4). Italian writes a number as ONE word
    // («ventuno», «trentotto», «duecento», «tremila») and the comparison does not cut words, so each is an entry: 0–20, the
    // tens, every compound 21–99 (a tens word drops its last vowel before «uno» and «otto», and «ventuno» is «ventun»
    // before a noun — «ventun anni»; «tre» at the end is «tré» — written with and without its accent), the hundreds,
    // «mille» and the thousands 2000–9000, «mila» and the scales. A number written apart («cento venti», «due mila») is
    // joined by the rule of the kernel. Only «uno» is the number 1: «un» and «una» are the articles and stay words («un
    // milione» is still 1 000 000 — an article before a scale). Compounds past a hundred («centoventi»,
    // «duecentocinquanta») are no entry, as de writes none («zweihundertfünfzig»): a recogniser writes those in digits.
    'number_words' => (static function (): array {
        $units = ['uno', 'due', 'tre', 'quattro', 'cinque', 'sei', 'sette', 'otto', 'nove'];
        $words = ['zero' => '0'];
        foreach ($units as $i => $unit) {
            $words[$unit] = (string) ($i + 1);
        }
        foreach (['dieci', 'undici', 'dodici', 'tredici', 'quattordici', 'quindici', 'sedici', 'diciassette', 'diciotto', 'diciannove'] as $i => $teen) {
            $words[$teen] = (string) ($i + 10);
        }
        foreach (['venti', 'trenta', 'quaranta', 'cinquanta', 'sessanta', 'settanta', 'ottanta', 'novanta'] as $i => $tens) {
            $value = ($i + 2) * 10;
            $words[$tens] = (string) $value;
            foreach ($units as $j => $unit) {
                $stem = in_array($unit, ['uno', 'otto'], true) ? substr($tens, 0, -1) : $tens;
                $words[$stem.($unit === 'tre' ? 'tré' : $unit)] = (string) ($value + $j + 1);
                if ($unit === 'tre') {
                    $words[$tens.'tre'] = (string) ($value + 3);
                }
                if ($unit === 'uno') {
                    $words[$stem.'un'] = (string) ($value + 1);
                }
            }
        }
        $words['cento'] = '100';
        $words['mille'] = '1000';
        $words['mila'] = '1000';
        foreach (array_slice($units, 1) as $i => $unit) {
            $words[$unit.'cento'] = (string) (($i + 2) * 100);
            $words[$unit.'mila'] = (string) (($i + 2) * 1000);
        }

        return $words + ['milione' => '1000000', 'milioni' => '1000000', 'miliardo' => '1000000000', 'miliardi' => '1000000000'];
    })(),

    // No word joins the parts of an Italian number — neither after a scale («cento venti», «mille e cento» is two
    // numbers) nor after the tens (a compound is one word; «le venti e cinque» is 20:05, never 25).
    'number_joiners' => [],
    'number_tens_joiners' => [],

    // Two forms of one word in an inflected language: both at least four letters, sharing all but the last two letters
    // of the shorter («giorno» — «giorni», «prendere» — «prendo»). One letter is no content word.
    'word_forms' => ['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2],

    // THE FORMS OF A DICTIONARY WORD (наряд GEN-4 — `vocab.not_found`, FATAL, and `vocab.used_in_wrong`; {@see TermForms}):
    // the vocabulary of a day writes a word in its dictionary form, the frames and lines say it inflected; the forms no
    // rule of letters reaches are listed here under the word as a term's content word. Read by the day's checks only —
    // not the talk's `irregular_forms` (a form → its base, in the canonical form of speech, read by `WordBases`).
    // Italian: the irregular verbs by the infinitive («volere» — «vorrei», «andare» — «vado»), irregular participles.
    'lemma_forms' => [
        'essere' => ['sono', 'sei', 'è', 'siamo', 'siete', 'ero', 'eri', 'era', 'eravamo', 'erano', 'stato', 'stata', 'sarò', 'sarà', 'sarei', 'sarebbe', 'sia'],
        'avere' => ['ho', 'hai', 'ha', 'abbiamo', 'avete', 'hanno', 'avevo', 'aveva', 'avuto', 'avrò', 'avrà', 'avrei', 'avrebbe', 'abbia'],
        'andare' => ['vado', 'vai', 'va', 'andiamo', 'andate', 'vanno', 'andrò', 'andrà', 'andrei', 'vada'],
        'fare' => ['faccio', 'fai', 'fa', 'facciamo', 'fate', 'fanno', 'fatto', 'farò', 'farà', 'farei', 'faccia', 'facevo'],
        'potere' => ['posso', 'puoi', 'può', 'possiamo', 'potete', 'possono', 'potrei', 'potrebbe', 'potrò', 'potuto', 'possa'],
        'volere' => ['voglio', 'vuoi', 'vuole', 'vogliamo', 'volete', 'vogliono', 'vorrei', 'vorrebbe', 'vorrò', 'voluto'],
        'dovere' => ['devo', 'devi', 'deve', 'dobbiamo', 'dovete', 'devono', 'dovrei', 'dovrebbe', 'dovrò', 'dovuto'],
        'sapere' => ['so', 'sai', 'sa', 'sappiamo', 'sapete', 'sanno', 'saprei', 'saputo'],
        'venire' => ['vengo', 'vieni', 'viene', 'veniamo', 'venite', 'vengono', 'verrò', 'verrà', 'venuto'],
        'dire' => ['dico', 'dici', 'dice', 'diciamo', 'dite', 'dicono', 'detto', 'dirò'],
        'dare' => ['do', 'dai', 'dà', 'diamo', 'date', 'danno', 'darò', 'dato'],
        'stare' => ['sto', 'stai', 'sta', 'stiamo', 'state', 'stanno', 'starò'],
        'uscire' => ['esco', 'esci', 'esce', 'usciamo', 'uscite', 'escono'],
        'bere' => ['bevo', 'bevi', 'beve', 'bevono', 'bevuto'],
        'rimanere' => ['rimango', 'rimane', 'rimangono', 'rimasto'],
        'tenere' => ['tengo', 'tieni', 'tiene', 'tengono'],
        'scegliere' => ['scelgo', 'sceglie', 'scelgono', 'scelto'],
        'prendere' => ['presi', 'preso'],
        'mettere' => ['messo'],
        'chiedere' => ['chiesto'],
        'rispondere' => ['risposto'],
        'vedere' => ['visto'],
        'leggere' => ['letto'],
        'scrivere' => ['scritto'],
        'aprire' => ['aperto'],
        'spendere' => ['speso'],
        'decidere' => ['deciso'],
        'succedere' => ['successo'],
        // GEN-4b: the impersonal «bisogna», beside «falloir» — «faut» of the French day the gate run failed on.
        'bisognare' => ['bisogna', 'bisognava', 'bisognerà', 'bisognerebbe'],
    ],

    // A number: a digit anywhere in the word; a numeral made of the parts of Italian numerals («ventitré», «ventun»,
    // «duecento», «tremila»), an elided article or preposition before it taken with it («l'otto maggio», «dall'undici») —
    // a lone «uno», «un», «una» is not: it is the article as often as the number —; the hour «l'una» / «all'una»; the
    // ordinals (not «prima»: it is «before» far more often than «first»; not «secondo»: «secondo me» is «in my view»);
    // «mezzo», «metà», «paio», «dozzina».
    'number_pattern' => '/\d|^(?:\p{L}+\')?(?!un[oa]?$)(?:zero|un[oa]?|due|tr[eéè]|quattro|cinque|sei|sette|otto|nove|dieci|undici|dodici|tredici|quattordici|quindici|sedici|diciassette|diciotto|diciannove|venti?|trenta?|quaranta?|cinquanta?|sessanta?|settanta?|ottanta?|novanta?|cento?|mille|mila|milion[ei]|miliard[oi])+$|^(?:l|all|dall|dell|nell|sull)\'una$|^(?:prim[oie]|second[ae]|terz[oaie]|quart[oaie]|quint[oaie]|sest[oaie]|settim[oaie]|ottav[oaie]|non[oaie]|decim[oaie]|mezz[oa]|metà|paio|dozzin[ae])$/u',

    // Time words — units, parts of the day, days, months, «oggi / domani / ieri» —, an elided article or preposition
    // before one taken with it («all'ora», «quest'anno», «mezz'ora»). Not «ora» alone (it is «now» as often as «hour»),
    // nor «prima», «dopo», «poi», «presto», «tardi»: read on the target side too, they would make almost every line «a
    // line that says a time» for «Поймай число».
    'time_pattern' => '/^(?:\p{L}+\')?(?:oggi|domani|dopodomani|ieri|altroieri|stamattina|stamani|stasera|stanotte|mezzogiorno|mezzanotte|secondi|minut[oi]|giorn[oi]|giornat[ae]|settiman[ae]|weekend|mes[ei]|ann[oi]|mattin[ao]|mattinat[ae]|pomerigg[io]|ser[ae]|serat[ae]|nott[ei]|nottat[ae]|luned[iì]|marted[iì]|mercoled[iì]|gioved[iì]|venerd[iì]|sabat[oi]|domenic(?:a|he)|gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre)$|^(?:ore|\p{L}+\'or[ae])$/u',

    // The units something is COUNTED in — what makes a value an amount and not a date («tre giorni», «una settimana»
    // against «domani», «venerdì»). Parts of the day, weekdays and months are not here.
    'amount_pattern' => '/^(?:\p{L}+\')?(?:secondi|minut[oi]|or[ae]|giorn[oi]|giornat[ae]|settiman[ae]|mes[ei]|ann[oi]|nott[ei]|volt[ae]|grad[oi]|percento|metr[oi]|chilometr[oi]|chil[oi]|chilogramm[oi]|gramm[oi]|milligramm[oi]|litr[oi]|millilitr[oi]|compress[ae]|pastigli[ae]|capsul[ae]|gocc[ei]|goccia|bustin[ae]|cucchiai[no]?|dos[ei])$/u',

    // What carries an amount and is said with it, standing right before it: a preposition, an article, a determiner —
    // «da tre giorni», «tra una settimana», «in due giorni», «nei prossimi tre giorni», «la prossima settimana», «tutti i
    // giorni», «alle 3».
    'amount_prefix' => '/^(?:per|da|tra|fra|in|nel|nella|nei|nelle|negli|entro|dopo|circa|quasi|almeno|ogni|tutti|tutte|questo|questa|questi|queste|prossim[oaie]|scors[oaie]|ultim[oaie]|il|lo|la|i|gli|le|un|uno|una|alle|dalle|verso)$/u',

    // The prompt's STOP LIST (numbers, family, time words, colours, essere / avere / andare) and the plain words a
    // learner knows at any level of the plan: not vocabulary.
    'everyday_words' => [
        'uno', 'una', 'due', 'tre', 'quattro', 'cinque', 'sei', 'sette', 'otto', 'nove', 'dieci', 'undici', 'dodici',
        'venti', 'trenta', 'quaranta', 'cinquanta', 'cento', 'mille', 'primo', 'prima', 'secondo', 'terzo',
        'madre', 'padre', 'mamma', 'papà', 'genitore', 'genitori', 'fratello', 'sorella', 'fratelli', 'sorelle',
        'figlio', 'figlia', 'figli', 'figlie', 'bambino', 'bambina', 'bambini', 'ragazzo', 'ragazza', 'famiglia', 'marito',
        'moglie', 'nonno', 'nonna', 'nonni',
        'giorno', 'giorni', 'settimana', 'settimane', 'mese', 'mesi', 'anno', 'anni', 'oggi', 'domani', 'ieri',
        'mattina', 'pomeriggio', 'sera', 'notte', 'ora', 'ore', 'minuto', 'minuti', 'tempo', 'adesso', 'dopo', 'presto',
        'tardi',
        'rosso', 'rossa', 'blu', 'verde', 'giallo', 'gialla', 'nero', 'nera', 'bianco', 'bianca', 'marrone', 'grigio',
        'grigia', 'arancione', 'rosa', 'viola',
        'essere', 'è', 'sono', 'era', 'avere', 'ho', 'ha', 'hai', 'hanno', 'andare', 'vado', 'va', 'vai', 'fare',
        'faccio', 'fa', 'stare', 'sto', 'sta', 'mangiare', 'bere', 'vedere', 'venire', 'prendere', 'dare', 'volere',
        'voglio', 'sapere', 'pensare', 'dire', 'guardare', 'aiutare', 'lavorare', 'parlare',
        'lavoro', 'casa', 'scuola', 'uomo', 'donna', 'uomini', 'donne', 'persona', 'persone', 'gente', 'amico', 'amica',
        'amici', 'cibo', 'acqua', 'macchina', 'auto', 'stanza', 'porta', 'tavolo', 'nome', 'cosa', 'cose', 'posto',
        'città', 'strada', 'soldi', 'libro', 'telefono', 'cane', 'gatto', 'mano', 'mani', 'testa', 'occhio', 'occhi',
        'problema', 'domanda', 'risposta',
        'buono', 'buona', 'cattivo', 'grande', 'piccolo', 'piccola', 'nuovo', 'nuova', 'vecchio', 'vecchia', 'bello',
        'bella', 'ciao', 'buongiorno', 'grazie',
    ],

    // Ordinary adjectives and quantifiers: the head of a free combination that is no chunk.
    'ordinary_heads' => [
        'grande', 'grandi', 'piccolo', 'piccola', 'piccoli', 'piccole', 'buono', 'buona', 'buoni', 'buone', 'buon',
        'bello', 'bella', 'bel', 'belli', 'belle', 'brutto', 'brutta', 'cattivo', 'cattiva', 'molto', 'molta', 'molti',
        'molte', 'poco', 'poca', 'pochi', 'poche', 'tanto', 'tanta', 'tanti', 'tante', 'altro', 'altra', 'altri', 'altre',
        'diverso', 'diversa', 'diversi', 'diverse', 'importante', 'importanti', 'vero', 'vera', 'veri', 'vere', 'intero',
        'intera', 'qualche', 'alcuni', 'alcune', 'pesante', 'pesanti',
    ],

    // A partner line that says nothing but «we are done» — its whole text, punctuation aside.
    'closers' => [
        'altro', "c'è altro", "nient'altro", 'serve altro', 'le serve altro', 'desidera altro', 'altro ancora', 'è tutto',
        'tutto qui', 'perfetto', 'perfetto grazie', 'benissimo', 'bene', 'molto bene', 'va bene', "d'accordo", 'ok', 'okay',
        'certo', 'certamente', 'grazie', 'grazie mille', 'grazie a lei', 'prego', 'di niente', 'di nulla', 'si figuri',
        'ottimo', 'a presto', 'a domani', 'arrivederci', 'arrivederla', 'buona giornata', 'buona serata', 'ci vediamo',
        'capito', 'tutto chiaro', 'nessun problema', 'volentieri',
    ],

    // The verbs a check names who SAID something with («Che cosa dice il paziente?»), in the forms it uses — «say, tell,
    // answer, mention, want» as en writes them. Not «chiedere» (en writes no «ask»): «Che cosa chiede il medico al
    // paziente?» is a check about the partner's question that names the learner's role, and would read as about the
    // learner.
    'saying_verbs' => [
        'dire', 'dice', 'dicono', 'dico', 'dici', 'detto', 'diceva', 'rispondere', 'risponde', 'rispondono', 'risposto',
        'raccontare', 'racconta', 'raccontano', 'raccontato', 'spiegare', 'spiega', 'spiegano', 'spiegato', 'menziona',
        'menzionano', 'menzionato', 'volere', 'vuole', 'vogliono', 'voleva',
    ],

    // The words a partner names alternatives with («oggi alle tre o domani alle nove»).
    'alternative_words' => ['o', 'oppure'],

    // One sentence that goes on after a comma with «e / o» and a new form of essere / avere or a modal that asks again:
    // two questions in one bubble («Da quanto tempo ha la febbre, e ha preso qualcosa?»), as the English pattern reads
    // «, and did you…?». «, o preferisce domani?» — one question offering two things — is not written.
    'second_question_pattern' => '/,\s*(?:e|o|oppure)\s+(?:ha|hai|ho|abbiamo|avete|hanno|è|sono|siamo|siete|può|puoi|potete|deve|devi|vuole|vuoi|volete|c[\'’]è|ci\s+sono)(?![\p{L}])[^?]*\?/iu',

    // Articles as they stand as a word of their own. An elided «l'», «un'» never does: the validator's tokens keep it
    // with its noun, the judge of the talk spells it out (`contractions`). «uno» is also the number 1 — in speech the
    // number wins («uno» → 1 on both sides).
    'articles' => ['il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una'],

    // WORDS A SENTENCE CANNOT END ON (наряд FIX-3 §7): the articles that never close one (not «uno», «una» — «Ne ho
    // una»), the prepositions and their forms with the article, what a cut-off elision leaves («Ho bisogno dell'» →
    // «dell»), and the conjunctions «e», «ed», «ma», «o» («Ho la febbre e»). A clitic «lo», «la», «le» never ends an
    // Italian sentence either — it stands before its verb.
    'dangling_words' => [
        'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'di', 'a', 'da', 'in', 'con', 'per', 'tra', 'fra',
        'del', 'dello', 'della', 'dei', 'degli', 'delle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle',
        'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle', 'nel', 'nello', 'nella', 'nei', 'negli', 'nelle',
        'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle', 'dell', 'all', 'dall', 'nell', 'sull', 'e', 'ed', 'ma', 'o',
    ],

    // No Italian word may be said twice at the seam of a frame and its filler and stay Italian — every doubled word there
    // is the fatal code's.
    'seam_repeatable_words' => [],

    // The article that changes with the next word's sound is a two-article rule of English (a / an). Italian il / lo /
    // l', un / uno / un' turn on more than a vowel (s + consonant, z, gn, ps, x) — not written: the spec's no-op.
    'article_sound' => [
        'before_vowel' => '', 'before_consonant' => '', 'vowel' => '/(?!)/u', 'consonant' => '/(?!)/u',
        'spelled' => '/(?!)/u', 'exception' => '/(?!)/u',
    ],

    // A clause where a value should stand. Italian drops its subject («ho la febbre»), so a whole sentence is known only
    // by a stressed subject pronoun and a form of essere / avere right after it («io sono allergico») — narrow on purpose:
    // `filler.ungrammatical` is fatal. A filler opening with a subordinator («se la febbre torna», «che sono paziente»)
    // is a clause — the warning `filler.is_clause`, never the fatal code.
    'clause' => [
        'subjects' => ['io', 'tu', 'lui', 'lei', 'noi', 'voi', 'loro'],
        'finite' => ['sono', 'sei', 'è', 'siamo', 'siete', 'ho', 'hai', 'ha', 'abbiamo', 'avete', 'hanno'],
        'contractions' => [],
        'subordinators' => ['se', 'che', 'quando', 'perché', 'mentre', 'benché', 'sebbene', 'finché', 'poiché', 'siccome', 'affinché'],
        'subordinators_before_subject' => [],
    ],

    // «The frame must stand alone» (FRAMES): the clitics a frame leans on with nothing in it they stand for — «Li ho ___»
    // (li = the symptoms), «Ne ho ___». «lo», «la», «le» are left out: spelt like the articles, they cannot be told from
    // them by the word after; «quello» is left out too («Quello che mi serve è ___» stands alone).
    'unresolved_pronouns' => [
        'words' => ['li', 'ne'],
        'frame_initial_subject' => [],
        'existential' => [],
        'determiner_or_number' => [],
        'partitive' => ['di'],
        'be_forms' => ['è', 'sono', 'era', 'erano', 'sarà', "c'è"],
        'determiners' => [
            'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'mio', 'mia', 'miei', 'mie', 'tuo', 'tua', 'suo', 'sua',
            'suoi', 'sue', 'nostro', 'nostra', 'vostro', 'vostra', 'questo', 'questa', 'questi', 'queste', 'quel', 'quei',
            'quegli',
        ],
    ],

    // A form with gender the learner says about themselves (a WARNING while the learner's gender is unknown): a form of
    // essere of the first person («sono», «ero», «sarò», «sarei», «fossi» — an adverb may stand between) and a participle
    // in -ato/-uto/-ito or an adjective of the few a learner says of themselves, masculine or feminine singular — «sono
    // stato/stata», «sono arrivato/a», «mi sono fatto/a male», «sono stanco/a», «sono allergico/a». Plural forms are not
    // the learner's; «sabato» and «subito» end so and are no participle.
    'gendered_past_pattern' => '/(?<![\p{L}])(?:sono|ero|sarò|sarei|fossi)\s+(?:(?:già|appena|mai|ancora|anche|sempre|molto|così|troppo|davvero|proprio|un\s+po[\'’]?)\s+)?(?!(?:sabato|subito)(?![\p{L}]))(\p{L}+(?:at|ut|it)[oa]|(?:fatt|rott|pres|mess|rimast|vist|scritt|chiest|mort|stanc|pront|sicur|content|liber|allergic|nuov)[oa])(?![\p{L}])/u',

    // «The native frame contains NO word that agrees with the slot in gender or number» (FRAMES). The word right before
    // `___` agrees when it is an article, an article with a preposition («del», «nella»), a possessive, a demonstrative,
    // «quale / quanto», a quantifier (not «molto», «poco», «tanto», «tutto»: before an adjective they are invariable
    // adverbs — «Sto molto ___») or one of the adjectives that stand before a noun («prossima ___»); one of the two
    // words after `___` agrees when it is one of `short_forms` — the predicate of a slot that is the subject («___ è
    // incluso?», «___ è rotto?»). No ending is read as an adjective's: every Italian word ends in a vowel, so
    // `suffixes_before_slot` stays empty; verbs agree in number too and are not listed («Quanto costa ___?» is the
    // prompt's own rephrase).
    'agreement' => [
        'words' => [
            'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una',
            'del', 'dello', 'della', 'dei', 'degli', 'delle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle',
            'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle', 'nel', 'nello', 'nella', 'nei', 'negli', 'nelle',
            'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle', 'col', 'coi',
            'mio', 'mia', 'miei', 'mie', 'tuo', 'tua', 'tuoi', 'tue', 'suo', 'sua', 'suoi', 'sue',
            'nostro', 'nostra', 'nostri', 'nostre', 'vostro', 'vostra', 'vostri', 'vostre',
            'questo', 'questa', 'questi', 'queste', 'quello', 'quella', 'quelli', 'quelle', 'quel', 'quei', 'quegli',
            'quale', 'quali', 'quanto', 'quanta', 'quanti', 'quante', 'nessun', 'nessuno', 'nessuna', 'alcuni', 'alcune',
            'molta', 'molti', 'molte', 'poca', 'pochi', 'poche', 'tanta', 'tanti', 'tante', 'tutta', 'tutti', 'tutte',
            'altro', 'altra', 'altri', 'altre',
            'primo', 'primi', 'prime', 'prossimo', 'prossima', 'prossimi', 'prossime', 'ultimo', 'ultima', 'ultimi', 'ultime',
            'stesso', 'stessa', 'stessi', 'stesse', 'nuovo', 'nuova', 'nuovi', 'nuove', 'buon', 'buono', 'buona', 'buoni',
            'buone', 'bel', 'bello', 'bella', 'belli', 'belle',
        ],
        'short_forms' => [
            'aperto', 'aperta', 'aperti', 'aperte', 'chiuso', 'chiusa', 'chiusi', 'chiuse', 'incluso', 'inclusa', 'inclusi',
            'incluse', 'compreso', 'compresa', 'compresi', 'comprese', 'pronto', 'pronta', 'pronti', 'pronte', 'libero',
            'libera', 'liberi', 'libere', 'occupato', 'occupata', 'occupati', 'occupate', 'disponibile', 'disponibili',
            'prenotato', 'prenotata', 'prenotati', 'prenotate', 'pagato', 'pagata', 'pagati', 'pagate', 'necessario',
            'necessaria', 'necessari', 'necessarie', 'obbligatorio', 'obbligatoria', 'obbligatori', 'obbligatorie',
            'permesso', 'permessa', 'permessi', 'permesse', 'consentito', 'consentita', 'consentiti', 'consentite',
            'previsto', 'prevista', 'previsti', 'previste', 'gratuito', 'gratuita', 'gratuiti', 'gratuite', 'valido',
            'valida', 'validi', 'valide', 'rotto', 'rotta', 'rotti', 'rotte', 'guasto', 'guasta', 'guasti', 'guaste',
            'scaduto', 'scaduta', 'scaduti', 'scadute', 'pieno', 'piena', 'pieni', 'piene',
        ],
        'suffixes_before_slot' => [],
        'min_letters' => 4,
        'after_slot_words' => 2,
    ],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а) — what a rescue move of the talk says in the learner's own bubble (en
    // «Sorry?»): the polite «Scusi?», the register the role speaks in (Lei).
    'rescue_line' => 'Scusi?',

    // THE RESCUE KIT (наряд LANG-1b §2): the six lines a learner of this language says when stuck, each with its translation into
    // every learner's language of the plan. Row 1 is `rescue_line`. Said in the learner's voice, filed by (target,
    // gender, voice, line) — `RescueKits`. The same matrix writes every pack: `docs/research/lang-1b/tools/rescue-kit.py`.
    'rescue' => [
        ['target' => 'Scusi?', 'native' => ['ru' => 'Простите?', 'uk' => 'Перепрошую?', 'be' => 'Прабачце?', 'pl' => 'Słucham?', 'ro' => 'Poftim?', 'es' => '¿Perdón?', 'it' => 'Scusi?', 'de' => 'Wie bitte?', 'fr' => "Pardon\u{00A0}?"]],
        ['target' => 'Può parlare più lentamente, per favore?', 'native' => ['ru' => 'Можно помедленнее, пожалуйста?', 'uk' => 'Можна повільніше, будь ласка?', 'be' => 'Можна павольней, калі ласка?', 'pl' => 'Proszę mówić trochę wolniej.', 'ro' => 'Puteți vorbi mai rar, vă rog?', 'es' => '¿Puede hablar más despacio, por favor?', 'it' => 'Può parlare più lentamente, per favore?', 'de' => 'Können Sie bitte langsamer sprechen?', 'fr' => "Vous pouvez parler plus lentement, s'il vous plaît\u{00A0}?"]],
        ['target' => 'Non capisco.', 'native' => ['ru' => 'Я не понимаю.', 'uk' => 'Я не розумію.', 'be' => 'Я не разумею.', 'pl' => 'Nie rozumiem.', 'ro' => 'Nu înțeleg.', 'es' => 'No entiendo.', 'it' => 'Non capisco.', 'de' => 'Ich verstehe nicht.', 'fr' => 'Je ne comprends pas.']],
        ['target' => 'Un momento.', 'native' => ['ru' => 'Одну минуту.', 'uk' => 'Хвилинку.', 'be' => 'Хвілінку.', 'pl' => 'Chwileczkę.', 'ro' => 'Un moment.', 'es' => 'Un momento.', 'it' => 'Un momento.', 'de' => 'Einen Moment.', 'fr' => 'Un instant.']],
        ['target' => 'Me lo può scrivere?', 'native' => ['ru' => 'Можете это записать?', 'uk' => 'Можете це записати?', 'be' => 'Можаце гэта запісаць?', 'pl' => 'Proszę mi to zapisać.', 'ro' => 'Îmi puteți scrie asta?', 'es' => '¿Me lo puede escribir?', 'it' => 'Me lo può scrivere?', 'de' => 'Können Sie mir das aufschreiben?', 'fr' => "Vous pouvez me l'écrire\u{00A0}?"]],
        ['target' => 'Grazie.', 'native' => ['ru' => 'Спасибо.', 'uk' => 'Дякую.', 'be' => 'Дзякуй.', 'pl' => 'Dziękuję.', 'ro' => 'Mulțumesc.', 'es' => 'Gracias.', 'it' => 'Grazie.', 'de' => 'Danke.', 'fr' => 'Merci.']],
    ],

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9) — the same line as every pack's, in Italian, in the role's Lei (en «I
    // see. Please go on.»).
    'neutral_reply' => 'Capisco. Continui, per favore.',

    // THE FORMS OF ONE WORD (наряд BACK-TAILS-2 §2), in the canonical form of the comparison of speech: the irregular
    // present, past and future forms of essere, avere, the modals, andare, fare, stare, venire, dire, sapere, and the
    // object pronouns of the first and second person — «ho» and «ha» are one verb, «mi» is «io». «sei» is left out: it is
    // read as the number 6 before this map is. No regular endings are written (`inflection_rules` is empty): every Italian
    // word ends in a vowel, and taking one off meets words that are not one («casa» — «caso», «porta» — «porto»).
    'irregular_forms' => [
        'sono' => 'essere', 'è' => 'essere', 'siamo' => 'essere', 'siete' => 'essere', 'ero' => 'essere', 'eri' => 'essere',
        'era' => 'essere', 'eravamo' => 'essere', 'erano' => 'essere', 'sarò' => 'essere', 'sarà' => 'essere',
        'saremo' => 'essere', 'saranno' => 'essere', 'sarei' => 'essere', 'sarebbe' => 'essere', 'stato' => 'essere',
        'stata' => 'essere', 'stati' => 'essere', 'sia' => 'essere', 'siano' => 'essere',
        'ho' => 'avere', 'hai' => 'avere', 'ha' => 'avere', 'abbiamo' => 'avere', 'avete' => 'avere', 'hanno' => 'avere',
        'avevo' => 'avere', 'avevi' => 'avere', 'aveva' => 'avere', 'avevamo' => 'avere', 'avevano' => 'avere',
        'avuto' => 'avere', 'avrò' => 'avere', 'avrà' => 'avere', 'avrei' => 'avere', 'avrebbe' => 'avere', 'abbia' => 'avere',
        'posso' => 'potere', 'puoi' => 'potere', 'può' => 'potere', 'possiamo' => 'potere', 'potete' => 'potere',
        'possono' => 'potere', 'potrei' => 'potere', 'potrebbe' => 'potere', 'potrà' => 'potere',
        'voglio' => 'volere', 'vuoi' => 'volere', 'vuole' => 'volere', 'vogliamo' => 'volere', 'volete' => 'volere',
        'vogliono' => 'volere', 'vorrei' => 'volere', 'vorrebbe' => 'volere', 'vorremmo' => 'volere', 'voluto' => 'volere',
        'devo' => 'dovere', 'devi' => 'dovere', 'deve' => 'dovere', 'dobbiamo' => 'dovere', 'dovete' => 'dovere',
        'devono' => 'dovere', 'dovrei' => 'dovere', 'dovrebbe' => 'dovere', 'dovrà' => 'dovere',
        'vado' => 'andare', 'vai' => 'andare', 'va' => 'andare', 'andiamo' => 'andare', 'andate' => 'andare',
        'vanno' => 'andare', 'andato' => 'andare', 'andata' => 'andare', 'andrò' => 'andare', 'andrà' => 'andare',
        'faccio' => 'fare', 'fai' => 'fare', 'fa' => 'fare', 'facciamo' => 'fare', 'fate' => 'fare', 'fanno' => 'fare',
        'fatto' => 'fare', 'fatta' => 'fare', 'farò' => 'fare', 'farà' => 'fare',
        'sto' => 'stare', 'stai' => 'stare', 'sta' => 'stare', 'stiamo' => 'stare', 'stanno' => 'stare',
        'vengo' => 'venire', 'vieni' => 'venire', 'viene' => 'venire', 'veniamo' => 'venire', 'venite' => 'venire',
        'vengono' => 'venire', 'venuto' => 'venire', 'venuta' => 'venire', 'verrò' => 'venire', 'verrà' => 'venire',
        'dico' => 'dire', 'dici' => 'dire', 'dice' => 'dire', 'diciamo' => 'dire', 'dite' => 'dire', 'dicono' => 'dire',
        'detto' => 'dire',
        'so' => 'sapere', 'sai' => 'sapere', 'sa' => 'sapere', 'sappiamo' => 'sapere', 'sapete' => 'sapere',
        'sanno' => 'sapere', 'saputo' => 'sapere',
        'mi' => 'io', 'me' => 'io', 'ti' => 'tu', 'te' => 'tu',
    ],
    'inflection_rules' => [],

    // THE FIRST AND THE SECOND PERSON, SWAPPED (наряд BACK-TAILS-2 §9): what the learner says of themselves comes back in
    // the role's Lei — «Mio figlio ha la febbre» is «Suo figlio ha la febbre». Only Lei: the role speaks so (a talk in «tu»
    // is read without the swap, as before).
    'person_swap' => [
        'io' => 'lei', 'me' => 'lei', 'mio' => 'suo', 'mia' => 'sua', 'miei' => 'suoi', 'mie' => 'sue',
        'lei' => 'io', 'suo' => 'mio', 'sua' => 'mia', 'suoi' => 'miei', 'sue' => 'mie',
    ],

    // ─── THE JUDGE OF THE TALK'S CONSTRUCTIONS (наряд FIX-4 §2, LANG-1 §1) ───────────────────────────────────────────────

    // AN ELISION IS ITS TWO WORDS: a key ending with an apostrophe is a prefix — «l'ospedale» is «lo ospedale», «c'è» is
    // «ci è», «dell'ospedale» «dello ospedale», «un'ora» «una ora», «d'accordo» «di accordo», «anch'io» «anche io». «qual'» is
    // the frequent misspelling of «qual è» and reads as the right one. No whole-word entry is needed.
    'contractions' => [
        "l'" => 'lo', "un'" => 'una', "dell'" => 'dello', "all'" => 'allo', "dall'" => 'dallo', "nell'" => 'nello',
        "sull'" => 'sullo', "c'" => 'ci', "d'" => 'di', "m'" => 'mi', "t'" => 'ti', "s'" => 'si', "v'" => 'vi',
        "n'" => 'ne', "quest'" => 'questo', "quell'" => 'quello', "dov'" => 'dove', "com'" => 'come', "cos'" => 'cosa',
        "quant'" => 'quanto', "qual'" => 'qual', "nessun'" => 'nessuna', "tutt'" => 'tutto', "senz'" => 'senza',
        "mezz'" => 'mezza', "sant'" => 'santo', "bell'" => 'bello', "anch'" => 'anche', "ch'" => 'che',
    ],
    'contractions_before' => [],

    // THE WORDS A MOVE MAY OPEN WITH before its construction: «Sì, ho la febbre», «Va bene, domani alle 3», «Mi scusi,
    // dov'è l'uscita?». Not «si» without its accent: «Si può pagare con la carta?» is no «Può pagare ___?».
    'intro_words' => [
        'sì', 'no', 'ok', 'okay', 'va bene', 'bene', 'allora', 'beh', 'ecco', 'grazie', 'grazie mille', 'buongiorno',
        'buonasera', 'salve', 'ciao', 'scusi', 'mi scusi', 'scusa', 'scusate', 'prego', 'certo', 'certamente', 'esatto',
        'perfetto', "d'accordo", 'ah', 'oh', 'eh', 'ehm', 'uhm', 'mah', 'dunque', 'senta', 'senti', 'guardi', 'per favore',
        'e',
    ],

    // THE WORDS A NEW CLAUSE OPENS WITH (наряд FIX-4b §1): «Ho la febbre e li ho da tre giorni», «…, allora vorrei un
    // appuntamento».
    'clause_starters' => ['e', 'ed', 'ma', 'però', 'quindi', 'allora', 'poi', 'o', 'oppure'],

    // A CONSTRUCTION SAID IN THE NEGATIVE IS THE SAME CONSTRUCTION (DECISIONS п. 395; LANG-1 §1): «non» stands before the
    // verb and its clitics wherever the construction begins — «Non ho la febbre» says «Ho ___» — so it is free anywhere.
    'negation' => ['words' => ['non'], 'after' => null, 'do_support' => []],

    // No partitive a determiner lets the frame leave out — the spec's no-op.
    'partitive' => [],

    // FREQUENT AND DISTINCTIVE (наряд LANG-1 §5, `common_words`) — «частые и отличительные», not «30 самых частых слов» of
    // the order: the guard of the role's translation reads a learner's grey line against EVERY pack in the same letters,
    // and a word that is also an ordinary word of the learner's own language, sitting in the Italian list only, counts as
    // Italian inside that learner's line — two such words refuse an honest translation (a probe refused «Для записи к
    // врачу приходите до двенадцати» for ru because a uk list held «для» and «до»). So every frequent Italian word another
    // Latin language of the plan (en pl ro es de fr) spells alike is left out, however rare it is there: «di», «e» (ro
    // «is», es «and»), «a», «la», «il», «un», «non», «per», «con», «da», «mi», «ma», «se», «si», «come», «dove» (en),
    // «cosa», «solo», «prima», «una», «del» (es), «lei», «noi», «voi», «mai», «ora», «mia», «ancora» (ro — «ancora» is «the
    // anchor»), «qui», «lui», «ne», «va», «perché» (fr — «perché» is «perched»), «dal» (pl «dal», «the distance»), «loro»
    // (es «parrot»), «pure» (en), «venga» (es), «alle» (de). What is left is written: one run of letters each, lower
    // case, accents as written. A short Italian line holds one or two of them — the words of a request, a service desk and
    // a clinic («vorrei», «bisogno», «mi dica», «per favore», «qual è») are here for that.
    'common_words' => [
        'che', 'è', 'sono', 'ho', 'io', 'questo', 'questa', 'questi', 'della', 'dei', 'nel', 'nella', 'bene', 'sì',
        'anche', 'più', 'grazie', 'prego', 'favore', 'buongiorno', 'gli', 'suo', 'sua', 'mio', 'tutto', 'molto', 'quando',
        'quanto', 'quanti', 'qual', 'già', 'adesso', 'dopo', 'oggi', 'domani', 'sempre', 'niente', 'qualcosa', 'può',
        'posso', 'deve', 'devo', 'vuole', 'vorrei', 'dica', 'bisogno', 'abbiamo', 'avete', 'hanno', 'ecco', 'allora',
    ],

    // THE TITLE OF A TALK (наряд LANG-1 §6): the roles as written, in no case — «Conversazione: receptionist e medico»,
    // «ed» before a role that opens with «e» («medico ed endocrinologo»), the role's first letter lower-cased unless it is
    // an acronym.
    'talk_title_template' => [
        'title' => 'Conversazione: {roles}',
        'and' => 'e',
        'anyone' => 'Conversazione',
        'lower_first' => true,
        'and_before' => ['/^e/iu' => 'ed'],
    ],
];
