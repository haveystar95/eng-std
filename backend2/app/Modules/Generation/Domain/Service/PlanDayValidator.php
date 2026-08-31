<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\PlanDayCandidate;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;
use App\Modules\Shared\Domain\Service\LanguagePurity;

/**
 * A day of a plan, judged.
 *
 * ## Why this exists at all
 *
 * There is no validator of the CORE anywhere in this app — `translation`, `description`, `example`
 * are checked by the prompt and by a person reading them, and that has been an acceptable trade
 * for a collection, where a weak card is a weak card. It is not an acceptable trade for a plan.
 * A plan is a mechanism: the day promises abilities, the conversation at the end of the day ticks
 * them off, and if no line in the day's material can tick a checkpoint then the day cannot be
 * passed and the learner finds out at the conversation. That is a MECHANICAL failure, and
 * mechanical failures are what deterministic code is for (docs/research/plan-sandbox-2026-08-29.md
 * §8, вопрос 2).
 *
 * So the rules below are the ones where being wrong breaks the machine, not the ones where being
 * wrong makes a card less pretty. Taste stays with the prompt.
 *
 * ## What v0.2 added, and what it took away
 *
 * The day is now three arrays with three exact counts, and the two rules that matter most are new:
 *
 * **The frame.** A line is a frame with a slot — «I worked on ___» — and `text` is that line with
 * a real word of the day in the hole. **Every word of the day fits some frame, and its `example` is
 * that sentence.** That is what turns eight lines and six words into twenty sentences the learner
 * can say instead of eight they memorised, and it is checkable: build the frame into a regular
 * expression, put the term in the slot, and look for it in the example.
 *
 * **The slot lives in `frame` and nowhere else.** v0.2.1's own addition, and it is here because the
 * live day failed on it: four lines of eight came back with `___` still standing in `text`. See
 * {@see SLOT_FORBIDDEN_IN}.
 *
 * ## What v0.3 changed, after four refusals in a row
 *
 * Two of the rules above were RIGHT and unenforceable by asking, so v0.3 stopped asking.
 *
 * **The line is assembled, not written.** The model returns `frame` and `filler` and the server
 * pastes them ({@see \App\Modules\Generation\Application\Service\PlanDayComposer::assemble()}).
 * «A line that is not its own frame» and «`___` left in `text`» stop being classes of defect and
 * become impossible constructions. What is checked instead is the pair that produced the line:
 * a frame carries {@see FRAME_SLOT_COUNT} at most one slot, {@see FILLER_MISMATCH} a filler exactly
 * when there is a slot to fill, and {@see FILLER_NOT_A_CARD} a filler that is some card of THIS
 * day, character for character. `day.frame_mismatch` is gone with the defect it named.
 *
 * **A connector lives in a frame, not in its hole.** Four answers in a row built the frame AROUND
 * the connector — «I mainly work with ___» — and that is how the language works, not a mistake.
 * So a chunk's example should CONTAIN a frame of this day that carries it anywhere
 * ({@see CHUNK_OUTSIDE_FRAME}) — containment, on exactly the terms a word's example is judged by,
 * so «I mainly work with Laravel, mostly.» is a legitimate example and not a near miss. The extra
 * condition is what earns the card its slot: the sentence may not be a line of the day repeated.
 * The «in the hole» rule stays for `words`, and stays FATAL for them and only for them.
 *
 * ## What is WARNED about rather than refused
 *
 * The formula cap, the missing question, the missing repair move, the silent interlocutor and the
 * connector standing outside every frame are counted and reported ({@see warnings()}) instead of
 * failing the day. They are taste with a number attached: a day with one formula too many is a
 * slightly worse day, and it is not worth a second paid call. Every one of them earned its way
 * onto this list by refusing a day that was fine — most recently the connector rule, which threw
 * away a whole live day because one example dropped the «Sorry,» from the front of its frame. The
 * counters are what a growing problem looks like — see
 * {@see \App\Modules\Generation\Application\Port\PlanDefectReporter}.
 *
 * **The interlocutor's lines are quoted, not invented.** A line marked `speaker: role` has to be,
 * character for character, one of the scene's `opening_lines`. The learner is going to hold that
 * conversation; a line the skeleton never promised is a line they meet unprepared.
 *
 * And the BAND is gone. v0.1 accepted 35–55% replies and forced the server's own `ceil(0.45 ×
 * budget)` inside that range, which meant two numbers had to be kept in step by hand. The server
 * now hands the model three exact counts ({@see \App\Modules\Learning\Domain\Service\DayCapacity::split()})
 * and this counts against them. A day one card off is not a generous day; it is a day the learner
 * did not ask for.
 *
 * ## The one check that repairs instead of refusing
 *
 * TRANSLITERATION. It is not judged here at all any more — {@see transliterationFor()} returns the
 * repaired hint or null, and the caller drops the field, logs it and counts it
 * ({@see \App\Modules\Generation\Application\Port\PlanDefectReporter}). Under v0.1 a stray
 * comma in one hint failed the whole day and bought a second paid generation whose second answer
 * failed the same way. A pronunciation hint is the one field a card can live without: it is the
 * LAST measure, it is visible in the log, and it is not the norm.
 */
final class PlanDayValidator
{
    public const ARRAY_COUNT = 'day.array_count';
    public const TERM_COUNT = 'day.term_count';
    public const KIND_MISMATCH = 'day.kind_mismatch';
    public const CHECKPOINT_UNCOVERED = 'day.checkpoint_uncovered';
    public const CHECKPOINT_ON_WORD = 'day.checkpoint_on_word';
    public const CHECKPOINT_OUT_OF_RANGE = 'day.checkpoint_out_of_range';
    // `day.frame_share` and `day.frame_mismatch` were here until v0.3. The first became a WARNING
    // ({@see FORMULA_CAP}); the second named a defect the assembly makes unconstructable — a line
    // built from its own frame is its own frame, always.
    public const FRAME_SLOT_COUNT = 'day.frame_slot_count';
    public const FILLER_MISMATCH = 'day.filler_mismatch';
    public const FILLER_NOT_A_CARD = 'day.filler_not_a_card';
    public const SLOT_OUTSIDE_FRAME = 'day.slot_outside_frame';
    public const ROLE_LINE_INVENTED = 'day.role_line_invented';
    public const EXAMPLE_IS_A_TERM = 'day.example_is_a_term';
    public const EXAMPLE_DUPLICATED = 'day.example_duplicated';
    public const EXAMPLE_MISSING = 'day.example_missing';
    public const KEY_IS_THE_TERM = 'day.key_is_the_term';
    public const KEY_DUPLICATED = 'day.key_duplicated';
    public const KEY_NOT_SUPPORT_LANGUAGE = 'day.key_not_support_language';
    public const DESCRIPTION_GIVES_AWAY = 'day.description_gives_away';
    public const IMAGE_PROMPT_MISSING = 'day.image_prompt_missing';

    /** The counters {@see warnings()} raises — named here because the Domain is what names them. */
    public const FORMULA_CAP = 'plan_day_formula_cap';

    public const NO_QUESTION = 'plan_day_no_question';

    public const NO_REPAIR = 'plan_day_no_repair';

    public const FILLER_MISMATCH_WARNING = 'plan_day_filler_mismatch';

    public const CHUNK_OUTSIDE_FRAME = 'plan_day_chunk_outside_frame';

    public const NO_ROLE_LINE = 'plan_day_no_role_line';

    /**
     * A WORD THAT FITS NO FRAME OF THE DAY — counted since v0.3.1, fatal before it.
     *
     * `day.substitution_without_frame` was the last gate standing between a live day and `ready`:
     * the v0.3 run failed on it three times running, always on ONE word of fourteen cards
     * («English team», whose example was a good sentence that happened not to be a frame of the
     * day). One card is not a broken day — and since v0.3.1 one card is not even a re-generation:
     * it is a P2R address ({@see \App\Modules\Generation\Application\Service\PlanDayRepairer}).
     * The rule is unchanged and now said in the log instead of paid for.
     */
    public const SUBSTITUTION_OUTSIDE_FRAME = 'plan_day_substitution_outside_frame';

    /**
     * MORE THAN A QUARTER OF THE LINES ARE THE INTERLOCUTOR'S — counted, not refused.
     *
     * The ceiling moved to the same footing as its own floor ({@see NO_ROLE_LINE}), which has been
     * a warning since the day it was written. A day with three role lines of eight is a slightly
     * lopsided day; refusing it costs a paid call for a shape defect, and the asymmetry of п. 198
     * says which of the two is worse.
     */
    public const ROLE_LINE_SHARE = 'plan_day_role_line_share';

    /**
     * The one language where «the filler is a card of this day, character for character» is a rule
     * a correct answer can obey.
     *
     * English substitutes bare: «the payment module» goes into «I worked on ___» unchanged. A
     * language with cases does not — «поясница» is the card and «в пояснице» is what the frame
     * needs, and a model that inflects correctly would be refused for it while one that pastes the
     * nominative into a prepositional slot would pass. Until that is decided properly (P2 v0.3.1),
     * the gate is FATAL for English and a counted WARNING everywhere else
     * ({@see FILLER_MISMATCH_WARNING}): the defect stays visible without refusing days for being
     * grammatical.
     */
    private const STRICT_FILLER_LANG = 'en';

    /**
     * The most lines that may be fixed formulas with no slot — «Nice to meet you».
     *
     * A third, ROUNDED UP since v0.3, and it stopped being fatal at the same time. Both changes
     * come from the same measurement: on the live «собеседование» day three of the eight lines had
     * no slot, and all three were right — «Yes, I can hear you clearly», the interlocutor's own
     * quoted line, and «I'm a PHP developer with three years of experience», which cannot be a
     * frame because `PHP` is a goal_term and holds its place. Refusing that day cost a second paid
     * call and would have kept costing one. A day with one formula too many is a slightly worse
     * day; it is not worth $0.05, so it is counted and reported instead ({@see FORMULA_CAP}).
     */
    private const MAX_FORMULA_SHARE = 1 / 3;

    /**
     * What a repair move sounds like — the learner saying they did not catch it.
     *
     * A LIST AND NOT A RULE, and it lives in config rather than here for the reason every list of
     * phrases eventually needs: it will be wrong, and being wrong should not be a code change. The
     * default is the six the live run's «глазами» section asked for by name, under the one target
     * language a plan has ever run in. Matched on word boundaries inside the normalised line, so
     * «Could you repeat the question?» matches «repeat» and «repeatedly» does not.
     *
     * A language with NO list — or one absent from the map — has the check switched off rather
     * than failing it: «nobody has written German's list yet» must not read as «every German day
     * lacks a repair move».
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_REPAIR_MARKERS = [
        'en' => [
            'repeat', 'say that again', 'slow down', "didn't catch", 'breaking up',
            'not sure I understood',
        ],
        'de' => [],
    ];

    /** The most lines that may be the interlocutor's rather than the learner's own. */
    private const MAX_ROLE_SHARE = 1 / 4;

    /** The slot in a frame. */
    private const SLOT = PlanDayItem::SLOT;

    /**
     * Where the slot is allowed to be, and therefore — everywhere else it is a defect.
     *
     * The live «собеседование» day left `___` in the `text` of four lines of eight: not a line with
     * a hole for the learner to fill, a line the learner cannot say at all. v0.2.1 says it in one
     * sentence («`___` never appears in `text`, in `example`, or in any field other than `frame`»)
     * and this is the same sentence, counted.
     *
     * `transliteration` is NOT on this list, and that is not an oversight: the reading hint is the
     * one field of a day that is repaired or dropped and never fails it (реестр решений, п. 189).
     * A hint with a slot in it is dropped like any other unusable hint.
     */
    private const SLOT_FORBIDDEN_IN = [
        'text', 'translation', 'description', 'example', 'example_translation', 'image_api_prompt',
    ];

    /**
     * What the slot becomes while a frame is being turned into a regular expression.
     *
     * A private-use codepoint and NOT the obvious `\0`, which `trim()` strips by default — a frame
     * whose slot sits at the end came out of {@see normalize()} with no slot at all, and every line
     * of every day was «not its own frame». One character, one silent gate, twenty minutes.
     */
    private const SLOT_MARK = "\u{E000}";

    /**
     * Sentence punctuation a transliteration picks up by reflex from the line it transcribes.
     * Stripped, not rejected: the field is a pronunciation hint, and a full stop at the end of one
     * is a typographic accident, not a broken hint.
     */
    private const SENTENCE_PUNCTUATION = [
        '.', ',', '?', '!', ';', ':', '"', '“', '”', '«', '»', '(', ')', '[', ']', '…', '–', '—',
    ];

    /** Marks a hint legitimately carries — a hyphen inside a word, an apostrophe inside one. */
    private const HINT_MARKS = [' ', '-', '\'', '’', '‑'];

    /**
     * ONE Latin word — the unit the day-vocabulary exemption is measured in ({@see dayVocabulary()},
     * {@see keyIsPure()}).
     *
     * A run of Latin letters, plus the apostrophe that lives INSIDE an English word («I'm»,
     * «don't»). A hyphen deliberately does not join: «backend-разработчик» is one Russian word
     * whose Latin half is `backend`, and the half is what has to be recognised.
     */
    private const LATIN_WORD = "/[A-Za-z]+(?:['\u{2019}][A-Za-z]+)*/u";

    /**
     * The shortest token the day's own vocabulary may excuse. One letter is not a word: it is a
     * size («размер L»), an initial, or a stray — evidence of nothing, and the whole exemption is
     * built on a token being EVIDENCE that the day teaches it.
     */
    private const MIN_VOCABULARY_TOKEN = 2;

    /** @param array<string, list<string>> $repairMarkers target language => phrases; {@see DEFAULT_REPAIR_MARKERS} */
    public function __construct(
        private readonly LanguagePurity $purity = new LanguagePurity(),
        private readonly SupportLanguageText $supportText = new SupportLanguageText(),
        private readonly array $repairMarkers = self::DEFAULT_REPAIR_MARKERS,
    ) {}

    /** @return list<PlanViolation> empty = the day may be written */
    public function validate(PlanDayCandidate $day): array
    {
        $violations = $this->checkCounts($day);
        if ($day->items === []) {
            return $violations;
        }

        return [
            ...$violations,
            ...$this->checkKinds($day),
            ...$this->checkCheckpoints($day),
            ...$this->checkFrames($day),
            ...$this->checkExamples($day->items),
            ...$this->checkKeys($day),
            ...$this->checkPerCard($day),
        ];
    }

    /**
     * THE HINT, REPAIRED — or null when it cannot be saved.
     *
     * The one rule that fixes instead of failing, and the reason is measured: the live gate
     * ({@see EnrichmentValidator::transliterationFor()}) allows a hint only a space, a hyphen and
     * an apostrophe, which is the right rule for a WORD. A line is a sentence and carries a full
     * stop and a comma by definition, so on plan material that gate fired as a lottery — 5 hints of
     * 16 thrown away on one day, 0 on another that happened not to end in a full stop
     * (docs/research/plan-sandbox-2026-08-29.md §7.2).
     *
     * So: strip sentence punctuation, then apply the alphabet rule unchanged. A hint with a Latin
     * letter in a Russian field is still refused — that one defeats the field for exactly the
     * reader it exists for, and no amount of stripping makes it readable. Refused means the FIELD
     * is dropped, never the day: see the class docblock.
     */
    public function transliterationFor(string $supportLang, ?string $raw): ?string
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return null;
        }

        $text = trim(str_replace(self::SENTENCE_PUNCTUATION, ' ', $text));
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        if ($text === '') {
            return null;
        }

        // Digits and brackets are gone by now; anything left that is not a letter or one of the
        // marks a spoken word carries means the model annotated instead of transliterating.
        $stripped = str_replace(self::HINT_MARKS, '', $text);
        if (preg_match('/^\p{L}*$/u', $stripped) !== 1) {
            return null;
        }

        return $this->purity->foreignScriptLetters($supportLang, $text) === [] ? $text : null;
    }

    /**
     * Do the two languages use different scripts — i.e. is a reading hint MANDATORY on this day?
     *
     * Cyrillic support with a Latin target means every term, always. When both share a script the
     * hint is optional and its absence is not a defect worth a line in the log.
     */
    public function scriptsDiffer(string $supportLang, string $targetLang): bool
    {
        return self::scriptOf($supportLang) !== self::scriptOf($targetLang);
    }

    /**
     * Which alphabet a language is written in — enough of them to answer «does this pair need a
     * reading hint», and no more. Anything unlisted is treated as Latin, which is the right guess
     * for a European language and the harmless one: it only ever means «no hint is mandatory».
     */
    private static function scriptOf(string $lang): string
    {
        return match (mb_strtolower(substr(trim($lang), 0, 2))) {
            'ru', 'uk', 'be', 'bg', 'sr', 'mk' => 'cyrillic',
            'el' => 'greek',
            'he' => 'hebrew',
            'ar', 'fa' => 'arabic',
            'ka' => 'georgian',
            'hy' => 'armenian',
            'zh', 'ja', 'ko' => 'cjk',
            'th' => 'thai',
            default => 'latin',
        };
    }

    /**
     * THE THREE NUMBERS, and the sum. A day one card off is rejected whole.
     *
     * @return list<PlanViolation>
     */
    private function checkCounts(PlanDayCandidate $day): array
    {
        $violations = [];

        $counted = [
            PlanDayItem::KIND_LINE => 0,
            PlanDayItem::KIND_WORD => 0,
            PlanDayItem::KIND_CHUNK => 0,
        ];
        foreach ($day->items as $item) {
            if (isset($counted[$item->kind])) {
                $counted[$item->kind]++;
            }
        }

        $expected = [
            PlanDayItem::KIND_LINE => $day->phraseCount,
            PlanDayItem::KIND_WORD => $day->wordCount,
            PlanDayItem::KIND_CHUNK => $day->chunkCount,
        ];

        $arrays = [
            PlanDayItem::KIND_LINE => 'phrases',
            PlanDayItem::KIND_WORD => 'words',
            PlanDayItem::KIND_CHUNK => 'chunks',
        ];

        foreach ($expected as $kind => $want) {
            if ($counted[$kind] !== $want) {
                $violations[] = PlanViolation::onAnswer(
                    self::ARRAY_COUNT,
                    "«{$kind}»: {$counted[$kind]}, а день просил {$want}",
                    "`{$arrays[$kind]}` holds {$counted[$kind]} cards and the day asked for {$want}",
                );
            }
        }

        if (count($day->items) !== $day->termBudget) {
            $violations[] = PlanViolation::onAnswer(
                self::TERM_COUNT,
                'карточек ' . count($day->items) . ', а день просил ' . $day->termBudget,
                'the day holds ' . count($day->items) . ' cards in all and asked for ' . $day->termBudget,
            );
        }

        return $violations;
    }

    /**
     * The four fields that describe what a card IS have to agree with each other.
     *
     * @return list<PlanViolation>
     */
    private function checkKinds(PlanDayCandidate $day): array
    {
        $violations = [];

        foreach ($day->items as $item) {
            $isLine = $item->kind === PlanDayItem::KIND_LINE;

            if ($item->isLine !== $isLine) {
                $violations[] = PlanViolation::onCard(
                    self::KIND_MISMATCH,
                    $item,
                    'is_line',
                    "`is_line` говорит одно, а «{$item->kind}» — другое",
                    '`is_line` contradicts the array this card stands in',
                );
            }

            if ($isLine && ! in_array($item->speaker, [PlanDayItem::SPEAKER_LEARNER, PlanDayItem::SPEAKER_ROLE], true)) {
                $violations[] = PlanViolation::onCard(
                    self::KIND_MISMATCH,
                    $item,
                    'speaker',
                    'у реплики нет говорящего — непонятно, произносит её юзер или собеседник',
                    'a line needs `speaker`: `learner` or `role`',
                );
            }

            if (! $isLine && $item->speaker !== null) {
                $violations[] = PlanViolation::onCard(
                    self::KIND_MISMATCH,
                    $item,
                    'speaker',
                    'у подстановки есть говорящий, хотя её никто не произносит целиком',
                    'a word or a connector has no `speaker` — nobody says it as a whole turn',
                );
            }

            // A connector is a phrasal verb or a fixed collocation. `word` is the one lexical type
            // it cannot be: a one-word term joins nothing.
            if ($item->kind === PlanDayItem::KIND_CHUNK && $item->type === 'word') {
                $violations[] = PlanViolation::onCard(
                    self::KIND_MISMATCH,
                    $item,
                    'type',
                    'связка объявлена как одно слово — связка соединяет, а одно слово не соединяет ничего',
                    'a connector cannot have `type: word` — one word joins nothing',
                );
            }
        }

        return $violations;
    }

    /** @return list<PlanViolation> */
    private function checkCheckpoints(PlanDayCandidate $day): array
    {
        $violations = [];
        $closed = [];

        foreach ($day->items as $item) {
            $covers = $item->coversCheckpoint;
            if ($covers === null) {
                continue;
            }

            if ($item->kind !== PlanDayItem::KIND_LINE) {
                $violations[] = PlanViolation::onCard(
                    self::CHECKPOINT_ON_WORD,
                    $item,
                    'covers_checkpoint',
                    "подстановка помечена как закрывающая чек-пойнт {$covers}; чек-пойнт закрывается репликой",
                    'only a line may close a checkpoint; a word or a connector must send `null`',
                );

                continue;
            }

            if ($covers < 1 || $covers > $day->checkpointCount) {
                $violations[] = PlanViolation::onCard(
                    self::CHECKPOINT_OUT_OF_RANGE,
                    $item,
                    'covers_checkpoint',
                    "чек-пойнт {$covers} не существует — их у дня {$day->checkpointCount}",
                    "checkpoint {$covers} does not exist; this day has {$day->checkpointCount}",
                );

                continue;
            }

            $closed[$covers] = true;
        }

        for ($i = 1; $i <= $day->checkpointCount; $i++) {
            if (! isset($closed[$i])) {
                $violations[] = PlanViolation::onAnswer(
                    self::CHECKPOINT_UNCOVERED,
                    "чек-пойнт {$i} не закрыт ни одной репликой — этот день нельзя пройти",
                    "checkpoint {$i} is closed by no line, so this day cannot be passed",
                );
            }
        }

        return $violations;
    }

    /**
     * THE PAIR THAT MAKES A LINE — `frame` and `filler`, judged instead of the sentence they build.
     *
     * v0.2 checked the OUTPUT: «is this line its own frame with something in the hole». v0.3 builds
     * the line itself, so that question answers itself and the interesting one moved upstream — is
     * the pair the server was handed a pair it can paste?
     *
     *   ONE slot at most. Two holes and one filler is a line with a hole left in it, which is the
     *   defect the assembly was introduced to make impossible; letting the paste fill only the
     *   first would hide it behind a sentence that reads fine.
     *   A filler exactly when there is a slot. Both halves fail: a slot with `""` leaves a hole,
     *   and a filler with no slot is a word the line never asked for.
     *   The filler is a CARD of this day, character for character. «payment module» when the card
     *   says «the payment module» is not a near miss: the learner meets the word on a card and in
     *   a line, and if the two differ they are two words.
     *
     * And unchanged from v0.2 for the ONE half of it that is about a card: an interlocutor's line
     * is quoted from the skeleton — its FRAME now, since that is what the model writes. The other
     * half, the QUARTER ceiling, left this method in v0.3.1 and is counted instead
     * ({@see ROLE_LINE_SHARE}): it is a fact about the day's proportions, not about a card, and
     * nothing a repair call can be pointed at.
     *
     * @return list<PlanViolation>
     */
    private function checkFrames(PlanDayCandidate $day): array
    {
        $violations = [];
        $lines = $this->linesOf($day);
        if ($lines === []) {
            return $violations;
        }

        $cards = [];
        foreach ($day->items as $item) {
            if ($item->kind !== PlanDayItem::KIND_LINE) {
                $cards[$item->text] = true;
            }
        }

        $openings = array_map(static fn (string $l): string => trim($l), $day->openingLines);

        foreach ($lines as $line) {
            $frame = trim($line->frame);
            $slots = mb_substr_count($frame, self::SLOT);

            if ($slots > 1) {
                $violations[] = PlanViolation::onCard(
                    self::FRAME_SLOT_COUNT,
                    $line,
                    'frame',
                    "в каркасе «{$frame}» дырок {$slots}, а дырка в каркасе бывает одна — "
                    . 'подставить в неё можно только одно слово дня',
                    "the frame carries {$slots} slots and a frame carries at most one",
                );
            }

            if ($slots >= 1 && $line->filler === '') {
                $violations[] = PlanViolation::onCard(
                    self::FILLER_MISMATCH,
                    $line,
                    'filler',
                    "у каркаса «{$frame}» есть дырка, а `filler` пуст — реплику не из чего собрать",
                    'the frame has a slot and `filler` is empty, so the line cannot be assembled',
                );
            } elseif ($slots === 0 && $line->filler !== '') {
                $violations[] = PlanViolation::onCard(
                    self::FILLER_MISMATCH,
                    $line,
                    'filler',
                    "`filler` «{$line->filler}» есть, а дырки в каркасе «{$frame}» нет — "
                    . 'ставить его некуда',
                    'there is a `filler` and no slot in the frame to put it in',
                );
            } elseif ($line->filler !== ''
                && ! isset($cards[$line->filler])
                && self::isStrictFillerLang($day->targetLang)) {
                // FATAL ONLY IN ENGLISH — see {@see STRICT_FILLER_LANG}. Elsewhere the same
                // mismatch is a warning ({@see warnings()}), because the honest answer in a
                // language with cases is an inflected filler and refusing it would be refusing
                // grammar.
                $violations[] = PlanViolation::onCard(
                    self::FILLER_NOT_A_CARD,
                    $line,
                    'filler',
                    "`filler` «{$line->filler}» не совпадает посимвольно ни с одним `text` "
                    . 'из words или chunks этого дня — в дырке стоит слово, которого день не учит',
                    'the `filler` is not, character for character, the `text` of any card in DAY TERMS',
                );
            }

            if ($line->speaker !== PlanDayItem::SPEAKER_ROLE) {
                continue;
            }

            if (! in_array($frame, $openings, true)) {
                $violations[] = PlanViolation::onCard(
                    self::ROLE_LINE_INVENTED,
                    $line,
                    'frame',
                    'реплика собеседника сочинена, а её `frame` должен быть дословно взят из '
                    . 'opening_lines сцены',
                    'a `speaker: role` line must be one of OPENING LINES, verbatim',
                );
            } elseif ($line->filler !== '') {
                $violations[] = PlanViolation::onCard(
                    self::ROLE_LINE_INVENTED,
                    $line,
                    'filler',
                    'реплику собеседника цитируют целиком: `filler` у неё пустой, подставлять в '
                    . 'чужую реплику нечего',
                    'a `speaker: role` line is quoted whole, so its `filler` is empty',
                );
            }
        }

        return $violations;
    }

    /**
     * WHAT IS WRONG WITH THE DAY AND IS NOT WORTH A SECOND PAID CALL.
     *
     * Things about the SHAPE of the conversation rather than about whether the machine can run it.
     * Every one of them was a candidate for a fatal gate and every one of them would have refused a
     * day the owner would have been happy with:
     *
     *   too many formulas — measured on the live day and wrong there (see {@see MAX_FORMULA_SHARE});
     *   no question from the learner — the live day was eight «I…» statements in a row, which reads
     *   as a questionnaire and not as an interview, and is a real defect the learner feels;
     *   no repair move — «Sorry, you're breaking up» — which is the moment a remote call actually
     *   breaks, and a day that trains only statements leaves the learner mute there;
     *   no line from the interlocutor at all, though the scene had lines to quote;
     *   too MANY of them — {@see ROLE_LINE_SHARE}, the ceiling, joined its own floor in v0.3.1;
     *   a word standing in no frame of the day — {@see SUBSTITUTION_OUTSIDE_FRAME}, the last gate
     *   that stood between a live day and `ready`, three runs in a row, over one card of fourteen.
     *
     * They are reported and counted rather than refused because the cost of being wrong is not
     * symmetric: a weak day is caught by reading it, and a refused day is $0.05 and a learner
     * staring at an error. A counter that climbs is a prompt problem.
     *
     * @return list<PlanViolation> empty = nothing to warn about
     */
    public function warnings(PlanDayCandidate $day): array
    {
        $lines = $this->linesOf($day);
        if ($lines === []) {
            return [];
        }

        $cards = [];
        foreach ($day->items as $item) {
            if ($item->kind !== PlanDayItem::KIND_LINE) {
                $cards[$item->text] = true;
            }
        }

        $out = [...$this->chunksOutsideFrames($day), ...$this->wordsOutsideFrames($day)];
        $formulas = 0;
        $question = false;
        $repair = false;
        $roleLines = 0;
        $markers = $this->repairMarkersFor($day->targetLang);

        foreach ($lines as $line) {
            if (! str_contains($line->frame, self::SLOT)) {
                $formulas++;
            }

            // The same mismatch that is fatal in English, counted in every other language until
            // the inflection question is answered — see {@see STRICT_FILLER_LANG}.
            if ($line->filler !== ''
                && ! isset($cards[$line->filler])
                && ! self::isStrictFillerLang($day->targetLang)) {
                $out[] = PlanViolation::onCard(
                    self::FILLER_MISMATCH_WARNING,
                    $line,
                    'filler',
                    "`filler` «{$line->filler}» не совпадает посимвольно ни с одной карточкой дня "
                    . '— возможно, склонение, а возможно, слово не из этого дня',
                    'the `filler` matches no card of this day character for character',
                );
            }

            if ($line->speaker === PlanDayItem::SPEAKER_ROLE) {
                $roleLines++;
            }

            // The learner's OWN lines only. The interlocutor asking a question teaches the learner
            // to recognise one, which is a different ability from asking one.
            if ($line->speaker !== PlanDayItem::SPEAKER_LEARNER) {
                continue;
            }
            if (str_ends_with(rtrim($line->text), '?')) {
                $question = true;
            }
            if ($markers !== [] && $this->isRepairMove($line->text, $markers)) {
                $repair = true;
            }
        }

        $cap = (int) ceil($day->phraseCount * self::MAX_FORMULA_SHARE);
        if ($formulas > $cap) {
            $out[] = PlanViolation::onAnswer(
                self::FORMULA_CAP,
                "реплик без дырки {$formulas} из {$day->phraseCount}, а треть с округлением вверх "
                . "— это {$cap}",
                "{$formulas} of {$day->phraseCount} lines have no slot; a third rounded up is {$cap}",
            );
        }

        // THE CEILING ON THE INTERLOCUTOR, counted since v0.3.1 — {@see ROLE_LINE_SHARE}. It lived
        // in `checkFrames()` and refused the day; it is a proportion of the answer, no card is to
        // blame for it, and a repair call has nothing to be pointed at.
        $ceiling = (int) floor(count($lines) * self::MAX_ROLE_SHARE);
        if ($roleLines > $ceiling) {
            $out[] = PlanViolation::onAnswer(
                self::ROLE_LINE_SHARE,
                'реплик собеседника ' . $roleLines . ' из ' . count($lines)
                . ', а их должно быть не больше четверти',
                $roleLines . ' of ' . count($lines) . ' lines are the interlocutor\'s; at most a '
                . 'quarter should be',
            );
        }

        if (! $question) {
            $out[] = PlanViolation::onAnswer(
                self::NO_QUESTION,
                'ни одна реплика юзера не заканчивается вопросительным знаком — день учит отвечать '
                . 'и не учит спрашивать',
                'no line of the learner\'s ends in a question mark',
            );
        }

        // THE FLOOR UNDER THE INTERLOCUTOR. The ceiling above has always been held — no more than
        // a quarter of the lines are the role's — and nothing held the floor, so a
        // day of eight lines with the interlocutor silent throughout passed. The live v0.3 day was
        // exactly that: the skeleton gave its scene three `opening_lines` and the day quoted none,
        // while its first line answered a question that was nowhere in the day. The learner drills
        // answers to lines they have never heard.
        //
        // Only where there IS somebody to hear: `openingLines` is empty when no scene of this day
        // has a role, and a scene of reading forms alone is a legitimate day with nobody in it.
        if ($roleLines === 0 && $day->openingLines !== []) {
            $out[] = PlanViolation::onAnswer(
                self::NO_ROLE_LINE,
                'ни одной реплики собеседника: у сцены есть opening_lines, но день не процитировал '
                . 'ни одной — юзер учит ответы на то, чего не слышал',
                'no line is the interlocutor\'s, though the scene has OPENING LINES to quote',
            );
        }

        // No list for this target language means the question was never asked of it, and an
        // unasked question has no answer to warn about.
        if (! $repair && $markers !== []) {
            $out[] = PlanViolation::onAnswer(
                self::NO_REPAIR,
                'ни одной реплики-починки («Could you repeat…», «Sorry, you`re breaking up») — '
                . 'на настоящем разговоре ломается ровно это',
                'no line asks for a repeat or says the connection broke up',
            );
        }

        return $out;
    }

    /**
     * Does this line ask for a repeat, a slower pace, or say the connection went?
     *
     * @param  list<string>  $markers
     */
    private function isRepairMove(string $text, array $markers): bool
    {
        $line = ' ' . $this->normalize($text) . ' ';

        foreach ($markers as $marker) {
            $needle = $this->normalize($marker);
            if ($needle !== '' && str_contains($line, ' ' . $needle . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * This target language's repair phrases — exact code first, then the bare language.
     *
     * @return list<string> empty = the check is off for this language
     */
    private function repairMarkersFor(string $targetLang): array
    {
        $lang = mb_strtolower(trim($targetLang));

        return $this->repairMarkers[$lang]
            ?? $this->repairMarkers[mb_substr($lang, 0, 2)]
            ?? [];
    }

    /** Is the filler rule fatal in this target language? {@see STRICT_FILLER_LANG} */
    private static function isStrictFillerLang(string $targetLang): bool
    {
        return mb_substr(mb_strtolower(trim($targetLang)), 0, 2) === self::STRICT_FILLER_LANG;
    }

    /**
     * EVERY substitution stands in some frame of this day — but a word and a connector stand in
     * different places, and v0.3 is where that stopped being one rule.
     *
     * This is what makes the day combine. Without it the vocabulary is a glossary next to the
     * lines: the learner memorises eight sentences and owns none of them, because nothing ever told
     * them which hole each word goes in.
     *
     * **A WORD goes in the HOLE.** The rule is unchanged: `example` is one of this day's frames
     * with this word in its slot. «the API» → «I mainly work with the API.» What changed in v0.3.1
     * is what happens when it is broken — see {@see SUBSTITUTION_OUTSIDE_FRAME}. It was the last
     * fatal gate standing between a live day and `ready`, three attempts running, always over ONE
     * word of fourteen cards; a day is not broken by one card, and since v0.3.1 one card is an
     * address a repair call can be pointed at rather than a day paid for twice.
     *
     * **A CONNECTOR lives in the frame, wherever the language puts it.** Four live answers in a
     * row built the frame AROUND the connector — «Right now, I mainly work on ___» beside the chunk
     * «work on» — and the v0.2 gate called that a defect four times. It is not one: that IS how a
     * phrasal verb is used, and no wording of the prompt moved it, because the model was right. So
     * the chunk's example must be a frame of this day CONTAINING it — in the slot or in the fixed
     * part — and the one thing that would make the card worthless is checked instead: the example
     * may not be a LINE of the day, word for word. «work on» whose only example is the line it
     * already stands in teaches that line twice and the connector not at all.
     *
     * @return list<PlanViolation>
     */
    private function wordsOutsideFrames(PlanDayCandidate $day): array
    {
        [, $slotFrames] = $this->framesOf($day);

        $violations = [];
        foreach ($day->items as $item) {
            if ($item->kind !== PlanDayItem::KIND_WORD) {
                continue;
            }

            foreach ($slotFrames as $frame) {
                if ($this->exampleUsesFrame($frame, $item->text, $item->example)) {
                    continue 2;
                }
            }

            $violations[] = PlanViolation::onCard(
                self::SUBSTITUTION_OUTSIDE_FRAME,
                $item,
                'example',
                'ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего',
                'the `example` is not one of the DAY LINES frames with this word in the slot',
            );
        }

        return $violations;
    }

    /**
     * THE CONNECTORS, counted rather than refused — {@see CHUNK_OUTSIDE_FRAME}.
     *
     * Fatal for one commit, and the live day measured what that costs. The model wrote «The
     * connection is breaking up again on my side.» for the chunk «breaking up», against the day's
     * own frame «Sorry, the connection is ___» — a sentence from this day's situation, not a clone
     * of the line, arrived at by FIXING the clone the previous attempt had. What it dropped on the
     * way was the leading «Sorry,», and the whole-frame rule refused the day for it
     * (`docs/research/plan-v0.3-run.md`). One word of politeness is not a broken day.
     *
     * The rule itself is unchanged and still worth saying: a connector's example should be a frame
     * of this day carrying it, with a filler that is not the line's own. It is now said in the log.
     *
     * Judged against ALL the day's frames, not only the ones with a hole: a connector may live in
     * a frame's fixed part, which is the whole point of the rule since v0.3.
     *
     * @return list<PlanViolation>
     */
    private function chunksOutsideFrames(PlanDayCandidate $day): array
    {
        [$allFrames, , $lineTexts] = $this->framesOf($day);
        $out = [];

        foreach ($day->items as $item) {
            if ($item->kind !== PlanDayItem::KIND_CHUNK) {
                continue;
            }

            $clone = $lineTexts[$this->normalize($item->example)] ?? null;
            if ($clone !== null) {
                $out[] = PlanViolation::onCard(
                    self::CHUNK_OUTSIDE_FRAME,
                    $item,
                    'example',
                    'пример связки — дословно реплика дня «' . $clone . '»: тот же каркас нужен '
                    . 'с ДРУГИМ наполнителем, иначе связку учат вместе с уже выученной репликой',
                    'the `example` is a line of this day word for word; the same frame is wanted '
                    . 'with a DIFFERENT filler',
                );

                continue;
            }

            foreach ($allFrames as $frame) {
                // The connector in the HOLE — the same containment check a word gets.
                if ($this->exampleUsesFrame($frame, $item->text, $item->example)) {
                    continue 2;
                }
                // The connector in the FIXED part: the example has to carry the whole frame, with
                // something in its hole, and may carry more besides.
                if ($this->frameHolds($frame, $item->text)
                    && $this->exampleContainsFrame($frame, $item->example)) {
                    continue 2;
                }
            }

            $out[] = PlanViolation::onCard(
                self::CHUNK_OUTSIDE_FRAME,
                $item,
                'example',
                'ни один каркас дня не содержит эту связку целиком — её пример не собирается '
                . 'из каркасов этого дня',
                'no frame of DAY LINES carries this connector whole, so the `example` is built '
                . 'from nothing in this day',
            );
        }

        return $out;
    }

    /**
     * The three lists every frame rule is judged against, gathered once.
     *
     * @return array{0: list<string>, 1: list<string>, 2: array<string, string>}
     */
    private function framesOf(PlanDayCandidate $day): array
    {
        $slotFrames = [];
        $allFrames = [];
        $lineTexts = [];

        foreach ($this->linesOf($day) as $line) {
            $frame = trim($line->frame);
            if ($frame !== '') {
                $allFrames[] = $frame;
                if (str_contains($frame, self::SLOT)) {
                    $slotFrames[] = $frame;
                }
            }
            $lineTexts[$this->normalize($line->text)] = $line->text;
        }

        return [$allFrames, $slotFrames, $lineTexts];
    }

    /**
     * Does `$example` CONTAIN this frame, filled with something — or contain the formula itself?
     *
     * Containment and not equality, which is the same rule a word's example lives by. It was
     * equality for one commit and that was wrong in a way that only shows up on real sentences:
     * «I mainly work with Laravel, mostly.» is the frame «I mainly work with ___» with a detail
     * added, which is exactly what the prompt asks an example to be, and an anchored comparison
     * refused it. The whole frame still has to be there — a frame with a fixed tail («…every day»)
     * is not carried by an example that drops the tail.
     *
     * The slot is lazy on purpose: a greedy one would let the hole swallow the frame's own tail
     * and match a sentence that never finished the frame.
     */
    private function exampleContainsFrame(string $frame, string $example): bool
    {
        $normalizedExample = $this->normalize($example);

        if (! str_contains($frame, self::SLOT)) {
            $needle = $this->normalize($frame);

            return $needle !== '' && str_contains($normalizedExample, $needle);
        }

        $pattern = $this->framePattern($frame, '.+?');

        return $pattern !== null && preg_match('/' . $pattern . '/u', $normalizedExample) === 1;
    }

    /**
     * Does the frame carry `$term` in its FIXED part — the half that does not move?
     *
     * Whole words only: «work» must not be found inside «network». The slot is flattened to a space
     * first, so a term the frame builds around («I mainly work with ___») is found and a term that
     * merely spans the hole is not.
     */
    private function frameHolds(string $frame, string $term): bool
    {
        $needle = $this->normalize($term);
        if ($needle === '') {
            return false;
        }

        $haystack = ' ' . $this->normalize(str_replace(self::SLOT, ' ', $frame)) . ' ';

        return str_contains($haystack, ' ' . $needle . ' ');
    }

    /** Does `$example` contain this frame with `$term` in the slot? */
    private function exampleUsesFrame(string $frame, string $term, string $example): bool
    {
        $pattern = $this->framePattern($frame, preg_quote($this->normalize($term), '/'));

        return $pattern !== null && preg_match('/' . $pattern . '/u', $this->normalize($example)) === 1;
    }

    /**
     * The frame as a regular expression, with `$slot` where the hole is.
     *
     * Null when the frame has no slot: a formula matches nothing and excuses nothing.
     */
    private function framePattern(string $frame, string $slot): ?string
    {
        $normalized = $this->normalize(str_replace(self::SLOT, self::SLOT_MARK, $frame));
        if (! str_contains($normalized, self::SLOT_MARK)) {
            return null;
        }

        $parts = array_map(
            static fn (string $part): string => preg_quote(trim($part), '/'),
            explode(self::SLOT_MARK, $normalized),
        );

        // The slot's own neighbours lose their spaces to normalisation, so the parts are re-joined
        // with «optional whitespace» rather than glued: «worked on» + term must still match
        // «worked on the payment module».
        return implode('\s*' . $slot . '\s*', $parts);
    }

    /**
     * @param  list<PlanDayItem>  $items
     * @return list<PlanViolation>
     */
    private function checkExamples(array $items): array
    {
        $violations = [];

        $terms = [];
        foreach ($items as $item) {
            $terms[$this->normalize($item->text)] = $item->text;
        }

        $seenExamples = [];
        foreach ($items as $item) {
            $example = trim($item->example);
            if ($example === '') {
                $violations[] = PlanViolation::onCard(
                    self::EXAMPLE_MISSING,
                    $item,
                    'example',
                    'у карточки нет примера',
                    'the card has no `example`',
                );

                continue;
            }

            $key = $this->normalize($example);

            // Its own text or ANY other card's. The «any other» half is the one v0 lost.
            //
            // The English reason names no card, and that is not brevity: the v0.3 retry was handed
            // «пример — это дословно термин «Right now, I am a backend developer.»» and answered
            // with that sentence. The address says which card to fix; the day's own text is in
            // front of the model already.
            if (isset($terms[$key])) {
                $violations[] = PlanViolation::onCard(
                    self::EXAMPLE_IS_A_TERM,
                    $item,
                    'example',
                    'пример — это дословно термин «' . $terms[$key] . '», а не предложение с ним внутри',
                    'the `example` is, word for word, a card of this day rather than a sentence '
                    . 'containing one',
                );
            }

            if (isset($seenExamples[$key])) {
                $violations[] = PlanViolation::onCard(
                    self::EXAMPLE_DUPLICATED,
                    $item,
                    'example',
                    'этот же пример уже стоит у «' . $seenExamples[$key] . '»',
                    'another card of this day already uses this `example`',
                );

                continue;
            }
            $seenExamples[$key] = $item->text;
        }

        return $violations;
    }

    /** @return list<PlanViolation> */
    private function checkKeys(PlanDayCandidate $day): array
    {
        $violations = [];
        $seen = [];
        $vocabulary = $this->dayVocabulary($day->items);

        foreach ($day->items as $item) {
            $translation = trim($item->translation);

            if ($this->normalize($translation) === $this->normalize($item->text)) {
                $violations[] = PlanViolation::onCard(
                    self::KEY_IS_THE_TERM,
                    $item,
                    'translation',
                    'ключ совпадает с термином — карточка спрашивает то, на что уже ответила',
                    'the `translation` is the term itself, so the card answers its own question',
                );
            }

            $key = $this->normalize($translation);
            if ($key !== '' && isset($seen[$key])) {
                // Two identical questions with two different accepted answers is a card that
                // cannot be passed by knowing the material.
                $violations[] = PlanViolation::onCard(
                    self::KEY_DUPLICATED,
                    $item,
                    'translation',
                    'тот же ключ уже стоит у «' . $seen[$key] . '»',
                    'another card of this day already uses this `translation`',
                );
            }
            $seen[$key] = $item->text;

            foreach (['translation' => $translation, 'example_translation' => trim($item->exampleTranslation)] as $field => $value) {
                if ($value !== '' && ! $this->keyIsPure($day, $item, $value, $vocabulary)) {
                    $violations[] = PlanViolation::onCard(
                        self::KEY_NOT_SUPPORT_LANGUAGE,
                        $item,
                        $field,
                        "`{$field}` написан не на языке поддержки",
                        "`{$field}` is not written in the support language",
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * The per-card rules that need no comparison with the rest of the day.
     *
     * @return list<PlanViolation>
     */
    private function checkPerCard(PlanDayCandidate $day): array
    {
        $violations = [];

        foreach ($day->items as $item) {
            foreach ($this->slotBearingFields($item) as $field) {
                $violations[] = PlanViolation::onCard(
                    self::SLOT_OUTSIDE_FRAME,
                    $item,
                    $field,
                    "`{$field}` содержит «" . self::SLOT . '» — дырка живёт только в `frame`, '
                    . 'а поле с дыркой юзеру не произнести',
                    "`{$field}` carries a `" . self::SLOT . '` — the slot lives in `frame` and '
                    . 'nowhere else; this field is about the FULL assembled line',
                );
            }

            if ($item->description !== '' && DescriptionSelfReference::givesAway($item->description, $item->text)) {
                $violations[] = PlanViolation::onCard(
                    self::DESCRIPTION_GIVES_AWAY,
                    $item,
                    'description',
                    'описание называет собственный термин — карточка спрашивает то, на что уже ответила',
                    'the `description` names its own term, so the card answers its own question',
                );
            }

            // A term with nothing to draw is a term with no picture, and the day's collection is
            // the only place the plan gets one: the core generator's image query never runs over
            // plan material. Empty here means the card is illustrated by nothing, for ever.
            if (trim($item->imageApiPrompt) === '') {
                $violations[] = PlanViolation::onCard(
                    self::IMAGE_PROMPT_MISSING,
                    $item,
                    'image_api_prompt',
                    'нет описания картинки — карточка останется без иллюстрации навсегда',
                    '`image_api_prompt` is empty, so this card is illustrated by nothing for ever',
                );
            }
        }

        return $violations;
    }

    /**
     * Which of this card's fields carry a slot they have no business carrying.
     *
     * @return list<string> field names, in the order {@see SLOT_FORBIDDEN_IN} lists them
     */
    private function slotBearingFields(PlanDayItem $item): array
    {
        $values = [
            'text' => $item->text,
            'translation' => $item->translation,
            'description' => $item->description,
            'example' => $item->example,
            'example_translation' => $item->exampleTranslation,
            'image_api_prompt' => $item->imageApiPrompt,
        ];

        $found = [];
        foreach (self::SLOT_FORBIDDEN_IN as $field) {
            if (str_contains($values[$field], self::SLOT)) {
                $found[] = $field;
            }
        }

        return $found;
    }

    /**
     * Is this key written in the learner's own language?
     *
     * The plain rule — «ни одной буквы чужого алфавита в переводе» — is right for ordinary content
     * and WRONG for a plan, in ways that are not exceptions to the product but the product itself.
     * The shape rules (an abbreviation, a code) and the learner's own `goal_terms` live in
     * {@see SupportLanguageText}; two more live here because they are about THIS card and THIS day:
     *
     * 1. **A term that is itself in the other alphabet.** A card for `backend` glossed «бэкенд» is
     *    fine, and a key that must quote the term to be unambiguous is not a key in the wrong
     *    language.
     * 2. **A WORD THE DAY ITSELF TEACHES.** A Latin token that appears in the `text` or the
     *    `example` of ANY card of the SAME day is not evidence that the key was written in the
     *    wrong language — it is the day's own subject matter, quoted where Russian quotes it
     *    anyway. «Привет, я Alex, junior-разработчик» is how that sentence is written, and the day
     *    it was on («Онлайн-собеседование разработчика», the owner's phone, 31.08) died twice on
     *    `Alex`, `junior` and `backend` — words the model had put on the cards one field earlier.
     *
     *    Two guards keep it from eating the rule it is an exemption to. The token must be at least
     *    {@see MIN_VOCABULARY_TOKEN} letters long — one letter is a size, not a word. And the key
     *    as a WHOLE must still read as the support language: when most of its letters are foreign
     *    ({@see LanguagePurity::isWrongScript()}) the exemption is off, or an `example_translation`
     *    left in English would excuse itself with the example it failed to translate.
     *
     * @param  array<string, true>  $vocabulary  {@see dayVocabulary()} — every Latin word of the day
     */
    private function keyIsPure(PlanDayCandidate $day, PlanDayItem $item, string $value, array $vocabulary): bool
    {
        $stripped = $this->supportText->strip($value, [...$day->goalTerms, $item->text]);

        if (! $this->purity->isWrongScript($day->supportLang, $value)) {
            $stripped = (string) preg_replace_callback(
                self::LATIN_WORD,
                static fn (array $m): string => isset($vocabulary[mb_strtolower($m[0])]) ? ' ' : $m[0],
                $stripped,
            );
        }

        return $this->purity->foreignScriptLetters($day->supportLang, $stripped) === [];
    }

    /**
     * Every Latin word the day says out loud — its cards' `text` and `example`, which are the two
     * fields written in the language being learned.
     *
     * @param  list<PlanDayItem>  $items
     * @return array<string, true>  lower-cased word => true
     */
    private function dayVocabulary(array $items): array
    {
        $words = [];

        foreach ($items as $item) {
            foreach ([$item->text, $item->example] as $source) {
                if (preg_match_all(self::LATIN_WORD, $source, $matches) === false) {
                    continue;
                }

                foreach ($matches[0] as $word) {
                    if (mb_strlen($word) >= self::MIN_VOCABULARY_TOKEN) {
                        $words[mb_strtolower($word)] = true;
                    }
                }
            }
        }

        return $words;
    }

    /** @return list<PlanDayItem> */
    private function linesOf(PlanDayCandidate $day): array
    {
        return array_values(array_filter(
            $day->items,
            static fn (PlanDayItem $i): bool => $i->kind === PlanDayItem::KIND_LINE,
        ));
    }

    /** Case-folded, punctuation-free, whitespace-collapsed — for comparing two strings as content. */
    private function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $stripped = preg_replace('/[^\p{L}\p{N}\x{E000}]+/u', ' ', $lower) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $stripped));
    }
}
