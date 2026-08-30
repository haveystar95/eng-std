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
 * them off, and if no reply in the day's material can tick a checkpoint then the day cannot be
 * passed and the learner finds out at the conversation. That is a MECHANICAL failure, and
 * mechanical failures are what deterministic code is for (docs/research/plan-sandbox-2026-08-29.md
 * §8, вопрос 2).
 *
 * So the rules below are the ones where being wrong breaks the machine, not the ones where being
 * wrong makes a card less pretty. Taste stays with the prompt.
 *
 * ## The nine rules, and what each one is protecting
 *
 * 1. **Reply share, 35–55%, and never narrower than the number the server itself asked for.** The
 *    day is a conversation. Under a third replies and it is a vocabulary list with an event date
 *    attached — which is exactly what v0 produced at 31.3% on a 16-term day. See
 *    {@see checkLineShare()} for why the band is expressed in COUNTS.
 * 2. **Every checkpoint closed by at least one REPLY.** The one failure this class cannot let
 *    through.
 * 3. **`covers_checkpoint` only on a reply.** A checkpoint is a thing that must be SAID; a noun
 *    marked as closing one claims the learner can tick it by knowing vocabulary.
 * 4. **No example is the `text` of ANY card of the day.** v0 read the ban as «not its own text» and
 *    filled word cards with other cards' replies verbatim — the learner met the same sentence four
 *    times. A clone is scrap, not a near miss.
 * 5. **No two cards share an example.** Two cards, two sentences.
 * 6. **A key is never its own term.** A card whose question contains its answer asks nothing.
 * 7. **Transliteration is NORMALISED, then checked against the support alphabet.** See
 *    {@see normalizedTransliteration()} — this is the one rule that repairs rather than rejects.
 * 8. **Keys are written in the support language** — with two exemptions, and they are the whole
 *    subtlety of the rule. See {@see keyIsPure()}.
 * 9. **A description never contains its own term.** Reuses the lookup's own check, so a description
 *    is judged by one rule wherever it is written.
 *
 * Plus a tenth that is not a canon rule and is here because the budget is a promise the scheduler
 * made: **the day has the number of cards it was asked for**. A day that came back with three of
 * nine terms is broken in a way no other rule notices.
 */
final class PlanDayValidator
{
    public const LINE_SHARE = 'day.line_share';
    public const CHECKPOINT_UNCOVERED = 'day.checkpoint_uncovered';
    public const CHECKPOINT_ON_WORD = 'day.checkpoint_on_word';
    public const CHECKPOINT_OUT_OF_RANGE = 'day.checkpoint_out_of_range';
    public const EXAMPLE_IS_A_TERM = 'day.example_is_a_term';
    public const EXAMPLE_DUPLICATED = 'day.example_duplicated';
    public const EXAMPLE_MISSING = 'day.example_missing';
    public const KEY_IS_THE_TERM = 'day.key_is_the_term';
    public const KEY_DUPLICATED = 'day.key_duplicated';
    public const KEY_NOT_SUPPORT_LANGUAGE = 'day.key_not_support_language';
    public const TRANSLITERATION_ALPHABET = 'day.transliteration_alphabet';
    public const DESCRIPTION_GIVES_AWAY = 'day.description_gives_away';
    public const TERM_COUNT = 'day.term_count';

    /** The share of the day that must be spoken turns. See {@see checkLineShare()}. */
    public const MIN_LINE_SHARE = 0.35;
    public const MAX_LINE_SHARE = 0.55;

    /**
     * The share the SERVER hands the model as a hard number — {@see
     * \App\Modules\Learning\Domain\ValueObject\ComputedDay::phraseCount()} computes
     * `ceil(0.45 × budget)` and the prompt is told that figure, not a band.
     *
     * Named here so this gate can never contradict it. The two must agree by construction, not by
     * two people keeping two numbers in step.
     */
    private const MANDATED_LINE_SHARE = 0.45;

    /**
     * Sentence punctuation a transliteration picks up by reflex from the reply it transcribes.
     * Stripped, not rejected: the field is a pronunciation hint, and a full stop at the end of one
     * is a typographic accident, not a broken hint.
     */
    private const SENTENCE_PUNCTUATION = [
        '.', ',', '?', '!', ';', ':', '"', '“', '”', '«', '»', '(', ')', '[', ']', '…', '–', '—',
    ];

    /** Marks a hint legitimately carries — a hyphen inside a word, an apostrophe inside one. */
    private const HINT_MARKS = [' ', '-', '\'', '’', '‑'];

    /**
     * An abbreviation: two to five capital Latin letters in a row, not glued to a longer Latin
     * word on either side. `API`, `PHP`, `QA`, `HTML`. See {@see keyIsPure()}.
     *
     * The boundaries are what keep it from eating a name: `(?<![A-Za-z])` and `(?![A-Za-z])` mean
     * the run has to stand on its own, so «BBC» is exempt and the «Sha» of a mixed-case word is
     * not. A trailing hyphenated tail is allowed through with it — «QA-инженерами» is one word in
     * Russian and its Latin half is the abbreviation.
     */
    private const ABBREVIATION = '/(?<![A-Za-z])[A-Z]{2,5}(?![A-Za-z])/u';

    /**
     * A CODE: a token that mixes digits and Latin letters — `14A`, `A320`, `B2`, `H1N1`, `PCR-2`.
     *
     * Decided by shape, exactly like {@see ABBREVIATION} above and for the same reason: a run
     * containing a digit is not a word in any alphabet, so it cannot be evidence that a Russian
     * sentence was written in English. «Извините, где место 14A?» is the only correct way to say it,
     * and the `A` is a seat letter, not a language.
     *
     * The single-letter case is precisely why the abbreviation rule could not cover this: it starts
     * at two letters, because one capital on its own is just a capitalised word. Bolted onto a
     * number it stops being a word at all.
     *
     * Bought on the owner's phone, twice in one night: a travel plan died on `14A`. The check is
     * meant to catch a key written in the wrong language, and a seat number is not that.
     */
    private const CODE = '/(?<![A-Za-z])(?=[0-9A-Za-z-]*[0-9])(?=[0-9A-Za-z-]*[A-Za-z])[0-9A-Za-z]+(?:-[0-9A-Za-z]+)*(?![A-Za-z])/u';

    public function __construct(private readonly LanguagePurity $purity = new LanguagePurity()) {}

    /**
     * THE accepted reply-count range for a day of `$total` cards, and the number the server asked
     * for.
     *
     * Public and static because a SECOND gate now judges the same share
     * ({@see PlanCoherenceValidator}), and two copies of this arithmetic is how one of them ends up
     * refusing a day the other accepts. The reasoning behind computing in counts rather than in
     * percentages is at {@see checkLineShare()}.
     *
     * @return array{0: int, 1: int, 2: int}  min, max, and the mandated `ceil(0.45 × total)`
     */
    public static function lineCountRange(int $total): array
    {
        $mandated = (int) ceil(self::MANDATED_LINE_SHARE * $total);

        return [
            min((int) floor(self::MIN_LINE_SHARE * $total), $mandated),
            max((int) ceil(self::MAX_LINE_SHARE * $total), $mandated),
            $mandated,
        ];
    }

    /** @return list<PlanViolation> empty = the day may be written */
    public function validate(PlanDayCandidate $day): array
    {
        $violations = [];
        $items = $day->items;

        if (count($items) !== $day->termBudget) {
            $violations[] = new PlanViolation(
                self::TERM_COUNT,
                'карточек ' . count($items) . ', а день просил ' . $day->termBudget,
            );
        }

        if ($items === []) {
            return $violations;
        }

        $violations = [...$violations, ...$this->checkLineShare($day, $items)];
        $violations = [...$violations, ...$this->checkCheckpoints($day, $items)];
        $violations = [...$violations, ...$this->checkExamples($items)];
        $violations = [...$violations, ...$this->checkKeys($day, $items)];

        foreach ($items as $item) {
            $hint = trim((string) $item->transliteration);
            if ($hint !== '' && $this->normalizedTransliteration($day->supportLang, $hint) === null) {
                $violations[] = new PlanViolation(
                    self::TRANSLITERATION_ALPHABET,
                    'транслитерация написана не буквами языка поддержки',
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
        }

        return $violations;
    }

    /**
     * The hint, repaired.
     *
     * The ONE rule that fixes instead of failing, and the reason is measured: the live gate
     * ({@see EnrichmentValidator::transliterationFor()}) allows a hint only a space, a hyphen and
     * an apostrophe, which is the right rule for a WORD. A reply is a sentence and carries a full
     * stop and a comma by definition, so on plan material that gate fired as a lottery — 5 hints of
     * 16 thrown away on one day, 0 on another that happened not to end in a full stop
     * (docs/research/plan-sandbox-2026-08-29.md §7.2). Throwing away a correct pronunciation hint
     * because of a comma is losing content over typography.
     *
     * So: strip sentence punctuation, then apply the alphabet rule unchanged. A hint with a Latin
     * letter in a Russian field is still refused — that one defeats the field for exactly the
     * reader it exists for, and no amount of stripping makes it readable.
     *
     * Returns the cleaned hint, or null when it cannot be saved.
     */
    public function normalizedTransliteration(string $supportLang, ?string $raw): ?string
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
     * @param  list<PlanDayItem>  $items
     * @return list<PlanViolation>
     */
    /**
     * Rule 1, and the reason it counts cards instead of comparing percentages.
     *
     * The canon says 35–55% replies. The server says `ceil(0.45 × budget)` and hands the model that
     * exact number. On an ODD budget the two disagree: 9 terms → 5 replies → 55.6%, which is
     * outside a band written as a percentage. The real S3 day is exactly that shape and it is a
     * good day — the sandbox passed it into design.
     *
     * A gate that refuses a day for obeying our own instruction is not a strict gate, it is a
     * broken one: it would send a correct day back for a second paid generation, and the second
     * answer would fail the same way. So the accepted range is computed in COUNTS and the mandated
     * number is forced inside it. The percentage band is what the range is derived FROM, not what
     * is compared.
     *
     * @param  list<PlanDayItem>  $items
     * @return list<PlanViolation>
     */
    private function checkLineShare(PlanDayCandidate $day, array $items): array
    {
        $total = count($items);
        $lines = 0;
        foreach ($items as $item) {
            if ($item->isLine) {
                $lines++;
            }
        }

        [$min, $max, $mandated] = self::lineCountRange($total);

        if ($lines >= $min && $lines <= $max) {
            return [];
        }

        return [new PlanViolation(
            self::LINE_SHARE,
            'реплик ' . $lines . ' из ' . $total . ' — ' . round($lines / $total * 100, 1)
            . "%, а надо {$min}–{$max} (35–55%, но не уже числа, которое сервер сам заказал: {$mandated})",
        )];
    }

    /**
     * @param  list<PlanDayItem>  $items
     * @return list<PlanViolation>
     */
    private function checkCheckpoints(PlanDayCandidate $day, array $items): array
    {
        $violations = [];
        $closed = [];

        foreach ($items as $item) {
            $covers = $item->coversCheckpoint;
            if ($covers === null) {
                continue;
            }

            if (! $item->isLine) {
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

    /**
     * @param  list<PlanDayItem>  $items
     * @return list<PlanViolation>
     */
    private function checkKeys(PlanDayCandidate $day, array $items): array
    {
        $violations = [];
        $seen = [];

        foreach ($items as $item) {
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
                if ($value !== '' && ! $this->keyIsPure($day, $item, $value)) {
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
     * Is this key written in the learner's own language?
     *
     * The plain rule — «ни одной буквы чужого алфавита в переводе» — is right for ordinary content
     * and WRONG for a plan, in two specific ways that are not exceptions to the product but the
     * product itself:
     *
     * 1. **An ABBREVIATION — two to five capital Latin letters in a row.** `API`, `PHP`, `QA`,
     *    `HTML`, `REST`. Always allowed, in every key, with no list to maintain and no model asked
     *    for an opinion: the SHAPE is the rule, and it is decidable by looking. That matters
     *    because the `goal_terms` exemption below only covers what the learner typed, and the S2
     *    day produced `QA` on its own — correctly, in «работаю с QA-инженерами», a word no Russian
     *    speaker writes any other way. Under the narrower rule that day's key was a violation for
     *    being right.
     *
     *    Two is the floor because one capital letter is just a capitalised word. Five is the
     *    ceiling because past it the run stops looking like an abbreviation and starts looking
     *    like a sentence shouted in the wrong alphabet, which is the thing the check exists to
     *    catch.
     * 2. **`goal_terms`.** The learner typed `Laravel`, `Docker`, `Zoom` themselves, and those are
     *    how their own field is spelled in Russian — mixed-case names the shape rule above cannot
     *    see. The gate used to flag five fields of one day for containing the only correct
     *    spelling (§7.3), and resolving that in the prompt is not possible — the prompt is right
     *    and the gate was right, so the resolution belongs in code, here.
     * 3. **A term that is itself in the other alphabet.** A card for `backend` glossed «бэкенд»
     *    is fine, but a key that must quote the term to be unambiguous is not a key in the wrong
     *    language.
     * 4. **A CODE — a token mixing digits and Latin letters.** `14A`, `A320`, `B2`. Shape again, and
     *    the reason it needs its own rule: the abbreviation shape starts at two letters, and a seat
     *    number carries exactly one. «Извините, где место 14A?» is the only way to say it in
     *    Russian, and the day it was on died twice for being right.
     *
     * So both are removed from the value before the alphabet is looked at. What is left has to be
     * the learner's own language, which is the rule the exemptions exist to keep enforceable.
     */
    private function keyIsPure(PlanDayCandidate $day, PlanDayItem $item, string $value): bool
    {
        // The two SHAPE rules first, because they need nothing told to them and hold in any
        // language: a run of 2–5 capital Latin letters is an abbreviation, and a token carrying a
        // digit is a code. Neither is evidence that a key was written in the wrong language.
        $stripped = (string) preg_replace([self::ABBREVIATION, self::CODE], ' ', $value);

        foreach ([...$day->goalTerms, $item->text] as $token) {
            $token = trim($token);
            if ($token !== '') {
                $stripped = str_ireplace($token, ' ', $stripped);
            }
        }

        return $this->purity->foreignScriptLetters($day->supportLang, $stripped) === [];
    }

    /** Case-folded, punctuation-free, whitespace-collapsed — for comparing two strings as content. */
    private function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $stripped = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $stripped));
    }
}
