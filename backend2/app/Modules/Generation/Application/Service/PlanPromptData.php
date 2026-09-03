<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

/**
 * How a plan brief is written into a plan prompt — in one place, because two prompts read it.
 *
 * P2 and P2R are handed the same facts about the plan: the entities with their gender and number,
 * the goal terms that stay verbatim in both languages, the interlocutor's own lines. P2R was going
 * to grow a second copy of these three formatters, and a second copy is how «- name — gender,
 * number» in one prompt becomes «name (gender, number)» in the other, at which point one of the
 * two answers is being judged against a brief the other never saw.
 *
 * Nothing here decides anything. Every method is a way of writing a list down.
 */
final class PlanPromptData
{
    /** @param list<array{name: string, gender: string, number: string, note: string}> $entities */
    public static function entities(array $entities): string
    {
        if ($entities === []) {
            return '(пусто)';
        }

        return implode("\n", array_map(
            static fn (array $e): string => '- ' . $e['name'] . ' — ' . $e['gender'] . ', ' . $e['number']
                . ($e['note'] !== '' ? ', ' . $e['note'] : ''),
            $entities,
        ));
    }

    /** @param list<string> $items */
    public static function bullets(array $items): string
    {
        return $items === []
            ? '(пусто)'
            : implode("\n", array_map(static fn (string $i): string => '- ' . $i, $items));
    }

    /**
     * THE LISTENING CHECK, as P1 reads it — «{{diagnostics}}».
     *
     * Prose and not JSON, alone among the formatters here, because this placeholder sits inside a
     * sentence of the prompt rather than beside a label: P1 says «Diagnostics (may be empty): …»
     * and then explains what to do with what it finds. A JSON blob dropped into a paragraph is
     * read as a quotation of something; three lines with a verdict under them are read as facts.
     *
     * The verdict is written out in the same words the learner saw on кадр V4·03в/03г, because the
     * plan the model builds is the promise that screen made. What is NOT here is a score, a
     * percentage or a level — the step has none, deliberately, and inventing one for the model
     * would be inventing one for the plan.
     *
     * @param  array{lines: list<array{text: string, translation: string, place: string, understood: bool}>}|null  $diagnostics
     */
    public static function diagnostics(?array $diagnostics, string $balance): string
    {
        $lines = $diagnostics['lines'] ?? [];
        if ($lines === []) {
            return '(empty — the user skipped the listening step)';
        }

        $rows = [];
        foreach ($lines as $line) {
            $where = trim($line['place']) !== '' ? ' [' . trim($line['place']) . ']' : '';
            $rows[] = '- "' . trim($line['text']) . '"' . $where . ' — '
                . ($line['understood'] ? 'understood' : 'not understood');
        }

        $verdict = $balance === 'speaking'
            ? 'Verdict: the user understands spoken lines of this situation confidently — put the '
                . 'weight on SPEAKING.'
            : 'Verdict: spoken lines of this situation are hard for the user — put the weight on '
                . 'LISTENING COMPREHENSION.';

        return "Listening check (the user heard these lines and said how each felt):\n"
            . implode("\n", $rows) . "\n" . $verdict;
    }

    /** @param array<string, mixed>|list<mixed> $value */
    public static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
