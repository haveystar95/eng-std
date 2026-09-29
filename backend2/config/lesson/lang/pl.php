<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · pl — what the day's checks, the judge of the talk and the speech comparison read of Polish
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b), keys by `docs/research/lang-1/pack-keys.md` (наряд LANG-1). Polish is BOTH sides of a
| plan: the language taught (ru→pl — what the learner says and hears, the frames, the talk) and the learner's own (pl→en —
| the readings, the native frames, the listening questions, the grey translation of the role). So every key of both sides
| is written; a rule that does not apply to Polish is an explicit no-op, never null (null would switch its rule off on
| every day of the pair).
|
| Polish has no articles, no contractions and no elisions; it drops the subject pronoun, marks a question with «czy» or
| the mark alone, and says the negation with «nie» anywhere before the verb. Its numerals and adjectives inflect for case,
| so every list of WORDS names the forms a line actually says, and every PATTERN names the stems.
|
| Words are compared as `LanguagePack::normal()` gives them — folded (NFC; Polish letters are not changed by the fold),
| lower case. Every list is a counter's reading of a rule, not the rule: the codes built on them are heuristics.
|
| Written deliberately EMPTY (the no-op): `irregular_forms`, `inflection_rules`, `person_swap` — see their comment.
*/
return [
    // A reading of the target in the learner's own letters (`pronunciation.script` — a WARNING): the POLISH alphabet — 32
    // letters, «ą ć ę ł ń ó ś ź ż» among them and no «q v x» —, digits, punctuation (the slot's «___», the hyphen of
    // «aj-di», the apostrophe), whitespace and the stress mark U+0301. Strict on purpose: «wizit» with a «v», or an IPA
    // «ə», reads and is only untidy — flagged, never failed; the fatal check is `script_letters`.
    'script' => '/^[aąbcćdeęfghijklłmnńoóprsśtuwyzźżAĄBCĆDEĘFGHIJKLŁMNŃOÓPRSŚTUWYZŹŻ\p{N}\p{P}\s\x{0301}]*$/u',

    // One LETTER of a reading, matched alone (`pronunciation.foreign_script` — FATAL): the Latin writing, exactly the
    // string every Latin pack writes — two packs are neighbours for the guard of the role's translation exactly when this
    // string is the same (pack-keys §3.2). Not the Polish alphabet: a strict one here would fail a valid day on one «v».
    // The Georgian «იან» of the scouting day pl→en («maj nejm iz იან kowalski») is foreign, and fatal, rightly.
    'script_letters' => '/^[\p{Latin}]$/u',

    // The marks a sentence ends with, and what each says. The Polish quotes „…” close a sentence as the English ones do
    // (SentenceEnds reads them).
    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence (наряд CHECK-1) — as written in a text, letter case aside: «o godz. 15:00»,
    // «ul. Zielona 12», «np. dowód osobisty», «za 10 min.», «u dr. Nowaka» (the genitive «doktora» takes the dot, the
    // nominative «dr» does not), «pok. 12», «gab. 5», «na II p.», «2 szt.». None of them is an ordinary Polish word with a
    // dot after it. Left out on purpose: «ok.» (około) — «OK.» is also the answer of a line and would stop ending it;
    // «lek.» — «lek» is a word («Proszę wziąć ten lek.»); «im.» (imienia, «szpital im. Kopernika») — «im» is a pronoun
    // that ends a sentence («Proszę powiedzieć im.»); the abbreviations that end with their word's last letter in the
    // nominative — «dr», «nr», «mgr», «zł», «gr», «mln» — take no dot there, and are read as they stand.
    'abbreviations' => [
        'np.', 'm.in.', 'tj.', 'tzn.', 'tzw.', 'itd.', 'itp.', 'godz.', 'min.', 'ul.', 'al.', 'pl.', 'tel.', 'wew.',
        'prof.', 'dr.', 'nr.', 'mgr.', 'inż.', 'p.', 'pok.', 'gab.', 'szt.', 'r.', 'tys.',
        'pon.', 'wt.', 'śr.', 'czw.', 'pt.', 'sob.', 'niedz.', 'św.', 'ds.',
    ],

    // Polish asks with «czy» or with the mark alone, never by inverting a subject and an auxiliary: a question is only its
    // mark (pack-keys §3.5 — the no-op for pl).
    'question_word_order' => ['auxiliaries' => [], 'subjects' => []],

    // Words that carry no content of their own — prepositions, conjunctions and particles, pronouns in their cases (the
    // polite «pan / pani / państwo» among them: «Czy Pan ma…» names nobody), possessives and demonstratives, question
    // words, the forms of «być» and «mieć» and of the modals, «można / trzeba», «tak / nie / proszę / dziękuję /
    // przepraszam».
    'function_words' => [
        // prepositions
        'w', 'we', 'na', 'z', 'ze', 'do', 'od', 'ode', 'o', 'po', 'za', 'przy', 'przez', 'dla', 'bez', 'pod', 'nad',
        'przed', 'między', 'u', 'ku', 'około', 'obok', 'według', 'oprócz', 'podczas', 'zamiast', 'wśród', 'poza',
        'mimo', 'wokół', 'naprzeciwko', 'spod', 'zza', 'przeciw',
        // conjunctions and particles
        'i', 'a', 'ale', 'lecz', 'lub', 'albo', 'czy', 'że', 'żeby', 'aby', 'bo', 'ponieważ', 'gdyż', 'jeśli', 'jeżeli',
        'gdy', 'gdyby', 'kiedy', 'ani', 'oraz', 'więc', 'czyli', 'to', 'jak', 'jako', 'niż', 'też', 'także', 'również',
        'nie', 'ni', 'już', 'jeszcze', 'tylko', 'bardzo', 'aż', 'nawet', 'właśnie', 'chyba', 'jednak', 'niech', 'tu',
        'tutaj', 'tam', 'by', 'bym', 'byś', 'byśmy', 'no', 'oh', 'och', 'tak',
        // pronouns and their cases
        'ja', 'ty', 'on', 'ona', 'ono', 'my', 'wy', 'oni', 'one', 'mnie', 'mi', 'mną', 'ciebie', 'cię', 'ci', 'tobie',
        'tobą', 'go', 'jego', 'niego', 'mu', 'jemu', 'niemu', 'nim', 'jej', 'niej', 'ją', 'nią', 'nas', 'nam', 'nami',
        'was', 'wam', 'wami', 'ich', 'nich', 'im', 'nimi', 'je', 'się', 'sobie', 'siebie', 'sobą',
        'pan', 'pani', 'pana', 'panu', 'panią', 'panem', 'państwo', 'państwa',
        'coś', 'czegoś', 'czymś', 'ktoś', 'kogoś', 'komuś', 'nic', 'niczego', 'nikt', 'nikogo', 'nikomu',
        'wszystko', 'wszystkiego', 'wszyscy', 'wszystkie', 'wszystkich', 'wszystkim',
        'każdy', 'każda', 'każde', 'każdego', 'każdej', 'każdym', 'sam', 'sama', 'samo', 'sami', 'same',
        'jakiś', 'jakaś', 'jakieś', 'jakiegoś', 'jakiejś', 'żaden', 'żadna', 'żadne', 'żadnego', 'żadnej',
        // possessives and demonstratives
        'mój', 'moja', 'moje', 'moi', 'mojego', 'mojej', 'moim', 'moją', 'moich', 'twój', 'twoja', 'twoje', 'twoi',
        'twojego', 'twojej', 'twoim', 'twoją', 'twoich', 'swój', 'swoja', 'swoje', 'swojego', 'swojej', 'swoim',
        'swoją', 'swoich', 'nasz', 'nasza', 'nasze', 'nasi', 'naszego', 'naszej', 'naszym', 'naszą', 'naszych',
        'wasz', 'wasza', 'wasze', 'waszego', 'waszej', 'waszym', 'waszą', 'waszych',
        'ten', 'ta', 'te', 'tego', 'tej', 'temu', 'tym', 'tę', 'tą', 'tych', 'tymi', 'tamten', 'tamta', 'tamto',
        // question words
        'co', 'kto', 'gdzie', 'jaki', 'jaka', 'jakie', 'jakiego', 'jakiej', 'jakim', 'jaką', 'jakich', 'który',
        'która', 'które', 'którą', 'którego', 'której', 'którym', 'których', 'dlaczego', 'czemu', 'ile', 'czego',
        'kogo', 'czym', 'kim', 'komu', 'skąd', 'dokąd',
        // «być», «mieć», the modals
        'być', 'jestem', 'jesteś', 'jest', 'jesteśmy', 'jesteście', 'są', 'był', 'była', 'było', 'byli', 'były',
        'byłem', 'byłam', 'byłeś', 'byłaś', 'byliśmy', 'będę', 'będziesz', 'będzie', 'będziemy', 'będziecie', 'będą',
        'mieć', 'mam', 'masz', 'ma', 'mamy', 'macie', 'mają', 'miał', 'miała', 'miałem', 'miałam', 'mieli',
        'można', 'trzeba', 'mogę', 'może', 'możemy', 'możesz', 'możecie', 'mogą', 'mógł', 'mogła',
        'muszę', 'musi', 'musisz', 'musimy', 'musicie', 'muszą',
        // the small words of a reply
        'proszę', 'dziękuję', 'dzięki', 'przepraszam', 'okej', 'ok',
    ],

    // THE WORDS A RECOGNISER EATS (наряд FIX-2, п. 2) — prepositions, «i / a», the question particle «czy» (the «do» of
    // «Do you have…»), «by» and the forms of «być»: left out of BOTH sides when a line the learner is LOOKING AT is
    // compared with what was said. Narrower than `function_words`: «nie», «co», «jaki», «proszę» change the sentence and are
    // not here, nor «bez» (it negates what follows: «kawa bez cukru» is no «kawa cukru»), nor «się» («umówić» and «umówić
    // się» are two verbs). Polish has no articles, so the list opens with prepositions.
    'unstressed_words' => [
        'w', 'we', 'na', 'z', 'ze', 'u', 'do', 'od', 'ode', 'o', 'po', 'za', 'przy', 'przez', 'dla', 'pod', 'nad',
        'przed', 'między', 'ku',
        'i', 'a', 'czy', 'by',
        'być', 'jestem', 'jesteś', 'jest', 'jesteśmy', 'jesteście', 'są', 'był', 'była', 'było', 'byli', 'były',
        'będę', 'będziesz', 'będzie', 'będziemy', 'będziecie', 'będą',
    ],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER (наряд FIX-2, п. 2; LANG-1 §4): 0–20, the tens, the hundreds (each one
    // word in Polish), the scales and their plural forms. Only the bare NOMINATIVE forms, as the Russian pack writes its
    // own: an inflected form («trzech», «pięciu», «dwóch») and the ordinals of the clock («o piętnastej») are not in the
    // list (Вопросы Дену: whether the phone's recogniser writes «od 3 dni» / «o 15» for them — then they belong here, on
    // both sides alike). Polish joins the parts of a number with no word — «sto dwadzieścia pięć» is 125, «dwa tysiące
    // dwadzieścia sześć» 2026, by the kernel's rule of fitting (SpokenNumbers) — so both lists of joiners are empty.
    'number_words' => [
        'zero' => '0', 'jeden' => '1', 'jedna' => '1', 'jedno' => '1', 'dwa' => '2', 'dwie' => '2', 'trzy' => '3',
        'cztery' => '4', 'pięć' => '5', 'sześć' => '6', 'siedem' => '7', 'osiem' => '8', 'dziewięć' => '9',
        'dziesięć' => '10', 'jedenaście' => '11', 'dwanaście' => '12', 'trzynaście' => '13', 'czternaście' => '14',
        'piętnaście' => '15', 'szesnaście' => '16', 'siedemnaście' => '17', 'osiemnaście' => '18',
        'dziewiętnaście' => '19', 'dwadzieścia' => '20', 'trzydzieści' => '30', 'czterdzieści' => '40',
        'pięćdziesiąt' => '50', 'sześćdziesiąt' => '60', 'siedemdziesiąt' => '70', 'osiemdziesiąt' => '80',
        'dziewięćdziesiąt' => '90', 'sto' => '100', 'dwieście' => '200', 'trzysta' => '300', 'czterysta' => '400',
        'pięćset' => '500', 'sześćset' => '600', 'siedemset' => '700', 'osiemset' => '800', 'dziewięćset' => '900',
        'tysiąc' => '1000', 'tysiące' => '1000', 'tysięcy' => '1000',
        'milion' => '1000000', 'miliony' => '1000000', 'milionów' => '1000000',
    ],
    'number_joiners' => [],
    'number_tens_joiners' => [],

    // Two forms of one word in an inflected language, as the Russian pack reads its own: both at least four letters,
    // sharing all but the last two letters of the shorter («gorączka» — «gorączkę», «lekarz» — «lekarza», «wizyta» —
    // «wizytę»). One letter is no content word (every one-letter Polish word is a preposition or a conjunction).
    'word_forms' => ['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2],

    // A number: a digit anywhere in the word, or a numeral in any of its cases — cardinals, the collective «dwoje»,
    // ordinals («o piętnastej», «drugi»), «pół / półtora / połowa / ćwierć», «raz / razy». Where a stem is shared with
    // another word the forms are named one by one: «jednak» (however) is no «jedn…», «czwartek» and «piątek» (weekdays)
    // are no «czwart…», «piąt…», «trzeba» is no «trze…», «ośmielić» no «ośmi…».
    'number_pattern' => '/\d|^(?:zer(?:o|a|em|ze|u)|jeden|jedn(?:a|o|ą|ej|ego|emu|ym|ymi|ych|e|i)|jedena(?:ście|st\p{L}*)'
        .'|dwa|dwie|dwóch|dwu|dwom|dwoma|dwoje|dwojga|dwiema|dwana(?:ście|st\p{L}*)|dwunast\p{L}*|dwadzieścia|dwudziest\p{L}*'
        .'|dwieście|dwustu|drug(?:i|a|ie|iego|iej|iemu|im|imi|ą|ich)'
        .'|trzy|trzech|trzem|trzema|troje|trojga|trzyna(?:ście|st\p{L}*)|trzydzie(?:ści|st\p{L}*)|trzysta|trzystu'
        .'|trzec(?:i|ia|ie|iego|iej|iemu|im|imi|ią|ich)'
        .'|czter\p{L}*|czwart(?:y|a|e|ego|ej|emu|ym|ymi|ą|ych)'
        .'|pięć(?:dziesiąt|set)?|pięci(?:u|oma|oro|uset)|pięćdziesi\p{L}*|piętna(?:ście|st\p{L}*)'
        .'|piąt(?:y|a|e|ego|ej|emu|ym|ymi|ą|ych)'
        .'|sześć(?:dziesiąt|set)?|sześci(?:u|oma|oro|uset)|sześćdziesi\p{L}*|szesna(?:ście|st\p{L}*)|szóst\p{L}*'
        .'|siedem(?:naście|dziesiąt|set)?|siedemnast\p{L}*|siedemdziesi\p{L}*|siedmi(?:u|oma|oro|uset)|siódm\p{L}*'
        .'|osiem(?:naście|dziesiąt|set)?|osiemnast\p{L}*|osiemdziesi\p{L}*|ośmi(?:u|oma|oro|uset)|ósm\p{L}*'
        .'|dziewięć(?:dziesiąt|set)?|dziewięci(?:u|oma|oro|uset)|dziewięćdziesi\p{L}*|dziewiętna(?:ście|st\p{L}*)|dziewiąt\p{L}*'
        .'|dziesięć|dziesięci(?:u|oma|oro)|dziesiąt(?:y|a|e|ego|ej|emu|ym|ymi|ą|ych|k\p{L}*)'
        .'|sto|stu|setk\p{L}*|setn\p{L}*|tysi[ąę]c\p{L}*|milion\p{L}*|miliard\p{L}*'
        .'|pół|półtor(?:a|ej)|połow\p{L}*|ćwierć|raz|razy|pierwsz\p{L}*)$/u',

    // Time and duration words — units (the abbreviations «godz.», «min.» read as the words «godz», «min»), parts of the
    // day in every case a line says them («przed południem», «nad ranem», «przed północą»), «dziennie» of a dose («trzy
    // razy dziennie»), weekdays, months, «wczoraj / dziś / jutro», «wcześniej / później», «temu». Written for the learner's
    // side (`listening.distractor_not_filler`) and read the same way for the taught one — which line of the day «says a
    // number or a time» (34-7 takes the LONGEST such line, and no card when its translation names no amount), so a word
    // that is seldom a value («chwila», the seasons, «codziennie») is not here. The forms of a month or a weekday whose stem
    // is another word are named one by one («środa», not «środek»; «czerwiec», not «czerwony»; «marzec», not «marzyć»).
    'time_pattern' => '/^(?:sekund\p{L}*|minut\p{L}*|min|godzin\p{L}*|godz|kwadrans\p{L}*'
        .'|doba|doby|dobę|dobie|dobą|dób'
        .'|dzień|dnia|dni|dniu|dniem|dniami|dniach|tydzień|tygodni\p{L}*|miesiąc\p{L}*|miesięc\p{L}*'
        .'|rok|roku|rokiem|lat|lata|latach|latami|dziennie'
        .'|rano|ranek|ranka|rankiem|ranem|przedpołudni\p{L}*|południe|południa|południu|południem|popołudni\p{L}*'
        .'|wieczór|wieczor\p{L}*|noc|nocy|nocą|nocami|nocach|północ|północy|północą'
        .'|poniedział\p{L}*|wtor(?:ek|ku|ki|kiem|kach|ków)|środ(?:a|y|ę|zie|ą)|czwart(?:ek|ku|ki|kiem|kach|ków)'
        .'|piąt(?:ek|ku|ki|kiem|kach|ków)|sobot\p{L}*|niedziel\p{L}*|weekend\p{L}*'
        .'|styczeń|styczni\p{L}*|lut(?:y|ego|ym)|marzec|marca|marcu|kwiecień|kwietni\p{L}*|maj|maja|maju'
        .'|czerwiec|czerwca|czerwcu|lipiec|lipca|lipcu|sierpień|sierpni\p{L}*|wrzesień|wrześni\p{L}*'
        .'|październik\p{L}*|listopad\p{L}*|grudzień|grudni\p{L}*'
        .'|wczoraj|wczorajsz\p{L}*|przedwczoraj|dziś|dzisiaj|dzisiejsz\p{L}*|jutro|jutra|jutrzejsz\p{L}*|pojutrze'
        .'|teraz|zaraz|wkrótce|niedługo|niedawno|dawno|wcześniej|wcześnie|później|późno|potem|temu)$/u',

    // The units something is COUNTED in — what makes a value an amount and not a date («za tydzień», «dwa dni» against
    // «dziś», «w piątek»). Read by «Поймай число» (34-7) for its options, and only of a word that is a number or a time
    // word already (NumberValues builds its runs of those): the time units here decide, the rest is for completeness.
    // Parts of the day, weekdays and months are not here.
    'amount_pattern' => '/^(?:sekund\p{L}*|minut\p{L}*|min|godzin\p{L}*|godz|kwadrans\p{L}*|doba|doby|dobę|dobie|dobą|dób'
        .'|dzień|dnia|dni|dniu|dniem|dniami|dniach|tydzień|tygodni\p{L}*|miesiąc\p{L}*|miesięc\p{L}*'
        .'|rok|roku|rokiem|lat|lata|latach|latami|raz|razy|stopień|stopnia|stopnie|stopni|stopniach|stopniami'
        .'|procent\p{L}*|metr\p{L}*|kilometr\p{L}*|kilogram\p{L}*|kilo|gram\p{L}*|litr\p{L}*|miligram\p{L}*|mililitr\p{L}*'
        .'|tabletk\p{L}*|kropl\p{L}*|kapsułk\p{L}*|dawk\p{L}*)$/u',

    // What carries an amount and is said WITH it — prepositions and determiners right before it («za tydzień», «przez
    // trzy dni», «w tym tygodniu», «co tydzień»). Read only to the left, and only next to the value.
    'amount_prefix' => '/^(?:na|w|we|za|przez|do|po|od|ode|o|około|ok|przed|co|ten|ta|to|tę|tej|tego|tym|te|tych'
        .'|zeszł\p{L}*|ubiegł\p{L}*|przyszł\p{L}*|następn\p{L}*|najbliższ\p{L}*|każd\p{L}*|cał\p{L}*|prawie|ponad|mniej|więcej'
        .'|niecał\p{L}*)$/u',

    // The prompt's STOP LIST (numbers, family, time words, colours, być / mieć / iść) and plain words a learner knows at
    // any level of the plan: not vocabulary. The forms a vocabulary item is written in — the nominative, the infinitive,
    // the first person.
    'everyday_words' => [
        'jeden', 'jedna', 'jedno', 'dwa', 'dwie', 'trzy', 'cztery', 'pięć', 'sześć', 'siedem', 'osiem', 'dziewięć',
        'dziesięć', 'jedenaście', 'dwanaście', 'dwadzieścia', 'trzydzieści', 'czterdzieści', 'pięćdziesiąt', 'sto',
        'tysiąc', 'pierwszy', 'pierwsza', 'drugi', 'druga', 'trzeci', 'trzecia',
        'mama', 'matka', 'tata', 'ojciec', 'rodzice', 'brat', 'siostra', 'syn', 'córka', 'dziecko', 'dzieci', 'rodzina',
        'mąż', 'żona', 'babcia', 'dziadek',
        'dzień', 'dni', 'tydzień', 'miesiąc', 'rok', 'lata', 'dziś', 'dzisiaj', 'jutro', 'wczoraj', 'rano', 'wieczór',
        'wieczorem', 'noc', 'czas', 'godzina', 'minuta', 'teraz', 'później', 'wkrótce',
        'czerwony', 'niebieski', 'zielony', 'żółty', 'czarny', 'biały', 'brązowy', 'szary', 'pomarańczowy', 'różowy',
        'fioletowy',
        'być', 'jest', 'jestem', 'są', 'był', 'była', 'mieć', 'mam', 'ma', 'mają', 'miał', 'miała', 'iść', 'idę', 'idzie',
        'jechać', 'jadę',
        'praca', 'dom', 'szkoła', 'mężczyzna', 'kobieta', 'ludzie', 'człowiek', 'przyjaciel', 'kolega', 'jedzenie',
        'woda', 'samochód', 'pokój', 'drzwi', 'stół', 'imię', 'rzecz', 'dobry', 'dobrze', 'zły', 'duży', 'mały', 'nowy',
        'stary', 'cześć', 'jeść', 'pić', 'widzieć', 'przyjść', 'robić', 'zrobić', 'brać', 'dać', 'chcieć', 'chcę',
        'lubić', 'wiedzieć', 'myśleć', 'mówić', 'powiedzieć', 'patrzeć', 'potrzebować', 'pomoc', 'pomóc', 'miejsce',
        'miasto', 'ulica', 'pieniądze', 'książka', 'telefon', 'pies', 'kot', 'ręka', 'głowa', 'oko', 'oczy', 'problem',
        'pytanie', 'odpowiedź',
    ],

    // Ordinary adjectives and quantifiers: the head of a free combination («duży pokój») that is no chunk — in the forms a
    // two-word chunk opens with. «wolny» is not here: «wolny termin» is the phrase of the appointment desk.
    'ordinary_heads' => [
        'duży', 'duża', 'duże', 'mały', 'mała', 'małe', 'dobry', 'dobra', 'dobre', 'zły', 'zła', 'złe', 'ładny', 'ładna',
        'ładne', 'fajny', 'fajna', 'fajne', 'świetny', 'świetna', 'świetne', 'ciężki', 'ciężka', 'ciężkie', 'dużo',
        'wiele', 'mało', 'kilka', 'trochę', 'niektóre', 'inny', 'inna', 'inne', 'różny', 'różna', 'różne', 'ważny',
        'ważna', 'ważne', 'prawdziwy', 'prawdziwa', 'prawdziwe', 'cały', 'cała', 'całe',
    ],

    // A partner line that says nothing but «we are done» — its whole text, punctuation aside («Dobrze, dziękuję.» is
    // «dobrze dziękuję»). «Proszę.» alone is not here: «Proszę?» asks for the line again.
    'closers' => [
        'coś jeszcze', 'czy coś jeszcze', 'czy mogę jeszcze w czymś pomóc', 'to wszystko', 'świetnie', 'super',
        'doskonale', 'idealnie', 'wspaniale', 'dobrze', 'bardzo dobrze', 'dobra', 'okej', 'ok', 'w porządku', 'jasne',
        'zgoda', 'rozumiem', 'dziękuję', 'dziękuję bardzo', 'bardzo dziękuję', 'dzięki', 'proszę bardzo',
        'nie ma za co', 'nie ma problemu', 'nie ma sprawy', 'oczywiście', 'miłego dnia', 'do zobaczenia', 'do widzenia',
        'do usłyszenia', 'zapraszam', 'wszystkiego dobrego', 'dużo zdrowia', 'dobrze dziękuję', 'świetnie dziękuję',
        'dziękuję to wszystko', 'to wszystko dziękuję',
    ],

    // The verbs a check uses to name who said or asked something («Co mówi pacjent?», «O co pyta pacjent?», «Jakie
    // objawy podaje pacjent?») — the question is about the learner's line when it names the learner's role with one.
    'saying_verbs' => [
        'mówi', 'mówią', 'mówił', 'mówiła', 'mówić', 'powie', 'powiedział', 'powiedziała', 'powiedzieć', 'odpowiada',
        'odpowiadają', 'odpowie', 'odpowiedział', 'odpowiedziała', 'odpowiedzieć', 'wspomina', 'wspomni', 'wspomniał',
        'wspomniała', 'wspomnieć', 'podaje', 'podał', 'podała', 'twierdzi', 'wymienia', 'wymienił', 'wymieniła',
        'opowiada', 'pyta', 'pytał', 'pytała', 'zapyta', 'zapytał', 'zapytała', 'prosi', 'prosił', 'prosiła', 'poprosi',
        'poprosił', 'poprosiła', 'chce', 'chcą', 'chciał', 'chciała',
    ],

    // The word a partner names alternatives with («dziś albo jutro»). Not «czy», though it says «or» in «dziś czy jutro»:
    // far more often it opens a question («Czy ma pan dokument?»), and every such partner line would read as a list.
    'alternative_words' => ['albo', 'lub', 'bądź'],

    // TWO QUESTIONS IN ONE BUBBLE, by one sentence: a sentence that OPENS with a question word — after one word and a
    // comma at most («Dobrze, czy…») — and asks again after «i / a / oraz / albo / lub» and a question word, a comma
    // before it or not (Polish puts none before «i»): «Czy ma pan gorączkę i czy boli gardło?», «Od kiedy to trwa i czy
    // ma pan gorączkę?», «Kiedy to się zaczęło, a jak się pan czuje teraz?». Not the English «, and …» alone: a Polish
    // partner opens ONE question with «a» after a word of assent all the time — «Rozumiem, a od kiedy?», «Dobrze, a jak
    // się pan nazywa?» — and «, czy» is one question of two options («rano, czy po południu?»). Matched against the raw
    // text; «(?![\p{L}])» and not «\b», which is not sure of Polish letters. A second question after a sentence that
    // asks by its mark alone («Ma pan gorączkę, a czy boli gardło?») is not seen — a warning's price.
    'second_question_pattern' => '/(?:^|[.!?…]\s+)(?:\p{L}+,\s*)?(?:czy|kiedy|gdzie|jak|ile|co|kto|dlaczego|czemu|skąd'
        .'|dokąd|jaki\p{L}*|któr\p{L}*|od\s+kiedy|do\s+kiedy)(?![\p{L}])[^.!?…]*?,?\s+(?:i|a|oraz|albo|lub)\s+(?:czy|kiedy'
        .'|gdzie|jak|ile|co|kto|dlaczego|czemu|skąd|dokąd|jaki\p{L}*|któr\p{L}*|od\s+kiedy|do\s+kiedy)(?![\p{L}])[^?]*\?/iu',

    // Polish has no articles (the no-op).
    'articles' => [],

    // WORDS A SENTENCE CANNOT END ON (наряд FIX-3 §7): a move that stops on a preposition or a conjunction — «Chcę umówić
    // wizytę do», «Mam gorączkę i», «Mam gorączkę a», «Przyjdę jutro, bo» — broke off. Not the possessives and
    // demonstratives: «To jest moje.», «Wezmę ten.» end; nor «jak», «kiedy», «co» («Nie wiem jak.»).
    'dangling_words' => [
        'w', 'we', 'na', 'z', 'ze', 'u', 'do', 'od', 'o', 'po', 'za', 'przy', 'przez', 'dla', 'bez', 'pod', 'nad', 'przed',
        'i', 'a', 'czy', 'że', 'żeby', 'aby', 'bo', 'ale', 'lecz', 'albo', 'lub', 'oraz', 'ani', 'niż', 'jeśli', 'jeżeli',
        'ponieważ', 'gdyż',
    ],

    // A word the seam may say twice and still be Polish: «to to» («Czy to ___?» + «to samo» — «Czy to to samo?»).
    'seam_repeatable_words' => ['to'],

    // No article of Polish changes with the next word's sound (the no-op: an empty string equals no word).
    'article_sound' => [
        'before_vowel' => '', 'before_consonant' => '', 'vowel' => '/(?!)/u', 'consonant' => '/(?!)/u',
        'spelled' => '/(?!)/u', 'exception' => '/(?!)/u',
    ],

    // A clause where a value should stand. A subject pronoun + a form of «być», «mieć» or a modal at the start of a filler
    // is a whole sentence («on ma gorączkę» after «Wiem, że ___» — the frame's verb is there already: fatal, as in English);
    // a filler that opens with a subordinator («jeśli gorączka wróci», «że boli») is a clause. Narrow on purpose (pack-keys
    // §3.25): Polish drops the subject, so «ma gorączkę» is not seen — the right miss —, and «to» is not a subject here,
    // it is the demonstrative of a value too («to samo»). No contraction is a subject and its verb in Polish.
    'clause' => [
        'subjects' => ['ja', 'ty', 'on', 'ona', 'ono', 'my', 'wy', 'oni', 'one'],
        'finite' => [
            'jestem', 'jesteś', 'jest', 'jesteśmy', 'jesteście', 'są', 'byłem', 'byłam', 'był', 'była', 'było', 'byliśmy',
            'byłyśmy', 'byli', 'były', 'będę', 'będzie', 'będziemy', 'będą', 'mam', 'masz', 'ma', 'mamy', 'macie', 'mają',
            'miałem', 'miałam', 'miał', 'miała', 'mieli', 'miały', 'mogę', 'może', 'możemy', 'mogą', 'muszę', 'musi',
            'musimy', 'muszą', 'chcę', 'chce', 'chcemy', 'chcą',
        ],
        'contractions' => [],
        'subordinators' => ['jeśli', 'jeżeli', 'gdy', 'gdyby', 'bo', 'ponieważ', 'chociaż', 'choć', 'żeby', 'aby', 'zanim', 'dopóki', 'skoro', 'że'],
        'subordinators_before_subject' => ['kiedy', 'jak', 'odkąd'],
    ],

    // «The frame must stand alone» (FRAMES): the pronouns a frame may lean on with nothing in it they stand for — «to»,
    // «ono», «go», «ją» (it / him / her), «tam», «jeden». A frame-initial «To» is the visit's own subject («To trwa ___»);
    // «tam» next to a form of «być» says where («Tam jest ___»); «to» and «jeden» before a content word, the slot or «z»
    // are a determiner, a number, a part («to badanie», «jeden ___», «jeden z ___»); a pronoun is resolved when the frame
    // names a thing before it — a determiner with a content word.
    'unresolved_pronouns' => [
        'words' => ['to', 'ono', 'go', 'ją', 'tam', 'jeden', 'jedna', 'jedno'],
        'frame_initial_subject' => ['to'],
        'existential' => ['tam'],
        'determiner_or_number' => ['to', 'jeden', 'jedna', 'jedno'],
        'partitive' => ['z', 'ze'],
        'be_forms' => ['jest', 'są', 'był', 'była', 'było', 'były', 'będzie', 'będą'],
        'determiners' => [
            'ten', 'ta', 'to', 'te', 'tego', 'tej', 'tym', 'tę', 'mój', 'moja', 'moje', 'moi', 'mojego', 'mojej', 'moim',
            'moją', 'twój', 'twoja', 'twoje', 'jego', 'jej', 'nasz', 'nasza', 'nasze', 'wasz', 'wasza', 'wasze', 'ich',
            'pana', 'pani',
        ],
    ],

    // THE LEARNER'S GENDER IN A POLISH LINE (`native.gendered_past`, only while the gender is unknown): the first person of
    // the past and of the conditional — «byłem / byłam», «miałem / miałam», «poszedłem / poszłam», «chciałbym /
    // chciałabym». Polish drops «ja», so no «ja» is asked for before it. The group is the form. «-łeś / -łaś» are not
    // here: the second person in the learner's own line is about whom the learner speaks to, not about the learner.
    // The instrumental «-łem» of a noun is no verb: an «o» before «ł» is left out — «stołem», «kołem», «kościołem» —, and
    // so are the nouns and names a line of these scenes says in it — «z gardłem» (the throat of the sore-throat day),
    // «z oddziałem / działem», «pomysłem», «ciałem», «kanałem», «masłem», «hasłem», «światłem», «z Michałem / Pawłem /
    // Rafałem»… A noun not named here («mydłem» is, «krzesłem» is, a rarer one is not) still reads as a verb — a warning's
    // price; a verb is never taken for one of them («chciałem», «działałem», «wymyśliłem» read).
    'gendered_past_pattern' => '/(?<![\p{L}])(?!(?:\p{L}*(?:dział|mysł)|ciał|upał|zapał|kanał|michał|rafał|pawł|orł|węzł'
        .'|posł|osł|kotł|kozł|masł|hasł|mydł|światł|krzesł|wiosł|gardł|źródł|szkł)em(?![\p{L}]))'
        .'(\p{L}+(?<!o)ł(?:em|am|bym|abym))(?![\p{L}])/u',

    // «The native frame contains NO word that agrees with the slot in gender or number» (FRAMES, v4.5). Read at the slot,
    // never elsewhere in the frame:
    //  - the word right before `___` agrees when it is one of `words` (possessives, demonstratives, «jaki», «który»,
    //    «jeden», «każdy», «inny», «cały», the ordinals of an appointment — in their cases) or one of `short_forms`, or is
    //    an adjective by an ending no noun and no verb of a frame has (`suffixes_before_slot`, at least `min_letters`
    //    letters: «wysoką ___», «wolnym ___», «nowego ___», «wysokich ___»);
    //  - one of the `after_slot_words` words after `___` agrees when it is listed — the predicate of a slot that is the
    //    subject («___ jest wolny?», «___ jest otwarte?»).
    // The one-letter endings of a Polish adjective («-y», «-a», «-e») are every noun's and verb's too («To trwa ___», «Do
    // zobaczenia ___»), and «-ej» is the comparative adverb's («wcześniej ___»), so those adjectives are the listed ones.
    // The genitive «-ego» is written by the letter before it, so that «dlaczego», «czego», «niczego» (which end «-czego»)
    // are no adjective. «to» agrees with nothing («Czy to ___?» is «is it ___»), nor does «jego / jej / ich» (his, her,
    // their), nor «temu» («Zaczęło się ___ temu» — «ago»), nor «ci» (far more «to you» — «Czy mogę ci ___?» — than «these»).
    'agreement' => [
        'words' => [
            'mój', 'moja', 'moje', 'moi', 'mojego', 'mojej', 'mojemu', 'moim', 'moją', 'moich', 'moimi',
            'twój', 'twoja', 'twoje', 'twoi', 'twojego', 'twojej', 'twojemu', 'twoim', 'twoją', 'twoich',
            'swój', 'swoja', 'swoje', 'swoi', 'swojego', 'swojej', 'swojemu', 'swoim', 'swoją', 'swoich',
            'nasz', 'nasza', 'nasze', 'nasi', 'naszego', 'naszej', 'naszemu', 'naszym', 'naszą', 'naszych',
            'wasz', 'wasza', 'wasze', 'wasi', 'waszego', 'waszej', 'waszemu', 'waszym', 'waszą', 'waszych',
            'ten', 'ta', 'te', 'tego', 'tej', 'tym', 'tę', 'tą', 'tych', 'tymi',
            'tamten', 'tamta', 'tamto', 'tamte', 'tamtego', 'tamtej', 'tamtym', 'tamtą', 'tamtych',
            'taki', 'taka', 'takie', 'tacy', 'takiego', 'takiej', 'takiemu', 'takim', 'taką', 'takich',
            'jaki', 'jaka', 'jakie', 'jacy', 'jakiego', 'jakiej', 'jakiemu', 'jakim', 'jaką', 'jakich', 'jakimi',
            'który', 'która', 'które', 'którzy', 'którego', 'której', 'któremu', 'którym', 'którą', 'których', 'którymi',
            'czyj', 'czyja', 'czyje', 'czyjego', 'czyjej',
            'jeden', 'jedna', 'jedno', 'jednego', 'jednej', 'jednemu', 'jednym', 'jedną',
            'każdy', 'każda', 'każde', 'każdego', 'każdej', 'każdemu', 'każdym', 'każdą',
            'inny', 'inna', 'inne', 'inni', 'innego', 'innej', 'innym', 'inną', 'innych',
            'cały', 'cała', 'całe', 'całego', 'całej', 'całym', 'całą', 'wszystkie', 'wszyscy', 'wszystkich',
            'pierwszy', 'pierwsza', 'pierwsze', 'pierwszego', 'pierwszej', 'pierwszym', 'pierwszą',
            'drugi', 'druga', 'drugie', 'drugiego', 'drugiej', 'drugim', 'drugą',
            'ostatni', 'ostatnia', 'ostatnie', 'ostatniego', 'ostatniej', 'ostatnim', 'ostatnią',
            'następny', 'następna', 'następne', 'następnego', 'następnej', 'następnym', 'następną',
        ],
        'short_forms' => [
            'otwarty', 'otwarta', 'otwarte', 'zamknięty', 'zamknięta', 'zamknięte', 'wolny', 'wolna', 'wolne', 'zajęty',
            'zajęta', 'zajęte', 'gotowy', 'gotowa', 'gotowe', 'potrzebny', 'potrzebna', 'potrzebne', 'dostępny',
            'dostępna', 'dostępne', 'zapłacony', 'zapłacona', 'zapłacone', 'zarezerwowany', 'zarezerwowana',
            'zarezerwowane', 'wymagany', 'wymagana', 'wymagane', 'dozwolony', 'dozwolona', 'dozwolone', 'wliczony',
            'wliczona', 'wliczone', 'czynny', 'czynna', 'czynne', 'obowiązkowy', 'obowiązkowa', 'obowiązkowe', 'ważny',
            'ważna', 'ważne',
        ],
        'suffixes_before_slot' => [
            'bego', 'cego', 'dego', 'hego', 'iego', 'łego', 'mego', 'nego', 'pego', 'rego', 'sego', 'szego', 'dzego',
            'tego', 'wego', 'żego', 'emu', 'ymi', 'imi', 'ych', 'ich', 'ym', 'im', 'owe', 'ną', 'ką',
        ],
        'min_letters' => 5,
        'after_slot_words' => 2,
    ],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а): what a rescue move of the talk says in the learner's own bubble —
    // «Słucham?», the Polish «Sorry?» of a line not caught.
    'rescue_line' => 'Słucham?',

    // THE RESCUE KIT (наряд LANG-1b §2): the six lines a learner of this language says when stuck, each with its translation into
    // every learner's language of the plan. Row 1 is `rescue_line`. Said in the learner's voice, filed by (target,
    // gender, voice, line) — `RescueKits`. The same matrix writes every pack: `docs/research/lang-1b/tools/rescue-kit.py`.
    'rescue' => [
        ['target' => 'Słucham?', 'native' => ['ru' => 'Простите?', 'uk' => 'Перепрошую?', 'be' => 'Прабачце?', 'pl' => 'Słucham?', 'ro' => 'Poftim?', 'es' => '¿Perdón?', 'it' => 'Scusi?', 'de' => 'Wie bitte?', 'fr' => "Pardon\u{00A0}?"]],
        ['target' => 'Proszę mówić trochę wolniej.', 'native' => ['ru' => 'Можно помедленнее, пожалуйста?', 'uk' => 'Можна повільніше, будь ласка?', 'be' => 'Можна павольней, калі ласка?', 'pl' => 'Proszę mówić trochę wolniej.', 'ro' => 'Puteți vorbi mai rar, vă rog?', 'es' => '¿Puede hablar más despacio, por favor?', 'it' => 'Può parlare più lentamente, per favore?', 'de' => 'Können Sie bitte langsamer sprechen?', 'fr' => "Vous pouvez parler plus lentement, s'il vous plaît\u{00A0}?"]],
        ['target' => 'Nie rozumiem.', 'native' => ['ru' => 'Я не понимаю.', 'uk' => 'Я не розумію.', 'be' => 'Я не разумею.', 'pl' => 'Nie rozumiem.', 'ro' => 'Nu înțeleg.', 'es' => 'No entiendo.', 'it' => 'Non capisco.', 'de' => 'Ich verstehe nicht.', 'fr' => 'Je ne comprends pas.']],
        ['target' => 'Chwileczkę.', 'native' => ['ru' => 'Одну минуту.', 'uk' => 'Хвилинку.', 'be' => 'Хвілінку.', 'pl' => 'Chwileczkę.', 'ro' => 'Un moment.', 'es' => 'Un momento.', 'it' => 'Un momento.', 'de' => 'Einen Moment.', 'fr' => 'Un instant.']],
        ['target' => 'Proszę mi to zapisać.', 'native' => ['ru' => 'Можете это записать?', 'uk' => 'Можете це записати?', 'be' => 'Можаце гэта запісаць?', 'pl' => 'Proszę mi to zapisać.', 'ro' => 'Îmi puteți scrie asta?', 'es' => '¿Me lo puede escribir?', 'it' => 'Me lo può scrivere?', 'de' => 'Können Sie mir das aufschreiben?', 'fr' => "Vous pouvez me l'écrire\u{00A0}?"]],
        ['target' => 'Dziękuję.', 'native' => ['ru' => 'Спасибо.', 'uk' => 'Дякую.', 'be' => 'Дзякуй.', 'pl' => 'Dziękuję.', 'ro' => 'Mulțumesc.', 'es' => 'Gracias.', 'it' => 'Grazie.', 'de' => 'Danke.', 'fr' => 'Merci.']],
    ],

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9) — the same line as every pack's, in this language (en «I see. Please
    // go on.»).
    'neutral_reply' => 'Rozumiem. Proszę mówić dalej.',

    // THE FORMS OF ONE WORD (BACK-TAILS-2 §2) and THE PERSONS SWAPPED (§9) — written EMPTY on purpose. Their only reader for
    // Polish is the role's echo guard (LineShare, RoleLines guard 3); the do-support of the judge is English. A Polish base
    // is a case ending off a stem («gorączkę» = «gorączka»), and a guard that read so would take the role's own follow-up
    // in the learner's words («Ból gardła i gorączka — od kiedy?» after «Mam ból gardła i gorączkę») for an echo and
    // silence it; the persons of Polish are the verb's ending and the polite «pan / pani» («Mam» → «Ma pan»), which a map
    // of one word to one word does not say. So a word is compared as it is, after the canonical form.
    'irregular_forms' => [],
    'inflection_rules' => [],
    'person_swap' => [],

    // ─── THE JUDGE OF THE TALK'S CONSTRUCTIONS (наряд FIX-4 §2, LANG-1 §1): what the judge may forgive of Polish.

    // No contraction, no elision: a Polish word is its own letters.
    'contractions' => [],
    'contractions_before' => [],

    // THE WORDS A MOVE MAY OPEN WITH before its construction: «Dobrze, przyjdę dziesięć minut wcześniej», «Tak, mam
    // gorączkę», «Dzień dobry, chcę umówić wizytę», «Dobrze, to przyjdę jutro» / «No to przyjdę jutro» («to» — «then»),
    // «Proszę pani, chcę umówić wizytę», «Panie doktorze, mam gorączkę», «Niestety nie mam dokumentu», the hesitations of
    // speech («yyy», «em», «hmm»). A frame that itself begins with one of them («To trwa ___», «Proszę ___») still starts
    // where the move begins.
    'intro_words' => [
        'tak', 'nie', 'no', 'dobrze', 'dobra', 'okej', 'okay', 'okey', 'ok', 'jasne', 'pewnie', 'oczywiście', 'super',
        'świetnie', 'spoko', 'zgoda', 'dziękuję', 'dziękuję bardzo', 'bardzo dziękuję', 'dzięki', 'proszę', 'przepraszam',
        'przepraszam bardzo', 'dzień dobry', 'dzień dobry pani', 'dzień dobry panu', 'dobry wieczór', 'cześć', 'witam',
        'halo', 'hej', 'aha', 'rozumiem', 'w porządku', 'słucham', 'niestety', 'więc', 'czyli', 'to', 'no to', 'a', 'i',
        'proszę pani', 'proszę pana', 'panie doktorze', 'pani doktor',
        'och', 'yyy', 'yy', 'eee', 'ee', 'em', 'hmm', 'hm', 'mhm',
    ],

    // THE WORDS A NEW CLAUSE OPENS WITH (наряд FIX-4b §1): «Nie mogę jutro, ale czy jest coś po południu?», «Myślę, że mam
    // gorączkę», «Nie przyjdę rano, bo mam pracę», «Dzwonię, ponieważ mam gorączkę», «…, czyli przyjdę jutro». «że», «bo»,
    // «ponieważ», «gdyż» open nothing but a clause in Polish. Not «kiedy», «jeśli», «czy»: «Nie wiem, kiedy mam przyjść»
    // says no «Mam ___».
    'clause_starters' => ['i', 'a', 'ale', 'lecz', 'więc', 'czyli', 'potem', 'albo', 'lub', 'bo', 'ponieważ', 'gdyż', 'że', 'oraz'],

    // A CONSTRUCTION SAID IN THE NEGATIVE IS THE SAME CONSTRUCTION (DECISIONS п. 395; LANG-1 §1): «nie» stands before the
    // verb wherever the verb stands — «Nie mam gorączki» says «Mam ___», «Jutro mi nie pasuje» says «___ mi pasuje» —
    // so it is free anywhere (`after` null). The genitive of negation («gorączki» for «gorączkę») is in the window, the
    // learner's own.
    'negation' => ['words' => ['nie'], 'after' => null, 'do_support' => []],

    // No partitive word to leave out.
    'partitive' => [],

    // FREQUENT AND DISTINCTIVE (наряд LANG-1 §5, `common_words`): some fifty words Polish lines are full of that are NO
    // ordinary word, in the same spelling, of any other Latin language of the plan (en ro es it de fr). The order said
    // «the 30 most frequent words»; the list is «frequent and distinctive» on purpose. The guard of the role's translation
    // reads a learner's grey line against EVERY neighbour in the same letters, and a word that is also an ordinary word of
    // the other language, but sits in Polish's list only, counts as Polish inside that language's own line — two such
    // words and an honest translation is refused (a probe refused «Для записи к врачу приходите до двенадцати» for ru
    // because a uk list held «для» and «до»). So the most frequent Polish words another language of the plan spells alike
    // are left out — «nie» (de «nie», never), «i» (it, en), «a» (ro es it fr), «o» (ro es it), «u» (es), «do» (en it),
    // «to» (en), «na» (de «na ja»), «po» (it «un po'»), «ja» (de), «mi» (es it ro), «ma» (it fr), «ale» (ro, en), «ten»,
    // «tu», «ta» (ro), «te», «ci» (it), «my», «on», «pan» (es fr), «pani» (it), «ich» (de) — and so is «ok». The list is
    // longer than thirty so that an ordinary Polish line holds two of it even when it quotes a foreign name. One run of
    // letters each, lower case.
    'common_words' => [
        'się', 'jest', 'że', 'jak', 'tak', 'czy', 'co', 'mam', 'mamy', 'mnie', 'jestem', 'są', 'być', 'był', 'była',
        'będzie', 'może', 'mogę', 'można', 'trzeba', 'chcę', 'proszę', 'dziękuję', 'przepraszam', 'dobrze', 'dobry',
        'dzień', 'już', 'jeszcze', 'tylko', 'bardzo', 'też', 'więc', 'dla', 'przez', 'od', 'w', 'z', 'gdzie', 'kiedy',
        'ile', 'który', 'jaki', 'coś', 'wszystko', 'tego', 'jego', 'jej', 'tutaj', 'teraz', 'dziś', 'jutro',
    ],

    // THE TITLE OF A TALK (наряд LANG-1 §6): «Rozmowa: recepcjonistka i lekarz» — the roles as written, in no case, the
    // first letter lowered unless the role is an acronym («MRI», «HR-menedżer»); «Rozmowa» when the talk names nobody.
    'talk_title_template' => ['title' => 'Rozmowa: {roles}', 'and' => 'i', 'anyone' => 'Rozmowa', 'lower_first' => true],
];
