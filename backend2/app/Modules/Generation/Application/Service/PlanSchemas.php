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

        // A LINE HAS NO `text`, AND THAT IS THE WHOLE OF v0.3 IN ONE LINE OF SCHEMA.
        //
        // The model writes `frame` — the line with its slot as `___`, or no slot at all for a
        // formula — and `filler`, the day's own word that goes in the hole; the server pastes them
        // together ({@see PlanDayComposer::items()}). Four live answers in a row came back with
        // `___` still standing in `text`, and prose could not stop it: a field the model cannot
        // write is a defect it cannot commit. `speaker` says whose turn it is.
        $line = self::object([
            ...$common,
            'frame' => self::string(),
            'filler' => self::string(),
            'speaker' => ['type' => 'string', 'enum' => ['learner', 'role']],
        ]);

        // A substitution DOES write its own text — it is a word, not an assembled sentence.
        $card = self::object(['text' => self::string(), ...$common]);

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
     * P2R — the broken cards, fixed, each back at the address it came from.
     *
     * ## One card shape and not a union
     *
     * A line and a substitution are different objects: a line has `frame`/`filler`/`speaker` and no
     * `text`, a word has `text` and none of the three. Expressing that as `anyOf` under `strict` is
     * possible and not worth it — a mis-chosen branch comes back as a schema error rather than as a
     * card, and the repair call has no second attempt to spend on one. So `card` carries every
     * field of both, with the ones that do not apply nullable. The ARRAY the entry names is what
     * decides which kind it is, exactly as it does in P2, and
     * {@see \App\Modules\Generation\Application\Service\PlanDayRepairer} reads the fields that kind
     * has.
     *
     * `speaker` is a plain nullable string rather than an enum-with-null: the gate already refuses
     * a line without a speaker ({@see PlanDayValidator::KIND_MISMATCH}), and a schema keyword one
     * provider interprets differently is a 400 on a paid path.
     *
     * ## No `minItems`/`maxItems`, and that is not an oversight
     *
     * «Exactly as many entries as cards under BROKEN» is stated in the prompt and CHECKED after the
     * answer ({@see PlanDayRepairer}), not asked of the schema: OpenAI's `strict` mode does not
     * apply array-length keywords — it refuses the whole schema for carrying them. See DECISIONS
     * п. 201; the same is true of the counters P2 would like on its three arrays.
     *
     * @return array<string, mixed>
     */
    public static function repair(): array
    {
        $card = self::object([
            'text' => self::nullableString(),
            'type' => ['type' => 'string', 'enum' => ['word', 'phrase', 'idiom', 'phrasal_verb']],
            'is_line' => ['type' => 'boolean'],
            'translation' => self::string(),
            'transliteration' => self::string(),
            'description' => self::string(),
            'example' => self::string(),
            'example_translation' => self::string(),
            'image_api_prompt' => self::string(),
            'covers_checkpoint' => ['type' => ['integer', 'null']],
            'frame' => self::nullableString(),
            'filler' => self::nullableString(),
            'speaker' => self::nullableString(),
        ]);

        $entry = self::object([
            'array' => ['type' => 'string', 'enum' => ['phrases', 'words', 'chunks']],
            'index' => self::integer(),
            'card' => $card,
        ]);

        return self::object(['cards' => self::arrayOf($entry)]);
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
    private static function nullableString(): array
    {
        return ['type' => ['string', 'null']];
    }

    /** @return array<string, mixed> */
    private static function integer(): array
    {
        return ['type' => 'integer'];
    }
}
