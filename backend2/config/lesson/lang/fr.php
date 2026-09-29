<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · fr — what the code-only checks read of French
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b), keys by `docs/research/lang-1/pack-keys.md` (наряд LANG-1). French is BOTH sides of
| a plan: the language taught (ru→fr — what the learner says and hears: the day's checks' target rules, the judge of
| the talk's constructions, the comparison of speech) and the learner's own (fr→en — the readings, the native frames, the
| listening, the guard of the role's translation, the title of the talk). Every key of both sides is written; a rule that
| is no rule of French is the spec's explicit no-op, never null (null would switch its rule off on every day of a pair).
|
| HOW THE WORDS ARE SPELT. Every list is written in the standard spelling (accents kept — «à» and «a», «où» and «ou» are
| two words each) and read through {@see \App\Modules\Plan\Domain\Check\Language\LanguagePack::normal()} or the kernel's
| canonical form, which fold «œ» into «oe» on both sides: «sœur» of a list meets «soeur» of a keyboard. A PATTERN is
| matched against the folded, lower-cased word, so it never names «œ»; the one pattern read on raw text
| (`second_question_pattern`) names no such letter either.
|
| THE ELISION. French glues a clipped word to the next with an apostrophe — «j'ai», «n'est», «l'hôpital», «qu'il». The
| families read it differently, and each list below says which form it holds: the validator's words (Ⓐ) keep «j'ai» one
| word; the judge of the talk (Ⓑ) spells it out through `contractions` («j'» → «je»); the comparison of speech (Ⓒ) drops
| the apostrophe («jai»); the guard of the translation (Ⓓ) cuts at it («j», «ai»).
|
| FRENCH TYPOGRAPHY — a note, no change of code: French puts a space (a no-break one in print) before « ? ! : ; ». The
| end of a sentence is read right through it (SentenceEnds finds the mark itself), but `FrameText::fill()` and
| `withEndMarkClosed()` glue the mark back onto the word («fièvre?» on a native card) — typographic, not a comparison
| error (pack-keys §8 п. 3).
|
| Every list is a counter's reading of a rule, not the rule: the codes built on them are heuristics.
*/

// ── THE NUMBERS (`number_words`, the comparison of speech — the kernel's canonical form: a hyphen is a space) ───────────
// 0–20 and the tens are one word each; 17–19 are two («dix-sept»); «vingt et un»…«soixante et un» join by «et»; 70–79
// count on from sixty and 80–99 from four twenties — «soixante-dix», «soixante et onze», «quatre-vingts», «quatre-vingt-
// dix-neuf» — which no rule of fitting can read out of «soixante» + «dix» or «quatre» + «vingt», so each of them is an
// entry of its own (pack-keys §4.5: the longest entry wins). «un» / «une» are NO number words: they are the articles too,
// and read as «1» every «un rendez-vous» would stop being an article the comparison may leave out — where they can only
// be the number (after «vingt et», after «quatre-vingt-») the entry says them whole. Belgian and Swiss tens (septante,
// huitante, octante, nonante) are read too; «septante-deux», «nonante-neuf» join by the rule of fitting.
$numbers = [
    'zéro' => '0', 'deux' => '2', 'trois' => '3', 'quatre' => '4', 'cinq' => '5', 'six' => '6', 'sept' => '7',
    'huit' => '8', 'neuf' => '9', 'dix' => '10', 'onze' => '11', 'douze' => '12', 'treize' => '13', 'quatorze' => '14',
    'quinze' => '15', 'seize' => '16', 'dix-sept' => '17', 'dix-huit' => '18', 'dix-neuf' => '19',
    'vingt' => '20', 'trente' => '30', 'quarante' => '40', 'cinquante' => '50', 'soixante' => '60',
    'septante' => '70', 'huitante' => '80', 'octante' => '80', 'nonante' => '90',
];
// «vingt et un» → 21 … «soixante et une» → 61, «septante et un» → 71, «nonante et un» → 91.
foreach (['vingt' => 20, 'trente' => 30, 'quarante' => 40, 'cinquante' => 50, 'soixante' => 60, 'septante' => 70, 'huitante' => 80, 'octante' => 80, 'nonante' => 90] as $tens => $value) {
    $numbers["{$tens} et un"] = (string) ($value + 1);
    $numbers["{$tens} et une"] = (string) ($value + 1);
}
// 70–79 on sixty and 80–99 on four twenties, one entry each: «soixante-dix» 70, «soixante et onze» 71, «soixante-douze»
// 72 … «soixante-dix-neuf» 79; «quatre-vingts» / «quatre-vingt» 80, «quatre-vingt-un» 81 … «quatre-vingt-dix-neuf» 99.
$overTen = [
    'dix' => 10, 'onze' => 11, 'douze' => 12, 'treize' => 13, 'quatorze' => 14, 'quinze' => 15, 'seize' => 16,
    'dix-sept' => 17, 'dix-huit' => 18, 'dix-neuf' => 19,
];
foreach ($overTen as $word => $value) {
    $numbers[$word === 'onze' ? 'soixante et onze' : "soixante-{$word}"] = (string) (60 + $value);
}
$numbers['quatre-vingts'] = '80';
$numbers['quatre-vingt'] = '80';
$numbers['quatre-vingt-un'] = '81';
$numbers['quatre-vingt-une'] = '81';
foreach (['deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6, 'sept' => 7, 'huit' => 8, 'neuf' => 9, ...$overTen] as $word => $value) {
    $numbers["quatre-vingt-{$word}"] = (string) (80 + $value);
}
// The scales, each with its plural form («deux cents», «trois millions»); «mille» has none («milles» are miles).
$numbers += [
    'cent' => '100', 'cents' => '100', 'mille' => '1000',
    'million' => '1000000', 'millions' => '1000000', 'milliard' => '1000000000', 'milliards' => '1000000000',
];

// ── THE VERBS A CHECK NAMES WHO SAID SOMETHING WITH (`saying_verbs`) — each form, and its inverted question form, which
// the validator reads as ONE word («Que demande-t-elle ?» → «demande-t-elle»).
$saying = [];
foreach ([
    'dire' => ['dit', 'disent', 'dis', 'dites', 'disait'],
    'répondre' => ['répond', 'répondent', 'réponds', 'répondu'],
    'mentionner' => ['mentionne', 'mentionnent', 'mentionné'],
    'demander' => ['demande', 'demandent', 'demandé'],
    'poser' => ['pose', 'posent', 'posé'],
    'parler' => ['parle', 'parlent', 'parlé'],
    'vouloir' => ['veut', 'veulent', 'voulait', 'voulu'],
    'souhaiter' => ['souhaite', 'souhaitent', 'souhaité'],
    'expliquer' => ['explique', 'expliquent', 'expliqué'],
    'raconter' => ['raconte', 'racontent', 'raconté'],
    'préciser' => ['précise', 'précisent', 'précisé'],
    'indiquer' => ['indique', 'indiquent', 'indiqué'],
    'proposer' => ['propose', 'proposent', 'proposé'],
] as $infinitive => $forms) {
    $saying = [...$saying, $infinitive, ...$forms];
    $third = $forms[0];
    $joint = str_ends_with($third, 'e') ? '-t-' : '-';
    $saying = [...$saying, "{$third}{$joint}il", "{$third}{$joint}elle", "{$forms[1]}-ils", "{$forms[1]}-elles"];
}

// ── WHAT «JE SUIS» + A WORD IN -é/-i/-u/-is/-it SAYS NO GENDER WITH (`gendered_past_pattern`): adverbs and pronouns
// («ici», «aussi», «celui» is gendered and stays out of here), the weekdays («je suis libre mardi» aside, «je suis
// lundi»), and the first names a model gives a French speaker who introduces themself — «Je suis Marie Dupont», «Je suis
// Louis Martin»: the pattern reads the line lower-cased, and a name is no participle.
$genderless = [
    'ici', 'aussi', 'ainsi', 'parmi', 'voici', 'merci', 'si', 'ni', 'fait', 'lui', 'qui', 'oui', 'midi',
    'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi',
    'marie', 'julie', 'sophie', 'nathalie', 'valérie', 'sylvie', 'aurélie', 'émilie', 'amélie', 'stéphanie', 'mélanie',
    'lucie', 'élodie', 'léonie', 'noémie', 'laurie', 'rosalie', 'annie', 'chloé', 'zoé', 'salomé', 'renée', 'élise',
    'lise', 'louise', 'denise', 'françoise', 'héloïse', 'marguerite', 'iris',
    'andré', 'rené', 'hervé', 'josé', 'noé', 'timothée', 'rémi', 'sami', 'mehdi', 'ali', 'louis', 'denis', 'alexis',
    'mathis', 'francis', 'clovis', 'mathieu', 'matthieu', 'lou', 'manu',
];

return [
    // A reading of the target (`pronunciation_native`) in the learner's own letters — a WARNING (`pronunciation.script`):
    // the French alphabet itself (a–z, the accented vowels, ç, œ, æ, ÿ), the decomposed accents a keyboard may send
    // (grave, acute, circumflex, diaeresis, cedilla), digits, punctuation (the apostrophe, « » are \p{P}), whitespace and
    // the stress mark U+0301. Strict on purpose: an IPA «ə» or «ʃ» in a reading is untidy and flagged, never failed — the
    // fatal check is `script_letters`.
    'script' => '/^[a-zA-ZàâäçéèêëîïôöùûüÿœæÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸŒÆ\p{N}\p{P}\s\x{0300}\x{0301}\x{0302}\x{0308}\x{0327}]*$/u',

    // One LETTER of a reading, matched alone (`pronunciation.foreign_script` — FATAL): the Latin writing, exactly the
    // string every Latin pack writes — two packs are neighbours for the guard of the translation exactly when this string
    // is the same (pack-keys §3.2). Not the French alphabet: a strict one here would fail a valid day on one «ə». A
    // reading in Cyrillic letters for a French learner (the fr→en scouting day wrote every one so) is fatal, and rightly.
    'script_letters' => '/^[\p{Latin}]$/u',

    // FREQUENT AND DISTINCTIVE (наряд LANG-1 §5, `common_words`). The order said «the 30 most frequent words»; this list is
    // «частые и отличительные» on purpose: the guard of the role's translation ({@see \App\Modules\Plan\Domain\Service\ReplyNative})
    // reads a learner's grey line against EVERY pack in Latin letters (en pl ro es it de), and a word that is also an
    // ordinary word of the learner's language, but sits in the French list only, counts as French inside that learner's
    // own line — two such words and an honest translation is refused (the probe that refused «Для записи к врачу приходите
    // до двенадцати» for ru because a uk list held «для» and «до»). So the most frequent French words a neighbour spells
    // alike, however rare there, are left out: «de», «la», «le», «un», «en», «a» (es it ro), «les», «me», «se», «que», «y»
    // (es), «je», «pas» (pl «je», «pas»), «tu», «il», «ce», «ne», «non», «qui», «lui», «quel», «quelle» (it, ro), «ma»
    // (it «but», pl «has»), «est» (it, ro «east»), «ai» (it, ro), «au» (ro), «va» (ro, it), «du», «des» (de), «on» (pl, en), «pour», «comment»,
    // «plus», «encore», «chose» (en), «bien», «sur», «mes», «son», «nos», «vos» (es, pl, ro, en), «mais», «merci» (it
    // «maize», «goods»), «trop» (pl), «cela» (es, it), «ou» (ro «egg»), «dans» (ro «dance»), «moi» (pl «my», ro «soft»),
    // «elle» (de «Elle», the ulna); «mon» too, English «c'mon» leaving «mon». Kept: «à», which German writes only in a price
    // («zwei Karten à 15 Euro» — a loan of commerce, and a German line needs two French-only words to be refused). The runs
    // «j», «qu», «aujourd» are French only: the guard cuts «j'ai», «qu'il», «aujourd'hui» at the apostrophe, and they are
    // what is left of the words no neighbour writes. One run of letters each, lower case, accents as written.
    'common_words' => [
        'j', 'qu', 'aujourd', 'nous', 'vous', 'ils', 'elles', 'suis', 'sont', 'êtes', 'avez', 'avons', 'ont', 'était',
        'être', 'vais', 'fait', 'cette', 'quoi', 'quand', 'combien', 'pourquoi', 'où', 'avec', 'chez', 'et', 'à', 'aux',
        'très', 'oui', 'bonjour', 'plaît', 'peux', 'peut', 'pouvez', 'voudrais', 'veux', 'faut', 'aussi', 'déjà', 'rien',
        'beaucoup', 'maintenant', 'demain', 'votre', 'ça', 'depuis', 'sûr',
    ],

    // The marks a sentence ends with, and what each says. French puts a space before « ? ! » — the end is the mark itself
    // and is read through the space; the French quotes « … » (spaces inside, a no-break one too) close a sentence as the
    // English ones do ({@see \App\Modules\Plan\Domain\Check\Language\SentenceEnds}).
    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence (наряд CHECK-1): «Bonjour M. Dupont», «avec le Dr. Lee», «votre carte, votre
    // ordonnance, etc.» — read in the text as written, letter case aside, a space inside one being any run of spaces.
    // French writes «Mme», «Mlle», «Dr» and «n°» without a dot, and a word without a dot needs no entry; the dotted
    // English habit a model has («Dr.», «Mme.») is listed. «M.» is one letter, and listed all the same: «Bonjour M.
    // Martin, je voudrais…» read as two sentences would put the learner's construction in the middle of the second one,
    // where the judge of the talk no longer finds it; the price is «à 50 m. Tournez…» read as one sentence. No
    // abbreviation that is also an ordinary word with a dot: not «sept.» («Il est sept.»), «sec.», «app.» (the phone's
    // app), «BD.», a bare «ex.» («mon ex.») — «p. ex.» and «par ex.» are.
    'abbreviations' => [
        'M.', 'MM.', 'Mme.', 'Dr.', 'Pr.', 'St.', 'Ste.', 'etc.', 'p. ex.', 'par ex.', 'c.-à-d.', 'cf.', 'env.', 'tél.',
        'av.', 'apt.', 'min.',
    ],

    // French asks by intonation, by «est-ce que» and by an inversion written with a hyphen («avez-vous» — one word of the
    // text, which this rule cannot read, pack-keys §3.5): a question is its mark alone — the no-op.
    'question_word_order' => ['auxiliaries' => [], 'subjects' => []],

    // Words that carry no content of their own (Ⓐ: a word glued by an apostrophe or a hyphen is one word of the text and
    // is listed whole — «c'est», «j'ai», «est-ce», «puis-je», «avez-vous», «d'accord»): articles and their contracted
    // forms, prepositions, conjunctions, pronouns, possessives and demonstratives, the question words, the forms of être,
    // avoir, aller, pouvoir, vouloir, devoir and «faut», the particles of negation, of yes / no and of politeness.
    'function_words' => [
        // articles, the contracted and elided forms
        'le', 'la', 'les', 'l', 'un', 'une', 'des', 'du', 'de', 'd', 'au', 'aux',
        // prepositions
        'à', 'en', 'dans', 'sur', 'sous', 'avec', 'sans', 'pour', 'par', 'chez', 'vers', 'entre', 'pendant', 'depuis',
        'avant', 'après', 'contre', 'dès', 'selon', 'près', "jusqu'à", "jusqu'au", 'voici', 'voilà',
        // conjunctions
        'et', 'ou', 'mais', 'donc', 'car', 'ni', 'que', 'qu', 'quand', 'si', 'comme', 'lorsque', 'puisque', 'parce',
        'alors', 'puis', 'ensuite',
        // pronouns
        'je', 'j', 'tu', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'elles', 'me', 'm', 'te', 't', 'se', 's', 'lui',
        'leur', 'y', 'moi', 'toi', 'soi', 'eux', 'ça', 'cela', 'ceci', 'celui', 'celle', 'ceux', 'celles', 'qui', 'quoi',
        'dont', 'où', 'lequel', 'laquelle', "quelqu'un", 'rien',
        // determiners
        'ce', 'cet', 'cette', 'ces', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'votre', 'nos',
        'vos', 'leurs', 'quel', 'quelle', 'quels', 'quelles', 'chaque', 'tout', 'toute', 'tous', 'toutes', 'aucun',
        'aucune', 'quelque', 'quelques', 'plusieurs', 'même', 'autre', 'autres',
        // question words
        'comment', 'combien', 'pourquoi',
        // être, avoir, aller, pouvoir, vouloir, devoir, falloir
        'être', 'suis', 'es', 'est', 'sommes', 'êtes', 'sont', 'étais', 'était', 'étaient', 'été', 'sera', 'serait',
        'avoir', 'ai', 'as', 'a', 'avons', 'avez', 'ont', 'avais', 'avait', 'eu', 'aura', 'aurait',
        'aller', 'vais', 'vas', 'va', 'allons', 'allez', 'vont',
        'pouvoir', 'peux', 'peut', 'pouvons', 'pouvez', 'peuvent', 'pourrais', 'pourrait', 'pourriez', 'puis-je',
        'vouloir', 'veux', 'veut', 'voulons', 'voulez', 'veulent', 'voudrais', 'voudrait', 'voudriez',
        'devoir', 'dois', 'doit', 'devons', 'devez', 'doivent', 'faut',
        // the elided and hyphenated forms a text writes as one word — the inversion of a question among them
        "c'est", "c'était", "j'ai", "j'avais", "j'étais", "n'est", "n'ai", "n'a", "n'y", "qu'il", "qu'elle", "qu'on",
        "qu'est-ce", 'est-ce', "s'il", "d'un", "d'une", "l'un", "l'une", 'a-t-il', 'a-t-elle', 'peut-être',
        'avez-vous', 'êtes-vous', 'pouvez-vous', 'voulez-vous', 'dois-je', 'faut-il', 'est-il', 'est-elle',
        // negation, yes / no, the small words of a reply
        'ne', 'n', 'pas', 'plus', 'non', 'oui', 'très', 'bien', 'aussi', 'déjà', 'encore', 'toujours', 'jamais',
        'seulement', 'juste', 'trop', 'peu', 'beaucoup', 'assez', 'ici', 'là', 'merci', 'pardon', 'excusez-moi', 'plaît',
        "d'accord", 'ok', 'okay', 'bon', 'ben', 'oh', 'ah', 'euh', 'désolé', 'désolée', 'sûr',
    ],

    // THE WORDS A RECOGNISER EATS (наряд FIX-2, п. 2) — articles, the short prepositions, «et», the forms of être and avoir,
    // the modals: left out of BOTH sides when a line the learner is LOOKING AT is compared with what was said. In the
    // canonical form of the comparison (Ⓒ): the apostrophe is DROPPED there, so an elided word is glued to the next one
    // («j'ai» is «jai», «l'heure» «lheure») and no «l'», «d'» can be listed. Narrower than `function_words`: «ne», «pas»,
    // «non» flip the meaning and are not here, nor «sans» (against «avec»), nor the pronouns («je», «vous» say who).
    'unstressed_words' => [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'au', 'aux',
        'à', 'en', 'dans', 'sur', 'pour', 'par', 'avec', 'chez',
        'et',
        'suis', 'es', 'est', 'sommes', 'êtes', 'sont',
        'ai', 'as', 'a', 'avons', 'avez', 'ont',
        'peux', 'peut', 'pouvons', 'pouvez', 'peuvent', 'dois', 'doit', 'devez',
    ],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER (наряд FIX-2 п. 2, LANG-1 §4) — built above: 0–20, the tens, «X et un»,
    // 70–99, the scales. Written as the language writes them; `speech()` puts them in the text's form («soixante dix»).
    'number_words' => $numbers,

    // No word joins a number after a SCALE: «cent vingt», «deux mille cinq», «mille neuf cent quatre-vingt-dix» stand side
    // by side, and «entre cent et deux cents» is two numbers.
    'number_joiners' => [],

    // «et» between a TENS word and the unit (наряд LANG-1 §4) — the joiner of «vingt et un». «un» / «une» being no number
    // words of their own, every «X et un» French says is an entry of `number_words`, and the joiner reads nothing else
    // there is to read: «vingt et trente» stays 20 and 30.
    'number_tens_joiners' => ['et'],

    // Two forms of one word in an inflected language: both at least four letters, sharing all but the last two letters
    // of the shorter («horaire» — «horaires», «réservé» — «réservée», «arrive» — «arrivez»). One letter is no content word.
    'word_forms' => ['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2],

    // THE FORMS OF A DICTIONARY WORD (наряд GEN-4 — `vocab.not_found`, FATAL, and `vocab.used_in_wrong`; {@see TermForms}):
    // the vocabulary of a day writes a word in its dictionary form, the frames and lines say it inflected; the forms no
    // rule of letters reaches are listed here under the word as a term's content word. Read by the day's checks only —
    // not the talk's `irregular_forms` (a form → its base, in the canonical form of speech, read by `WordBases`).
    // French: the irregular verbs by the infinitive («vouloir» — «veux», «falloir» — «faut», «être» — «suis»).
    'lemma_forms' => [
        'être' => ['suis', 'es', 'est', 'sommes', 'êtes', 'sont', 'étais', 'était', 'étions', 'étiez', 'étaient', 'été', 'serai', 'seras', 'sera', 'serons', 'serez', 'seront', 'serais', 'serait', 'soit', 'soyez'],
        'avoir' => ['ai', 'as', 'a', 'avons', 'avez', 'ont', 'avais', 'avait', 'avions', 'aviez', 'avaient', 'eu', 'aurai', 'aura', 'aurons', 'aurez', 'auront', 'aurais', 'aurait', 'ayez'],
        'aller' => ['vais', 'vas', 'va', 'allons', 'allez', 'vont', 'irai', 'ira', 'irons', 'irez', 'iront', 'irais', 'irait'],
        'faire' => ['fais', 'fait', 'faisons', 'faites', 'font', 'ferai', 'fera', 'ferons', 'ferez', 'feront', 'ferais', 'ferait', 'faisais', 'faisait', 'fasse'],
        'vouloir' => ['veux', 'veut', 'voulons', 'voulez', 'veulent', 'voudrais', 'voudrait', 'voudrions', 'voudriez', 'voudraient', 'voulu', 'voulais', 'voulait', 'veuillez'],
        'pouvoir' => ['peux', 'peut', 'pouvons', 'pouvez', 'peuvent', 'pourrai', 'pourra', 'pourrais', 'pourrait', 'pourrions', 'pourriez', 'pourraient', 'pu', 'pouvais', 'pouvait', 'puisse'],
        'devoir' => ['dois', 'doit', 'devons', 'devez', 'doivent', 'devrai', 'devra', 'devrais', 'devrait', 'devrions', 'devriez', 'devraient', 'dû', 'due', 'devais', 'devait'],
        'falloir' => ['faut', 'faudra', 'faudrait', 'fallait', 'fallu'],
        'savoir' => ['sais', 'sait', 'savons', 'savez', 'savent', 'saurai', 'saura', 'saurais', 'saurait', 'su', 'sachez'],
        'venir' => ['viens', 'vient', 'venons', 'venez', 'viennent', 'viendrai', 'viendra', 'viendrais', 'viendrait', 'venu', 'venue'],
        'tenir' => ['tiens', 'tient', 'tenons', 'tenez', 'tiennent', 'tenu'],
        'prendre' => ['prends', 'prend', 'prenons', 'prenez', 'prennent', 'pris', 'prise'],
        'comprendre' => ['comprends', 'comprend', 'comprenons', 'comprenez', 'comprennent', 'compris'],
        'apprendre' => ['apprends', 'apprend', 'apprenons', 'apprenez', 'apprennent', 'appris'],
        'mettre' => ['mets', 'met', 'mettons', 'mettez', 'mettent', 'mis', 'mise'],
        'dire' => ['dis', 'dit', 'disons', 'dites', 'disent'],
        'voir' => ['vois', 'voit', 'voyons', 'voyez', 'voient', 'vu', 'vue', 'verrai', 'verra'],
        'payer' => ['paie', 'paies', 'paient', 'paierai', 'paiera'],
        'envoyer' => ['envoie', 'envoies', 'envoient', 'enverrai', 'enverra'],
        'écrire' => ['écris', 'écrit', 'écrivons', 'écrivez', 'écrivent'],
        'lire' => ['lis', 'lit', 'lisons', 'lisez', 'lisent', 'lu'],
        'boire' => ['bois', 'boit', 'buvons', 'buvez', 'boivent', 'bu'],
        'connaître' => ['connais', 'connaît', 'connaissons', 'connaissez', 'connaissent', 'connu'],
        'recevoir' => ['reçois', 'reçoit', 'recevons', 'recevez', 'reçoivent', 'reçu'],
        'suivre' => ['suis', 'suit', 'suivons', 'suivez', 'suivent', 'suivi'],
        'partir' => ['pars', 'part', 'partons', 'partez', 'partent'],
        'sortir' => ['sors', 'sort', 'sortons', 'sortez', 'sortent'],
        'dormir' => ['dors', 'dort'],
        'sentir' => ['sens', 'sent', 'sentons', 'sentez', 'sentent'],
        'servir' => ['sers', 'sert'],
        'ouvrir' => ['ouvre', 'ouvres', 'ouvrent', 'ouvert'],
        'offrir' => ['offre', 'offres', 'offrent', 'offert'],
        'plaire' => ['plaît', 'plu'],
        'valoir' => ['vaut', 'vaudrait'],
        'croire' => ['crois', 'croit', 'croyons', 'croyez', 'cru'],
        'vivre' => ['vis', 'vit', 'vécu'],
        'asseoir' => ['assieds', 'assied', 'asseyez', 'assis'],
    ],

    // A number (Ⓐ, one word, folded): a digit anywhere in the word («2», «15h», «06»), a cardinal — hyphenated compounds
    // too («vingt-deux», «quatre-vingt-dix», «vingt-et-un») —, an ordinal («premier», «deuxième»), «demi», «moitié»,
    // «quart», a round amount («dizaine», «quinzaine»). Not «un» / «une» — the articles, every «une place» would be a
    // count; not «seconde» (a second of time as often).
    'number_pattern' => '/\d|^(?:zéro|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|onze|douze|treize|quatorze|quinze|seize|vingts?|trente|quarante|cinquante|soixante|septante|huitante|octante|nonante|cents?|mille|millions?|milliards?|demie?|moitié|quarts?|douzaines?|dizaines?|quinzaines?|vingtaines?|trentaines?|centaines?|milliers?|premiers?|premières?|\p{L}+ièmes?)(?:-(?:et|un|une|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|onze|douze|treize|quatorze|quinze|seize|vingts?|trente|quarante|cinquante|soixante|cents?|mille|millions?|milliards?|\p{L}+ièmes?))*$/u',

    // Time words (Ⓐ, one word, folded — «aujourd'hui», «après-midi», «week-end» are one word each, and an elided «l'» or
    // «d'» may open one: «l'heure», «d'heure»): the units, the parts of the day, the days, the months, «hier», «demain»,
    // «tôt», «tard», the hour written «15 h». Read on both sides — native (the listening's kinds of value) and target
    // (which line of the visit says a number or a time) — so it keeps to words that NAME a time: no «maintenant»,
    // «avant», «après», «ensuite».
    'time_pattern' => '/^(?:[dl]\')?(?:secondes?|minutes?|min|heures?|h|demi-heures?|jours?|journées?|semaines?|mois|ans?|années?|matins?|matinées?|après-midi|soirs?|soirées?|nuits?|midi|minuit|hier|avant-hier|aujourd\'hui|demain|après-demain|tôt|tard|lundis?|mardis?|mercredis?|jeudis?|vendredis?|samedis?|dimanches?|week-ends?|weekends?|janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre|veille|lendemain)$/u',

    // The units something is COUNTED in — what makes a value an amount and not a date («trois jours», «dix minutes», «deux
    // fois par jour» against «demain», «lundi»). Parts of the day, weekdays and months are not here.
    'amount_pattern' => '/^(?:[dl]\')?(?:secondes?|minutes?|min|heures?|demi-heures?|jours?|journées?|semaines?|mois|ans?|années?|fois|degrés?|pourcents?|kilos?|kilogrammes?|grammes?|milligrammes?|mg|litres?|millilitres?|ml|mètres?|kilomètres?|km|comprimés?|cachets?|pilules?|gélules?|gouttes?|doses?|sachets?|cuillères?|euros?|centimes?|dollars?)$/u',

    // What carries an amount and is said WITH it, standing right before it — prepositions, articles, determiners, the
    // elided «d'un(e)»: «depuis trois jours», «pendant plus d'une semaine», «dans dix minutes», «cette semaine», «au moins
    // deux fois». Read only to the left. Not «il y a» — «a» and «il» would grow «Il a deux…» out of a line.
    'amount_prefix' => '/^(?:à|au|aux|en|dans|depuis|pendant|pour|par|sous|vers|après|avant|environ|presque|près|plus|moins|de|d\'un|d\'une|du|des|le|la|les|un|une|ce|cet|cette|ces|chaque|tous|toutes|quelques|prochaine?s?|derni(?:er|ère)s?)$/u',

    // The prompt's STOP LIST (numbers, family, time words, colours, être / avoir / aller) and plain words a learner knows at
    // any level of the plan: not vocabulary.
    'everyday_words' => [
        'un', 'une', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'vingt',
        'trente', 'cent', 'mille', 'premier', 'première', 'deuxième', 'second', 'seconde', 'troisième',
        'mère', 'père', 'maman', 'papa', 'parent', 'parents', 'frère', 'sœur', 'fils', 'fille', 'enfant', 'enfants',
        'bébé', 'famille', 'mari', 'femme', 'grand-mère', 'grand-père',
        'jour', 'jours', 'semaine', 'semaines', 'mois', 'an', 'ans', 'année', 'années', "aujourd'hui", 'demain', 'hier',
        'matin', 'soir', 'nuit', 'heure', 'heures', 'minute', 'minutes', 'maintenant', 'bientôt', 'tard', 'tôt', 'temps',
        'rouge', 'bleu', 'bleue', 'vert', 'verte', 'jaune', 'noir', 'noire', 'blanc', 'blanche', 'marron', 'gris',
        'grise', 'orange', 'rose', 'violet',
        'être', 'suis', 'es', 'est', 'sommes', 'êtes', 'sont', 'avoir', 'ai', 'as', 'a', 'avons', 'avez', 'ont', 'aller',
        'vais', 'vas', 'va', 'allons', 'allez', 'vont',
        'travail', 'maison', 'école', 'homme', 'hommes', 'femmes', 'gens', 'personne', 'ami', 'amie', 'amis',
        'nourriture', 'eau', 'voiture', 'chambre', 'porte', 'table', 'nom', 'chose', 'choses', 'bon', 'bonne', 'mauvais',
        'mauvaise', 'grand', 'grande', 'petit', 'petite', 'nouveau', 'nouvelle', 'vieux', 'vieille', 'bonjour', 'salut',
        'manger', 'boire', 'voir', 'venir', 'faire', 'prendre', 'donner', 'vouloir', 'aimer', 'savoir', 'penser', 'dire',
        'regarder', 'aider', 'aide', 'lieu', 'endroit', 'ville', 'rue', 'argent', 'livre', 'téléphone', 'chien', 'chat',
        'main', 'tête', 'œil', 'yeux', 'problème', 'question', 'réponse',
    ],

    // Ordinary adjectives and quantifiers: the head of a free combination («plusieurs jours», «gros sac») that is no
    // chunk. Not «bon», «petit», «grand»: «bon appétit», «bonne journée», «petit déjeuner», «grande surface» are units of
    // the language; not «tout» («tout droit»); not «autre» («autre chose», «autre part» — «something else», «elsewhere»).
    'ordinary_heads' => [
        'gros', 'grosse', 'joli', 'jolie', 'beaucoup', 'peu', 'plusieurs', 'quelques', 'tous', 'toutes', 'différent',
        'différente', 'différents', 'différentes', 'important', 'importante', 'vrai', 'vraie', 'certain', 'certaine',
        'nombreux', 'nombreuses', 'mauvais', 'mauvaise',
    ],

    // A partner line that says nothing but «we are done» — its whole text, punctuation aside (Ⓐ: «d'accord», «c'est» one
    // word each). «Et avec ceci ?» is the shop's «Anything else?».
    'closers' => [
        "d'accord", 'parfait', "c'est parfait", 'très bien', 'bien', 'entendu', "c'est entendu", "c'est noté", 'noté',
        'merci', 'merci beaucoup', 'je vous remercie', 'de rien', 'avec plaisir', 'je vous en prie', 'pas de problème',
        'aucun problème', 'bien sûr', 'certainement', 'à bientôt', 'à demain', 'au revoir', 'bonne journée',
        'bonne soirée', 'bonne fin de journée', 'excellent', 'super', 'génial', "c'est bon", 'ça marche', 'ça va',
        "c'est tout", 'ce sera tout', 'ça sera tout', 'voilà', 'ok', 'okay', 'autre chose', 'vous désirez autre chose',
        "rien d'autre", 'et avec ceci', 'à votre service', "à tout à l'heure",
    ],

    // The verbs a check uses to name who said something («Que dit le patient ?», «Que demande-t-elle ?», «Quelle question
    // pose-t-il ?») — built above.
    'saying_verbs' => $saying,

    // The word a partner names alternatives with («aujourd'hui ou demain ?»).
    'alternative_words' => ['ou'],

    // One sentence that goes on after a comma with «et / ou» and asks again — a question word, «est-ce que», or an
    // inverted verb («Vous avez de la fièvre, et depuis quand ?», «…, ou avez-vous mal ailleurs ?»). An intonation question
    // after «et» («…, et vous avez de la fièvre ?», «…, et vous ?») is not read: it is often one question.
    'second_question_pattern' => '/,\s*(?:et|ou)\s+(?:est-ce|qu[\'’]est-ce|quel|quelle|quels|quelles|quand|où|comment|combien|pourquoi|qui|depuis\s+quand|à\s+quelle|avez-vous|êtes-vous|pouvez-vous|voulez-vous|souhaitez-vous|as-tu|es-tu|peux-tu|veux-tu|y\s+a-t-il)(?![\p{L}])[^?]*\?/iu',

    // Articles: «an article after an article» at the seam of a frame and its filler («Je voudrais un ___» + «une place»),
    // left out of the judge's comparison and of speech. The elided «l'» is no word of its own anywhere: the judge spells it
    // «le» through `contractions`. «des», «du», «au», «aux» are spelt out there too («de les», «à le»), so their article
    // goes and their preposition stays.
    'articles' => ['le', 'la', 'les', 'un', 'une'],

    // WORDS A SENTENCE CANNOT END ON (наряд FIX-3 §7), as the judge reads a move (elisions and «des», «du», «au» spelt
    // out): a definite article, a possessive, a demonstrative, a preposition that strands nothing, «et», «que» — «J'ai mal
    // à la», «Mon numéro, c'est le», «Je voudrais prendre rendez-vous chez». Not «un», «une» («J'en voudrais une»), not a
    // subject pronoun («puis-je», «avez-vous» are read as two words), not «pour», «avec», «sans» («Je suis pour», «Je
    // viens avec»). «le», «la», «les» can close an imperative («Faites-le») — rare in a visit, and listed.
    'dangling_words' => [
        'le', 'la', 'les', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'votre', 'nos', 'vos',
        'ce', 'cet', 'cette', 'ces', 'de', 'à', 'dans', 'sur', 'chez', 'et', 'que',
    ],

    // A word the seam of a frame and its filler may say twice and still be French: the reflexive pronoun after its
    // subject — «Vous ___ ?» + «vous appelez comment», «Nous ___.» + «nous voyons demain». Every other doubled word at
    // the seam counts.
    'seam_repeatable_words' => ['vous', 'nous'],

    // THE ARTICLE THAT CHANGES WITH THE NEXT WORD'S SOUND: «cet» before a vowel, «ce» before a consonant («cet après-midi»,
    // «ce matin»). Words that open with «h» are left alone («cet homme», «ce héros» — the h aspiré is not in the spelling),
    // and so is «y» («ce yaourt») and an initialism. The exceptions are «onze», «onzième» (no liaison: «ce onze») and the
    // words the PRONOUN «ce» stands before, a vowel or not — «Je ne sais pas ce ___» + «à quoi ça sert», «Sur ce, ___ !»
    // + «à demain», «au revoir»: «à», «au», «aux», «en», «avec», «où» are never the noun of a determiner «ce» / «cet», and a
    // pronoun read as the article there is a fatal `filler.ungrammatical` on a healthy line. The elision of «le», «la»,
    // «de» is no pair of two words and is not read.
    'article_sound' => [
        'before_vowel' => 'cet',
        'before_consonant' => 'ce',
        'vowel' => '/^[aeiouàâäéèêëîïôöùûü]/u',
        'consonant' => '/^[bcçdfgjklmnpqrstvwxz]/u',
        'spelled' => '/^(?:\p{Lu}{2,}|\p{Lu}-)/u',
        'exception' => '/^(?:onze|onzièmes?|à|au|aux|en|avec|où)$/u',
    ],

    // A clause where a value should stand. Narrow on purpose (a SENTENCE at the start of a filler after a frame's words is
    // fatal): a subject pronoun and a finite form of être / avoir / pouvoir / aller («vous avez rendez-vous», «il est
    // dix heures»), or «c'est», «j'ai» — a subject and its verb in one word of the text. «il y a» is no sentence here
    // («y» is no verb). A filler that opens with «lorsque», «puisque», «parce que» is a clause; «si», «quand», «comme»,
    // «que», «où» only before a subject («si vous voulez», never «si possible», «comme d'habitude»).
    'clause' => [
        'subjects' => ['je', 'tu', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'elles'],
        'finite' => [
            'suis', 'es', 'est', 'sommes', 'êtes', 'sont', 'ai', 'as', 'a', 'avons', 'avez', 'ont',
            'peux', 'peut', 'pouvons', 'pouvez', 'peuvent', 'vais', 'vas', 'va', 'allons', 'allez', 'vont',
        ],
        'contractions' => ["c'est", "j'ai"],
        'subordinators' => ['lorsque', 'puisque', 'parce', 'quoique', "lorsqu'il", "lorsqu'elle", "lorsqu'on", "puisqu'il", "puisqu'elle", "puisqu'on"],
        'subordinators_before_subject' => ['si', 'quand', 'comme', 'que', 'où'],
    ],

    // «The frame must stand alone: no unresolved it / that / one / there» (FRAMES). French drops no subject and has no
    // «there» of existence of its own («il y a»); what a frame can lean on is «ça» / «cela» — at the start of the frame it
    // is the visit's own subject («Ça fait ___.», as en «It gets worse when I ___») and left alone, anywhere else it is
    // resolved by a determiner and a content word before it. The object pronouns le / la / les are the articles, and
    // «il» the impersonal subject («Il faut ___») — not listed.
    'unresolved_pronouns' => [
        'words' => ['ça', 'cela'],
        'frame_initial_subject' => ['ça', 'cela'],
        'existential' => [],
        'determiner_or_number' => [],
        'partitive' => [],
        'be_forms' => [],
        'determiners' => [
            'le', 'la', 'les', 'un', 'une', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'votre',
            'nos', 'vos', 'leur', 'leurs', 'ce', 'cet', 'cette', 'ces',
        ],
    ],

    // A past participle or an adjective after «je suis / j'étais / je serais» (a «ne», «me», and one adverb may stand
    // between) — the learner's gender said in their own line while it is unknown: «Je suis désolé», «je suis arrivée», «je
    // suis inscrit», «je me suis levé». The endings -é(e), -i(e), -u(e), -is(e), -it(e); the words of `$genderless` above
    // («ici», «aussi», the weekdays, the first names of «Je suis Marie Dupont») say no gender and are left out.
    // Heuristic, like the Russian one: «prêt», «content», «sûr» are gendered too and not read.
    'gendered_past_pattern' => '/(?<![\p{L}])(?:je\s+(?:ne\s+)?(?:me\s+)?(?:suis|serai|serais)|j[\'’]étais|je\s+(?:ne\s+|n[\'’])?(?:m[\'’])?étais)\s+(?:(?:pas|déjà|jamais|bien|très|trop|encore|vraiment|tellement|toujours|plus|aussi|si|tout|toute)\s+)?(?!(?:'.implode('|', $genderless).')(?![\p{L}]))(\p{L}+(?:é|ée|i|ie|u|ue|is|ise|it|ite))(?![\p{L}])/u',

    // «The native frame contains NO word that agrees with the slot in gender or number» (FRAMES) — read at the slot:
    //  - the word right before `___` agrees when it is one of `words` — an article, a contracted «du», «au», a possessive, a
    //    demonstrative, «quel», a quantifier, an adjective that stands before its noun («Je voudrais un ___», «C'est pour
    //    ma ___», «Quelle ___ ?», «le premier ___») — or one of `short_forms`;
    //  - one of the two words after `___` agrees when it is one of them — the participle of a slot that is the subject
    //    («___ est inclus ?», «___ est réservée»). The same list is read there, so an article or a possessive that opens
    //    the next noun is read too («Je cherche ___ pour mon fils») — a warning the reader discounts.
    // No ending is read (`suffixes_before_slot` empty): French adjectives follow their noun, and an ending read before the
    // slot would take verbs («Je voudrais ___»). The adjectives with one form for both genders — «disponible», «libre»,
    // «nécessaire» — are left out: they agree in number only, and they stand before a slot that is no subject of theirs
    // (the fr→en scouting frame «Quels horaires sont disponibles ___ ?» + «aujourd'hui»).
    'agreement' => [
        'words' => [
            'le', 'la', 'les', 'l', 'un', 'une', 'des', 'du', 'au', 'aux',
            'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'votre', 'nos', 'vos', 'leur', 'leurs',
            'ce', 'cet', 'cette', 'ces', 'quel', 'quelle', 'quels', 'quelles',
            'tout', 'toute', 'tous', 'toutes', 'aucun', 'aucune', 'quelques', 'certain', 'certaine', 'certains',
            'certaines', 'autre', 'autres',
            'premier', 'première', 'premiers', 'premières', 'dernier', 'dernière', 'derniers', 'dernières', 'prochain',
            'prochaine', 'prochains', 'prochaines', 'nouveau', 'nouvel', 'nouvelle', 'nouveaux', 'nouvelles', 'bon', 'bonne',
            'bons', 'bonnes', 'petit', 'petite', 'petits', 'petites', 'grand', 'grande', 'grands', 'grandes', 'seul', 'seule',
            'seuls', 'seules',
        ],
        'short_forms' => [
            'inclus', 'incluse', 'incluses', 'compris', 'comprise', 'comprises', 'ouvert', 'ouverte', 'ouverts', 'ouvertes',
            'fermé', 'fermée', 'fermés', 'fermées', 'réservé', 'réservée', 'réservés', 'réservées', 'payé', 'payée', 'payés',
            'payées', 'confirmé', 'confirmée', 'confirmés', 'confirmées', 'occupé', 'occupée', 'occupés', 'occupées', 'pris',
            'prise', 'prises', 'prêt', 'prête', 'prêts', 'prêtes', 'gratuit', 'gratuite', 'gratuits', 'gratuites',
            'remboursé', 'remboursée', 'remboursés', 'remboursées', 'complet', 'complète', 'complets', 'complètes',
            'autorisé', 'autorisée', 'autorisés', 'autorisées', 'prévu', 'prévue', 'prévus', 'prévues',
        ],
        'suffixes_before_slot' => [],
        'min_letters' => 99,
        'after_slot_words' => 2,
    ],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а) — what a rescue move of the talk says in the learner's own bubble
    // (кадр 37-7, en «Sorry?»): the French «Pardon ?», the no-break space before the mark as French prints it.
    'rescue_line' => "Pardon\u{00A0}?",

    // THE RESCUE KIT (наряд LANG-1b §2): the six lines a learner of this language says when stuck, each with its translation into
    // every learner's language of the plan. Row 1 is `rescue_line`. Said in the learner's voice, filed by (target,
    // gender, voice, line) — `RescueKits`. The same matrix writes every pack: `docs/research/lang-1b/tools/rescue-kit.py`.
    'rescue' => [
        ['target' => "Pardon\u{00A0}?", 'native' => ['ru' => 'Простите?', 'uk' => 'Перепрошую?', 'be' => 'Прабачце?', 'pl' => 'Słucham?', 'ro' => 'Poftim?', 'es' => '¿Perdón?', 'it' => 'Scusi?', 'de' => 'Wie bitte?', 'fr' => "Pardon\u{00A0}?"]],
        ['target' => "Vous pouvez parler plus lentement, s'il vous plaît\u{00A0}?", 'native' => ['ru' => 'Можно помедленнее, пожалуйста?', 'uk' => 'Можна повільніше, будь ласка?', 'be' => 'Можна павольней, калі ласка?', 'pl' => 'Proszę mówić trochę wolniej.', 'ro' => 'Puteți vorbi mai rar, vă rog?', 'es' => '¿Puede hablar más despacio, por favor?', 'it' => 'Può parlare più lentamente, per favore?', 'de' => 'Können Sie bitte langsamer sprechen?', 'fr' => "Vous pouvez parler plus lentement, s'il vous plaît\u{00A0}?"]],
        ['target' => 'Je ne comprends pas.', 'native' => ['ru' => 'Я не понимаю.', 'uk' => 'Я не розумію.', 'be' => 'Я не разумею.', 'pl' => 'Nie rozumiem.', 'ro' => 'Nu înțeleg.', 'es' => 'No entiendo.', 'it' => 'Non capisco.', 'de' => 'Ich verstehe nicht.', 'fr' => 'Je ne comprends pas.']],
        ['target' => 'Un instant.', 'native' => ['ru' => 'Одну минуту.', 'uk' => 'Хвилинку.', 'be' => 'Хвілінку.', 'pl' => 'Chwileczkę.', 'ro' => 'Un moment.', 'es' => 'Un momento.', 'it' => 'Un momento.', 'de' => 'Einen Moment.', 'fr' => 'Un instant.']],
        ['target' => "Vous pouvez me l'écrire\u{00A0}?", 'native' => ['ru' => 'Можете это записать?', 'uk' => 'Можете це записати?', 'be' => 'Можаце гэта запісаць?', 'pl' => 'Proszę mi to zapisać.', 'ro' => 'Îmi puteți scrie asta?', 'es' => '¿Me lo puede escribir?', 'it' => 'Me lo può scrivere?', 'de' => 'Können Sie mir das aufschreiben?', 'fr' => "Vous pouvez me l'écrire\u{00A0}?"]],
        ['target' => 'Merci.', 'native' => ['ru' => 'Спасибо.', 'uk' => 'Дякую.', 'be' => 'Дзякуй.', 'pl' => 'Dziękuję.', 'ro' => 'Mulțumesc.', 'es' => 'Gracias.', 'it' => 'Grazie.', 'de' => 'Danke.', 'fr' => 'Merci.']],
    ],

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9) — the same line as every pack's, in this language, in the «vous» of
    // the roles (en «I see. Please go on.», ru «Понятно. Продолжайте, пожалуйста.»).
    'neutral_reply' => "Je vois. Continuez, s'il vous plaît.",

    // THE FORMS OF ONE WORD and THE PERSONS SWAPPED (наряд BACK-TAILS-2 §§2, 9) — the no-ops, written: French words are
    // compared as they are, after the canonical form. That form drops the apostrophe and glues the elided pronoun to its
    // verb («j'ai» is «jai», «m'appelle» «mappelle»), so no one-word-for-one swap can turn «jai» into «vous avez», and a
    // base of «suis» / «êtes» would meet nothing the learner's line spells; a swap of persons is no word for a word either
    // («votre» is «mon» or «ma»). Written wrong they would find echoes nobody said — left for a later order.
    'irregular_forms' => [],
    'inflection_rules' => [],
    'person_swap' => [],

    // ─── THE JUDGE OF THE TALK'S CONSTRUCTIONS (наряд FIX-4 §2, LANG-1 §1) — what the judge may forgive of French.

    // A CONTRACTION IS ITS WORDS. Whole words first: «s'il» is «si il» (never the elision «s'» → «se»), «au», «aux», «du»,
    // «des» are a preposition and an article — «J'ai mal au ventre» says «J'ai mal à ___», «J'ai besoin des résultats»
    // says «J'ai besoin de ___» (the article is then left out of the comparison). Then the ELISIONS — a key ending with an
    // apostrophe is a prefix (pack-keys §4.4): «j'ai» is «je ai», «n'est» «ne est», «l'hôpital» «le hôpital», «c'est» «ce
    // est», «qu'il» «que il», «jusqu'à» «jusque à»; either apostrophe counts.
    'contractions' => [
        "s'il" => 'si il', "s'ils" => 'si ils',
        'au' => 'à le', 'aux' => 'à les', 'du' => 'de le', 'des' => 'de les',
        "j'" => 'je', "n'" => 'ne', "l'" => 'le', "d'" => 'de', "m'" => 'me', "t'" => 'te', "s'" => 'se', "c'" => 'ce',
        "qu'" => 'que', "jusqu'" => 'jusque', "lorsqu'" => 'lorsque', "puisqu'" => 'puisque', "quoiqu'" => 'quoique',
    ],
    'contractions_before' => [],

    // THE WORDS A MOVE MAY OPEN WITH before its construction: «Bonjour madame, je voudrais un rendez-vous», «D'accord,
    // super, j'arrive dix minutes avant», «Euh, en fait, j'ai de la fièvre». Read as the judge reads a move («d'accord» is
    // «de accord», «excusez-moi» two words).
    'intro_words' => [
        'bonjour', 'bonsoir', 'salut', 'oui', 'non', 'si', 'ok', 'okay', "d'accord", 'alors', 'bon', 'ben', 'bah', 'eh bien',
        'bien', 'très bien', 'parfait', 'super', 'génial', 'entendu', 'tout à fait', 'en fait', "c'est ça", 'merci',
        'merci beaucoup', "s'il vous plaît", "s'il te plaît", 'pardon', 'excusez-moi', 'désolé', 'désolée', 'ah', 'oh',
        'euh', 'hum', 'et', 'donc', 'écoutez', 'attendez', 'voilà', 'bien sûr', 'madame', 'monsieur', 'mademoiselle',
        'docteur',
    ],

    // THE WORDS A NEW CLAUSE OPENS WITH (наряд FIX-4b §1): «J'ai mal à la gorge et j'ai de la fièvre» says both
    // constructions.
    'clause_starters' => ['et', 'mais', 'donc', 'alors', 'puis', 'ensuite', 'ou', 'car', 'parce que'],

    // A CONSTRUCTION SAID IN THE NEGATIVE IS THE SAME CONSTRUCTION (DECISIONS п. 395, наряд LANG-1 §1): «ne» and «pas»
    // anywhere in the move — «Je n'ai pas de fièvre» says «J'ai ___», spoken «J'ai pas de fièvre» too, «Demain matin, ce
    // n'est pas bon» says «___, c'est bon.» — and «n», what a written «n'» leaves when it is not read as the elision.
    'negation' => ['words' => ['ne', 'pas', 'n'], 'after' => null, 'do_support' => []],

    // A QUANTITY SAYS ITS OWN «DE»: «J'ai ___ d'expérience» is said as «Je n'ai aucune expérience», «J'ai une certaine
    // expérience» — a window of one of these determiners may leave out the frame's «de» after it. «beaucoup de», «un peu
    // de» keep it. Compared as written: folded, lower case.
    'partitive' => [
        'word' => 'de',
        'determiners' => ['aucun', 'aucune', 'certain', 'certaine', 'quelque'],
    ],

    // THE TITLE OF A TALK (наряд LANG-1 §6): French declension is no matter here — the title names the roles in no case,
    // «Conversation : réceptionniste et médecin», the no-break space before the colon as French prints it, the first letter
    // lowered unless the role is an acronym.
    'talk_title_template' => [
        'title' => "Conversation\u{00A0}: {roles}",
        'and' => 'et',
        'anyone' => 'Conversation',
        'lower_first' => true,
    ],
];
