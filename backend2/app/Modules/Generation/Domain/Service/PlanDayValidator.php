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
 * a real word of the day in the hole. **Every word and connector of the day fits some frame, and
 * its `example` is that sentence.** That is what turns eight lines and six words into twenty
 * sentences the learner can say instead of eight they memorised, and it is checkable: build the
 * frame into a regular expression, put the term in the slot, and look for it in the example.
 *
 * **The slot lives in `frame` and nowhere else.** v0.2.1's own addition, and it is here because the
 * live day failed on it: four lines of eight came back with `___` still standing in `text`. The
 * frame rule above cannot catch that on its own — «I'm a ___ developer» IS its frame with something
 * in the hole, and the something is the hole. See {@see SLOT_FORBIDDEN_IN}.
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
 * ({@see \App\Modules\Generation\Application\Port\PlanDayDefectReporter}). Under v0.1 a stray
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
    public const FRAME_SHARE = 'day.frame_share';
    public const FRAME_MISMATCH = 'day.frame_mismatch';
    public const SLOT_OUTSIDE_FRAME = 'day.slot_outside_frame';
    public const ROLE_LINE_INVENTED = 'day.role_line_invented';
    public const ROLE_LINE_SHARE = 'day.role_line_share';
    public const SUBSTITUTION_WITHOUT_FRAME = 'day.substitution_without_frame';
    public const EXAMPLE_IS_A_TERM = 'day.example_is_a_term';
    public const EXAMPLE_DUPLICATED = 'day.example_duplicated';
    public const EXAMPLE_MISSING = 'day.example_missing';
    public const KEY_IS_THE_TERM = 'day.key_is_the_term';
    public const KEY_DUPLICATED = 'day.key_duplicated';
    public const KEY_NOT_SUPPORT_LANGUAGE = 'day.key_not_support_language';
    public const DESCRIPTION_GIVES_AWAY = 'day.description_gives_away';
    public const IMAGE_PROMPT_MISSING = 'day.image_prompt_missing';

    /**
     * The most lines that may be fixed formulas with no slot — «Nice to meet you».
     *
     * A THIRD, ROUNDED DOWN, and the rounding is the rule rather than an implementation detail:
     * 8 lines → 2 formulas, 4 → 1, 14 → 4. v0.2 said «не больше трети» in prose and the model read
     * it as «около трети», answering 3–4 of 8 twice in a row; v0.2.1 prints the three numbers.
     */
    private const MAX_FORMULA_SHARE = 1 / 3;

    /** The most lines that may be the interlocutor's rather than the learner's own. */
    private const MAX_ROLE_SHARE = 1 / 4;

    /** The slot in a frame. */
    private const SLOT = '___';

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

    public function __construct(
        private readonly LanguagePurity $purity = new LanguagePurity(),
        private readonly SupportLanguageText $supportText = new SupportLanguageText(),
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
            ...$this->checkSubstitutions($day),
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

        foreach ($expected as $kind => $want) {
            if ($counted[$kind] !== $want) {
                $violations[] = new PlanViolation(
                    self::ARRAY_COUNT,
                    "«{$kind}»: {$counted[$kind]}, а день просил {$want}",
                );
            }
        }

        if (count($day->items) !== $day->termBudget) {
            $violations[] = new PlanViolation(
                self::TERM_COUNT,
                'карточек ' . count($day->items) . ', а день просил ' . $day->termBudget,
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
                $violations[] = new PlanViolation(
                    self::KIND_MISMATCH,
                    "`is_line` говорит одно, а «{$item->kind}» — другое",
                    $item->text,
                );
            }

            if ($isLine && ! in_array($item->speaker, [PlanDayItem::SPEAKER_LEARNER, PlanDayItem::SPEAKER_ROLE], true)) {
                $violations[] = new PlanViolation(
                    self::KIND_MISMATCH,
                    'у реплики нет говорящего — непонятно, произносит её юзер или собеседник',
                    $item->text,
                );
            }

            if (! $isLine && $item->speaker !== null) {
                $violations[] = new PlanViolation(
                    self::KIND_MISMATCH,
                    'у подстановки есть говорящий, хотя её никто не произносит целиком',
                    $item->text,
                );
            }

            // A connector is a phrasal verb or a fixed collocation. `word` is the one lexical type
            // it cannot be: a one-word term joins nothing.
            if ($item->kind === PlanDayItem::KIND_CHUNK && $item->type === 'word') {
                $violations[] = new PlanViolation(
                    self::KIND_MISMATCH,
                    'связка объявлена как одно слово — связка соединяет, а одно слово не соединяет ничего',
                    $item->text,
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
                $violations[] = new PlanViolation(
                    self::CHECKPOINT_ON_WORD,
                    "подстановка помечена как закрывающая чек-пойнт {$covers}; чек-пойнт закрывается репликой",
                    $item->text,
                );

                continue;
            }

            if ($covers < 1 || $covers > $day->checkpointCount) {
                $violations[] = new PlanViolation(
                    self::CHECKPOINT_OUT_OF_RANGE,
                    "чек-пойнт {$covers} не существует — их у дня {$day->checkpointCount}",
                    $item->text,
                );

                continue;
            }

            $closed[$covers] = true;
        }

        for ($i = 1; $i <= $day->checkpointCount; $i++) {
            if (! isset($closed[$i])) {
                $violations[] = new PlanViolation(
                    self::CHECKPOINT_UNCOVERED,
                    "чек-пойнт {$i} не закрыт ни одной репликой — этот день нельзя пройти",
                );
            }
        }

        return $violations;
    }

    /**
     * THE FRAMES — the rule the whole of v0.2 turns on.
     *
     * Three things, and each one has a number:
     *
     *   at most a THIRD of the lines are formulas with no slot. A day of fixed formulas teaches
     *   sentences the learner can say and nothing they can say NEXT.
     *   a line with a frame IS that frame with something in the hole. «I worked on ___» and
     *   «I worked on the payment module» — if they do not line up, one of the two was invented
     *   after the other and the words of the day have no line to stand in.
     *   at most a QUARTER of the lines are the interlocutor's, and each one is quoted from the
     *   skeleton character for character.
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

        $formulas = 0;
        $roleLines = 0;
        $openings = array_map(static fn (string $l): string => trim($l), $day->openingLines);

        foreach ($lines as $line) {
            if (trim($line->frame) === '') {
                $formulas++;
            } elseif (! $this->fillsFrame($line->frame, $line->text)) {
                $violations[] = new PlanViolation(
                    self::FRAME_MISMATCH,
                    'реплика не является своим каркасом «' . $line->frame . '» с реальным словом в дырке',
                    $line->text,
                );
            }

            if ($line->speaker !== PlanDayItem::SPEAKER_ROLE) {
                continue;
            }

            $roleLines++;
            if (! in_array(trim($line->text), $openings, true)) {
                $violations[] = new PlanViolation(
                    self::ROLE_LINE_INVENTED,
                    'реплика собеседника сочинена, а должна быть дословно взята из opening_lines сцены',
                    $line->text,
                );
            }
        }

        $total = count($lines);
        // The cap is NAMED in the violation, because the violation is what the retry reads: «не
        // больше трети» is the rule the first answer already had and disobeyed, «не больше 2» is
        // a number it can count against.
        $formulaCap = (int) floor($total * self::MAX_FORMULA_SHARE);
        if ($formulas > $formulaCap) {
            $violations[] = new PlanViolation(
                self::FRAME_SHARE,
                "реплик без каркаса {$formulas} из {$total}, а формул можно не больше {$formulaCap} "
                . '— это треть с округлением вниз',
            );
        }

        if ($roleLines > (int) floor($total * self::MAX_ROLE_SHARE)) {
            $violations[] = new PlanViolation(
                self::ROLE_LINE_SHARE,
                "реплик собеседника {$roleLines} из {$total}, а их должно быть не больше четверти",
            );
        }

        return $violations;
    }

    /**
     * EVERY word and connector stands in some frame of this day, and its example is that sentence.
     *
     * This is the rule that makes the day combine. Without it the words are a glossary next to the
     * lines: the learner memorises eight sentences and owns none of them, because nothing ever told
     * them which hole each word goes in.
     *
     * @return list<PlanViolation>
     */
    private function checkSubstitutions(PlanDayCandidate $day): array
    {
        $frames = [];
        foreach ($this->linesOf($day) as $line) {
            $frame = trim($line->frame);
            if ($frame !== '' && str_contains($frame, self::SLOT)) {
                $frames[] = $frame;
            }
        }

        $violations = [];
        foreach ($day->items as $item) {
            if ($item->kind === PlanDayItem::KIND_LINE) {
                continue;
            }

            foreach ($frames as $frame) {
                if ($this->exampleUsesFrame($frame, $item->text, $item->example)) {
                    continue 2;
                }
            }

            $violations[] = new PlanViolation(
                self::SUBSTITUTION_WITHOUT_FRAME,
                'ни один каркас дня не принимает это слово в дырку — его пример не собирается ни из чего',
                $item->text,
            );
        }

        return $violations;
    }

    /**
     * Is `$text` the frame with SOMETHING in its slot?
     *
     * Compared on content and not on characters: case folded, punctuation flattened to spaces,
     * whitespace collapsed. A line that differs from its frame by a full stop is the same line.
     */
    private function fillsFrame(string $frame, string $text): bool
    {
        $pattern = $this->framePattern($frame, '.+');

        return $pattern !== null && preg_match('/^' . $pattern . '$/u', $this->normalize($text)) === 1;
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
                $violations[] = new PlanViolation(self::EXAMPLE_MISSING, 'у карточки нет примера', $item->text);

                continue;
            }

            $key = $this->normalize($example);

            // Its own text or ANY other card's. The «any other» half is the one v0 lost.
            if (isset($terms[$key])) {
                $violations[] = new PlanViolation(
                    self::EXAMPLE_IS_A_TERM,
                    'пример — это дословно термин «' . $terms[$key] . '», а не предложение с ним внутри',
                    $item->text,
                );
            }

            if (isset($seenExamples[$key])) {
                $violations[] = new PlanViolation(
                    self::EXAMPLE_DUPLICATED,
                    'этот же пример уже стоит у «' . $seenExamples[$key] . '»',
                    $item->text,
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
                $violations[] = new PlanViolation(
                    self::KEY_IS_THE_TERM,
                    'ключ совпадает с термином — карточка спрашивает то, на что уже ответила',
                    $item->text,
                );
            }

            $key = $this->normalize($translation);
            if ($key !== '' && isset($seen[$key])) {
                // Two identical questions with two different accepted answers is a card that
                // cannot be passed by knowing the material.
                $violations[] = new PlanViolation(
                    self::KEY_DUPLICATED,
                    'тот же ключ уже стоит у «' . $seen[$key] . '»',
                    $item->text,
                );
            }
            $seen[$key] = $item->text;

            foreach (['translation' => $translation, 'example_translation' => trim($item->exampleTranslation)] as $field => $value) {
                if ($value !== '' && ! $this->keyIsPure($day, $item, $value, $vocabulary)) {
                    $violations[] = new PlanViolation(
                        self::KEY_NOT_SUPPORT_LANGUAGE,
                        "`{$field}` написан не на языке поддержки",
                        $item->text,
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
                $violations[] = new PlanViolation(
                    self::SLOT_OUTSIDE_FRAME,
                    "`{$field}` содержит «" . self::SLOT . '» — дырка живёт только в `frame`, '
                    . 'а поле с дыркой юзеру не произнести',
                    $item->text,
                );
            }

            if ($item->description !== '' && DescriptionSelfReference::givesAway($item->description, $item->text)) {
                $violations[] = new PlanViolation(
                    self::DESCRIPTION_GIVES_AWAY,
                    'описание называет собственный термин — карточка спрашивает то, на что уже ответила',
                    $item->text,
                );
            }

            // A term with nothing to draw is a term with no picture, and the day's collection is
            // the only place the plan gets one: the core generator's image query never runs over
            // plan material. Empty here means the card is illustrated by nothing, for ever.
            if (trim($item->imageApiPrompt) === '') {
                $violations[] = new PlanViolation(
                    self::IMAGE_PROMPT_MISSING,
                    'нет описания картинки — карточка останется без иллюстрации навсегда',
                    $item->text,
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
