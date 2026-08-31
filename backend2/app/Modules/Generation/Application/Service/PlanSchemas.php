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
            'required' => ['name', 'opening_lines', 'if_silent'],
            'properties' => [
                'name' => self::string(),
                'opening_lines' => self::arrayOf($line),
                'if_silent' => self::string(),
            ],
        ];

        // One checkpoint per skill, and the schema is where that stops being a hope. Until v0.2 the
        // checkpoints were a separate list on the role, kept parallel to the abilities by nothing
        // but instruction, and the validator's job was to notice when the two lengths came apart.
        $skill = self::object([
            'outcome' => self::string(),
            'checkpoint' => self::string(),
            'est_terms' => self::integer(),
            'topics' => self::arrayOf(self::string()),
        ]);

        $scene = self::object([
            'title' => self::string(),
            'role' => $role,
            'skills' => self::arrayOf($skill),
        ]);

        $entity = self::object([
            'name' => self::string(),
            'gender' => self::string(),
            'number' => self::string(),
            'note' => self::string(),
        ]);

        // No `days`, no `final_day`, no `estimated_terms`, no `single_day`: every one of them was a
        // number about the calendar, and the calendar is the server's. Their ABSENCE is enforced
        // here (`additionalProperties: false`) and not only asked for in the prose, because a model
        // handed a familiar shape tends to fill it back in.
        return self::object([
            'title' => self::string(),
            'goal_restated' => self::string(),
            'entities' => self::arrayOf($entity),
            'constraints' => self::arrayOf(self::string()),
            'goal_terms' => self::arrayOf(self::string()),
            'scenes' => self::arrayOf($scene),
        ]);
    }

    /** @return array<string, mixed> */
    public static function day(): array
    {
        $common = [
            'text' => self::string(),
            'type' => ['type' => 'string', 'enum' => ['word', 'phrase', 'idiom', 'phrasal_verb']],
            'is_line' => ['type' => 'boolean'],
            'translation' => self::string(),
            'transliteration' => self::string(),
            'description' => self::string(),
            'example' => self::string(),
            'example_translation' => self::string(),
            'image_api_prompt' => self::string(),
            'covers_checkpoint' => ['type' => ['integer', 'null']],
        ];

        // A LINE carries two fields a substitution does not, and the schema is where «only on
        // phrases» stops being a sentence in the prose. `frame` is the line with its slot as `___`
        // (or '' for a formula); `speaker` says whose turn it is.
        $line = self::object([
            ...$common,
            'frame' => self::string(),
            'speaker' => ['type' => 'string', 'enum' => ['learner', 'role']],
        ]);

        $card = self::object($common);

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
            'phrases' => self::arrayOf($line),
            'words' => self::arrayOf($card),
            'chunks' => self::arrayOf($card),
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
