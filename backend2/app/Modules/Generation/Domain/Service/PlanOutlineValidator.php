<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\PlanViolation;

/**
 * P1's answer, judged before it is stored.
 *
 * Narrow on purpose. This checks the things that make an outline USABLE — is there anything to
 * schedule, does every day that has a conversation have one checkpoint per promise, are the three
 * binding lists lists at all. It does not judge whether the plan is a good plan; that is what the
 * skeleton screen is for, and the learner reads it before committing.
 *
 * The one thing it deliberately does NOT check is `final_day.checkpoints`, because there is no such
 * field: v0 asked the model for it and the answer drifted from the days it was supposed to copy,
 * promising an exam harder than the plan. The server assembles that list from the days
 * ({@see \App\Modules\Learning\Domain\ValueObject\PlanOutline::finalCheckpoints()}), so there is
 * nothing here to validate — which is what a rule moved from a prompt into code looks like.
 *
 * Works on the DECODED ARRAY rather than on a typed outline, and that is a boundary rule, not
 * laziness: the typed outline is Learning's ({@see
 * \App\Modules\Learning\Domain\ValueObject\PlanOutline}) and this module cannot see another
 * module's Domain. Generation judges the raw answer, Learning types the judged one.
 */
final class PlanOutlineValidator
{
    public const NO_DAYS = 'outline.no_days';
    public const DAY_WITHOUT_OUTCOME = 'outline.day_without_outcome';
    public const CHECKPOINT_COUNT = 'outline.checkpoint_count';
    public const CHECKPOINT_MISMATCH = 'outline.checkpoint_mismatch';
    public const CHECKPOINT_ECHOES_OUTCOME = 'outline.checkpoint_echoes_outcome';
    public const BUDGET_MISSING = 'outline.budget_missing';
    public const NOT_A_LIST = 'outline.not_a_list';
    public const NO_FINAL_DAY = 'outline.no_final_day';

    /**
     * @param  array<mixed>  $answer  the decoded JSON, exactly as the model returned it
     * @return list<PlanViolation>  empty = usable
     */
    public function validate(array $answer): array
    {
        $violations = [];

        $days = is_array($answer['days'] ?? null) ? $answer['days'] : [];
        if ($days === []) {
            $violations[] = new PlanViolation(self::NO_DAYS, 'в каркасе нет ни одного дня знакомства');
        }

        foreach ($days as $position => $day) {
            $label = 'день ' . (is_array($day) && isset($day['index']) && is_scalar($day['index'])
                ? (string) $day['index']
                : (string) ((int) $position + 1));

            if (! is_array($day)) {
                $violations[] = new PlanViolation(self::NOT_A_LIST, 'день каркаса — не объект', $label);

                continue;
            }

            $outcome = $this->strings($day['outcome'] ?? null);
            if ($outcome === []) {
                $violations[] = new PlanViolation(self::DAY_WITHOUT_OUTCOME, 'день ничего не обещает', $label);
            }

            $budget = $day['term_budget'] ?? null;
            if (! is_int($budget) && ! (is_string($budget) && ctype_digit($budget))) {
                $violations[] = new PlanViolation(self::BUDGET_MISSING, 'у дня нет бюджета терминов', $label);
            }

            // `role: null` is a legitimate answer — a day of reading forms alone has nobody to
            // talk to, and the prompt says inventing an interlocutor is worse than admitting it.
            // A day with no role simply has no checkpoints of its own; its outcome is checked on
            // the final day.
            $role = $day['role'] ?? null;
            if ($role === null) {
                continue;
            }
            if (! is_array($role)) {
                $violations[] = new PlanViolation(self::NOT_A_LIST, '`role` — не объект и не null', $label);

                continue;
            }

            $checkpoints = $this->strings($role['checkpoints'] ?? null);
            if (count($checkpoints) < 2 || count($checkpoints) > 3) {
                $violations[] = new PlanViolation(
                    self::CHECKPOINT_COUNT,
                    'чек-пойнтов ' . count($checkpoints) . ', а должно быть 2–3',
                    $label,
                );
            }
            if ($outcome !== [] && count($checkpoints) !== count($outcome)) {
                // A promise the conversation never checks is a lie; a checkpoint the day never
                // promised is a trap. The two lists are the same list, seen from two sides.
                $violations[] = new PlanViolation(
                    self::CHECKPOINT_MISMATCH,
                    'умений ' . count($outcome) . ', чек-пойнтов ' . count($checkpoints),
                    $label,
                );
            }

            foreach ($checkpoints as $i => $checkpoint) {
                $promise = $outcome[$i] ?? null;
                if ($promise !== null && $this->sameWording($checkpoint, $promise)) {
                    $violations[] = new PlanViolation(
                        self::CHECKPOINT_ECHOES_OUTCOME,
                        "чек-пойнт {$i} — копия обещания, а должен говорить, что СЛЫШНО",
                        $label,
                    );
                }
            }
        }

        $final = $answer['final_day'] ?? null;
        if (! is_array($final) || $this->text($final['title'] ?? '') === '') {
            $violations[] = new PlanViolation(self::NO_FINAL_DAY, 'нет финального дня или у него нет названия');
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
