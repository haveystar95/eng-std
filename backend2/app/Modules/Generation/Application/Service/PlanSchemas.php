<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

/**
 * The JSON Schemas the two plan prompts' answers are forced into by the vendor.
 *
 * The prompt STATES the shape and the schema ENFORCES it, and both are needed: a prompt is a
 * request, a schema is a guarantee. Everything here mirrors the «Output» section of the
 * corresponding prompt file, and a change to one without the other is the kind of drift the
 * registry rule exists to prevent.
 *
 * OpenAI's `strict` mode has two rules that shape the code below and neither is negotiable: every
 * property must be listed in `required`, and `additionalProperties` must be false everywhere. So a
 * genuinely optional field is expressed as a nullable type, not as an absent key — which is why
 * `role` and `covers_checkpoint` are `['object', 'null']` and friends.
 */
final class PlanSchemas
{
    /** @return array<string, mixed> */
    public static function outline(): array
    {
        $line = self::object([
            'text' => self::string(),
            'translation' => self::string(),
        ]);

        $role = [
            'type' => ['object', 'null'],
            'additionalProperties' => false,
            'required' => ['name', 'opening_lines', 'checkpoints', 'if_silent'],
            'properties' => [
                'name' => self::string(),
                'opening_lines' => self::arrayOf($line),
                'checkpoints' => self::arrayOf(self::string()),
                'if_silent' => self::string(),
            ],
        ];

        $day = self::object([
            'index' => self::integer(),
            'title' => self::string(),
            'term_budget' => self::integer(),
            'outcome' => self::arrayOf(self::string()),
            'role' => $role,
            'topics' => self::arrayOf(self::string()),
        ]);

        $entity = self::object([
            'name' => self::string(),
            'gender' => self::string(),
            'number' => self::string(),
            'note' => self::string(),
        ]);

        // `final_day` carries no `checkpoints` — the server assembles that list from the days.
        // The absence is enforced here as well as asked for in the prompt, because v0 wrote one
        // anyway and it drifted from the days it was meant to copy.
        $finalDay = self::object([
            'index' => self::integer(),
            'same_day' => ['type' => 'boolean'],
            'title' => self::string(),
        ]);

        return self::object([
            'title' => self::string(),
            'goal_restated' => self::string(),
            'entities' => self::arrayOf($entity),
            'constraints' => self::arrayOf(self::string()),
            'goal_terms' => self::arrayOf(self::string()),
            'single_day' => ['type' => 'boolean'],
            'days' => self::arrayOf($day),
            'final_day' => $finalDay,
            'estimated_terms' => self::integer(),
        ]);
    }

    /** @return array<string, mixed> */
    public static function day(): array
    {
        $card = self::object([
            'text' => self::string(),
            'type' => ['type' => 'string', 'enum' => ['word', 'phrase', 'idiom', 'phrasal_verb']],
            'is_line' => ['type' => 'boolean'],
            'translation' => self::string(),
            'transliteration' => self::string(),
            'description' => self::string(),
            'example' => self::string(),
            'example_translation' => self::string(),
            'covers_checkpoint' => ['type' => ['integer', 'null']],
        ]);

        $knownExample = self::object([
            'example' => self::string(),
            'example_translation' => self::string(),
        ]);

        $known = self::object([
            'text' => self::string(),
            'examples' => self::arrayOf($knownExample),
        ]);

        return self::object([
            'day_index' => self::integer(),
            'day_title' => self::string(),
            'phrases' => self::arrayOf($card),
            'words' => self::arrayOf($card),
            'known' => self::arrayOf($known),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($properties),
            'properties' => $properties,
        ];
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    private static function arrayOf(array $items): array
    {
        return ['type' => 'array', 'items' => $items];
    }

    /** @return array<string, mixed> */
    private static function string(): array
    {
        return ['type' => 'string'];
    }

    /** @return array<string, mixed> */
    private static function integer(): array
    {
        return ['type' => 'integer'];
    }
}
