<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\PlanCoherenceCandidate;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;

/**
 * A day, judged against THE REST OF ITS PLAN.
 *
 * {@see PlanDayValidator} asks whether a day works on its own — enough replies, every checkpoint
 * closed, no example that is merely a term. This asks the question that only exists because a plan
 * is a SEQUENCE: does this day belong in it, or does it quietly re-teach yesterday?
 *
 * The failure it exists for is specific and was watched happening. Day 2 is written with day 1's
 * terms in its KNOWN block and asked for fresh examples of them, not for the words again. A model
 * that ignores that instruction produces a day that looks perfect to every other gate: the counts
 * are right, the checkpoints are closed, the keys are clean — and the learner is introduced to
 * «болит спина» for the second time while day 2's own material never arrives. Terms are globally
 * deduplicated, so the second copy does not even become a second term: day 2's collection ends up
 * holding day 1's words, the two days become one day, and the plan silently teaches half of what it
 * promised.
 *
 * ## The four rules
 *
 * 1. **A term is new on ONE day of the plan.** Checked against the same KNOWN map the prompt was
 *    built from, so the gate and the instruction cannot disagree about what «already met» means.
 * 2. **Two days do not promise the same checkpoint.** A duplicate is not a harmless repetition: the
 *    conversation ticks checkpoints off, and the same line ticked twice reads as two abilities.
 * 3. **The skeleton's ENTITIES are respected.** Minimal and deterministic — see {@see checkEntities()}.
 * The reply share used to be a fourth rule here, restating {@see PlanDayValidator}'s band so that
 * a regenerated day could not come back as a vocabulary list. v0.2 removed the band: the server
 * hands the model three EXACT counts and the day validator counts against them, on every attempt,
 * including the re-run. A second copy of an exact count is not a safety net — it is a second place
 * to forget to update.
 */
final class PlanCoherenceValidator
{
    public const TERM_REPEATED = 'plan.term_repeated';
    public const CHECKPOINT_DUPLICATED = 'plan.checkpoint_duplicated';
    public const ENTITY_DISAGREEMENT = 'plan.entity_disagreement';

    /**
     * The agreement markers, per grammatical value — words that CONTRADICT it.
     *
     * Deliberately tiny, deliberately Russian, and deliberately about pronouns and demonstratives
     * only. The rule this serves is «род/число из entities не противоречат переводам дня», and the
     * honest scope of a deterministic check is exactly that: a sentence that names the cat and then
     * calls it «она» is wrong in a way a regular expression can see. Anything subtler — agreement
     * inside a noun phrase, verb endings in the past tense — is a parser's job, and a half-parser
     * that fires on «одна из них» would cost a paid regeneration for being clever.
     *
     * Absent from the table is `note`, which is free text nobody can check, and any language but
     * the support one: the check runs only when the plan's support language is Russian
     * ({@see validate()}), because these markers are Russian words.
     *
     * @var array<string, list<string>>
     */
    private const CONTRADICTS = [
        'masculine' => ['она', 'её', 'ее', 'эта', 'одна'],
        'feminine' => ['он', 'его', 'этот', 'один'],
        'neuter' => ['он', 'она', 'его', 'её', 'ее', 'этот', 'эта'],
        'singular' => ['они', 'их', 'эти'],
        'plural' => ['он', 'она', 'оно', 'его', 'её', 'ее', 'этот', 'эта'],
    ];

    /** The support language whose agreement markers the table above holds. */
    private const MARKER_LANG = 'ru';

    /**
     * The shortest entity name this check will look for. Below three letters a «name» is a particle,
     * and matching one would fire on half the language.
     */
    private const MIN_NAME = 3;

    /**
     * How many letters an inflected form may add to the name before it stops being that word.
     *
     * The whole of the matching heuristic, and it is here because the obvious version is wrong in
     * Russian: «кот» is a prefix of «который», so a plain `str_contains` on a three-letter entity
     * name would flag every sentence with the word «который» in it and buy a regeneration for it.
     * A case ending is short — «кота», «коту», «котом» — so a word that starts with the name and is
     * no more than three letters longer is that word inflected, and anything longer is a different
     * word that happens to start the same way.
     */
    private const MAX_INFLECTION = 3;

    /** @return list<PlanViolation> empty = the day belongs in its plan */
    public function validate(PlanCoherenceCandidate $day): array
    {
        return [
            ...$this->checkNewTerms($day),
            ...$this->checkCheckpoints($day),
            ...$this->checkEntities($day),
        ];
    }

    /**
     * Rule 1 — a term is introduced on ONE day.
     *
     * @return list<PlanViolation>
     */
    private function checkNewTerms(PlanCoherenceCandidate $day): array
    {
        if ($day->knownTexts === []) {
            return [];
        }

        $known = [];
        foreach ($day->knownTexts as $text) {
            $known[$this->normalize($text)] = $text;
        }

        $violations = [];
        foreach ($day->items as $item) {
            $key = $this->normalize($item->text);
            if ($key === '' || ! isset($known[$key])) {
                continue;
            }

            $violations[] = new PlanViolation(
                self::TERM_REPEATED,
                'этот термин уже введён на более раннем дне плана — для него нужен только новый '
                . 'пример в блоке KNOWN, а не карточка заново',
                $item->text,
            );
        }

        return $violations;
    }

    /**
     * Rule 2 — no checkpoint is promised twice.
     *
     * @return list<PlanViolation>
     */
    private function checkCheckpoints(PlanCoherenceCandidate $day): array
    {
        $seen = [];
        foreach ($day->previousCheckpoints as $checkpoint) {
            $seen[$this->normalize($checkpoint)] = true;
        }

        $violations = [];
        foreach ($day->dayCheckpoints as $checkpoint) {
            $key = $this->normalize($checkpoint);
            if ($key === '') {
                continue;
            }
            if (isset($seen[$key])) {
                $violations[] = new PlanViolation(
                    self::CHECKPOINT_DUPLICATED,
                    'этот чек-пойнт уже обещает другой день плана: «' . $checkpoint . '»',
                );
            }
            $seen[$key] = true;
        }

        return $violations;
    }

    /**
     * Rule 3 — the skeleton's entities are not contradicted.
     *
     * The check is deliberately narrow and it fires on ONE shape only: a sentence that NAMES the
     * entity and, in the same sentence, uses a pronoun or demonstrative of the wrong gender or
     * number. «Кота зовут Барсик, она любит спать» is caught; everything requiring agreement
     * analysis is not, on purpose ({@see CONTRADICTS}).
     *
     * It runs only when the plan's support language is the one the marker table is written in.
     * A Romanian plan gets no entity check rather than a Russian one applied to Romanian, which
     * would fire on every second sentence and cost a paid regeneration each time.
     *
     * @return list<PlanViolation>
     */
    private function checkEntities(PlanCoherenceCandidate $day): array
    {
        if ($day->supportLang !== self::MARKER_LANG || $day->entities === []) {
            return [];
        }

        $violations = [];
        foreach ($day->items as $item) {
            foreach ([$item->translation, $item->exampleTranslation] as $text) {
                $violations = [...$violations, ...$this->checkSentence($day, $item, (string) $text)];
            }
        }

        return $violations;
    }

    /**
     * One key or one example gloss, checked against every entity it names.
     *
     * @return list<PlanViolation>
     */
    private function checkSentence(PlanCoherenceCandidate $day, PlanDayItem $item, string $text): array
    {
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            return [];
        }
        $words = explode(' ', $normalized);

        $violations = [];
        foreach ($day->entities as $entity) {
            if (! $this->mentions($words, $this->normalize($entity['name']))) {
                continue;
            }

            foreach ([$entity['gender'], $entity['number']] as $value) {
                $markers = self::CONTRADICTS[mb_strtolower(trim((string) $value))] ?? [];
                $hit = array_values(array_intersect($words, $markers));
                if ($hit === []) {
                    continue;
                }

                $violations[] = new PlanViolation(
                    self::ENTITY_DISAGREEMENT,
                    'перевод говорит про «' . $entity['name'] . '» словом «' . $hit[0]
                    . '», а каркас объявил его ' . $value,
                    $item->text,
                );
            }
        }

        return $violations;
    }

    /**
     * Is this sentence ABOUT the entity — does it name it, in any case ending?
     *
     * A multi-word name is matched on its first word: «врач-терапевт» is «врач», and the second half
     * carries no agreement the first does not.
     *
     * @param  list<string>  $words  the sentence, normalised and split
     */
    private function mentions(array $words, string $name): bool
    {
        $stem = explode(' ', $name)[0];
        if (mb_strlen($stem) < self::MIN_NAME) {
            return false;
        }

        $ceiling = mb_strlen($stem) + self::MAX_INFLECTION;
        foreach ($words as $word) {
            if (str_starts_with($word, $stem) && mb_strlen($word) <= $ceiling) {
                return true;
            }
        }

        return false;
    }

    /** Case-folded, punctuation-free, whitespace-collapsed — the same normalisation as the day gate. */
    private function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $stripped = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $stripped));
    }
}
