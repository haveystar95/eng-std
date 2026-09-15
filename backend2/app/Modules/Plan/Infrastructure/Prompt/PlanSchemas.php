<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Prompt;

/**
 * The JSON schemas the plan's calls are made with — the prompts' OUTPUT SCHEMA sections, spelled as
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

    /**
     * `lesson_day.v4.5`'s STRICT OUTPUT SCHEMA, keys in its order (the same structure as v4.4 — v4.5 changed
     * rules, not fields). Enums hold what the vendor can hold: the kinds, the speakers, the gender, and every
     * reference — a frame id is one of `p1…pN` (N = DIALOGUE_COUNT, the most frames a day can have) or null, a
     * vocabulary id one of `v1…vM`, a `used_in` entry a frame id or a partner line `A1…AN`. `in_dialogue` is a
     * boolean; «exactly the fillers the dialogue says» is the validator's, the schema cannot say it. No list has a
     * length (п. 202: a forced length is padded with invented items) — the counts are the validator's too.
     *
     * @return array<string, mixed>
     */
    public static function lesson(int $dialogueCount, int $vocabularyCount): array
    {
        $string = ['type' => 'string'];
        $frameIds = self::ids('p', $dialogueCount);

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
            'role_gender' => ['type' => 'string', 'enum' => ['female', 'male']],
            'dialogue' => [
                'type' => 'array',
                'items' => self::exchange($frameIds, ['type' => 'integer']),
            ],
            'phrases' => ['type' => 'array', 'items' => self::frame($frameIds)],
            'listening' => self::object([
                'questions' => ['type' => 'array', 'items' => self::listeningQuestion()],
            ]),
            'vocabulary' => [
                'type' => 'array',
                'items' => self::object([
                    'id' => ['type' => 'string', 'enum' => self::ids('v', $vocabularyCount)],
                    'term_target' => $string,
                    'translation_native' => $string,
                    'pronunciation_native' => $string,
                    'definition_target' => $string,
                    'kind' => ['type' => 'string', 'enum' => ['word', 'chunk']],
                    'image_prompt' => ['type' => ['string', 'null']],
                    'used_in' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => [...$frameIds, ...self::ids('A', $dialogueCount)]]],
                ]),
            ],
        ]);
    }

    /**
     * THE REPAIR OF ONE CARD (P2R, `lesson_card_repair.v1.1`): `{card}` in the shape that card has in the
     * lesson. A frame keeps its id (the enum has only it); a learner line may name any frame of the day; an
     * exchange keeps its step (the enum has only it) and comes with `frame_update` — the frame its learner line
     * stands on, whole. The prompt says «omit "frame_update"» when no filler needed marking; a strict schema
     * has no optional key, so «omitted» is `null` there.
     *
     * @param  'frame'|'exchange'|'line'|'check'|'listening'  $kind
     * @param  list<string>  $frameIds  the frames of the day
     * @return array<string, mixed>
     */
    public static function lessonCard(string $kind, string $address, array $frameIds): array
    {
        $frames = $frameIds === [] ? ['p1'] : $frameIds;
        if ($kind === 'exchange') {
            return self::object([
                'card' => self::exchange($frames, ['type' => 'integer', 'enum' => [(int) substr($address, 1)]]),
                'frame_update' => ['type' => ['object', 'null']] + array_slice(self::frame($frames), 1),
            ]);
        }
        $card = match ($kind) {
            'frame' => self::frame([$address]),
            'line' => self::learnerMessage($frames),
            'check' => self::check(),
            'listening' => self::listeningQuestion(),
        };

        return self::object(['card' => $card]);
    }

    /**
     * THE SEAM JUDGE (`lesson_seam_judge.v1.1`): a verdict per sentence sent — its id (the enum has only the ids
     * sent) and whether it reads. No length (п. 202): a sentence left without a verdict is simply not judged.
     *
     * @param  list<string>  $ids
     * @return array<string, mixed>
     */
    public static function seamJudge(array $ids): array
    {
        return self::object([
            'verdicts' => [
                'type' => 'array',
                'items' => self::object([
                    'id' => ['type' => 'string', 'enum' => $ids === [] ? ['p1.f1'] : $ids],
                    'reads' => ['type' => 'boolean'],
                ]),
            ],
        ]);
    }

    /**
     * One exchange of the dialogue: its step, kind, initiator, the two messages (A and B through `anyOf`) and its
     * check.
     *
     * @param  list<string>  $frameIds
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private static function exchange(array $frameIds, array $step): array
    {
        return self::object([
            'step' => $step,
            'kind' => ['type' => 'string', 'enum' => ['answer', 'ask', 'rescue']],
            'initiator' => ['type' => 'string', 'enum' => ['A', 'B']],
            'messages' => ['type' => 'array', 'items' => ['anyOf' => [self::partnerMessage(), self::learnerMessage($frameIds)]]],
            'check' => self::check(),
        ]);
    }

    /** @return array<string, mixed> */
    private static function partnerMessage(): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'speaker' => ['type' => 'string', 'enum' => ['A']],
            'role_target' => $string,
            'role_native' => $string,
            'text_target' => $string,
            'text_native' => $string,
        ]);
    }

    /**
     * @param  list<string>  $frameIds
     * @return array<string, mixed>
     */
    private static function learnerMessage(array $frameIds): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'speaker' => ['type' => 'string', 'enum' => ['B']],
            'role_target' => $string,
            'role_native' => $string,
            'phrase_id' => ['type' => ['string', 'null'], 'enum' => [...$frameIds, null]],
            'filler' => ['type' => ['string', 'null']],
            'text_target' => $string,
            'text_native' => $string,
            'pronunciation_native' => $string,
            'speaking_key' => $string,
            'simplified_variants' => ['type' => 'array', 'items' => $string],
        ]);
    }

    /** @return array<string, mixed> */
    private static function check(): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'text_target' => $string,
            'text_native' => $string,
            'options' => ['type' => 'array', 'items' => self::object(['text_target' => $string, 'text_native' => $string])],
            'correct_option_index' => ['type' => 'integer'],
            'explanation_native' => $string,
        ]);
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, mixed>
     */
    private static function frame(array $ids): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'id' => ['type' => 'string', 'enum' => $ids],
            'kind' => ['type' => 'string', 'enum' => ['answer', 'ask']],
            'frame_target' => $string,
            'frame_native' => $string,
            'pronunciation_native' => $string,
            'slot' => self::nullableObject([
                'hint_native' => $string,
                'fillers' => [
                    'type' => 'array',
                    'items' => self::object([
                        'target' => $string,
                        'native' => $string,
                        'pronunciation_native' => $string,
                        'in_dialogue' => ['type' => 'boolean'],
                    ]),
                ],
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private static function listeningQuestion(): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'text_native' => $string,
            'options_native' => ['type' => 'array', 'items' => $string],
            'correct_option_index' => ['type' => 'integer'],
            'explanation_native' => $string,
        ]);
    }

    /** @return list<string> `{$prefix}1` … `{$prefix}{$count}` (at least one, so an enum is never empty) */
    private static function ids(string $prefix, int $count): array
    {
        return array_map(static fn (int $n): string => $prefix.$n, range(1, max(1, $count)));
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
