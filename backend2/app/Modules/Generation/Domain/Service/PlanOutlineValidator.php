<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\PlanViolation;

/**
 * P1's answer, judged before it is stored.
 *
 * Narrow on purpose. This checks the things that make an outline USABLE — is there anything to
 * schedule, does every promise have something audible that proves it, is every ability PRICED in a
 * range the scheduler can divide by. It does not judge whether the plan is a good plan; that is
 * what the skeleton screen is for, and the learner reads it before committing.
 *
 * ## What v0.2 moved, and what that did to this class
 *
 * The old shape was DAYS with a `term_budget` each and a `checkpoints` list on the role, kept
 * parallel to `outcome` by nothing but instruction — so half of this class was about that
 * parallelism (same length, same order, nth checks nth). The new shape puts the checkpoint on the
 * skill it proves, and the schema forbids the absence, so the parallelism check is gone and the
 * count checks it is replaced by are about the SHAPE OF A PLAN instead: 1–5 scenes, 3–12 abilities,
 * 3–8 terms each.
 *
 * `est_terms` is the one that matters most and the one that did not exist before. Everything the
 * scheduler decides — how many days, whether the goal fits before the event, what gets cut — is
 * arithmetic over these numbers. A skill priced at 40 makes a fourteen-day plan out of a two-day
 * goal; a skill priced at 0 is an ability the scheduler believes is free. The prompt asks for 3–8
 * and this is what makes the answer keep to it.
 *
 * ## The one rule that costs money when it is wrong
 *
 * There is NO retry on an outline ({@see \App\Modules\Generation\Application\Service\PlanOutlineService}):
 * one call, and a refusal is a learner looking at an error after a paid request. So every rule here
 * has to be one that is wrong only when the answer really is broken — which is why the
 * target-language gate runs through {@see SupportLanguageText} (abbreviations, codes and the
 * learner's own `goal_terms` are not evidence of anything) and why the «и» rule below is as narrow
 * as it is.
 *
 * Works on the DECODED ARRAY rather than on a typed outline, and that is a boundary rule, not
 * laziness: the typed outline is Learning's ({@see \App\Modules\Learning\Domain\ValueObject\PlanOutline})
 * and this module cannot see another module's Domain. Generation judges the raw answer, Learning
 * types the judged one.
 */
final class PlanOutlineValidator
{
    public const NO_SCENES = 'outline.no_scenes';
    public const SCENE_COUNT = 'outline.scene_count';
    public const SKILL_COUNT = 'outline.skill_count';
    public const SKILL_WITHOUT_OUTCOME = 'outline.skill_without_outcome';
    public const CHECKPOINT_MISSING = 'outline.checkpoint_missing';
    public const CHECKPOINT_ECHOES_OUTCOME = 'outline.checkpoint_echoes_outcome';
    public const EST_TERMS = 'outline.est_terms';
    public const ROLE_SHAPE = 'outline.role_shape';
    public const OPENING_LINES = 'outline.opening_lines';
    public const OUTCOME_TWO_ACTIONS = 'outline.outcome_two_actions';
    public const TARGET_LANGUAGE = 'outline.target_language';
    public const NOT_A_LIST = 'outline.not_a_list';

    /** A plan is one goal. Past five situations it is a course, and below one it is nothing. */
    private const MIN_SCENES = 1;
    private const MAX_SCENES = 5;

    /**
     * Abilities across the whole plan. Three is the floor because «спросить дорогу» is honestly
     * three; twelve is the ceiling because the scheduler will not teach more than fourteen days of
     * material and a plan of twenty abilities is one that arrives at its event half-taught.
     */
    private const MIN_SKILLS = 3;
    private const MAX_SKILLS = 12;

    /**
     * What one ability may cost, in cards.
     *
     * Both ends are load-bearing. Under three it is not an ability, it is a fragment of its
     * neighbour; over eight it is two abilities the model declined to split, and the scheduler
     * would spread it across a day boundary as if it were atomic.
     */
    private const MIN_EST_TERMS = 3;
    private const MAX_EST_TERMS = 8;

    /** A role the learner cannot hear is not a role; four lines is already a scene of its own. */
    private const MIN_OPENING_LINES = 2;
    private const MAX_OPENING_LINES = 4;

    /**
     * The infinitive ending of a verb the learner performs — «объяснить», «спросить», «повторить».
     *
     * The whole of the «one ability per skill» check, and it is deliberately this crude. See
     * {@see checkOutcomeActions()}.
     */
    private const INFINITIVE = '/(ть|ться|чь|чься)$/u';

    /**
     * The one «и» that belongs, as the prompt itself puts it: a comprehension ability has to say
     * how the understanding is made audible, and «…и повторить своими словами» is that.
     */
    private const COMPREHENSION_PREFIX = 'понять';

    public function __construct(private readonly SupportLanguageText $supportText = new SupportLanguageText()) {}

    /**
     * @param  array<mixed>  $answer  the decoded JSON, exactly as the model returned it
     * @param  string  $supportLang  the learner's own language — what the skeleton must be readable in
     * @return list<PlanViolation>  empty = usable
     */
    public function validate(array $answer, string $supportLang = 'ru'): array
    {
        $violations = [];
        $goalTerms = $this->strings($answer['goal_terms'] ?? null);

        $scenes = is_array($answer['scenes'] ?? null) ? $answer['scenes'] : [];
        if ($scenes === []) {
            $violations[] = new PlanViolation(self::NO_SCENES, 'в каркасе нет ни одной сцены');
        } elseif (count($scenes) > self::MAX_SCENES) {
            // No floor to check beside the emptiness above: one scene is a legitimate plan
            // («спросить дорогу»), and MIN_SCENES states that in the same place as the ceiling.
            $violations[] = new PlanViolation(
                self::SCENE_COUNT,
                'сцен ' . count($scenes) . ', а должно быть ' . self::MIN_SCENES . '–' . self::MAX_SCENES,
            );
        }

        $skillTotal = 0;

        foreach ($scenes as $position => $scene) {
            $label = 'сцена ' . ((int) $position + 1);

            if (! is_array($scene)) {
                $violations[] = new PlanViolation(self::NOT_A_LIST, 'сцена каркаса — не объект', $label);

                continue;
            }

            $violations = [...$violations, ...$this->checkRole($scene['role'] ?? null, $label)];

            $skills = is_array($scene['skills'] ?? null) ? $scene['skills'] : [];
            $skillTotal += count($skills);
            foreach ($skills as $i => $skill) {
                $violations = [
                    ...$violations,
                    ...$this->checkSkill($skill, $label . ', умение ' . ((int) $i + 1), $supportLang, $goalTerms),
                ];
            }

            foreach (['title' => $scene['title'] ?? ''] as $field => $value) {
                $violations = [
                    ...$violations,
                    ...$this->checkSupportLanguage($value, $field, $label, $supportLang, $goalTerms),
                ];
            }
        }

        if ($scenes !== [] && ($skillTotal < self::MIN_SKILLS || $skillTotal > self::MAX_SKILLS)) {
            $violations[] = new PlanViolation(
                self::SKILL_COUNT,
                'умений в плане ' . $skillTotal . ', а должно быть ' . self::MIN_SKILLS . '–' . self::MAX_SKILLS,
            );
        }

        foreach (['title', 'goal_restated'] as $field) {
            $violations = [
                ...$violations,
                ...$this->checkSupportLanguage($answer[$field] ?? '', $field, null, $supportLang, $goalTerms),
            ];
        }

        foreach (['entities', 'constraints', 'goal_terms'] as $field) {
            $value = $answer[$field] ?? null;
            // Absent is fine — an empty list is a legitimate answer for a goal with no entities.
            // A STRING where a list belongs is not, and it is the failure that would otherwise
            // reach the day prompt as the literal characters of a JSON array.
            if ($value !== null && ! is_array($value)) {
                $violations[] = new PlanViolation(self::NOT_A_LIST, "`{$field}` — не массив");
            }
        }

        return $violations;
    }

    /**
     * `role` is an object or `null`, and an object has 2–4 utterances in it.
     *
     * Null is a legitimate answer — a scene of reading forms alone has nobody to talk to, and the
     * prompt says inventing «сотрудник, который просто рядом» is worse than admitting it. What is
     * NOT legitimate is a role with one opening line: P2 quotes these verbatim as the lines the
     * learner must recognise, so a role with nothing to say produces a day with no conversation to
     * recognise.
     *
     * @return list<PlanViolation>
     */
    private function checkRole(mixed $role, string $label): array
    {
        if ($role === null) {
            return [];
        }
        if (! is_array($role)) {
            return [new PlanViolation(self::ROLE_SHAPE, '`role` — не объект и не null', $label)];
        }

        $lines = is_array($role['opening_lines'] ?? null) ? $role['opening_lines'] : [];
        if (count($lines) < self::MIN_OPENING_LINES || count($lines) > self::MAX_OPENING_LINES) {
            return [new PlanViolation(
                self::OPENING_LINES,
                'реплик собеседника ' . count($lines) . ', а должно быть '
                . self::MIN_OPENING_LINES . '–' . self::MAX_OPENING_LINES,
                $label,
            )];
        }

        return [];
    }

    /**
     * @param  list<string>  $goalTerms
     * @return list<PlanViolation>
     */
    private function checkSkill(mixed $skill, string $label, string $supportLang, array $goalTerms): array
    {
        if (! is_array($skill)) {
            return [new PlanViolation(self::NOT_A_LIST, 'умение — не объект', $label)];
        }

        $violations = [];

        $outcome = $this->text($skill['outcome'] ?? '');
        if ($outcome === '') {
            $violations[] = new PlanViolation(self::SKILL_WITHOUT_OUTCOME, 'умение ничего не обещает', $label);
        }

        $checkpoint = $this->text($skill['checkpoint'] ?? '');
        if ($checkpoint === '') {
            // A promise the conversation never checks is a lie. One per ability, always — the
            // «умение без чек-пойнта» that v0.1 could only catch by comparing two list lengths.
            $violations[] = new PlanViolation(
                self::CHECKPOINT_MISSING,
                'у умения нет чек-пойнта — обещание, которое нечем проверить',
                $label,
            );
        } elseif ($outcome !== '' && $this->sameWording($checkpoint, $outcome)) {
            $violations[] = new PlanViolation(
                self::CHECKPOINT_ECHOES_OUTCOME,
                'чек-пойнт — копия обещания, а должен говорить, что СЛЫШНО',
                $label,
            );
        }

        $est = $skill['est_terms'] ?? null;
        $estValue = is_int($est) ? $est : (is_string($est) && ctype_digit($est) ? (int) $est : null);
        if ($estValue === null || $estValue < self::MIN_EST_TERMS || $estValue > self::MAX_EST_TERMS) {
            $violations[] = new PlanViolation(
                self::EST_TERMS,
                '`est_terms` = ' . (is_scalar($est) ? (string) $est : 'нет')
                . ', а должно быть целое ' . self::MIN_EST_TERMS . '–' . self::MAX_EST_TERMS,
                $label,
            );
        }

        $violations = [...$violations, ...$this->checkOutcomeActions($outcome, $label)];

        foreach (['outcome' => $outcome, 'checkpoint' => $checkpoint] as $field => $value) {
            $violations = [
                ...$violations,
                ...$this->checkSupportLanguage($value, $field, $label, $supportLang, $goalTerms),
            ];
        }

        foreach ($this->strings($skill['topics'] ?? null) as $topic) {
            $violations = [
                ...$violations,
                ...$this->checkSupportLanguage($topic, 'topics', $label, $supportLang, $goalTerms),
            ];
        }

        return $violations;
    }

    /**
     * ONE ABILITY PER SKILL — «и» joining two things the learner DOES.
     *
     * The prompt's rule, in the narrowest mechanical form there is: an «и» after which the text
     * starts with an INFINITIVE, when something before that «и» was an infinitive too. That is the
     * exact shape of two abilities badly packed:
     *
     *     «объяснить, что болит, и попросить направление»   ✘ two actions
     *     «сказать, что записан, и назвать время»           ✘ two actions
     *     «сказать, где именно болит и как давно»           ✔ one action, two questions
     *     «назвать время и место приёма»                    ✔ one action, two objects
     *
     * The one exemption is the prompt's own: an ability that begins «понять…» must end «…и
     * повторить своими словами», because understanding cannot be observed and saying it back is
     * the only evidence there is. That «и» is the rule being obeyed, not broken.
     *
     * Written this crudely on purpose. The outline gets no second attempt, so a gate that fires on
     * a correct answer costs the learner the whole plan — and a cleverer parser is a gate that
     * fires more often, not less.
     *
     * @return list<PlanViolation>
     */
    private function checkOutcomeActions(string $outcome, string $label): array
    {
        if ($outcome === '' || mb_stripos($outcome, self::COMPREHENSION_PREFIX) === 0) {
            return [];
        }

        $parts = preg_split('/\s+и\s+/u', $outcome);
        if (! is_array($parts) || count($parts) < 2) {
            return [];
        }

        $sawVerb = false;
        foreach ($parts as $i => $part) {
            if ($i > 0 && $sawVerb && $this->startsWithVerb((string) $part)) {
                return [new PlanViolation(
                    self::OUTCOME_TWO_ACTIONS,
                    'в обещании «и» соединяет два разных действия — это два умения, упакованных в одно',
                    $label,
                )];
            }
            $sawVerb = $sawVerb || $this->containsVerb((string) $part);
        }

        return [];
    }

    private function containsVerb(string $part): bool
    {
        foreach ($this->words($part) as $word) {
            if (preg_match(self::INFINITIVE, $word) === 1) {
                return true;
            }
        }

        return false;
    }

    private function startsWithVerb(string $part): bool
    {
        $words = $this->words($part);

        return $words !== [] && preg_match(self::INFINITIVE, $words[0]) === 1;
    }

    /** @return list<string> */
    private function words(string $part): array
    {
        $words = preg_split('/[^\p{L}]+/u', trim($part), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? $words : [];
    }

    /**
     * NOT ONE WORD OF THE LANGUAGE BEING LEARNED, anywhere the learner reads before committing.
     *
     * The skeleton is the screen a person with zero {{target_lang}} reads to decide whether to pay
     * with two weeks of their evenings. A `topic` that says «past simple» or an outcome with an
     * English phrase in brackets is not a small stylistic miss — it is the screen failing at its
     * one job. `role.opening_lines` are the deliberate exception and are not checked here: they
     * are utterances, and a role the learner cannot hear is not a role.
     *
     * @param  list<string>  $goalTerms
     * @return list<PlanViolation>
     */
    private function checkSupportLanguage(
        mixed $value,
        string $field,
        ?string $label,
        string $supportLang,
        array $goalTerms,
    ): array {
        $text = $this->text($value);
        if ($text === '' || $this->supportText->isSupportLanguage($supportLang, $text, $goalTerms)) {
            return [];
        }

        return [new PlanViolation(
            self::TARGET_LANGUAGE,
            "`{$field}` написан не на языке юзера: «{$text}»",
            $label,
        )];
    }

    /**
     * Is the checkpoint just the promise said again?
     *
     * Compared on letters and digits only, case-folded. The failure this catches is a model
     * re-emitting «сказать, где именно болит» as its own checkpoint with a comma moved, and
     * punctuation is exactly what moves.
     */
    private function sameWording(string $checkpoint, string $outcome): bool
    {
        $normalize = static function (string $value): string {
            $stripped = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value));

            return $stripped ?? '';
        };

        $a = $normalize($checkpoint);
        $b = $normalize($outcome);

        return $a !== '' && $a === $b;
    }

    /** @return list<string> */
    private function strings(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            $text = $this->text($item);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    private function text(mixed $raw): string
    {
        return is_scalar($raw) ? trim((string) $raw) : '';
    }
}
