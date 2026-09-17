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
| language needs (`script`, `time_pattern`, `gendered_past_pattern`, `agreement`) are null — no plan has
| English as the learner's language.
|
| Every list is a counter's reading of a rule, not the rule: the codes built on them are heuristics and the
| canon names them so.
*/
return [
    // The writing a reading of the target is spelled in — read only when this is the learner's language.
    'script' => null,

    // One letter of that writing, matched alone — a native-side key (наряд BACK-TAILS-1 §3.2).
    'script_letters' => null,

    // The marks a sentence ends with, and what each says. «Does it end with a question?», «how many
    // sentences?», «does the native frame end the way the target frame ends?» are asked with these.
    'sentence_ends' => ['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis'],

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

    // Two forms of one word: the shorter's letters but its last `stem_tail`, never fewer than `stem_min`, shared
    // from the start («heat» — «heating», «use» — «used»). A content word is at least `content_min_letters` long.
    'word_forms' => ['stem_min' => 3, 'stem_tail' => 3, 'content_min_letters' => 1],

    // A number: a digit anywhere in the word («2», «400», «14a»), a number word, or number words hyphenated
    // («thirty-nine»).
    'number_pattern' => '/\d|^(?:zero|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand|million|half|dozen|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|once|twice)(?:-(?:zero|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|thirty|forty|fifty|sixty|seventy|eighty|ninety|hundred|thousand|million|half|dozen|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|once|twice))*$/u',

    // Time words of the learner's language, for «is this answer a number or a time» — a native-side key.
    'time_pattern' => null,

    // The units an amount is counted in — a native-side key: what «Поймай число» may offer as an option.
    'amount_pattern' => null,

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

    // A word the seam may say twice and still be English: the particle of a phrasal verb before a preposition that
    // opens the filler («I can move in ___» + «in June», «Can I check out ___» + «out of the room»), «that that», «had
    // had». «my my», «the the», «is is» stay a doubled word (GEN-2b, the rent day of the live run).
    'seam_repeatable_words' => ['in', 'on', 'out', 'off', 'up', 'down', 'over', 'back', 'away', 'through', 'around', 'by', 'that', 'had'],

    // The article that changes with the next word's sound, and how the sound is read off the spelling. An
    // initialism is read by its letters («an MRI», «an X-ray») and «one» starts with a «w»: both left alone;
    // «u» and «h» are left alone too («a university», «an hour»).
    'article_sound' => [
        'before_vowel' => 'an',
        'before_consonant' => 'a',
        'vowel' => '/^[aeio]/u',
        'consonant' => '/^[bcdfgjklmnpqrstvwyz]/u',
        'spelled' => '/^(?:[A-Z]{2,}|[A-Z]-)/u',
        'exception' => '/^one/u',
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

    // Learner's-language keys: a gendered past form after «I», words that agree with the slot.
    'gendered_past_pattern' => null,
    'agreement' => null,
];
