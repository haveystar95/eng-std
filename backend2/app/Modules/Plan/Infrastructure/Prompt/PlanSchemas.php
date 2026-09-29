<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Prompt;

/**
 * The JSON schemas the plan's calls are made with — the prompts' OUTPUT SCHEMA sections, spelled as
 * strict JSON Schema (every property required, no extras) so the vendor refuses an off-shape
 * answer before we see it. The one place the schemas exist; the parsers re-check shape on read.
 *
 * A schema is the first thing the vendor reads, before the rules, and one that changed from call to call would keep the
 * rules out of its prompt cache (наряд GEN-3): no schema here names an id of THIS day — an id is one of every id a day
 * may have ({@see FRAMES}, {@see PARTNER_LINES}, {@see WORDS}), and the server holds the rest.
 */
final class PlanSchemas
{
    /** The most frames a skeleton may have: one per `must_say` item, eight at most (`plan-builder-v2.1`). */
    public const FRAMES = 8;

    /** The most partner lines: one per `must_understand` item, two for an item of two questions — five items at most. */
    public const PARTNER_LINES = 10;

    /** The most words of a day: the top of the widest VOCABULARY_COUNT (`plan.counts`). */
    public const WORDS = 12;

    /**
     * THE PLAN (`plan-builder-v2.1`, OUTPUT SCHEMA), keys in its order: a scene's SURVIVAL SET — `must_say` and
     * `must_understand`, lists of strings — right after its priority, the order the model writes in.
     *
     * @return array<string, mixed>
     */
    public static function plan(): array
    {
        $string = ['type' => 'string'];
        $strings = ['type' => 'array', 'items' => $string];

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
                    'must_say' => $strings,
                    'must_understand' => $strings,
                    'title_native' => $string,
                    'title_target' => $string,
                    'teaches_native' => $string,
                    'goals_native' => $strings,
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
     * ONE SCREEN LINE OF A PLAN, SHORTENED (`plan_line_repair.v1`, наряд GEN-4): `{line}`.
     *
     * @return array<string, mixed>
     */
    public static function planLine(): array
    {
        return self::object(['line' => ['type' => 'string']]);
    }

    /**
     * THE SKELETON (`lesson_skeleton.v1.1`, OUTPUT SCHEMA), keys in its order. No list has a length (п. 202: a forced length
     * is padded with invented items) — the counts are {@see \App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck}'s.
     *
     * @return array<string, mixed>
     */
    public static function skeleton(): array
    {
        return self::object([
            'topic' => self::topic(),
            'learner_role' => self::object(['role_target' => ['type' => 'string'], 'role_native' => ['type' => 'string']]),
            'role_gender' => ['type' => 'string', 'enum' => ['female', 'male']],
            'phrases' => ['type' => 'array', 'items' => self::frame()],
            'partner_lines' => ['type' => 'array', 'items' => self::partnerLine()],
            'vocabulary' => ['type' => 'array', 'items' => self::vocabularyItem()],
        ]);
    }

    /**
     * THE DIALOGUE (`lesson_dialogue.v1.1`, OUTPUT SCHEMA), keys in its order: every exchange with the item and the partner line
     * it carries (nullable), a learner message's frame and filler (nullable), a check; the listening. No length —
     * DIALOGUE_COUNT is {@see \App\Modules\Plan\Domain\Check\Dialogue\DialogueCheck}'s.
     *
     * @return array<string, mixed>
     */
    public static function dialogue(): array
    {
        return self::object([
            'dialogue' => ['type' => 'array', 'items' => self::exchange()],
            'listening' => self::object([
                'questions' => ['type' => 'array', 'items' => self::listeningQuestion()],
            ]),
        ]);
    }

    /**
     * THE REPAIR OF ONE CARD (`lesson_card_repair.v1.5`): `{card}` in the shape that card has in its stage — a frame (with its
     * `must_say`), a partner line or a word of the skeleton; a whole exchange, a check or a listening question of the
     * dialogue. The ids are every id a day may have, never this card's own: the server keeps a card's place and its job
     * ({@see \App\Modules\Plan\Domain\Lesson\LessonCard::replace()}).
     *
     * @param  'frame'|'term'|'partner_line'|'exchange'|'check'|'listening'  $kind
     * @return array<string, mixed>
     */
    public static function lessonCard(string $kind): array
    {
        return self::object(['card' => match ($kind) {
            'frame' => self::frame(),
            'term' => self::vocabularyItem(),
            'partner_line' => self::partnerLine(),
            'exchange' => self::exchange(),
            'check' => self::check(),
            'listening' => self::listeningQuestion(),
        }]);
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
     * THE SLOT JUDGE (`slot_judge.v2`, наряд SESSION-1a, разд. 4): the prompt's OUTPUT — whether the attempt passes,
     * the words that stood in the slot and, for a refusal, one sentence to the learner. The
     * judge re-checks the shape on read: a vendor that let an off-shape answer through gets the code's verdict.
     *
     * @return array<string, mixed>
     */
    public static function slotJudge(): array
    {
        return self::object([
            'accepted' => ['type' => 'boolean'],
            'slot_value' => ['type' => ['string', 'null']],
            'reason_native' => ['type' => ['string', 'null']],
        ]);
    }

    /**
     * ONE MOVE OF THE AGENT (`conversation_agent.v3.4`, наряд CONV-1; FIX-3 §7; FIX-4 §3; FIX-4b §3; FIX-4c §6; ACC-1 §6): its line in both
     * languages, what it judged about the move it answers, the target its line opens the door to — by the short id of its
     * scene's target (`T3`) — and whether the talk is over. Which constructions the learner said and when a scene is over are
     * not asked at all (v3.2 took `phrases_used` and `checkpoint_done` out): the first is the code's judge's, the second the
     * server's.
     *
     * `end` is an enum and not a boolean because the two ways a talk ends by itself are not the
     * same fact: «the scene is finished» and «the learner pushed a refused subject twice» read
     * differently in the summary and in the report. The third way — the money cap — is the
     * server's, never the model's.
     *
     * The target ids are an enum of what was actually sent — the targets of the scene the role is in: an id the scene does
     * not carry is not a door of this scene. The server checks the door all the same (a target said, a model that does not
     * keep a schema): the enum is the first fence, not the only one.
     *
     * @param  list<string>  $targetIds
     * @return array<string, mixed>
     */
    public static function conversationAgent(array $targetIds): array
    {
        return self::object([
            'reply_target' => ['type' => 'string'],
            'reply_native' => ['type' => 'string'],
            'understood' => ['type' => ['boolean', 'null']],
            'off_topic' => ['type' => 'boolean'],
            'opens' => $targetIds === [] ? ['type' => 'null'] : ['type' => ['string', 'null'], 'enum' => [...$targetIds, null]],
            'end' => ['type' => 'string', 'enum' => ['no', 'natural', 'declined']],
        ]);
    }

    /** @return array<string, mixed> */
    private static function topic(): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'title_target' => $string,
            'title_native' => $string,
            'description_target' => $string,
            'description_native' => $string,
        ]);
    }

    /**
     * One exchange of the dialogue: its step, kind, initiator, the item and the partner line it carries, the two messages
     * (A and B through `anyOf`) and its check.
     *
     * @return array<string, mixed>
     */
    private static function exchange(): array
    {
        return self::object([
            'step' => ['type' => 'integer'],
            'kind' => ['type' => 'string', 'enum' => ['answer', 'ask', 'rescue']],
            'initiator' => ['type' => 'string', 'enum' => ['A', 'B']],
            'must_understand' => ['type' => ['integer', 'null']],
            'partner_line' => ['type' => ['string', 'null'], 'enum' => [...self::ids('a', self::PARTNER_LINES), null]],
            'messages' => ['type' => 'array', 'items' => ['anyOf' => [self::partnerMessage(), self::learnerMessage()]]],
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

    /** @return array<string, mixed> */
    private static function learnerMessage(): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'speaker' => ['type' => 'string', 'enum' => ['B']],
            'role_target' => $string,
            'role_native' => $string,
            'phrase_id' => ['type' => ['string', 'null'], 'enum' => [...self::ids('p', self::FRAMES), null]],
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

    /** @return array<string, mixed> a frame of the skeleton, with the `must_say` items it serves */
    private static function frame(): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'id' => ['type' => 'string', 'enum' => self::ids('p', self::FRAMES)],
            'kind' => ['type' => 'string', 'enum' => ['answer', 'ask']],
            'must_say' => ['type' => 'array', 'items' => ['type' => 'integer']],
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
    private static function partnerLine(): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'id' => ['type' => 'string', 'enum' => self::ids('a', self::PARTNER_LINES)],
            'must_understand' => ['type' => 'integer'],
            'kind' => ['type' => 'string', 'enum' => ['question', 'statement']],
            'pairs_with' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'text_target' => $string,
            'text_native' => $string,
        ]);
    }

    /** @return array<string, mixed> a word of the day; `used_in` — frames and partner lines */
    private static function vocabularyItem(): array
    {
        $string = ['type' => 'string'];

        return self::object([
            'id' => ['type' => 'string', 'enum' => self::ids('v', self::WORDS)],
            'term_target' => $string,
            'translation_native' => $string,
            'pronunciation_native' => $string,
            'definition_target' => $string,
            'kind' => ['type' => 'string', 'enum' => ['word', 'chunk']],
            'image_prompt' => ['type' => ['string', 'null']],
            'used_in' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => [...self::ids('p', self::FRAMES), ...self::ids('a', self::PARTNER_LINES)]]],
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
