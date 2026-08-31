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

    /** @param array<string, mixed>|list<mixed> $value */
    public static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
