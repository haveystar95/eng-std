<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Prompt;

/**
 * The JSON schemas the two calls are made with — the prompts' OUTPUT SCHEMA sections, spelled as
 * strict JSON Schema (every property required, no extras) so the vendor refuses an off-shape
 * answer before we see it. The one place the schemas exist; the parsers re-check shape on read.
 */
final class PlanSchemas
{
    /** @return array<string, mixed> */
    public static function plan(): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'status' => ['type' => 'string', 'enum' => ['ok', 'unclear']],
            'unclear_reason' => ['type' => ['string', 'null']],
            'plan' => self::nullableObject([
                'title_native' => $string,
                'title_target' => $string,
                'event_native' => $string,
                'until_phrase_native' => $string,
                'overdue_native' => $string,
                'cover_image_prompt' => $string,
                'learner_role_target' => $string,
                'learner_role_native' => $string,
            ]),
            'scenes' => [
                'type' => 'array',
                'items' => self::object([
                    'order' => ['type' => 'integer'],
                    'kind' => ['type' => 'string', 'enum' => ['situation', 'variant']],
                    'priority' => ['type' => 'integer'],
                    'title_native' => $string,
                    'title_target' => $string,
                    'teaches_native' => $string,
                    'goals_native' => ['type' => 'array', 'items' => $string],
                    'learner_role_target' => $string,
                    'learner_role_native' => $string,
                    'partner_role_target' => $string,
                    'partner_role_native' => $string,
                    'topic_description' => $string,
                    'image_prompt' => $string,
                ]),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public static function lesson(): array
    {
        $string = ['type' => 'string'];
        $ids = ['type' => 'array', 'items' => $string];

        $partnerMessage = self::object([
            'speaker' => ['type' => 'string', 'enum' => ['A']],
            'role_target' => $string,
            'role_native' => $string,
            'text_target' => $string,
            'text_native' => $string,
            'phrase_ids' => $ids,
            'vocabulary_ids' => $ids,
        ]);
        $learnerMessage = self::object([
            'speaker' => ['type' => 'string', 'enum' => ['B']],
            'role_target' => $string,
            'role_native' => $string,
            'text_target' => $string,
            'text_native' => $string,
            'pronunciation_native' => $string,
            'speaking_key' => $string,
            'simplified_variants' => ['type' => 'array', 'items' => $string],
            'phrase_ids' => $ids,
            'vocabulary_ids' => $ids,
        ]);

        return self::object([
            'topic' => self::object([
                'title_target' => $string,
                'title_native' => $string,
                'description_target' => $string,
                'description_native' => $string,
            ]),
            'learner_role' => self::object([
                'role_target' => $string,
                'role_native' => $string,
            ]),
            'dialogue' => [
                'type' => 'array',
                'items' => self::object([
                    'step' => ['type' => 'integer'],
                    'initiator' => ['type' => 'string', 'enum' => ['A', 'B']],
                    'messages' => ['type' => 'array', 'items' => ['anyOf' => [$partnerMessage, $learnerMessage]]],
                    'question' => self::object([
                        'text_target' => $string,
                        'text_native' => $string,
                        'options' => ['type' => 'array', 'items' => self::object(['text_target' => $string, 'text_native' => $string])],
                        'correct_option_index' => ['type' => 'integer'],
                        'explanation_native' => $string,
                    ]),
                ]),
            ],
            'phrases' => [
                'type' => 'array',
                'items' => self::object([
                    'id' => $string,
                    'text_target' => $string,
                    'text_native' => $string,
                    'pronunciation_native' => $string,
                ]),
            ],
            'vocabulary' => [
                'type' => 'array',
                'items' => self::object([
                    'id' => $string,
                    'term_target' => $string,
                    'translation_native' => $string,
                    'pronunciation_native' => $string,
                    'definition_target' => $string,
                    'kind' => ['type' => 'string', 'enum' => ['word', 'chunk']],
                    'image_prompt' => ['type' => ['string', 'null']],
                ]),
            ],
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
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    private static function nullableObject(array $properties): array
    {
        return ['type' => ['object', 'null']] + array_slice(self::object($properties), 1);
    }
}
