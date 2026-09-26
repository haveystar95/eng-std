<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LANGUAGE PACK · en — what the lesson validator reads of English
|--------------------------------------------------------------------------
|
| docs/plan-v2.md §4 (наряд GEN-2b). A rule of the validator is about the PAIR of languages (the lesson's
| target, the learner's own), never about English or Russian: everything a rule has to know about a language
| lives in that language's pack. A key set to null is not written for this language yet — the rule that needs
| it does not run and counts `lang.pack_missing`; it never borrows another language's words.
|
| English is written as a TARGET language: what the learner says and hears. The keys only a learner's own
| language needs (`script`, `time_pattern`, `amount_pattern`, `amount_prefix`, `gendered_past_pattern`,
| `agreement`) are written as the key spec's no-ops (наряд LANG-1: no key of a pack is null) — no plan has English
| as the learner's language (LanguageRoles::planNatives()), so nothing reads them, and a no-op reads exactly as the
| null it replaced did.
|
| Every list is a counter's reading of a rule, not the rule: the codes built on them are heuristics and the
| canon names them so.
*/
return [
    // The writing a reading of the target is spelled in — read only when this is the learner's language, and English
    // never is: English's own alphabet, digits, marks, spaces and the stress mark, written for completeness (наряд LANG-1:
    // `script` is the STRICT alphabet, `script_letters` the whole writing).
    'script' => '/^[A-Za-z\p{N}\p{P}\s\x{0301}]*$/u',

    // One letter of the LATIN writing, matched alone (наряд LANG-1 §5). English is nobody's own language, so no reading
    // is ever checked against it (`pronunciation.foreign_script` reads the learner's pack, and the learner is never
    // English); it is written for the OTHER packs: two languages are neighbours exactly when their packs write this very
    // string, and the guard of the role's translation ({@see \App\Modules\Plan\Domain\Service\ReplyNative}) tells a
    // Polish, Romanian, Spanish, Italian, German or French learner's grey line from an English one only when English says
    // it writes Latin letters. The key spec's reference string, character for character — any other spelling of the same
    // letters makes English nobody's neighbour.
    'script_letters' => '/^[\p{Latin}]$/u',

    // FREQUENT AND DISTINCTIVE (наряд LANG-1 §5, `common_words`): some forty words English lines are full of that are NO
    // ordinary word, in the same spelling, of any other Latin language of the plan (pl ro es it de fr). The order said «the
    // 30 most frequent words»; the list is «frequent and distinctive» on purpose. The guard reads a learner's grey line
    // against EVERY neighbour, and a word that is also an ordinary word of the learner's own language, but sits in
    // English's list only, counts as English inside the learner's own line — two such words and an honest translation is
    // refused (a probe refused «Для записи к врачу приходите до двенадцати» for ru because a uk list held «для» and «до»).
    // So the most frequent English words another language of the plan spells alike are left out — «a», «i», «in», «to»,
    // «on», «do», «go», «so», «no», «me», «my», «we», «he», «by», «am», «an», «as», «or», «but», «also», «her», «was»,
    // «will», «are», «has», «not», «don», «come», «still», «bring», «name» (pl «to», «my», «go», «we», «do»; ro «are»,
    // «am»; es «has», «he», «me», «don»; it «so», «do», «come»; de «was», «will», «also», «her», «Not», «still», «bring»,
    // «Name»; fr «on», «or», «as», «but») — and «okay», «sorry», «hello», which every language says. «today» and
    // «tomorrow» are here because the role's lines say them all the time: without them «We have 10 a.m. today or 3 p.m.
    // tomorrow.», sent back as its «translation», held one listed word and passed. One run of letters each, lower case: the
    // guard splits a line on everything else, the apostrophe too («don't» is «don», «t»; «you're» is «you», «re»).
    'common_words' => [
        'the', 'you', 'your', 'is', 'it', 'and', 'of', 'for', 'with', 'this', 'that', 'have', 'had', 'been',
        'does', 'did', 'can', 'could', 'would', 'should', 'need', 'like', 'what', 'which', 'how', 'when', 'where',
        'there', 'here', 'about', 'from', 'before', 'at', 'some', 'yes', 'please', 'thank', 'she', 'they',
        'today', 'tomorrow',
    ],

    // The marks a sentence ends with, and what each says. «Does it end with a question?», «how many
    // sentences?», «does the native frame end the way the target frame ends?» are asked with these.
    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

    // Words whose dot ends no sentence (наряд CHECK-1): «3 p.m.» in a slot is a time, not a sentence of its own, and
    // «We have 3 p.m. and 5:30 p.m. today.» is one sentence. Read case-insensitively, as a word on its own. Without the
    // key every dot ends a sentence. «No.» (the number) is not listed: it is also «No.» the answer, and with it listed
    // «Oh no.» would end with no mark and a filler «No.» would pass as a value.
    'abbreviations' => ['a.m.', 'p.m.', 'e.g.', 'i.e.', 'etc.', 'vs.', 'Mr.', 'Mrs.', 'Ms.', 'Dr.', 'St.'],

    // A question is known by its word order too, mark or no mark: a last sentence that opens with an auxiliary and
    // a subject pronoun («May I see your passport», «Do you have any bags») asks. Without this key a question is
    // only its question mark — and a repair that deletes the mark from «Can I see your passport first?» passes
    // (GEN-2b: P2R on the cheap model did exactly that, twice).
    'question_word_order' => [
        'auxiliaries' => ['can', 'could', 'may', 'might', 'will', 'would', 'shall', 'should', 'must', 'do', 'does', 'did', 'is', 'are', 'was', 'were', 'am', 'have', 'has', 'had'],
        'subjects' => ['i', 'you', 'he', 'she', 'it', 'we', 'they', 'there'],
    ],

    // Words that carry no content of their own: the server's speaking key takes the part of the frame with more words
    // that are not one of them; a repeated pair of two of them copies nothing.
    'function_words' => [
        'a', 'an', 'the', 'to', 'of', 'in', 'on', 'at', 'for', 'with', 'by', 'from', 'up', 'down', 'about',
        'into', 'over', 'after', 'before', 'under', 'and', 'or', 'but', 'so', 'if', 'than', 'as', 'because',
        'while', 'then', 'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being', 'do', 'does', 'did',
        'have', 'has', 'had', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them',
        'my', 'your', 'his', 'its', 'our', 'their', 'this', 'that', 'these', 'those', 'there', 'here',
        'can', 'could', 'will', 'would', 'should', 'shall', 'may', 'might', 'must', 'not', 'no', 'yes',
        'please', 'what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how', 'some', 'any',
        'just', 'very', 'too', 'also', 'all', 'each', 'every', 'both', 'either', 'neither', 'one', 'ones',
        "don't", "doesn't", "didn't", "isn't", "aren't", "wasn't", "weren't", "can't", "won't", "i'm",
        "i've", "i'll", "i'd", "it's", "you're", "we're", "they're", "let's", "that's", "there's",
        'okay', 'ok', 'well', 'oh', 'sorry', 'thanks', 'thank', 'sure', 'let',
    ],

    // THE WORDS A RECOGNISER EATS (наряд FIX-2, п. 2) — articles, prepositions, auxiliary and modal verbs: left out
    // of BOTH sides when a line the learner is LOOKING AT is compared with what was said
    // ({@see \App\Modules\Shared\Domain\ValueObject\SpeechMode::Repeat}). Narrower than `function_words` on purpose:
    // that list is about what carries no content for a speaking KEY, and it holds «what», «no», «one», «please» —
    // words a repeat must not forgive, because dropping them changes the sentence. Written in the canonical form of
    // the comparison, so no contractions: «I'd» is already «i would» by the time this list is read, and «not» is
    // NOT here — it flips the meaning.
    'unstressed_words' => [
        'a', 'an', 'the',
        'to', 'of', 'in', 'on', 'at', 'for', 'with', 'by', 'from', 'into', 'about', 'over', 'under', 'after',
        'before', 'through', 'up', 'down', 'out', 'off', 'as', 'than',
        'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'do', 'does', 'did', 'have', 'has', 'had',
        'will', 'would', 'shall', 'should', 'can', 'could', 'may', 'might', 'must',
    ],

    // A NUMBER SAID EITHER WAY IS THE SAME NUMBER (наряд FIX-2, п. 2): a recogniser writes «3» where the card says
    // «three» and the other way round, and the learner said one thing. Read as whole words after the text is
    // canonicalised, so a hyphenated «thirty-nine» arrives as two words and folds to «30 9» on both sides.
    'number_words' => [
        'zero' => '0', 'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5', 'six' => '6',
        'seven' => '7', 'eight' => '8', 'nine' => '9', 'ten' => '10', 'eleven' => '11', 'twelve' => '12',
        'thirteen' => '13', 'fourteen' => '14', 'fifteen' => '15', 'sixteen' => '16', 'seventeen' => '17',
        'eighteen' => '18', 'nineteen' => '19', 'twenty' => '20', 'thirty' => '30', 'forty' => '40',
        'fifty' => '50', 'sixty' => '60', 'seventy' => '70', 'eighty' => '80', 'ninety' => '90',
        'hundred' => '100', 'thousand' => '1000', 'million' => '1000000',
    ],

    // The word that joins the parts of one number said the British way (наряд FIX-3 §4: «составные складываются»): «one
    // hundred and twenty» is 120, «two thousand and five» 2005 — only after a SCALE (hundred, thousand, million) and before
    // a number below a hundred; «two hundred and a thousand» stays two numbers. English writes no `number_tens_joiners`
    // (наряд LANG-1 §4): after a tens word «and» starts the next number — «between twenty and one hundred» is 20 and 100.
    'number_joiners' => ['and'],

    // …and none after a tens word (наряд LANG-1 §4): the key's no-op, written — «between twenty and one hundred» stays two
    // numbers, and the phone is served the very speech block it was before the key existed (an empty list goes out as none).
    'number_tens_joiners' => [],

    // Two forms of one word: the shorter's letters but its last `stem_tail`, never fewer than `stem_min`, shared
    // from the start («heat» — «heating», «use» — «used»). A content word is at least `content_min_letters` long.
    'word_forms' => ['stem_min' => 3, 'stem_tail' => 3, 'content_min_letters' => 1],

    // A number: a digit anywhere in the word («2», «400», «14a»), a number word, or number words hyphenated
    // («thirty-nine»).
    'number_pattern' => '/\d|^(?:zero|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand|million|half|dozen|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|once|twice)(?:-(?:zero|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand|million|half|dozen|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|once|twice))*$/u',

    // Time words of the learner's language, for «is this answer a number or a time» — a native-side key. The no-op, never
    // matching: English is nobody's own language, and a target's time words would change which line «Поймай число» picks
    // (key spec §3.12) — so, as when this key was null, a time is only what `number_pattern` calls a number.
    'time_pattern' => '/(?!)/u',

    // The units an amount is counted in, and what carries one — native-side keys: what «Поймай число» may offer as
    // an option, and in what form. No-ops, never matching (they were null): only a numeral is an amount, nothing grows
    // to its left.
    'amount_pattern' => '/(?!)/u',
    'amount_prefix' => '/(?!)/u',

    // The prompt's STOP LIST (numbers, family, time words, colours, be / have / go) and plain words a learner
    // knows at any level of the plan: not vocabulary.
    'everyday_words' => [
        'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
        'twenty', 'thirty', 'forty', 'fifty', 'hundred', 'thousand', 'first', 'second', 'third',
        'mother', 'father', 'mom', 'mum', 'dad', 'parent', 'parents', 'brother', 'sister', 'son', 'daughter',
        'child', 'children', 'kid', 'kids', 'baby', 'family', 'husband', 'wife', 'grandmother', 'grandfather',
        'day', 'days', 'week', 'weeks', 'month', 'months', 'year', 'years', 'today', 'tomorrow', 'yesterday',
        'morning', 'evening', 'night', 'time', 'hour', 'hours', 'minute', 'minutes', 'now', 'later', 'soon',
        'red', 'blue', 'green', 'yellow', 'black', 'white', 'brown', 'grey', 'gray', 'orange', 'pink', 'purple',
        'be', 'is', 'am', 'are', 'was', 'were', 'have', 'has', 'had', 'go', 'goes', 'went', 'gone',
        'work', 'house', 'home', 'school', 'man', 'woman', 'men', 'women', 'people', 'person', 'friend',
        'food', 'water', 'car', 'room', 'door', 'table', 'name', 'thing', 'things', 'good', 'bad', 'big',
        'small', 'new', 'old', 'hello', 'eat', 'drink', 'see', 'come', 'get', 'make', 'take', 'give', 'want',
        'like', 'know', 'think', 'say', 'tell', 'look', 'need', 'help', 'place', 'city', 'street', 'money',
        'book', 'phone', 'job', 'dog', 'cat', 'hand', 'head', 'eye', 'eyes', 'problem', 'question', 'answer',
    ],

    // Ordinary adjectives and quantifiers: the head of a free combination («heavy things») that is no chunk.
    'ordinary_heads' => [
        'big', 'small', 'little', 'good', 'bad', 'nice', 'great', 'heavy', 'many', 'much', 'lot', 'few',
        'some', 'other', 'different', 'important', 'real', 'whole',
    ],

    // A partner line that says nothing but «we are done» — its whole text, punctuation aside.
    'closers' => [
        'anything else', 'is there anything else', 'great', 'sounds good', 'sounds great', 'perfect', 'okay',
        'ok', 'all right', 'alright', 'good', 'fine', 'thank you', 'thanks', "you're welcome", 'no problem',
        'sure', 'of course', 'have a nice day', 'see you', 'got it', 'excellent', 'wonderful', 'nice',
        "that's great", "that's fine", 'no worries',
    ],

    // The verbs a check uses to name who said something («What does the parent say?»).
    'saying_verbs' => [
        'say', 'says', 'said', 'tell', 'tells', 'told', 'answer', 'answers', 'answered', 'mention', 'mentions',
        'mentioned', 'reply', 'replies', 'replied', 'want', 'wants', 'wanted',
    ],

    // The word a partner names alternatives with («sit, stand, or bend»).
    'alternative_words' => ['or'],

    // One sentence that goes on after a comma with «and / or» and a new auxiliary: two questions in one
    // bubble («When did it start, and did you lift anything heavy?»).
    'second_question_pattern' => '/,\s*(?:and|or)\s+(?:do|does|did|is|are|was|were|have|has|had|can|could|will|would|should)\b[^?]*\?/iu',

    // Articles: «an article after an article» at the seam of a frame and its filler.
    'articles' => ['a', 'an', 'the'],

    // WORDS A SENTENCE CANNOT END ON (наряд FIX-3 §7: «обрывок ≠ „не понял"»): a move of the talk that stops on one of
    // them — «Yes my», «I have a» — broke off, and the role's «not understood» of it is not counted. Only the words that
    // never close a sentence: «her», «his», «this» can («I told her», «It's his», «I'd like this»).
    'dangling_words' => ['a', 'an', 'the', 'my', 'your', 'our', 'their'],

    // A word the seam may say twice and still be English: the particle of a phrasal verb before a preposition that
    // opens the filler («I can move in ___» + «in June», «Can I check out ___» + «out of the room»), «that that», «had
    // had». «my my», «the the», «is is» stay a doubled word (GEN-2b, the rent day of the live run).
    'seam_repeatable_words' => ['in', 'on', 'out', 'off', 'up', 'down', 'over', 'back', 'away', 'through', 'around', 'by', 'that', 'had'],

    // The article that changes with the next word's sound, and how the sound is read off the spelling. An
    // initialism is read by its letters («an MRI», «an X-ray») and «one», «once» start with a «w», «eu» with a «y»
    // («a one-way ticket», «a once-daily tablet», «a euro account», «a European health insurance card» — every one of
    // them a FATAL `filler.ungrammatical` at the seam of «… a ___» before the review of LANG-1): all left alone; «u» and
    // «h» are left alone too («a university», «an hour»).
    'article_sound' => [
        'before_vowel' => 'an',
        'before_consonant' => 'a',
        'vowel' => '/^[aeio]/u',
        'consonant' => '/^[bcdfgjklmnpqrstvwyz]/u',
        'spelled' => '/^(?:[A-Z]{2,}|[A-Z]-)/u',
        'exception' => '/^(?:one|once|eu)/u',
    ],

    // A clause where a value should stand. `subjects` + `finite` at the start of a filler, or a contraction
    // that is a subject with its verb, is a whole sentence («I am patient»); a filler that opens with a
    // subordinator («if the fever returns»), or with a word that is a subordinator only before a subject
    // («after he eats», never «after meals»), or says a subject and its verb anywhere, is a clause.
    'clause' => [
        'subjects' => ['i', 'we', 'he', 'she', 'they', 'it', 'you'],
        'finite' => ['am', 'is', 'are', 'was', 'were', 'have', 'has', 'had', 'do', 'does', 'did', 'can', 'will'],
        'contractions' => ["i'm", "it's", "we're", "they're", "he's", "she's", "you're"],
        'subordinators' => ['if', 'when', 'whenever', 'because', 'while', 'unless', 'although', 'though', 'whether'],
        'subordinators_before_subject' => ['after', 'before', 'since', 'until', 'till', 'once', 'as'],
    ],

    // «The frame must stand alone: no unresolved it / that / one / there» (FRAMES). A frame-initial «It» is the
    // visit's own subject («It gets worse when I ___», the prompt's example) and left alone; «there» next to a
    // form of «be» is existential («Is there ___?»); «that» and «one» before a content word, the slot or «of» are
    // a determiner, a number, a part («that room», «one ___», «one of ___»). A pronoun is resolved when the frame
    // names a thing before it — a determiner with a content word («The soup — does it have ___?»).
    'unresolved_pronouns' => [
        'words' => ['it', 'that', 'one', 'there'],
        'frame_initial_subject' => ['it'],
        'existential' => ['there'],
        'determiner_or_number' => ['that', 'one'],
        'partitive' => ['of'],
        'be_forms' => ['is', 'are', 'was', 'were', 'be', 'been', "isn't", "aren't", "wasn't", "weren't", "there's"],
        'determiners' => ['the', 'a', 'an', 'my', 'your', 'his', 'her', 'our', 'their', 'this', 'these', 'those'],
    ],

    // Learner's-language keys: a gendered past form after «I», words that agree with the slot. English says neither
    // (an English past has no gender, an English determiner does not agree) and is nobody's own language: the key spec's
    // no-ops, never read.
    'gendered_past_pattern' => '/(?!)/u',
    'agreement' => ['words' => [], 'short_forms' => [], 'suffixes_before_slot' => [], 'min_letters' => 99, 'after_slot_words' => 0],

    // «НЕ ПОНЯЛ» НА ЯЗЫКЕ ЦЕЛИ (наряд CONV-2, п. 4а): what a rescue move of the talk says in the learner's own bubble —
    // кадр 37-7 draws «Sorry?» there, and a rescue used to come with no words at all. One short line a learner says
    // when they did not catch the partner; the role hears it in HISTORY too.
    'rescue_line' => 'Sorry?',

    // THE RESCUE KIT (наряд LANG-1b §2): the six lines a learner of this language says when stuck, each with its translation into
    // every learner's language of the plan. Row 1 is `rescue_line`. Said in the learner's voice, filed by (target,
    // gender, voice, line) — `RescueKits`. The same matrix writes every pack: `docs/research/lang-1b/tools/rescue-kit.py`.
    'rescue' => [
        ['target' => 'Sorry?', 'native' => ['ru' => 'Простите?', 'uk' => 'Перепрошую?', 'be' => 'Прабачце?', 'pl' => 'Słucham?', 'ro' => 'Poftim?', 'es' => '¿Perdón?', 'it' => 'Scusi?', 'de' => 'Wie bitte?', 'fr' => "Pardon\u{00A0}?"]],
        ['target' => 'Could you say that more slowly, please?', 'native' => ['ru' => 'Можно помедленнее, пожалуйста?', 'uk' => 'Можна повільніше, будь ласка?', 'be' => 'Можна павольней, калі ласка?', 'pl' => 'Proszę mówić trochę wolniej.', 'ro' => 'Puteți vorbi mai rar, vă rog?', 'es' => '¿Puede hablar más despacio, por favor?', 'it' => 'Può parlare più lentamente, per favore?', 'de' => 'Können Sie bitte langsamer sprechen?', 'fr' => "Vous pouvez parler plus lentement, s'il vous plaît\u{00A0}?"]],
        ['target' => 'I don\'t understand.', 'native' => ['ru' => 'Я не понимаю.', 'uk' => 'Я не розумію.', 'be' => 'Я не разумею.', 'pl' => 'Nie rozumiem.', 'ro' => 'Nu înțeleg.', 'es' => 'No entiendo.', 'it' => 'Non capisco.', 'de' => 'Ich verstehe nicht.', 'fr' => 'Je ne comprends pas.']],
        ['target' => 'One moment.', 'native' => ['ru' => 'Одну минуту.', 'uk' => 'Хвилинку.', 'be' => 'Хвілінку.', 'pl' => 'Chwileczkę.', 'ro' => 'Un moment.', 'es' => 'Un momento.', 'it' => 'Un momento.', 'de' => 'Einen Moment.', 'fr' => 'Un instant.']],
        ['target' => 'Can you write it down?', 'native' => ['ru' => 'Можете это записать?', 'uk' => 'Можете це записати?', 'be' => 'Можаце гэта запісаць?', 'pl' => 'Proszę mi to zapisać.', 'ro' => 'Îmi puteți scrie asta?', 'es' => '¿Me lo puede escribir?', 'it' => 'Me lo può scrivere?', 'de' => 'Können Sie mir das aufschreiben?', 'fr' => "Vous pouvez me l'écrire\u{00A0}?"]],
        ['target' => 'Thank you.', 'native' => ['ru' => 'Спасибо.', 'uk' => 'Дякую.', 'be' => 'Дзякуй.', 'pl' => 'Dziękuję.', 'ro' => 'Mulțumesc.', 'es' => 'Gracias.', 'it' => 'Grazie.', 'de' => 'Danke.', 'fr' => 'Merci.']],
    ],

    // THE ROLE'S NEUTRAL MOVE (наряд BACK-TAILS-2 §9): what the role says when its answer, asked for twice, was nothing
    // but the learner's own words said back — one short line that keeps the scene going and says nothing of its own.
    // Every pack writes the same line in its own language: the target's is said, the learner's is its translation.
    'neutral_reply' => 'I see. Please go on.',

    // THE TITLE OF A TALK (наряд LANG-1 §6): the no-op. An English title is the code's (`NativeStrings::TALK_TITLE`, «Talk
    // to the receptionist and the doctor»), whatever the pack says — no template of a pack is ever read for English.
    'talk_title_template' => [],

    // THE FORMS OF ONE WORD (наряд BACK-TAILS-2 §2): «did the learner use the phrase» is read off the phrase's KEY WORDS,
    // and a word said in another form is the same word — «works» for «work», «bought» for «buy». A word's bases are the
    // word itself, its line in `irregular_forms`, and what every rule of `inflection_rules` that matches it leaves
    // (a regular ending taken off: -s, -es, -ed, -ing, a possessive «'s», which the canonical form spells «s»); two
    // words are one when their bases meet. Written in the canonical form of the comparison: lower case, no apostrophe.
    // A language without `irregular_forms` compares its words exactly, after the canonical form.
    'irregular_forms' => [
        'am' => 'be', 'is' => 'be', 'are' => 'be', 'was' => 'be', 'were' => 'be', 'been' => 'be', 'being' => 'be',
        'has' => 'have', 'had' => 'have', 'having' => 'have',
        'does' => 'do', 'did' => 'do', 'done' => 'do', 'doing' => 'do',
        'goes' => 'go', 'went' => 'go', 'gone' => 'go',
        'got' => 'get', 'gotten' => 'get',
        'made' => 'make', 'took' => 'take', 'taken' => 'take', 'gave' => 'give', 'given' => 'give', 'came' => 'come',
        'saw' => 'see', 'seen' => 'see', 'knew' => 'know', 'known' => 'know', 'thought' => 'think', 'told' => 'tell',
        'said' => 'say', 'says' => 'say', 'found' => 'find', 'felt' => 'feel', 'left' => 'leave', 'brought' => 'bring',
        'bought' => 'buy', 'paid' => 'pay', 'kept' => 'keep', 'began' => 'begin', 'begun' => 'begin', 'wrote' => 'write',
        'written' => 'write', 'ate' => 'eat', 'eaten' => 'eat', 'drank' => 'drink', 'drunk' => 'drink', 'slept' => 'sleep',
        'spoke' => 'speak', 'spoken' => 'speak', 'broke' => 'break', 'broken' => 'break', 'lost' => 'lose', 'met' => 'meet',
        'sat' => 'sit', 'stood' => 'stand', 'ran' => 'run', 'sent' => 'send', 'spent' => 'spend', 'wore' => 'wear',
        'worn' => 'wear', 'chose' => 'choose', 'chosen' => 'choose', 'forgot' => 'forget', 'forgotten' => 'forget',
        'understood' => 'understand', 'held' => 'hold', 'fell' => 'fall', 'fallen' => 'fall', 'fed' => 'feed',
        'bled' => 'bleed', 'caught' => 'catch', 'taught' => 'teach', 'sold' => 'sell', 'drove' => 'drive',
        'driven' => 'drive', 'rode' => 'ride', 'ridden' => 'ride', 'flew' => 'fly', 'flown' => 'fly', 'grew' => 'grow',
        'grown' => 'grow', 'threw' => 'throw', 'thrown' => 'throw', 'shown' => 'show', 'woke' => 'wake', 'woken' => 'wake',
        'meant' => 'mean', 'heard' => 'hear',
        'children' => 'child', 'men' => 'man', 'women' => 'woman', 'people' => 'person', 'feet' => 'foot',
        'teeth' => 'tooth', 'mice' => 'mouse', 'knives' => 'knife', 'wives' => 'wife', 'lives' => 'life',
        'halves' => 'half', 'shelves' => 'shelf',
        // A pronoun's object form is the same pronoun: «you told me» says back «I told you».
        'me' => 'i', 'him' => 'he', 'us' => 'we', 'them' => 'they',
    ],

    // The regular endings, each a pattern and the base it leaves — every rule that matches gives one base, so «used»
    // is both «us» and «use», and «stopped» is «stopp», «stope» and «stop»: a wrong base meets nothing, the right one
    // meets its word. Longer endings and the spellings they change (tries → try, running → run) come first.
    'inflection_rules' => [
        ['/^(.{2,})ies$/u', '$1y'],
        ['/^(.{2,})ied$/u', '$1y'],
        ['/^(.{2,}?)([bdfgklmnprstvz])\2(?:ed|ing)$/u', '$1$2'],
        ['/^(.{2,})ing$/u', '$1'],
        ['/^(.{2,})ing$/u', '$1e'],
        ['/^(.{2,})ed$/u', '$1'],
        ['/^(.{2,})ed$/u', '$1e'],
        ['/^(.{2,})es$/u', '$1'],
        ['/^(.{2,})s$/u', '$1'],
    ],

    // THE FIRST AND THE SECOND PERSON, SWAPPED (наряд BACK-TAILS-2 §9): what the learner said about themselves, said back
    // by the role, turns «I» into «you» — «My son has a fever» comes back «Your son has a fever». The role's echo of the
    // learner's move is looked for in the move as said and in the move with these words swapped.
    'person_swap' => [
        'i' => 'you', 'me' => 'you', 'my' => 'your', 'mine' => 'yours', 'am' => 'are',
        'you' => 'i', 'your' => 'my', 'yours' => 'mine', 'are' => 'am',
    ],

    // ─── THE JUDGE OF THE TALK'S CONSTRUCTIONS (наряд FIX-4 §2): a frame is said as a COHERENT PHRASE — its part before
    // the window as an unbroken run of words where the move begins, a window of its own, its part after the window
    // straight after it ({@see \App\Modules\Plan\Domain\Service\FrameJudge}). What the judge may forgive of English is here.

    // A CONTRACTION IS ITS WORDS: both sides spelt out before the words are compared — «I'm working on» is «i am working
    // on», «I don't have» is «i do not have». Read as a whole word, a typographic apostrophe as a plain one.
    'contractions' => [
        "i'm" => 'i am', "i'll" => 'i will', "i've" => 'i have', "i'd" => 'i would',
        "you're" => 'you are', "you'll" => 'you will', "you've" => 'you have', "you'd" => 'you would',
        "he's" => 'he is', "she's" => 'she is', "it's" => 'it is', "he'll" => 'he will', "she'll" => 'she will',
        "it'll" => 'it will', "he'd" => 'he would', "she'd" => 'she would',
        "we're" => 'we are', "we'll" => 'we will', "we've" => 'we have', "we'd" => 'we would',
        "they're" => 'they are', "they'll" => 'they will', "they've" => 'they have', "they'd" => 'they would',
        "that's" => 'that is', "there's" => 'there is', "here's" => 'here is', "what's" => 'what is',
        "where's" => 'where is', "who's" => 'who is', "how's" => 'how is', "let's" => 'let us',
        "don't" => 'do not', "doesn't" => 'does not', "didn't" => 'did not', "isn't" => 'is not', "aren't" => 'are not',
        "wasn't" => 'was not', "weren't" => 'were not', "haven't" => 'have not', "hasn't" => 'has not', "hadn't" => 'had not',
        "can't" => 'can not', 'cannot' => 'can not', "won't" => 'will not', "wouldn't" => 'would not',
        "couldn't" => 'could not', "shouldn't" => 'should not', "mustn't" => 'must not',
    ],

    // …AND ONE THE NEXT WORD DECIDES: before «been», «'s» is «has» and «'d» is «had» — «he's been sick» is «he has been
    // sick», never «he is been» (the rule the kernel's canonical form reads forward too).
    'contractions_before' => ['been' => ["'s" => 'has', "'d" => 'had']],

    // THE WORDS A MOVE MAY OPEN WITH before its construction: «Hello, what kind of memberships do you have», «OK thank
    // you, how do I use this machine». A frame is said where the move begins or straight after a run of these.
    'intro_words' => [
        'hello', 'hi', 'hey', 'yes', 'no', 'okay', 'ok', 'oh', 'well', 'so', 'sure', 'great', 'nice', 'thanks',
        'thank you', 'please', 'and', 'um', 'uh',
    ],

    // THE WORDS A NEW CLAUSE OPENS WITH (наряд FIX-4b §1): a learner glues two constructions into one sentence — «Yes, this
    // is my first visit and I have about a year of experience» — and the second one starts straight after one of these,
    // a comma before it or not. A frame is said there too; nowhere else in the middle of a sentence («Hello what kind of
    // memberships do you have» is still no «Do you have ___?»: no conjunction stands before «do»).
    'clause_starters' => ['and', 'but', 'so', 'then', 'or'],

    // A CONSTRUCTION SAID IN THE NEGATIVE IS THE SAME CONSTRUCTION (канон владельца, DECISIONS п. 395; наряд FIX-4 §2):
    // «I don't have any experience» says «I have ___ of experience», «he doesn't have a fever» says «He has ___». Inside
    // the frame's own words the judge does not count as a difference `word` after one of `after` (be, a modal, the
    // auxiliary have), nor `do_support` + `word` before a verb — and that verb is read by its base (has = have).
    'negation' => [
        'word' => 'not',
        'do_support' => ['do', 'does', 'did'],
        'after' => ['am', 'is', 'are', 'was', 'were', 'be', 'been', 'will', 'would', 'shall', 'should', 'can', 'could', 'may', 'might', 'must', 'have', 'has', 'had'],
    ],

    // THE PARTITIVE «OF» GOES WITH A QUANTITY, NOT WITH A DETERMINER: «I have ___ of experience» is said as «I have no
    // experience», «I don't have any experience», «I have some experience» — a window of one of `determiners` takes the
    // frame's `word` after it or leaves it out (канон владельца, наряд FIX-4 §2: «I have no experience» — said).
    'partitive' => [
        'word' => 'of',
        'determiners' => ['no', 'any', 'some', 'much', 'little', 'enough', 'more', 'less'],
    ],
];
