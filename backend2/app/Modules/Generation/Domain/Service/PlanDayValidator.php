<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\PlanDayCandidate;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanShelf;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;
use App\Modules\Shared\Domain\Service\LanguagePurity;

/**
 * A DAY-SCENE, judged.
 *
 * ## Why a plan has a validator at all, and a collection does not
 *
 * There is no gate over `generate_collection` anywhere in this app: a weak card in a collection is
 * a weak card. A plan is a MECHANISM — the day promises abilities, the learner walks into the
 * appointment with what it wrote, and a card that cannot be dealt is a stage that never closes and
 * a day that never passes. Those are MECHANICAL failures, and mechanical failures are what
 * deterministic code is for. Taste stays with the prompt.
 *
 * ## v0.4: the shelves, and what stopped being a rule
 *
 * The day used to be three arrays with three exact counts, and half this class counted them. A
 * day-scene has SHELVES with guide sizes (канон §2), so every count moved to {@see warnings()} and
 * the fatal list is now about the CARD: can it be assembled, is it about this scene, is it a card
 * at all. Gone with the counts:
 *
 *   `day.array_count` / `day.term_count`   there are no exact counts to be off by;
 *   `day.checkpoint_*`                     the checkpoint index was replaced by {@see SKILL_REF_INVALID},
 *                                          which says the same thing about a card instead of about
 *                                          a number, and is therefore repairable;
 *   `day.role_line_invented`               v0.4 asks the model to ADAPT `opening_lines` to the
 *                                          scene rather than quote them, so «not verbatim» stopped
 *                                          being a defect;
 *   `day.kind_mismatch`                    the SHELF decides what a card is, exactly as the array
 *                                          did in v0.3 — a `kind` that disagrees with it is
 *                                          overwritten on the way in, not argued with.
 *
 * ## The one asymmetry this class is built on
 *
 * A gate that refuses a GOOD day costs more than one that lets a weak day through. The second is
 * caught by reading the day; the first spends $0.06, shows the learner an error, and then gets
 * switched off. So every rule below is one where being wrong breaks the machine, everything
 * measured in «about how many» is a counter, and three checks stay SILENT when their language has
 * no rule written ({@see TranslationKeyPresence}, {@see BasicVocabulary}, {@see NumberSpelling}) —
 * «немецкое правило ещё не написано» must not read as «каждый немецкий день сломан».
 */
final class PlanDayValidator
{
    // ── carded, fatal → one repair call (P2R) ────────────────────────────────────────────────

    /**
     * THE PAIR CANNOT BE ASSEMBLED — two holes and one filler, a hole with nothing to put in it,
     * or a filler with nowhere to stand.
     *
     * v0.3 spelled these as `day.frame_slot_count` + `day.filler_mismatch`; they are one defect
     * seen from two sides and one address a repair call is pointed at, so v0.4 names them once.
     */
    public const GAP_MISSING = 'card.gap_missing';

    /** `___` in a field that is not `frame` — a field the learner cannot say. */
    public const GAP_OUTSIDE_FRAME = 'card.gap_outside_frame';

    /**
     * `___` IN THE TRANSLATION — the узор of live day 2, named on its own because it is the one
     * field the learner READS: «Я работал над ___» asks nothing.
     */
    public const TRANSLATION_HAS_GAP = 'card.translation_has_gap';

    /** The line's translation does not render its key card (гейт 219, unchanged, renamed to `card.`). */
    public const TRANSLATION_MISSING_KEY = 'card.translation_missing_key';

    /**
     * The filler is not a word or connector of THIS day.
     *
     * RETIRED AS A REFUSAL and kept as a name: the check itself lives on as
     * {@see FILLER_MISMATCH_WARNING}, for the reasons the live run wrote at its old site. The
     * constant stays because the mobile client words this code and old failed days still carry it
     * in `fail_code` — a code that stops being emitted is not a code that stops being READ.
     */
    public const FILLER_NOT_CARD = 'card.filler_not_card';

    /** The card is another card of the day, a rescue-kit phrase, or a unit an earlier day taught. */
    public const CLONE = 'card.clone';

    /** The example is a card of this day rather than a sentence containing one. */
    public const EXAMPLE_IS_A_TERM = 'card.example_is_a_term';

    /** Two examples are ONE sentence with the term swapped — Д-29. {@see ExampleSkeleton} */
    public const EXAMPLE_SKELETON_CLONE = 'card.example_skeleton_clone';

    /** Basic vocabulary as a card — fatal from «Понимаю простое» up. {@see BasicVocabulary} */
    public const WORD_IS_BASIC = 'card.word_is_basic';

    /** Word > 3 words, chunk outside 2–4, say/ask outside 3–8, hear over 12 (канон §7). */
    public const KIND_SIZE = 'card.kind_size';

    /** A proper name of the scenario as a card of its own. */
    public const TERM_IS_A_NAME = 'card.term_is_a_name';

    /** The «translation» is the term itself, or the term written in the other alphabet. */
    public const TRANSLATION_IS_TRANSLITERATION = 'card.translation_is_transliteration';

    /** The card names no skill of this scene — «почему я это учу» with no answer (канон §8). */
    public const SKILL_REF_INVALID = 'card.skill_ref_invalid';

    /** The number the line says and the number the card is graded on are not the same number. */
    public const NUMBER_VALUE_MISMATCH = 'card.number_value_mismatch';

    // ── about the DAY, no address, so the day goes back whole ────────────────────────────────

    /**
     * A SHELF THE SCENE CANNOT LIVE WITHOUT IS EMPTY.
     *
     * Three of them: «Тебе скажут» (no lines to recognise = the learner drills answers to a
     * question they have never heard), «Ты ответишь» (nothing to say), and the substitutions
     * together (nothing the lines are built from, so nothing combines). `ask` and `numbers` are
     * guides and are counted rather than refused — a scene where there is genuinely nothing to
     * clarify is a real scene.
     */
    public const SHELF_MISSING = 'day.shelf_missing';

    // ── counted, never refused ───────────────────────────────────────────────────────────────

    /** A shelf outside its guide size — 4–6 / 4–6 / 2–3 / 6–8 / 2–4. */
    public const SIZE_OUT_OF_RANGE = 'plan_day_size_out_of_range';

    public const FORMULA_CAP = 'plan_day_formula_cap';

    public const NO_QUESTION = 'plan_day_no_question';

    public const NO_REPAIR = 'plan_day_no_repair';

    /** A skill of the scene that no card serves — the other half of {@see SKILL_REF_INVALID}. */
    public const SKILL_UNCOVERED = 'plan_day_skill_uncovered';

    /** More of the day is the interlocutor's than the learner's own. */
    public const ROLE_LINE_SHARE = 'plan_day_role_line_share';

    /**
     * A word or connector that stands in NO line of the day.
     *
     * The measure moved with v0.4 and the name did not. v0.3 asked «is the example one of the day's
     * frames with this word in the slot», which was a rule about the EXAMPLE; the canon asks for
     * «слова и связки — из этих же реплик» (§2), which is a rule about the DAY. So it now looks for
     * the term inside the day's assembled lines, which is what «combines» actually means, and stays
     * a counter for the reason it became one: it was the last fatal gate between a live day and
     * `ready`, three runs running, always over one card of fourteen.
     */
    public const SUBSTITUTION_OUTSIDE_FRAME = 'plan_day_substitution_outside_frame';

    /** The model retold the scene's вводка in a card instead of writing the scene. */
    public const INTRO_REPEATED = 'plan_day_intro_repeated';

    /** The same mismatch {@see FILLER_NOT_CARD} names, in a language where it may be inflection. */
    public const FILLER_MISMATCH_WARNING = 'plan_day_filler_mismatch';

    /** Two cards of the day ask the same question in the support language. */
    public const KEY_DUPLICATED = 'plan_day_key_duplicated';

    /** A word with no image query — it will be illustrated by nothing, for ever (канон §7). */
    public const IMAGE_PROMPT_MISSING = 'plan_day_image_prompt_missing';

    /** A key with letters of the wrong alphabet in it. */
    public const KEY_NOT_SUPPORT_LANGUAGE = 'plan_day_key_not_support_language';

    /** Basic vocabulary at level `zero`, where it is legitimately the lesson. */
    public const WORD_IS_BASIC_WARNING = 'plan_day_word_is_basic';

    /**
     * The guide sizes of the six shelves — канон §2, and the only place they are written.
     *
     * `words` and `chunks` share one guide («внизу — слова и связки, 6–8 штук»), so they are
     * counted TOGETHER and the pair is keyed by `words`.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const SHELF_GUIDE = [
        'hear' => [4, 6],
        'say' => [4, 6],
        'ask' => [2, 3],
        'words' => [6, 8],      // words + chunks together
        'numbers' => [2, 4],
    ];

    /**
     * The length of a card, by what it is — канон §7, in words.
     *
     * «Длинного текста в карточках нет вообще»: a fifteen-word card is not a hard card, it is a
     * paragraph the learner memorises instead of a turn they can say. The interlocutor gets more
     * room than the learner on purpose — понимать можно длиннее, чем говорить.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const SIZE_LIMIT = [
        'words' => [1, 3],
        'chunks' => [2, 4],
        'say' => [3, 8],
        'ask' => [3, 8],
        'hear' => [1, 12],
    ];

    /** The most lines with no slot at all — «формулы должны остаться меньшинством дня». */
    private const MAX_FORMULA_SHARE = 1 / 3;

    /** The most of the day's lines that may be the interlocutor's. */
    private const MAX_ROLE_SHARE = 1 / 2;

    /**
     * What a repair move sounds like — the learner saying they did not catch it.
     *
     * A LIST AND NOT A RULE, in config for the reason every list of phrases eventually needs: it
     * will be wrong, and being wrong should not be a code change. A language absent from the map
     * has the check switched off rather than failing it.
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

    /** The slot in a frame. */
    private const SLOT = PlanDayItem::SLOT;

    /** Where the slot may never be. `transliteration` is absent on purpose — a bad hint is dropped. */
    private const SLOT_FORBIDDEN_IN = ['text', 'description', 'example', 'example_translation', 'image_api_prompt'];

    /** Sentence punctuation a reading hint picks up by reflex from the line it transcribes. */
    private const SENTENCE_PUNCTUATION = [
        '.', ',', '?', '!', ';', ':', '"', '“', '”', '«', '»', '(', ')', '[', ']', '…', '–', '—',
    ];

    /** Marks a hint legitimately carries — a hyphen inside a word, an apostrophe inside one. */
    private const HINT_MARKS = [' ', '-', '\'', '’', '‑'];

    /** ONE Latin word — the unit the day-vocabulary exemption is measured in. */
    private const LATIN_WORD = "/[A-Za-z]+(?:['\u{2019}][A-Za-z]+)*/u";

    /** The shortest token the day's own vocabulary may excuse. One letter is a size, not a word. */
    private const MIN_VOCABULARY_TOKEN = 2;

    /** @param array<string, list<string>> $repairMarkers target language => phrases */
    public function __construct(
        private readonly LanguagePurity $purity = new LanguagePurity(),
        private readonly SupportLanguageText $supportText = new SupportLanguageText(),
        private readonly array $repairMarkers = self::DEFAULT_REPAIR_MARKERS,
        private readonly TransliteratedSameness $sameness = new TransliteratedSameness(),
        private readonly TranslationKeyPresence $keyPresence = new TranslationKeyPresence(),
        private readonly BasicVocabulary $basics = new BasicVocabulary(),
        private readonly NumberSpelling $numbers = new NumberSpelling(),
        private readonly ExampleSkeleton $skeletons = new ExampleSkeleton(),
    ) {}

    /** @return list<PlanViolation> empty = the day may be written */
    public function validate(PlanDayCandidate $day): array
    {
        $violations = $this->checkShelves($day);
        if ($day->items === []) {
            return $violations;
        }

        return [
            ...$violations,
            ...$this->checkSkillRefs($day),
            ...$this->checkGaps($day),
            ...$this->checkSizes($day),
            ...$this->checkClones($day),
            ...$this->checkExamples($day),
            ...$this->checkBasics($day),
            ...$this->checkNames($day),
            ...$this->checkKeys($day),
            ...$this->checkLineTranslations($day),
            ...$this->checkNumbers($day),
        ];
    }

    /**
     * WHAT IS WRONG WITH THE DAY AND IS NOT WORTH A SECOND PAID CALL.
     *
     * Everything the canon states as «около столько-то» plus the shape rules whose being wrong
     * makes a slightly worse day rather than an unplayable one. Reported for every answer and
     * counted only for the one that was WRITTEN, so the counters measure weak days the learner
     * GOT, never the machine correctly refusing one.
     *
     * @return list<PlanViolation>
     */
    public function warnings(PlanDayCandidate $day): array
    {
        if ($day->items === []) {
            return [];
        }

        return [
            ...$this->warnShelfSizes($day),
            ...$this->warnConversation($day),
            ...$this->warnCoverage($day),
            ...$this->warnCards($day),
        ];
    }

    // ── fatal ────────────────────────────────────────────────────────────────────────────────

    /** @return list<PlanViolation> */
    private function checkShelves(PlanDayCandidate $day): array
    {
        $out = [];

        foreach ([PlanShelf::Hear, PlanShelf::Say] as $shelf) {
            if ($day->shelf($shelf) === []) {
                $out[] = PlanViolation::onAnswer(
                    self::SHELF_MISSING,
                    "полка «{$shelf->value}» пуста — без неё сцену не пройти",
                    "the `{$shelf->value}` shelf is empty and the scene cannot be walked without it",
                );
            }
        }

        if ($day->shelf(PlanShelf::Words) === [] && $day->shelf(PlanShelf::Chunks) === []) {
            $out[] = PlanViolation::onAnswer(
                self::SHELF_MISSING,
                'ни слов, ни связок — репликам дня не из чего собираться',
                'both `words` and `chunks` are empty, so the day\'s lines are built from nothing',
            );
        }

        return $out;
    }

    /**
     * EVERY CARD NAMES ONE SKILL OF THIS SCENE — канон §8, mechanically.
     *
     * Silent when the scene handed in no skills at all: a day generated from a stored brief written
     * before skills had ids has nothing to check against, and refusing it would refuse the plan the
     * learner is halfway through.
     *
     * @return list<PlanViolation>
     */
    private function checkSkillRefs(PlanDayCandidate $day): array
    {
        if ($day->skillIds === []) {
            return [];
        }

        $known = array_flip($day->skillIds);
        $out = [];
        foreach ($day->items as $item) {
            $ref = trim((string) $item->skillRef);
            if ($ref !== '' && isset($known[$ref])) {
                continue;
            }

            $out[] = PlanViolation::onCard(
                self::SKILL_REF_INVALID,
                $item,
                'skill_ref',
                $ref === ''
                    ? 'карточка не называет умение сцены — непонятно, зачем она в этом дне'
                    : "умения «{$ref}» у этой сцены нет",
                'the card names no skill of this scene; `skill_ref` must be one of the scene\'s skill ids',
            );
        }

        return $out;
    }

    /**
     * THE HOLE — where it is, where it is not, and whether the pair can be pasted at all.
     *
     * @return list<PlanViolation>
     */
    private function checkGaps(PlanDayCandidate $day): array
    {
        $cards = $this->substitutionTexts($day);
        $out = [];

        foreach ($day->items as $item) {
            $shelf = PlanShelf::tryFromName($item->arrayName());
            $frame = trim($item->frame);
            $slots = mb_substr_count($frame, self::SLOT);

            if ($shelf !== null && $shelf->isAssembled()) {
                if ($slots > 1) {
                    $out[] = PlanViolation::onCard(
                        self::GAP_MISSING,
                        $item,
                        'frame',
                        "в каркасе дырок {$slots}, а подставить можно только одно слово дня",
                        "the frame carries {$slots} gaps and a frame carries at most one",
                    );
                } elseif ($slots === 1 && $item->filler === '') {
                    $out[] = PlanViolation::onCard(
                        self::GAP_MISSING,
                        $item,
                        'filler',
                        'у каркаса есть дырка, а `filler` пуст — реплику не из чего собрать',
                        'the frame has a gap and `filler` is empty, so nothing can be assembled',
                    );
                } elseif ($slots === 0 && $item->filler !== '') {
                    $out[] = PlanViolation::onCard(
                        self::GAP_MISSING,
                        $item,
                        'frame',
                        "`filler` «{$item->filler}» есть, а дырки в каркасе нет — ставить его некуда",
                        'there is a `filler` and no gap in the frame to put it in',
                    );
                }

                // THE FILLER IS NOT REFUSED ANY MORE — it is counted, in every language, and
                // {@see FILLER_MISMATCH_WARNING} is where it now lands.
                //
                // The rule «в дырке стоит то, что день учит» is real and the live run of наряд
                // P2-v0.4 measured what enforcing it costs. Three P2 answers for the goal «К врачу
                // с ребёнком» were refused by it and three repairs were bought trying to satisfy
                // it, $0.20 in all, and day 1 never shipped. Every refused card was a sentence a
                // person would say: «Is it for your child?», «Should we go to the front desk?»,
                // «In which clinic is it?». The gaps fall on `child`, `time`, `front desk` — and
                // the day CANNOT card those: basic words are barred by {@see BasicVocabulary} and
                // numbers live only on their own shelf, so two gates were asking for opposite
                // things and the model was caught between them.
                //
                // Decisive: the P2 v0.4 prompt never states this rule. The repair prompt does state
                // it, plainly, and the model broke it three times running — which is what an
                // unsatisfiable instruction looks like from outside. Until the owner rules (either
                // P2 v0.4 gains the sentence and this goes back to card-fatal, or it stays a
                // counter), a day that ships with a counted mismatch beats a plan with no day 1.
            }

            if (str_contains($item->translation, self::SLOT)) {
                $out[] = PlanViolation::onCard(
                    self::TRANSLATION_HAS_GAP,
                    $item,
                    'translation',
                    'в переводе стоит «' . self::SLOT . '» — ученик читает вопрос с дыркой вместо фразы',
                    'the `translation` carries a gap; it describes the FULL assembled sentence',
                );
            }

            foreach ($this->slotBearingFields($item) as $field) {
                $out[] = PlanViolation::onCard(
                    self::GAP_OUTSIDE_FRAME,
                    $item,
                    $field,
                    "`{$field}` содержит «" . self::SLOT . '» — дырка живёт только в `frame`',
                    "`{$field}` carries a gap; the gap lives in `frame` and nowhere else",
                );
            }
        }

        return $out;
    }

    /**
     * «Длинного текста в карточках нет вообще» — канон §7, counted in words.
     *
     * @return list<PlanViolation>
     */
    private function checkSizes(PlanDayCandidate $day): array
    {
        $out = [];
        foreach ($day->items as $item) {
            $limit = self::SIZE_LIMIT[$item->arrayName()] ?? null;
            if ($limit === null) {
                continue;
            }

            $words = self::wordCount($item->text);
            if ($words === 0 || ($words >= $limit[0] && $words <= $limit[1])) {
                continue;
            }

            $out[] = PlanViolation::onCard(
                self::KIND_SIZE,
                $item,
                $item->frame === '' ? 'text' : 'frame',
                "в карточке {$words} слов(а), а полка «{$item->arrayName()}» держит {$limit[0]}–{$limit[1]}",
                "this card is {$words} words and its shelf allows {$limit[0]}–{$limit[1]}",
            );
        }

        return $out;
    }

    /**
     * NO CARD IS ANOTHER CARD — of this day, of the rescue kit, or of a day already taught.
     *
     * Three populations and one code, because they are one failure for the learner: a slot spent on
     * something they already have. The rescue kit is the newest of the three and the one the model
     * cannot see coming — it is added by the server, so «Could you repeat that?» is a perfectly
     * sensible line to write and a duplicate all the same.
     *
     * @return list<PlanViolation>
     */
    private function checkClones(PlanDayCandidate $day): array
    {
        $forbidden = [];
        foreach ([...$day->rescueKit, ...$day->knownTexts] as $text) {
            $key = $this->normalize($text);
            if ($key !== '') {
                $forbidden[$key] = trim($text);
            }
        }

        $seen = [];
        $out = [];
        foreach ($day->items as $item) {
            $key = $this->normalize($item->text);
            if ($key === '') {
                continue;
            }

            if (isset($forbidden[$key])) {
                $out[] = PlanViolation::onCard(
                    self::CLONE,
                    $item,
                    $item->frame === '' ? 'text' : 'frame',
                    'эта карточка уже есть у ученика — она в спасательном наборе или её ввёл более '
                    . 'ранний день плана',
                    'this card duplicates a rescue-kit phrase or a unit an earlier day of this plan taught',
                );

                continue;
            }

            if (isset($seen[$key])) {
                $out[] = PlanViolation::onCard(
                    self::CLONE,
                    $item,
                    $item->frame === '' ? 'text' : 'frame',
                    'две карточки дня собираются в один и тот же текст',
                    'another card of this day assembles into the same text',
                );

                continue;
            }
            $seen[$key] = true;
        }

        return $out;
    }

    /**
     * THE EXAMPLES — a sentence with the word in it, and a DIFFERENT sentence for every word.
     *
     * @return list<PlanViolation>
     */
    private function checkExamples(PlanDayCandidate $day): array
    {
        $terms = [];
        foreach ($day->items as $item) {
            $key = $this->normalize($item->text);
            if ($key !== '') {
                $terms[$key] = $item->text;
            }
        }
        $dayTerms = array_values($terms);

        $skeletons = [];
        $out = [];
        foreach ($day->items as $item) {
            $example = trim($item->example);
            if ($example === '') {
                continue;
            }

            if (isset($terms[$this->normalize($example)])) {
                $out[] = PlanViolation::onCard(
                    self::EXAMPLE_IS_A_TERM,
                    $item,
                    'example',
                    'пример — это дословно карточка дня, а не предложение с ней внутри',
                    'the `example` is, word for word, a card of this day rather than a sentence containing one',
                );

                continue;
            }

            $skeleton = $this->skeletons->of($example, $dayTerms);
            if ($skeleton === null) {
                continue;
            }
            if (isset($skeletons[$skeleton])) {
                $out[] = PlanViolation::onCard(
                    self::EXAMPLE_SKELETON_CLONE,
                    $item,
                    'example',
                    'это то же предложение, что у другой карточки, с подставленным своим словом — '
                    . 'так получается «I need to worse tomorrow»',
                    'this `example` is another card\'s sentence with this card\'s term swapped in; write a sentence of its own',
                );

                continue;
            }
            $skeletons[$skeleton] = true;
        }

        return $out;
    }

    /**
     * BASIC VOCABULARY IS NOT A CARD — канон §7, and the level decides how loudly.
     *
     * @return list<PlanViolation>
     */
    private function checkBasics(PlanDayCandidate $day): array
    {
        if (! $this->basics->judges($day->targetLang) || ! $this->basics->isFatalAt($day->level)) {
            return [];
        }

        $out = [];
        foreach ($this->substitutions($day) as $item) {
            if (! $this->basics->isBasic($day->targetLang, $item->text)) {
                continue;
            }

            $out[] = PlanViolation::onCard(
                self::WORD_IS_BASIC,
                $item,
                'text',
                'это базовое слово — число, день недели, местоимение и подобное; день сцены на них '
                . 'не тратится, а числа живут на полке «numbers»',
                'this is basic vocabulary (a number, a weekday, a pronoun, be/have/go and the like) '
                . 'and is never a card; replace it with something this scene actually needs',
            );
        }

        return $out;
    }

    /**
     * A NAME IS NOT A CARD — it belongs in the filler of a line (канон §7).
     *
     * ## Only what is actually a name
     *
     * The list this reads is P1's, and P1 fills it loosely. On the live run of наряд P2-v0.4 the
     * skeleton for «К врачу с ребёнком» answered `entities: [clinic, front desk, appointment,
     * walk-in clinic]` — four common nouns, and precisely the vocabulary the scene exists to teach.
     * Barring them turned the day into an impossible request: teach this encounter, but not with
     * any of its words.
     *
     * So an entity bars a card only when it LOOKS like a name — a capital letter where the
     * language does not put one by default, «Dr. Ahmed», «Boots», «Charing Cross». A lowercase
     * entity is P1 having filed ordinary vocabulary in the wrong list, and the day is judged on
     * what the word is rather than on where the skeleton put it. Goal terms keep the strict rule:
     * those are the learner's OWN Latin-alphabet words, which they already have.
     *
     * @return list<PlanViolation>
     */
    private function checkNames(PlanDayCandidate $day): array
    {
        $names = [];
        foreach ($day->entityNames as $name) {
            $key = $this->normalize($name);
            if ($key !== '' && self::looksLikeAName($name)) {
                $names[$key] = trim($name);
            }
        }
        foreach ($day->goalTerms as $name) {
            $key = $this->normalize($name);
            if ($key !== '') {
                $names[$key] = trim($name);
            }
        }
        if ($names === []) {
            return [];
        }

        $out = [];
        foreach ($this->substitutions($day) as $item) {
            $named = $names[$this->normalize($item->text)] ?? null;
            if ($named === null) {
                continue;
            }

            $out[] = PlanViolation::onCard(
                self::TERM_IS_A_NAME,
                $item,
                'text',
                'карточка учит имя собственное «' . $named . '» — учить в нём нечего; имя должно '
                . 'стоять в дырке реплики',
                'this card teaches a proper noun of the scenario. A name is filler, not vocabulary',
            );
        }

        return $out;
    }

    /**
     * THE KEY — the support-language side of a card, and the two ways it stops being one.
     *
     * @return list<PlanViolation>
     */
    private function checkKeys(PlanDayCandidate $day): array
    {
        $out = [];
        foreach ($day->items as $item) {
            $translation = trim($item->translation);
            if ($translation === '') {
                continue;
            }

            // «Ivanov» glossed «Иванов» is one word and one piece of information: the learner reads
            // the Latin, says the Cyrillic, and has learned that a name is spelled as it sounds.
            // Every other gate passes it, because character by character the two differ.
            if ($this->normalize($translation) === $this->normalize($item->text)
                || $this->sameness->same($translation, $item->text)) {
                $out[] = PlanViolation::onCard(
                    self::TRANSLATION_IS_TRANSLITERATION,
                    $item,
                    'translation',
                    'перевод — это сама карточка (или она же в другом алфавите): карточка отвечает '
                    . 'на собственный вопрос',
                    'the `translation` is the term itself, or the same word written in the other alphabet',
                );
            }
        }

        return $out;
    }

    /**
     * EVERY LINE'S TRANSLATION CARRIES ITS OWN KEY — гейт 219, unchanged.
     *
     * @return list<PlanViolation>
     */
    private function checkLineTranslations(PlanDayCandidate $day): array
    {
        if (! $this->keyPresence->judges($day->supportLang)) {
            return [];
        }

        $translations = [];
        foreach ($this->substitutions($day) as $item) {
            $translations[$item->text] = $item->translation;
        }

        $out = [];
        foreach ($day->lines() as $line) {
            $key = PlanSpeakingKey::of($line, $day->items);
            $keyTranslation = $key === null ? null : ($translations[$key] ?? null);
            if ($key === null || $keyTranslation === null || trim($keyTranslation) === '') {
                continue;
            }
            if ($this->keyPresence->holds($day->supportLang, $line->translation, $keyTranslation)) {
                continue;
            }

            $out[] = PlanViolation::onCard(
                self::TRANSLATION_MISSING_KEY,
                $line,
                'translation',
                "перевод реплики не содержит перевода ключевой карточки «{$key}» "
                . "(«{$keyTranslation}») — ученик читает вопрос, в котором не спрошено то, "
                . 'что карточка требует произнести',
                'the line translation does not render its key card, so the prompt does not ask for '
                . 'the word the card grades',
            );
        }

        return $out;
    }

    /**
     * THE NUMBER THE LINE SAYS IS THE NUMBER THE CARD IS GRADED ON — {@see NumberSpelling}.
     *
     * @return list<PlanViolation>
     */
    private function checkNumbers(PlanDayCandidate $day): array
    {
        if (! $this->numbers->judges($day->targetLang)) {
            return [];
        }

        $out = [];
        foreach ($day->shelf(PlanShelf::Numbers) as $item) {
            $value = trim((string) $item->value);
            if ($value === '') {
                $out[] = PlanViolation::onCard(
                    self::NUMBER_VALUE_MISMATCH,
                    $item,
                    'value',
                    'у числа нет `value` — ученик вводит цифрами, а сверять их не с чем',
                    'a `numbers` card has no `value`; the learner answers in digits and there is nothing to grade against',
                );

                continue;
            }

            if ($this->numbers->heardIn($day->targetLang, $item->text, $value)) {
                continue;
            }

            $out[] = PlanViolation::onCard(
                self::NUMBER_VALUE_MISMATCH,
                $item,
                'value',
                "в реплике не звучит число «{$value}» — карточка просит услышать одно, а засчитывает другое",
                'the line does not say the number this card is graded on',
            );
        }

        return $out;
    }

    // ── counted ──────────────────────────────────────────────────────────────────────────────

    /** @return list<PlanViolation> */
    private function warnShelfSizes(PlanDayCandidate $day): array
    {
        $counted = [
            'hear' => count($day->shelf(PlanShelf::Hear)),
            'say' => count($day->shelf(PlanShelf::Say)),
            'ask' => count($day->shelf(PlanShelf::Ask)),
            'words' => count($day->shelf(PlanShelf::Words)) + count($day->shelf(PlanShelf::Chunks)),
            'numbers' => count($day->shelf(PlanShelf::Numbers)),
        ];

        $out = [];
        foreach (self::SHELF_GUIDE as $shelf => [$min, $max]) {
            $have = $counted[$shelf];
            if ($have >= $min && $have <= $max) {
                continue;
            }

            $label = $shelf === 'words' ? 'слов и связок' : "полка «{$shelf}»";
            $out[] = PlanViolation::onAnswer(
                self::SIZE_OUT_OF_RANGE,
                "{$label}: {$have}, а ориентир — {$min}–{$max}",
                "the `{$shelf}` shelf holds {$have} cards; the guide is {$min}–{$max}",
            );
        }

        return $out;
    }

    /**
     * IS THIS A CONVERSATION OR A QUESTIONNAIRE — the three shape counters of the day.
     *
     * @return list<PlanViolation>
     */
    private function warnConversation(PlanDayCandidate $day): array
    {
        $spoken = [...$day->shelf(PlanShelf::Say), ...$day->shelf(PlanShelf::Ask)];
        if ($spoken === []) {
            return [];
        }

        $out = [];
        $formulas = 0;
        $question = false;
        $repair = false;
        $markers = $this->repairMarkersFor($day->targetLang);

        foreach ($spoken as $line) {
            if (! str_contains($line->frame, self::SLOT)) {
                $formulas++;
            }
            if (str_ends_with(rtrim($line->text), '?')) {
                $question = true;
            }
            if ($markers !== [] && $this->isRepairMove($line->text, $markers)) {
                $repair = true;
            }
        }

        $cap = (int) ceil(count($spoken) * self::MAX_FORMULA_SHARE);
        if ($formulas > $cap) {
            $out[] = PlanViolation::onAnswer(
                self::FORMULA_CAP,
                "реплик без дырки {$formulas} из " . count($spoken) . ", а треть с округлением вверх — это {$cap}",
                "{$formulas} of " . count($spoken) . " spoken lines have no gap; a third rounded up is {$cap}",
            );
        }

        if (! $question) {
            $out[] = PlanViolation::onAnswer(
                self::NO_QUESTION,
                'ни одна реплика ученика не заканчивается вопросительным знаком — день учит отвечать '
                . 'и не учит спрашивать',
                'no line of the learner\'s ends in a question mark',
            );
        }

        if (! $repair && $markers !== []) {
            $out[] = PlanViolation::onAnswer(
                self::NO_REPAIR,
                'ни одной реплики-починки («Could you repeat…») — на настоящем разговоре ломается ровно это',
                'no line asks for a repeat or says it was not caught',
            );
        }

        $lines = $day->lines();
        $role = count($day->shelf(PlanShelf::Hear));
        $ceiling = (int) floor(count($lines) * self::MAX_ROLE_SHARE);
        if ($lines !== [] && $role > $ceiling) {
            $out[] = PlanViolation::onAnswer(
                self::ROLE_LINE_SHARE,
                "реплик собеседника {$role} из " . count($lines) . ', а их должно быть не больше половины',
                "{$role} of " . count($lines) . ' lines are the interlocutor\'s',
            );
        }

        return $out;
    }

    /**
     * DOES EVERY PROMISE OF THE SCENE HAVE A CARD, and does every piece stand in a line?
     *
     * @return list<PlanViolation>
     */
    private function warnCoverage(PlanDayCandidate $day): array
    {
        $out = [];

        $served = [];
        foreach ($day->items as $item) {
            $ref = trim((string) $item->skillRef);
            if ($ref !== '') {
                $served[$ref] = true;
            }
        }
        foreach ($day->skillIds as $skillId) {
            if (! isset($served[$skillId])) {
                $out[] = PlanViolation::onAnswer(
                    self::SKILL_UNCOVERED,
                    "умение «{$skillId}» не закрыто ни одной карточкой дня",
                    "no card of this day serves the skill `{$skillId}`",
                );
            }
        }

        // «Слова и связки — из этих же реплик» (канон §2). A piece that stands in no line of the
        // day is a piece the day never combines, which is the whole difference between a scene and
        // a glossary with a date on it.
        $lines = '';
        foreach ($day->lines() as $line) {
            $lines .= ' ' . $this->normalize($line->text) . ' ';
        }
        foreach ($this->substitutions($day) as $item) {
            $needle = $this->normalize($item->text);
            if ($needle === '' || str_contains($lines, ' ' . $needle . ' ')) {
                continue;
            }

            $out[] = PlanViolation::onCard(
                self::SUBSTITUTION_OUTSIDE_FRAME,
                $item,
                'text',
                'это слово не стоит ни в одной реплике дня — его некуда подставить',
                'this piece stands in no line of the day, so nothing combines with it',
            );
        }

        // The вводка is already written and shown above the day; a card that retells it spends a
        // slot on something the learner has read.
        $intro = $this->normalize($day->sceneIntro);
        if ($intro !== '') {
            foreach ($day->items as $item) {
                $translation = $this->normalize($item->translation);
                if ($translation !== '' && self::wordCount($translation) >= 4 && str_contains($intro, $translation)) {
                    $out[] = PlanViolation::onCard(
                        self::INTRO_REPEATED,
                        $item,
                        'translation',
                        'карточка пересказывает вводку сцены, которую ученик уже прочитал',
                        'this card retells the scene intro the learner has already read',
                    );
                }
            }
        }

        return $out;
    }

    /**
     * The per-card counters — everything that makes ONE card weaker without making the day
     * unplayable.
     *
     * @return list<PlanViolation>
     */
    private function warnCards(PlanDayCandidate $day): array
    {
        $out = [];
        $cards = $this->substitutionTexts($day);
        $vocabulary = $this->dayVocabulary($day->items);
        $seenKeys = [];
        $basicsCounted = $this->basics->judges($day->targetLang) && ! $this->basics->isFatalAt($day->level);

        foreach ($day->items as $item) {
            $shelf = PlanShelf::tryFromName($item->arrayName());

            // THE WHOLE OF `card.filler_not_card` NOW LIVES HERE, in every language — a counter on
            // the day rather than a refusal of it, for the reasons written at the fatal check's old
            // site. `hear` and `numbers` are exempt even from the counter: those lines are
            // understood and never produced, so a filler the day did not teach is not a demand made
            // of the learner, and counting it would bury the counter in noise.
            if ($item->filler !== ''
                && $shelf !== PlanShelf::Numbers
                && $shelf !== PlanShelf::Hear
                && ! $this->fillerIsTaught($day, $item->filler, $cards)) {
                $out[] = PlanViolation::onCard(
                    self::FILLER_MISMATCH_WARNING,
                    $item,
                    'filler',
                    "`filler` «{$item->filler}» не совпадает посимвольно ни с одной карточкой дня — "
                    . 'возможно, склонение, а возможно, слово не из этого дня',
                    'the `filler` matches no card of this day character for character',
                );
            }

            if ($shelf !== null && $shelf->wantsImage() && trim($item->imageApiPrompt) === '') {
                $out[] = PlanViolation::onCard(
                    self::IMAGE_PROMPT_MISSING,
                    $item,
                    'image_api_prompt',
                    'у слова нет описания картинки — карточка останется без иллюстрации',
                    'this word card carries no image query, so it will never be illustrated',
                );
            }

            $key = $this->normalize($item->translation);
            if ($key !== '' && isset($seenKeys[$key])) {
                $out[] = PlanViolation::onCard(
                    self::KEY_DUPLICATED,
                    $item,
                    'translation',
                    'тот же перевод уже стоит у другой карточки дня — два одинаковых вопроса с разными ответами',
                    'another card of this day already uses this `translation`',
                );
            }
            $seenKeys[$key] = true;

            foreach (['translation' => $item->translation, 'example_translation' => $item->exampleTranslation] as $field => $value) {
                if (trim($value) !== '' && ! $this->keyIsPure($day, $item, trim($value), $vocabulary)) {
                    $out[] = PlanViolation::onCard(
                        self::KEY_NOT_SUPPORT_LANGUAGE,
                        $item,
                        $field,
                        "`{$field}` написан не на языке поддержки",
                        "`{$field}` is not written in the support language",
                    );
                }
            }

            if ($basicsCounted
                && $shelf !== null
                && ! $shelf->isAssembled()
                && $this->basics->isBasic($day->targetLang, $item->text)) {
                $out[] = PlanViolation::onCard(
                    self::WORD_IS_BASIC_WARNING,
                    $item,
                    'text',
                    'базовое слово карточкой — на уровне «с нуля» это законно, но слот дня оно тратит',
                    'a piece of basic vocabulary as a card; legitimate at level zero, still a spent slot',
                );
            }
        }

        return $out;
    }

    // ── the reading hint: repaired, never fatal ──────────────────────────────────────────────

    /**
     * THE HINT, REPAIRED — or null when it cannot be saved.
     *
     * The one rule that fixes instead of failing, unchanged since v0.2: strip sentence punctuation,
     * then apply the alphabet rule. A hint with a Latin letter in a Russian field is refused —
     * refused meaning the FIELD is dropped, never the day.
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

        $stripped = str_replace(self::HINT_MARKS, '', $text);
        if (preg_match('/^\p{L}*$/u', $stripped) !== 1) {
            return null;
        }

        return $this->purity->foreignScriptLetters($supportLang, $text) === [] ? $text : null;
    }

    /** Do the two languages use different scripts — i.e. is a reading hint mandatory? */
    public function scriptsDiffer(string $supportLang, string $targetLang): bool
    {
        return self::scriptOf($supportLang) !== self::scriptOf($targetLang);
    }

    /** Which alphabet a language is written in. Anything unlisted is Latin — the harmless guess. */
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

    // ── helpers ──────────────────────────────────────────────────────────────────────────────

    /**
     * The cards a filler may be — the words and connectors of this day, by their exact text.
     *
     * @return array<string, true>
     */
    /**
     * IS WHAT STANDS IN THE GAP SOMETHING THIS DAY GIVES THE LEARNER?
     *
     * The rule is «в дырке стоит то, что день учит», and until the live run it was spelled as
     * character-for-character equality with a `words`/`chunks` card. Two live days died on that
     * spelling, and both times the model was right:
     *
     *   «Should we go to the ___?» / `front desk`, on a day whose chunk is «come to the front desk»
     *   — the piece IS taught, inside a larger one. The repair prompt states the exact-text rule
     *   plainly and the model broke it twice anyway, which is what a rule that cannot be satisfied
     *   looks like from the outside;
     *   «Is it for your ___?» / `child` — `child` is on the basic stop list, so
     *   {@see BasicVocabulary} FORBIDS the day from carding it. Demanding that the filler be a card
     *   made the two gates contradict each other, and a day could satisfy only one of them.
     *
     * So the match is by coverage rather than by equality, in three ways, all cheap and all
     * conservative — a filler passes when the day teaches it (exactly, or inside a bigger piece, or
     * as part of one), or when nothing could have taught it because it is basic vocabulary the
     * learner is assumed to have. Everything else is still refused: an untaught content word in a
     * line the learner must SAY is the defect this gate exists for.
     *
     * @param  array<string, true>  $cards  the day's words and connectors, by text
     */
    private function fillerIsTaught(PlanDayCandidate $day, string $filler, array $cards): bool
    {
        if (isset($cards[$filler])) {
            return true;
        }

        $needle = self::fold($filler);
        if ($needle === '') {
            return true;
        }

        foreach (array_keys($cards) as $card) {
            // INSIDE a bigger piece the day teaches, on word boundaries: «front desk» in the chunk
            // «come to the front desk». One direction only, and never a bare substring: a filler
            // that ADDS words to a card («tusea mare» over the card «tusea») has added something
            // the day did not teach, which is the case the counter is for, and «form» must not be
            // excused by «information».
            $hay = self::fold((string) $card);
            if ($hay !== '' && self::containsWords($hay, $needle)) {
                return true;
            }
        }

        if (! $this->basics->judges($day->targetLang)) {
            return false;
        }

        foreach (preg_split('/\s+/u', $needle) ?: [] as $word) {
            if ($word !== '' && ! $this->basics->isBasic($day->targetLang, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Is this string written the way a name is written — a capital where a common noun has none?
     *
     * Deliberately shallow. It is not asking whether the thing IS a name, it is asking whether P1
     * wrote it as one, which is the only signal a list of bare strings carries. «Charing Cross»
     * qualifies, «front desk» does not, and a language whose script has no letter case (Japanese,
     * Arabic, Georgian) answers false for everything — the same silence every rule here keeps when
     * the language gives it nothing to judge by.
     */
    private static function looksLikeAName(string $text): bool
    {
        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $first = mb_substr(ltrim($word, '«"\'('), 0, 1);
            if ($first !== '' && mb_strtoupper($first) === $first && mb_strtolower($first) !== $first) {
                return true;
            }
        }

        return false;
    }

    /** Does `$hay` contain `$needle` on word boundaries? */
    private static function containsWords(string $hay, string $needle): bool
    {
        return str_contains(' ' . $hay . ' ', ' ' . $needle . ' ');
    }

    /** Case and punctuation off, spaces collapsed — the shape a filler is compared in. */
    private static function fold(string $text): string
    {
        $folded = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower(trim($text))) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $folded));
    }

    /**
     * The day's pieces as a lookup — every word and connector it teaches, by text.
     *
     * @return array<string, true>
     */
    private function substitutionTexts(PlanDayCandidate $day): array
    {
        $out = [];
        foreach ($this->substitutions($day) as $item) {
            $out[$item->text] = true;
        }

        return $out;
    }

    /**
     * The day's PIECES — words and connectors, the two shelves a line is built from.
     *
     * @return list<PlanDayItem>
     */
    private function substitutions(PlanDayCandidate $day): array
    {
        return [...$day->shelf(PlanShelf::Words), ...$day->shelf(PlanShelf::Chunks)];
    }

    /** @return list<string> field names that carry a gap they have no business carrying */
    private function slotBearingFields(PlanDayItem $item): array
    {
        $shelf = PlanShelf::tryFromName($item->arrayName());
        $values = [
            // An assembled card's `text` is the server's own paste, so a gap there means the frame
            // had two and is already named; a written card's `text` is the model's.
            'text' => $shelf !== null && $shelf->isAssembled() ? '' : $item->text,
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
     * Does this line ask for a repeat, a slower pace, or say it was not caught?
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

    /** @return list<string> empty = the check is off for this language */
    private function repairMarkersFor(string $targetLang): array
    {
        $lang = mb_strtolower(trim($targetLang));

        return $this->repairMarkers[$lang]
            ?? $this->repairMarkers[mb_substr($lang, 0, 2)]
            ?? [];
    }

    /**
     * Is this key written in the learner's own language?
     *
     * The plain rule is right for ordinary content and wrong for a plan: abbreviations, codes, the
     * learner's own `goal_terms` ({@see SupportLanguageText}) and the day's OWN vocabulary are
     * legitimate foreign letters in a Russian sentence.
     *
     * @param  array<string, true>  $vocabulary
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
     * Every Latin word the day says out loud — its cards' `text` and `example`.
     *
     * @param  list<PlanDayItem>  $items
     * @return array<string, true>
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

    private static function wordCount(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? 0 : count($words);
    }

    /** Case-folded, punctuation-free, whitespace-collapsed — for comparing two strings as content. */
    private function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $stripped = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $stripped));
    }
}
